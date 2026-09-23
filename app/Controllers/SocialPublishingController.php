<?php
namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\SocialPublishingService;
use App\Services\OperationalErrorSanitizer;

final class SocialPublishingController
{
    private function designer(): array
    {
        H::requireSeller();
        $d=DB::row('select * from designers where user_id=? and status="approved"',[(int)H::user()['id']]);
        if(!$d)H::abort(403);
        return $d;
    }

    public function index(): void
    {
        $d=$this->designer();$service=new SocialPublishingService();$boards=[];$boardError=null;
        $connections=DB::rows('select * from seller_social_connections where designer_id=?',[$d['id']]);
        foreach($connections as $connection)if($connection['platform']==='pinterest'&&$connection['connection_status']==='connected')try{$boards=$service->boards($connection);}catch(\Throwable $e){$boardError='Pinterest boards could not be refreshed. Reconnect if this continues.';}
        H::view('seller/social_publishing',['connections'=>array_column($connections,null,'platform'),'integrations'=>array_column(DB::rows('select * from social_platform_integrations'),null,'platform'),'boards'=>$boards,'boardError'=>$boardError,'products'=>DB::rows('select p.id,p.title,p.slug from products p where p.designer_id=? and p.status in ("approved","published") order by p.updated_at desc',[$d['id']]),'customDesigns'=>DB::rows('select s.id,s.title,s.slug from custom_design_services s where s.designer_id=? and s.is_active=1 order by s.updated_at desc',[$d['id']]),'logs'=>DB::rows('select l.*,p.id current_product_id,p.status current_product_status,s.id current_custom_service_id,s.is_active current_custom_service_active,coalesce(l.listing_title,p.title,s.title,if(l.custom_service_id is not null,concat("Deleted Custom Design #",l.custom_service_id),concat("Deleted product #",l.product_id))) listing_title,if(l.custom_service_id is null,"Product","Custom Design") listing_type from social_post_logs l left join products p on p.id=l.product_id left join custom_design_services s on s.id=l.custom_service_id where l.designer_id=? order by l.attempted_at desc limit 100',[$d['id']])]);
    }

    public function connect(string $platform): void
    {
        $this->designer();H::verifyCsrf();
        $state=bin2hex(random_bytes(32));$_SESSION['social_oauth'][$platform]=['state'=>$state,'expires'=>time()+600];
        try{header('Location: '.(new SocialPublishingService())->authorizationUrl($platform,$state),true,303);exit;}catch(\Throwable $e){H::flash('error','Social connection could not be started: '.OperationalErrorSanitizer::sanitize($e->getMessage()));H::redirect('/seller/social-publishing');}
    }

    public function callback(string $platform): void
    {
        $d=$this->designer();$pending=$_SESSION['social_oauth'][$platform]??null;unset($_SESSION['social_oauth'][$platform]);
        if(!SocialPublishingService::validOAuthState($pending,(string)($_GET['state']??''),time()))H::abort(419);
        if(!empty($_GET['error'])){H::flash('warning','The social provider did not authorize the connection.');H::redirect('/seller/social-publishing');}
        try{$service=new SocialPublishingService();if($platform==='pinterest'){$service->connectPinterestFromCode((int)$d['id'],(string)($_GET['code']??''));H::flash('success','Pinterest connected.');}else{$authorization=$service->metaDestinations($platform,(string)($_GET['code']??''),(int)$d['id']);if(empty($authorization['destinations']))throw new \RuntimeException($platform==='instagram'?'No eligible professional Instagram accounts were found.':'No permitted Facebook Pages were found.');$_SESSION['social_meta_selection'][$platform]=['payload'=>\App\Services\SocialCredentialCipher::encrypt($authorization),'expires'=>time()+600];H::redirect('/seller/social/'.$platform.'/select');}}catch(\Throwable $e){H::flash('error','Connection failed: '.OperationalErrorSanitizer::sanitize($e->getMessage()));}
        H::redirect('/seller/social-publishing');
    }

    public function select(string $platform):void
    {
        $d=$this->designer();if(!in_array($platform,['facebook','instagram'],true))H::abort(404);$pending=$_SESSION['social_meta_selection'][$platform]??null;if(!$pending||($pending['expires']??0)<time()){unset($_SESSION['social_meta_selection'][$platform]);H::flash('warning','Account selection expired. Connect again.');H::redirect('/seller/social-publishing');}
        try{$authorization=\App\Services\SocialCredentialCipher::decrypt($pending['payload']);}catch(\Throwable $e){unset($_SESSION['social_meta_selection'][$platform]);H::flash('error','Account selection could not be restored. Connect again.');H::redirect('/seller/social-publishing');}
        if($_SERVER['REQUEST_METHOD']==='POST'){H::verifyCsrf();try{(new SocialPublishingService())->connectSelectedMetaDestination((int)$d['id'],$platform,(string)($_POST['destination_id']??''),$authorization);unset($_SESSION['social_meta_selection'][$platform]);H::flash('success',ucfirst($platform).' connected.');H::redirect('/seller/social-publishing');}catch(\Throwable $e){H::flash('error',OperationalErrorSanitizer::sanitize($e->getMessage()));}}
        H::view('seller/social_destination',['platform'=>$platform,'destinations'=>$authorization['destinations']]);
    }

    public function settings(string $platform): void
    {
        $d=$this->designer();H::verifyCsrf();
        $connection=DB::row('select * from seller_social_connections where designer_id=? and platform=?',[$d['id'],$platform]);if(!$connection)H::abort(404);
        $boardId=null;$boardName=null;$autoPost=isset($_POST['auto_post']);
        if($platform==='pinterest'){[$submittedId]=array_pad(explode('|',(string)($_POST['board']??''),2),1,'');try{$service=new SocialPublishingService();$available=$submittedId!==''||$autoPost?$service->boards($connection):[];$match=$service->authorizedPinterestBoard($available,$submittedId,$autoPost);}catch(\DomainException $e){H::flash('error',$e->getMessage());H::redirect('/seller/social-publishing');}if($match){$boardId=(string)$match['id'];$boardName=(string)($match['name']??'Board');}}
        DB::exec('update seller_social_connections set auto_post_enabled=?,pinterest_board_id=?,pinterest_board_name=? where id=?',[$autoPost?1:0,$boardId,$boardName,$connection['id']]);H::flash('success','Social publishing settings saved.');H::redirect('/seller/social-publishing');
    }

    public function disconnect(string $platform): void
    {
        $d=$this->designer();H::verifyCsrf();$connection=DB::row('select * from seller_social_connections where designer_id=? and platform=?',[$d['id'],$platform]);if(!$connection)H::abort(404);$warning=false;$revoked=false;$service=new SocialPublishingService();if($platform==='pinterest'){$revoke=true;}else{$remaining=(int)(DB::row('select count(*) c from seller_social_connections where designer_id=? and platform in ("facebook","instagram") and id<>?',[$d['id'],$connection['id']])['c']??0);$revoke=SocialPublishingService::shouldRevokeMeta($remaining);}if($revoke)try{$service->revoke($connection);$revoked=true;}catch(\Throwable $e){$warning=true;}DB::exec('delete from seller_social_connections where id=? and designer_id=?',[$connection['id'],$d['id']]);$message=ucfirst($platform).' disconnected from Creative Moth.';if($warning)$message.=' Provider revocation could not be confirmed; revoke Creative Moth in your provider account if desired.';elseif($revoked)$message.=' Provider access was also revoked.';elseif(in_array($platform,['facebook','instagram'],true))$message.=' Shared Meta authorization was retained for your other connected Meta platform.';H::flash($warning?'warning':'success',$message);H::redirect('/seller/social-publishing');
    }

    public function compose(string $id): void
    {
        $this->composeListing('product',(int)$id);
    }

    public function composeCustomDesign(string $id):void{$this->composeListing('custom_design',(int)$id);}

    private function composeListing(string $type,int $id):void
    {
        $d=$this->designer();$custom=$type==='custom_design';$p=$custom?DB::row('select s.*,d.display_name from custom_design_services s join designers d on d.id=s.designer_id where s.id=? and s.designer_id=? and s.is_active=1',[$id,$d['id']]):DB::row('select p.*,d.display_name from products p join designers d on d.id=p.designer_id where p.id=? and p.designer_id=? and p.status in ("approved","published")',[$id,$d['id']]);if(!$p)H::abort(404);
        $images=$custom?DB::rows('select * from custom_service_images where custom_service_id=? order by sort_order,id',[$id]):DB::rows('select * from product_images where product_id=? order by sort_order,id',[$id]);$caption=$p['title'].' — '.$p['display_name']."\n\n".strip_tags((string)$p['description']);H::view('seller/social_compose',['p'=>$p,'images'=>$images,'caption'=>mb_substr($caption,0,5000),'listingType'=>$type,'connections'=>DB::rows('select platform,external_account_name,connection_status,pinterest_board_id from seller_social_connections where designer_id=?',[$d['id']])]);
    }

    public function post(string $id): void
    {
        $this->postListing('product',(int)$id);
    }

    public function postCustomDesign(string $id):void{$this->postListing('custom_design',(int)$id);}

    private function postListing(string $type,int $id):void
    {
        $d=$this->designer();H::verifyCsrf();$platforms=array_values(array_intersect(SocialPublishingService::PLATFORMS,(array)($_POST['platforms']??[])));if(!$platforms){H::flash('error','Select at least one connected platform.');H::redirect('/seller/social/'.($type==='custom_design'?'custom-design':'product').'/'.$id);}
        $caption=trim((string)($_POST['caption']??''));if($caption===''||mb_strlen($caption)>5000)H::abort(422);$ok=0;$failed=0;$service=new SocialPublishingService();foreach($platforms as $platform){$r=$type==='custom_design'?$service->publishCustomDesign((int)$d['id'],$id,$platform,(int)($_POST['image_id']??0),$caption):$service->publish((int)$d['id'],$id,$platform,(int)($_POST['image_id']??0),$caption);$r['status']==='succeeded'?$ok++:$failed++;}H::flash($failed?'warning':'success',"Social posting completed: $ok succeeded, $failed failed.");H::redirect('/seller/social-publishing');
    }

    public function retry(string $id): void
    {
        $d=$this->designer();H::verifyCsrf();$log=DB::row('select * from social_post_logs where id=? and designer_id=? and status="failed"',[(int)$id,$d['id']]);if(!$log)H::abort(404);try{$service=new SocialPublishingService();$r=$log['custom_service_id']!==null?$service->publishCustomDesign((int)$d['id'],(int)$log['custom_service_id'],$log['platform'],(int)$log['image_id'],$log['caption'],'retry',(int)$log['id']):$service->publish((int)$d['id'],(int)$log['product_id'],$log['platform'],(int)$log['image_id'],$log['caption'],'retry',(int)$log['id']);H::flash($r['status']==='succeeded'?'success':'warning',$r['status']==='succeeded'?'Post retry succeeded.':'Post retry failed. Review the connection and error below.');}catch(\DomainException $e){H::flash('warning',OperationalErrorSanitizer::sanitize($e->getMessage()));}H::redirect('/seller/social-publishing');
    }
}
