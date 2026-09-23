<?php
namespace App\Services;

use App\Core\Database as DB;
use App\Core\Helpers as H;

final class SocialPublishingService
{
    public const PLATFORMS = ['facebook','instagram','pinterest'];
    private $transport;
    private $integrationEnabled;
    private static $defaultTransport;

    public function __construct(?callable $transport = null,?callable $integrationEnabled=null) { $this->transport = $transport ?? self::$defaultTransport;$this->integrationEnabled=$integrationEnabled; }
    public static function setTestTransport(?callable $transport):void { self::$defaultTransport=$transport; }

    public static function validOAuthState(?array $pending,string $returned,int $now):bool
    { return $pending!==null&&($pending['expires']??0)>=$now&&isset($pending['state'])&&hash_equals((string)$pending['state'],$returned); }

    public function authorizationUrl(string $platform, string $state): string
    {
        $this->assertPlatform($platform);
        $redirect = $this->redirectUri($platform);
        if ($platform === 'pinterest') {
            return 'https://www.pinterest.com/oauth/?'.http_build_query(['response_type'=>'code','client_id'=>$this->env('PINTEREST_APP_ID'),'redirect_uri'=>$redirect,'scope'=>'boards:read,pins:read,pins:write','state'=>$state]);
        }
        $scopes = $platform === 'facebook' ? 'pages_show_list,pages_read_engagement,pages_manage_posts' : 'pages_show_list,instagram_basic,instagram_content_publish';
        return 'https://www.facebook.com/'.$this->metaVersion().'/dialog/oauth?'.http_build_query(['client_id'=>$this->env('META_APP_ID'),'redirect_uri'=>$redirect,'scope'=>$scopes,'state'=>$state,'response_type'=>'code']);
    }

    public function metaDestinations(string $platform, string $code, ?int $designerId=null): array
    {
        $this->assertEnabled($platform);
        if ($platform === 'pinterest') throw new \InvalidArgumentException('Pinterest does not use Meta destinations.');
        $token = $this->request('POST','https://graph.facebook.com/'.$this->metaVersion().'/oauth/access_token',[],['client_id'=>$this->env('META_APP_ID'),'client_secret'=>$this->env('META_APP_SECRET'),'redirect_uri'=>$this->redirectUri($platform),'code'=>$code]);
        $destinations=[];$after=null;$seen=[];$instagramIds=[];
        do{
            $query=['fields'=>'id,name,access_token,instagram_business_account'];if($after!==null)$query['after']=$after;
            $pages=$this->request('GET','https://graph.facebook.com/'.$this->metaVersion().'/me/accounts',['Authorization: Bearer '.$token['access_token']],$query);
            foreach(($pages['data']??[]) as $page){
                if($platform==='facebook')$destinations[]=['id'=>(string)$page['id'],'name'=>(string)$page['name'],'page_id'=>(string)$page['id'],'access_token'=>(string)$page['access_token']];
                elseif(!empty($page['instagram_business_account']['id'])){$igId=(string)$page['instagram_business_account']['id'];$profile=$this->request('GET','https://graph.facebook.com/'.$this->metaVersion().'/'.$igId,['Authorization: Bearer '.$page['access_token']],['fields'=>'id,username']);$destinations[]=['id'=>$igId,'name'=>(string)($profile['username']??'Instagram'),'page_id'=>(string)$page['id'],'access_token'=>(string)$page['access_token']];$instagramIds[$igId]=true;}
            }
            $next=(string)($pages['paging']['cursors']['after']??'');
            if($next===''||isset($seen[$next])){$after=null;}else{$seen[$next]=true;$after=$next;}
        }while($after!==null&&count($seen)<100);
        if($platform==='instagram'&&$designerId!==null){
            $facebook=DB::row('select * from seller_social_connections where designer_id=? and platform="facebook" and connection_status="connected"',[$designerId]);
            if($facebook)try{
                $credentials=SocialCredentialCipher::decrypt($facebook['encrypted_credentials']);$pageToken=(string)($credentials['access_token']??'');$pageId=(string)$facebook['external_account_id'];
                if($pageToken!==''&&$pageId!==''){$page=$this->request('GET','https://graph.facebook.com/'.$this->metaVersion().'/'.$pageId,['Authorization: Bearer '.$pageToken],['fields'=>'id,name,instagram_business_account']);$igId=(string)($page['instagram_business_account']['id']??'');if($igId!==''&&!isset($instagramIds[$igId])){$profile=$this->request('GET','https://graph.facebook.com/'.$this->metaVersion().'/'.$igId,['Authorization: Bearer '.$pageToken],['fields'=>'id,username']);$destinations[]=['id'=>$igId,'name'=>(string)($profile['username']??'Instagram'),'page_id'=>$pageId,'access_token'=>$pageToken];$instagramIds[$igId]=true;}}
            }catch(\Throwable){}
        }
        return ['destinations'=>$destinations,'user_access_token'=>(string)$token['access_token'],'expires_in'=>$token['expires_in']??null];
    }

    public function connectSelectedMetaDestination(int $designerId,string $platform,string $selectedId,array $authorization):void
    {
        if(!in_array($platform,['facebook','instagram'],true))throw new \InvalidArgumentException('Invalid Meta platform.');
        $selected=$this->selectedMetaDestination($authorization,$selectedId);
        $this->saveConnection($designerId,$platform,$selected['id'],$selected['name'],['access_token'=>$selected['access_token'],'user_access_token'=>$authorization['user_access_token'],'page_id'=>$selected['page_id'],'expires_in'=>$authorization['expires_in']??null]);
    }

    public function selectedMetaDestination(array $authorization,string $selectedId):array
    { foreach(($authorization['destinations']??[]) as $candidate)if(isset($candidate['id'])&&hash_equals((string)$candidate['id'],$selectedId))return $candidate;throw new \DomainException('Select an account authorized by Meta.'); }

    public function connectPinterestFromCode(int $designerId, string $code): void
    {
            $this->assertEnabled('pinterest');
            $token = $this->request('POST','https://api.pinterest.com/v5/oauth/token',['Authorization: Basic '.base64_encode($this->env('PINTEREST_APP_ID').':'.$this->env('PINTEREST_APP_SECRET'))],['grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>$this->redirectUri('pinterest')],true);
            $account = $this->request('GET','https://api.pinterest.com/v5/user_account',['Authorization: Bearer '.$token['access_token']]);
            $ownsTransaction=!DB::pdo()->inTransaction();if($ownsTransaction)DB::begin();try{$this->saveConnection($designerId,'pinterest',(string)($account['username']??$account['id']??''),(string)($account['username']??'Pinterest'),$token);DB::exec('update seller_social_connections set auto_post_enabled=0,pinterest_board_id=null,pinterest_board_name=null where designer_id=? and platform="pinterest"',[$designerId]);if($ownsTransaction)DB::commit();}catch(\Throwable $e){if($ownsTransaction&&DB::pdo()->inTransaction())DB::rollBack();throw $e;}
    }

    public function revoke(array $connection): void
    {
        $credentials=SocialCredentialCipher::decrypt($connection['encrypted_credentials']);
        if($connection['platform']==='pinterest'){
            $this->request('POST','https://api.pinterest.com/v5/oauth/token/revoke',['Authorization: Basic '.base64_encode($this->env('PINTEREST_APP_ID').':'.$this->env('PINTEREST_APP_SECRET'))],['token'=>$credentials['access_token'],'token_type_hint'=>'access_token']);
            return;
        }
        $this->request('DELETE','https://graph.facebook.com/'.$this->metaVersion().'/me/permissions',['Authorization: Bearer '.($credentials['user_access_token']??$credentials['access_token'])]);
    }

    public static function shouldRevokeMeta(int $remainingMetaConnections):bool
    { return $remainingMetaConnections===0; }

    public function boards(array $connection): array
    {
        if ($connection['platform'] !== 'pinterest') return [];
        $credentials = SocialCredentialCipher::decrypt($connection['encrypted_credentials']);
        return $this->pinterestBoardsWithAccessToken($credentials['access_token']);
    }

    public function pinterestBoardsWithAccessToken(string $accessToken):array
    {
        $items=[];$bookmark=null;$seen=[];
        do{$query=['page_size'=>100];if($bookmark!==null)$query['bookmark']=$bookmark;$page=$this->request('GET','https://api.pinterest.com/v5/boards',['Authorization: Bearer '.$accessToken],$query);$items=array_merge($items,$page['items']??[]);$next=(string)($page['bookmark']??'');if($next===''||isset($seen[$next]))$bookmark=null;else{$seen[$next]=true;$bookmark=$next;}}while($bookmark!==null&&count($seen)<100);
        return $items;
    }

    public function authorizedPinterestBoard(array $boards,?string $selectedId,bool $autoPosting):?array
    {
        $selectedId=trim((string)$selectedId);
        if($selectedId===''){if($autoPosting)throw new \DomainException('Select an authorized Pinterest board before enabling automatic posting.');return null;}
        foreach($boards as $board)if(isset($board['id'])&&hash_equals((string)$board['id'],$selectedId))return $board;
        throw new \DomainException('Select a Pinterest board authorized for this connected account.');
    }

    public function publish(int $designerId, int $productId, string $platform, int $imageId, string $caption, string $trigger='manual', ?int $retryOf=null): array
    {
        return $this->publishListing($designerId,'product',$productId,$platform,$imageId,$caption,$trigger,$retryOf);
    }

    public function publishCustomDesign(int $designerId, int $serviceId, string $platform, int $imageId, string $caption, string $trigger='manual', ?int $retryOf=null): array
    {
        return $this->publishListing($designerId,'custom_design',$serviceId,$platform,$imageId,$caption,$trigger,$retryOf);
    }

    private function publishListing(int $designerId,string $listingType,int $listingId,string $platform,int $imageId,string $caption,string $trigger,?int $retryOf):array
    {
        $this->assertPlatform($platform);
        $custom=$listingType==='custom_design';
        $listing=$custom
            ?DB::row('select s.*,d.display_name,d.store_slug,d.user_id from custom_design_services s join designers d on d.id=s.designer_id where s.id=? and s.designer_id=?',[$listingId,$designerId])
            :DB::row('select p.*,d.display_name,d.store_slug,d.user_id from products p join designers d on d.id=p.designer_id where p.id=? and p.designer_id=?',[$listingId,$designerId]);
        if (!$listing) throw new \DomainException($custom?'This Custom Design is no longer available for social posting.':'This product is no longer available for social posting.');
        $connection = DB::row('select * from seller_social_connections where designer_id=? and platform=?',[$designerId,$platform]);
        $automaticKey = $trigger === 'automatic' ? $listingType.':'.$listingId.':platform:'.$platform : null;
        if ($automaticKey && DB::row('select id from social_post_logs where automatic_key=?',[$automaticKey])) return ['status'=>'duplicate'];
        $logId = $this->startLog($designerId,$custom?null:$listingId,$custom?$listingId:null,$listing['title'],$connection,$platform,$imageId,$caption,$trigger,$automaticKey,$retryOf);
        try {
            $image = $custom
                ?DB::row('select id,image_path from custom_service_images where id=? and custom_service_id=?',[$imageId,$listingId])
                :DB::row('select id,image_path from product_images where id=? and product_id=?',[$imageId,$listingId]);
            $this->validatePostingEligibility($listing,$image,$custom);
            $this->assertEnabled($platform);
            if (!$connection || $connection['connection_status'] !== 'connected') throw new \RuntimeException('Reconnect this social account before posting.');
            if (!empty($connection['credential_expires_at']) && strtotime($connection['credential_expires_at']) <= time()) throw new \RuntimeException('This social connection has expired. Reconnect it.');
            $credentials = SocialCredentialCipher::decrypt($connection['encrypted_credentials']);
            $url = H::canonical(($custom?'/custom-design/':'/product/').$listing['slug']);
            $imageUrl = H::assetUrl($image['image_path']);
            $postId = $this->send($platform,$connection,$credentials,$listing,$imageUrl,$url,$caption);
            DB::exec('update social_post_logs set status="succeeded",platform_post_id=?,completed_at=now() where id=?',[$postId,$logId]);
            return ['status'=>'succeeded','id'=>$logId,'platform_post_id'=>$postId];
        } catch (\Throwable $e) {
            $safe = OperationalErrorSanitizer::sanitize($e->getMessage(),500);
            DB::exec('update social_post_logs set status="failed",error_code=?,error_message=?,completed_at=now() where id=?',[$e instanceof \DomainException?'validation':'api_error',$safe,$logId]);
            if ($connection && preg_match('/expired|token|permission|reconnect|oauth/i',$safe)) DB::exec('update seller_social_connections set connection_status=?,last_error=? where id=?',[preg_match('/permission/i',$safe)?'permission_error':'reconnect_required',$safe,$connection['id']]);
            if ($trigger === 'automatic') NotificationService::create((int)$listing['user_id'],'social_post_failed','designer','Social post needs attention','An automatic '.$platform.' post for “'.$listing['title'].'” failed. Review the connection and retry.',"social-post-failed:$logId",'/seller/social-publishing');
            return ['status'=>'failed','id'=>$logId,'error'=>$safe];
        }
    }

    public function autoPostNewlyPublished(int $productId): void
    {
        $product=DB::row('select p.*,d.id designer_id,d.display_name from products p join designers d on d.id=p.designer_id where p.id=? and p.status in ("approved","published")',[$productId]);
        if(!$product)return;
        $image=DB::row('select id from product_images where product_id=? order by sort_order,id limit 1',[$productId]);
        if(!$image)return;
        $caption=trim($product['title'].' — '.$product['display_name']."\n\n".strip_tags((string)$product['description']));
        foreach(DB::rows('select platform from seller_social_connections where designer_id=? and connection_status="connected" and auto_post_enabled=1',[$product['designer_id']]) as $row) $this->publish((int)$product['designer_id'],$productId,$row['platform'],(int)$image['id'],mb_substr($caption,0,2000),'automatic');
    }

    public function autoPostCustomDesign(int $serviceId):void
    {
        $service=DB::row('select s.*,d.display_name from custom_design_services s join designers d on d.id=s.designer_id where s.id=? and s.is_active=1',[$serviceId]);
        if(!$service)return;
        $image=DB::row('select id from custom_service_images where custom_service_id=? order by sort_order,id limit 1',[$serviceId]);
        if(!$image)return;
        $caption=trim($service['title'].' — '.$service['display_name']."\n\n".strip_tags((string)$service['description']));
        foreach(DB::rows('select platform from seller_social_connections where designer_id=? and connection_status="connected" and auto_post_enabled=1',[$service['designer_id']]) as $row) $this->publishCustomDesign((int)$service['designer_id'],$serviceId,$row['platform'],(int)$image['id'],mb_substr($caption,0,2000),'automatic');
    }

    private function send(string $platform,array $connection,array $credentials,array $product,string $imageUrl,string $url,string $caption): string
    {
        $caption=$this->platformCaption($platform,$caption,$url);
        if($platform==='facebook'){$r=$this->request('POST','https://graph.facebook.com/'.$this->metaVersion().'/'.$connection['external_account_id'].'/photos',['Authorization: Bearer '.$credentials['access_token']],['url'=>$imageUrl,'caption'=>$caption]);return $this->requiredProviderId($r['post_id']??$r['id']??null,'Facebook did not return a post ID.');}
        if($platform==='instagram'){$media=$this->request('POST','https://graph.facebook.com/'.$this->metaVersion().'/'.$connection['external_account_id'].'/media',['Authorization: Bearer '.$credentials['access_token']],['image_url'=>$imageUrl,'caption'=>$caption]);$container=$this->requiredProviderId($media['id']??null,'Instagram did not return a media container ID.');$r=$this->request('POST','https://graph.facebook.com/'.$this->metaVersion().'/'.$connection['external_account_id'].'/media_publish',['Authorization: Bearer '.$credentials['access_token']],['creation_id'=>$container]);return $this->requiredProviderId($r['id']??null,'Instagram did not return a published media ID.');}
        if(empty($connection['pinterest_board_id']))throw new \DomainException('Select a Pinterest board before posting.');
        $r=$this->request('POST','https://api.pinterest.com/v5/pins',['Authorization: Bearer '.$credentials['access_token']],['board_id'=>$connection['pinterest_board_id'],'title'=>mb_substr($product['title'],0,100),'description'=>mb_substr($caption,0,500),'link'=>$url,'media_source'=>['source_type'=>'image_url','url'=>$imageUrl]],false,true);return $this->requiredProviderId($r['id']??null,'Pinterest did not return a Pin ID.');
    }

    private function requiredProviderId(mixed $id,string $message):string
    { $id=trim((string)$id);if($id==='')throw new \RuntimeException($message);return $id; }

    public function platformCaption(string $platform,string $caption,string $productUrl):string
    {
        $this->assertPlatform($platform);$caption=trim(str_replace($productUrl,'',$caption));
        if($platform==='pinterest')return mb_substr($caption,0,500);
        $suffix=$platform==='instagram'?"\n\nCreative Moth: ".$productUrl:"\n\n".$productUrl;
        $limit=$platform==='instagram'?2200:63206;
        return rtrim(mb_substr($caption,0,$limit-mb_strlen($suffix))).$suffix;
    }

    public function validatePostingEligibility(array $listing,?array $image,bool $customDesign=false):void
    { if($customDesign){if(empty($listing['is_active']))throw new \DomainException('Only active Custom Designs can be posted.');}elseif(!in_array($listing['status']??null,['approved','published'],true))throw new \DomainException('Only approved or published products can be posted.');if(!$image)throw new \DomainException('The previously selected preview image is no longer available. Choose another image for a new post.'); }

    private function startLog(int $d,?int $p,?int $customServiceId,string $title,?array $c,string $platform,int $image,string $caption,string $trigger,?string $key,?int $retry): int
    { DB::exec('insert into social_post_logs(designer_id,product_id,custom_service_id,listing_title,connection_id,platform,trigger_type,image_id,caption,status,attempted_at,automatic_key,retry_of_id) values(?,?,?,?,?,?,?,?,?,"attempting",now(),?,?)',[$d,$p,$customServiceId,mb_substr($title,0,190),$c['id']??null,$platform,$trigger,$image,mb_substr($caption,0,5000),$key,$retry]);return (int)DB::id(); }
    private function saveConnection(int $d,string $p,string $id,string $name,array $credentials):void
    { if($id==='')throw new \RuntimeException('The provider did not return an account.');$expires=!empty($credentials['expires_in'])?date('Y-m-d H:i:s',time()+(int)$credentials['expires_in']):null;DB::exec('insert into seller_social_connections(designer_id,platform,external_account_id,external_account_name,encrypted_credentials,credential_expires_at,connection_status,connected_at) values(?,?,?,?,?,? ,"connected",now()) on duplicate key update external_account_id=values(external_account_id),external_account_name=values(external_account_name),encrypted_credentials=values(encrypted_credentials),credential_expires_at=values(credential_expires_at),connection_status="connected",last_error=null,connected_at=now()',[$d,$p,$id,mb_substr($name,0,190),SocialCredentialCipher::encrypt($credentials),$expires]); }
    private function request(string $method,string $url,array $headers=[],array $data=[],bool $form=false,bool $json=false):array
    {
        if($this->transport)return ($this->transport)($method,$url,$headers,$data,$form,$json);
        $ch=curl_init();
        if($method==='GET'&&$data)$url.='?'.http_build_query($data);
        if($method!=='GET'){
            if($json){$headers[]='Content-Type: application/json';$body=json_encode($data,JSON_THROW_ON_ERROR);}
            else {$body=http_build_query($data);$headers[]='Content-Type: application/x-www-form-urlencoded';}
        }
        curl_setopt_array($ch,[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers]);
        if(isset($body))curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
        $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$network=curl_error($ch);curl_close($ch);
        if($raw===false)throw new \RuntimeException('Social provider request failed'.($network!==''?': '.$network:'.'));
        $decoded=json_decode($raw,true);
        if($status<200||$status>=300){$message=(string)($decoded['error']['message']??$decoded['message']??'Provider returned HTTP '.$status);throw new \RuntimeException($message);}
        if(!is_array($decoded))throw new \RuntimeException('Social provider returned an invalid response.');
        return $decoded;
    }
    private function assertPlatform(string $p):void { if(!in_array($p,self::PLATFORMS,true))throw new \InvalidArgumentException('Unsupported social platform.'); }
    private function assertEnabled(string $p):void{$this->assertPlatform($p);if($this->integrationEnabled){if(!(($this->integrationEnabled)($p)))throw new \RuntimeException(ucfirst($p).' publishing is temporarily disabled.');return;}$r=DB::row('select is_enabled,disabled_reason from social_platform_integrations where platform=?',[$p]);if(!$r||!(int)$r['is_enabled'])throw new \RuntimeException(ucfirst($p).' publishing is temporarily disabled'.(!empty($r['disabled_reason'])?': '.$r['disabled_reason']:'.'));}
    private function env(string $key):string{$v=trim((string)($_ENV[$key]??''));if($v==='')throw new \RuntimeException($key.' is not configured.');return $v;}
    private function redirectUri(string $p):string{return trim((string)($_ENV[strtoupper($p).'_OAUTH_REDIRECT_URI']??''))?:H::canonical('/seller/social/'.$p.'/callback');}
    private function metaVersion():string{return trim((string)($_ENV['META_GRAPH_API_VERSION']??'v23.0'));}
}
