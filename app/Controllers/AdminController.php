<?php

namespace App\Controllers;
use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\LicenseService;
use App\Services\WatermarkService;
use App\Repositories\IpRiskRepository;
use App\Services\NotificationService;
use App\Services\EmailQueueService;
use App\Services\SellerReferralCommissionService;
use App\Services\CreatorRecognitionService;
use Throwable;
class AdminController
{
    private function gate()
    {
        H::requireRole('admin');
        if(!DB::row('select id from users where id=? and role="admin" and status="active"',[(int)H::user()['id']]))H::abort(403);

    }
    private function applicationById($id)
    {
        return DB::row('select da.*,u.name user_name,u.email user_email,u.status user_status,u.role user_role from designer_applications da join users u on u.id=da.user_id where da.id=?',[$id]);

    }
    private function slugTakenByOther(string $slug, int $userId): bool
    {
        $d=DB::row('select id from designers where store_slug=? and user_id<>? limit 1',[$slug,$userId]);
        return (bool)$d;

    }
    private function log(string $action, string $entityType, int $entityId, array $metadata=[]): int
    {
        DB::exec('insert into admin_logs (admin_user_id,action,entity_type,entity_id,metadata) values (?,?,?,?,?)',[H::user()['id'],$action,$entityType,$entityId,json_encode($metadata)]);
        return (int)DB::id();

    }
    private function approveApplication(int $id): void
    {
        $a=$this->applicationById($id);
        if(!$a)
        {
           H::flash('error','Application not found.');
            return;

        }
        if(!in_array($a['status'],['pending','denied'],true))
        {
           H::flash('warning','Only pending or denied applications can be approved.');
            return;

        }
        if($a['user_status']==='disabled')
        {
           H::flash('error','Disabled users cannot be approved as designers.');
            return;

        }
        if($this->slugTakenByOther($a['desired_slug'],(int)$a['user_id']))
        {
           H::flash('error','That store URL is already taken. Please choose another.');
            return;

        }
        try
        {
           DB::begin();
            $existing=DB::row('select * from designers where user_id=?',[$a['user_id']]);
            if($existing)
           {
               DB::exec('update designers set display_name=?,store_slug=?,bio=?,social_links=?,status="approved",creator_rank=coalesce(creator_rank,"Bronze"),rank_override=0,is_featured=0,updated_at=now() where id=?',[$a['display_name'],$a['desired_slug'],$a['bio'],$a['social_links'],$existing['id']]);
                $designerId=$existing['id'];

           }
            else
           {
               DB::exec('insert into designers (user_id,display_name,store_slug,bio,social_links,status,creator_rank,rank_override,is_featured) values (?,?,?,?,?,?,?,?,?)',[$a['user_id'],$a['display_name'],$a['desired_slug'],$a['bio'],$a['social_links'],'approved','Bronze',0,0]);
                $designerId=DB::id();

           }
            DB::exec('update designer_applications set status="approved",denial_reason=null,updated_at=now() where id=?',[$id]);
            DB::exec('update users set role="designer",updated_at=now() where id=?',[$a['user_id']]);
            $this->log('approved_designer_application','designer_application',$id,['user_id'=>$a['user_id'],'designer_id'=>$designerId]);
            DB::commit();
            H::flash('success','Designer application approved.');

        }
        catch(Throwable $e)
        {
           DB::rollBack();
            H::flash('error','Approval failed. Please try again.');

        }

    }
    private function denyApplication(int $id, string $reason, string $notes=''): void
    {
        $a=$this->applicationById($id);
        if(!$a)
        {
           H::flash('error','Application not found.');
            return;

        }
        if(mb_strlen(trim($reason))<5)
        {
           H::flash('error','Denial reason must be at least 5 characters.');
            return;

        }
        DB::exec('update designer_applications set status="denied",denial_reason=?,admin_notes=?,updated_at=now() where id=?',[$reason,$notes,$id]);
        $this->log('denied_designer_application','designer_application',$id,['user_id'=>$a['user_id']]);
        H::flash('success','Designer application denied.');

    }
    public function home()
    {
        $this->gate();
        $adminId=(int)H::user()['id'];
        $stats=DB::row('select
            (select count(*) from users) total_users,
            (select count(*) from users where role="buyer") total_buyers,
            (select count(*) from designers where status="approved") approved_designers,
            (select count(*) from designer_applications where status="pending") pending_apps,
            (select count(*) from products where status="pending_review") pending_products,
            (select count(*) from products p join product_ip_risk_states s on s.product_id=p.id where p.status="pending_review" and s.review_status="pending_review" and exists (select 1 from product_ip_risk_detections d where d.product_id=p.id and d.scan_id=s.latest_scan_id and d.is_active=1 limit 1)) ip_risk_products,
            (select count(*) from products where status in ("approved","published")) active_products,
            (select count(*) from products where status="draft") draft_products,
            (select count(*) from orders where payment_status in ("failed","manual_review") or manual_review_required=1) payment_warnings,
            (select count(*) from seller_payouts where (payout_status="transfer_failed" or stripe_transfer_error is not null) and admin_resolved_at is null) failed_transfers,
            (select count(*) from designers where status="approved" and stripe_connect_account_id is null) stripe_missing,
            (select count(*) from designers where status="approved" and stripe_connect_account_id is not null and (stripe_details_submitted=0 or stripe_payouts_enabled=0)) payout_incomplete,
            (select count(*) from stripe_events where (processing_status="failed" or processing_error is not null) and admin_resolved_at is null) webhook_issues,
            (select count(*) from orders where payment_status in ("paid","partially_refunded") and stripe_checkout_session_id like "cs_live_%") live_paid_orders,
            (select coalesce(round(sum(total),2),0) from orders where payment_status in ("paid","partially_refunded") and stripe_checkout_session_id like "cs_live_%") live_gross_sales,
            (select coalesce(round(sum(platform_commission_amount),2),0) from order_items oi join orders o on o.id=oi.order_id where o.payment_status in ("paid","partially_refunded") and o.stripe_checkout_session_id like "cs_live_%") asset_moth_commission,
            (select coalesce(round(sum(seller_payout_amount),2),0) from seller_payouts sp join orders o on o.id=sp.order_id where o.stripe_checkout_session_id like "cs_live_%" and sp.payout_status not in ("transfer_failed","reversed")) seller_payouts,
            (select count(*) from waitlist_entries) waitlist_total');
        $waitlist=DB::row('select count(*) total,sum(created_at>=date_sub(now(),interval 7 day)) recent,sum(interest_type in ("buyer","both")) buyer_interest,sum(interest_type in ("seller","both")) seller_interest,sum(confirmation_sent_at is not null) confirmed,sum(invited_at is not null) invited,sum(status="subscribed" and invited_at is null) awaiting_invitation from waitlist_entries');
        H::view('admin/home',['s'=>$stats,'waitlist'=>$waitlist,'recentActivity'=>DB::rows('select * from admin_logs order by created_at desc,id desc limit 8'),'notifications'=>DB::rows('select * from notifications where user_id=? order by created_at desc,id desc limit 8',[$adminId]),'unreadCount'=>(int)(DB::row('select count(*) c from notifications where user_id=? and read_at is null',[$adminId])['c']??0)]);

    }
    public function users()
    {
        $this->gate();
        if ($_POST) {
            H::verifyCsrf();
            $userId = (int)($_POST['id'] ?? 0);
            $status = ($_POST['status'] ?? '') === 'active' ? 'active' : 'disabled';
            DB::begin();
            try {
                $commissionStopped = $status === 'disabled'
                    && (new SellerReferralCommissionService())->permanentlyStop($userId, 'store_disabled');
                DB::exec('update users set status=? where id=?', [$status, $userId]);
                DB::commit();
                if ($commissionStopped) {
                    (new SellerReferralCommissionService())->notifyPermanentStop($userId);
                }
            } catch (Throwable $error) {
                DB::rollBack();
                H::flash('error', 'Account status was not changed.');
            }
        }
        H::view('admin/users',['users'=>DB::rows('select id,name,email,role,status,created_at from users order by created_at desc')]);

    }
    private function userDeletionBlockReason(array $user): ?string
    {
        $id=(int)$user['id'];
        if ($id===(int)H::user()['id']) return 'You cannot permanently delete your own account.';
        if (($user['role']??'')==='admin') return 'Admin accounts cannot be permanently deleted.';
        if (DB::row('select id from orders where user_id=? limit 1',[$id])) return 'This account has an order that must be retained.';
        if (DB::row('select id from coupon_usages where user_id=? limit 1',[$id])) return 'This account has coupon usage history that must be retained.';
        if (DB::row('select id from seller_earnings where buyer_id=? limit 1',[$id])) return 'This account has buyer earnings history that must be retained.';
        if (DB::row('select id from referrals where referrer_user_id=? or referred_user_id=? limit 1',[$id,$id])) return 'This account has immutable referral history that must be retained.';
        if (DB::row('select id from credit_transactions where user_id=? limit 1',[$id])) return 'This account has an immutable financial credit ledger that must be retained.';
        if (DB::row('select id from email_campaigns where created_by=? limit 1',[$id])) return 'This account has email campaign author history that must be retained.';
        if (DB::row('select id from coupons where created_by=? limit 1',[$id])) return 'This account has coupon author history that must be retained.';
        if (DB::row('select id from designer_applications where user_id=? and status="approved" limit 1',[$id])) return 'This account has approved seller application history that must be retained.';
        $designer=DB::row('select id,status from designers where user_id=? limit 1',[$id]);
        if ($designer) {
            if ($designer['status']==='approved') return 'This account has an approved seller store that must be retained.';
            if (DB::row('select id from products where designer_id=? limit 1',[$designer['id']])) return 'This seller account has products that must be retained.';
            if (DB::row('select id from seller_payouts where designer_id=? limit 1',[$designer['id']])) return 'This seller account has payout or transfer history that must be retained.';
            if (DB::row('select id from seller_earnings where designer_id=? limit 1',[$designer['id']])) return 'This seller account has financial earnings history that must be retained.';
            if (DB::row('select id from platform_commissions where designer_id=? limit 1',[$designer['id']])) return 'This seller account has commission history that must be retained.';
            if (DB::row('select id from ads where designer_id=? limit 1',[$designer['id']])) return 'This seller account has advertising history that must be retained.';
            if (DB::row('select id from creator_rank_history where designer_id=? limit 1',[$designer['id']])) return 'This seller account has creator rank history that must be retained.';
            if (DB::row('select c.id from coupons c left join coupon_usages cu on cu.coupon_id=c.id where c.seller_id=? and (c.usage_count>0 or cu.id is not null) limit 1',[$designer['id']])) return 'This seller account has coupon redemption history that must be retained.';
            if (DB::row('select id from order_items where designer_id=? limit 1',[$designer['id']])) return 'This seller account has order-item history that must be retained.';
            if (DB::row('select id from reviews where designer_id=? limit 1',[$designer['id']])) return 'This seller account has store review history that must be retained.';
        }
        if (DB::row('select id from product_ip_risk_scans where seller_id=? limit 1',[$id])||DB::row('select id from product_ip_rights_confirmations where seller_id=? limit 1',[$id])) return 'This seller account has IP compliance history that must be retained.';
        if (DB::row('select id from ip_risk_terms where created_by_admin_id=? or updated_by_admin_id=? limit 1',[$id,$id])||DB::row('select product_id from product_ip_risk_states where reviewed_by_admin_id=? limit 1',[$id])||DB::row('select id from product_ip_risk_review_history where admin_id=? limit 1',[$id])||DB::row('select id from admin_logs where admin_user_id=? limit 1',[$id])) return 'This account has administration or review history that must be retained.';
        if (DB::row('select pt.id from payment_transactions pt join orders o on o.id=pt.order_id where o.user_id=? limit 1',[$id])) return 'This account has payment transaction history that must be retained.';
        if (DB::row('select id from downloads where user_id=? limit 1',[$id])||DB::row('select id from reviews where user_id=? limit 1',[$id])) return 'This account has marketplace history that must be retained.';
        return null;
    }
    private function removeDeletedStoreFile(?string $path, string $folder): void
    {
        if (!in_array($folder,['store_avatars','store_banners'],true)||!$path||!preg_match('#^/uploads/'.preg_quote($folder,'#').'/[a-f0-9]{24,64}\.(?:jpe?g|png|webp)$#',$path)) return;
        $base=realpath(public_path('uploads/'.$folder));
        $file=realpath(public_path(ltrim($path,'/')));
        if ($base&&$file&&str_starts_with($file,$base.DIRECTORY_SEPARATOR)&&is_file($file)) @unlink($file);
    }
    public function deleteUser($id): void
    {
        $this->gate(); $id=(int)$id;
        $user=DB::row('select id,name,email,role,status from users where id=?',[$id]);
        if (!$user) { H::flash('error','User not found.'); H::redirect('/admin/users'); }
        $reason=$this->userDeletionBlockReason($user);
        if ($reason) { H::flash('error',$reason); H::redirect('/admin/users'); }
        if (!hash_equals((string)$user['email'],trim((string)($_POST['confirm_email']??'')))) { H::flash('error','Type the target user’s exact email address to confirm permanent deletion.'); H::redirect('/admin/users'); }
        $storeFiles=[];
        try {
            DB::begin();
            $designer=DB::row('select id,avatar_path,banner_path from designers where user_id=?',[$id]); $designerId=(int)($designer['id']??0);
            if ($designer) $storeFiles=['avatar'=>$designer['avatar_path']??null,'banner'=>$designer['banner_path']??null];
            DB::exec('delete from notifications where user_id=?',[$id]);
            DB::exec('delete from email_preferences where user_id=?',[$id]);
            DB::exec('delete from email_messages where lower(recipient_email)=lower(?)',[$user['email']]);
            DB::exec('delete from email_campaign_recipients where user_id=? or lower(email)=lower(?)',[$id,$user['email']]);
            DB::exec('delete from cart_items where user_id=?',[$id]);
            DB::exec('delete from wishlists where user_id=?',[$id]);
            DB::exec('delete from follows where user_id=?',[$id]);
            DB::exec('delete from marketplace_credits where user_id=?',[$id]);
            DB::exec('delete from credit_transactions where user_id=?',[$id]);
            DB::exec('delete from referrals where referrer_user_id=? or referred_user_id=?',[$id,$id]);
            if ($designerId) DB::exec('delete from referrals where referred_designer_id=?',[$designerId]);
            DB::exec('delete from designer_applications where user_id=? and status in ("pending","denied")',[$id]);
            if ($designerId) {
                DB::exec('delete from follows where designer_id=?',[$designerId]);
                DB::exec('delete from homepage_features where feature_type="designer" and feature_id=?',[$designerId]);
                DB::exec('delete from coupon_restrictions where restrictable_type="seller" and restrictable_id=?',[$designerId]);
                DB::exec('delete from seller_license_presets where designer_id=?',[$designerId]);
                DB::exec('delete cr from coupon_restrictions cr join coupons c on c.id=cr.coupon_id where c.seller_id=? and c.usage_count=0',[$designerId]);
                DB::exec('delete from coupons where seller_id=? and usage_count=0',[$designerId]);
                DB::exec('delete from designers where id=? and status<>"approved"',[$designerId]);
            }
            DB::exec('delete from waitlist_entries where lower(email)=lower(?)',[$user['email']]);
            DB::exec('delete from users where id=?',[$id]);
            $this->log('permanently_deleted_user','user',$id,['personal_data_retained'=>false]);
            DB::commit();
            $this->removeDeletedStoreFile($storeFiles['avatar']??null,'store_avatars');
            $this->removeDeletedStoreFile($storeFiles['banner']??null,'store_banners');
            H::flash('success','User permanently deleted. The email address can register again.');
        } catch (Throwable $e) {
            if (DB::pdo()->inTransaction()) DB::rollBack();
            H::flash('error','Permanent deletion failed. No account records were changed.');
        }
        H::redirect('/admin/users');
    }
    public function applications($id=null)
    {
        $this->gate();
        if($_POST)
        {
           $id=(int)($_POST['id']??0);
            if(($_POST['action']??'')==='approve') $this->approveApplication($id);
            if(($_POST['action']??'')==='deny') $this->denyApplication($id, trim($_POST['reason']??''), trim($_POST['admin_notes']??''));
            H::redirect('/admin/applications');

        }
        if($id)
        {
           H::view('admin/application_detail',['app'=>$this->applicationById($id)??H::abort(404)]);
            return;

        }
        $status=$_GET['status']??'pending';
        $where='';
        $params=[];
        if(in_array($status,['pending','approved','denied'],true))
        {
           $where=' where da.status=?';
            $params[]=$status;

        }
        H::view('admin/applications',['status'=>$status,'apps'=>DB::rows('select da.*,u.name user_name,u.email user_email from designer_applications da join users u on u.id=da.user_id'.$where.' order by field(da.status,"pending","approved","denied"), da.created_at desc',$params)]);

    }
    public function designers()
    {
        $this->gate();
        if($_POST) {
            H::verifyCsrf();
            $id = (int)($_POST['id'] ?? 0);
            $action = $_POST['action'] ?? 'change_rank';
            if (in_array($action,['set_rank_override','remove_rank_override'],true)) {
                try {(new CreatorRecognitionService)->setRankOverride($id,$action==='set_rank_override'?($_POST['creator_rank']??''):null,(int)H::user()['id'],$_POST['reason']??'');H::flash('success','Creator rank recognition updated.');} catch(Throwable $error){H::flash('error',$error instanceof \DomainException?$error->getMessage():'Recognition was not changed.');}
            } elseif (in_array($action,['grant','force_active','force_inactive','automatic','restore'],true)) {
                try {(new CreatorRecognitionService)->founderAction($id,$action,(int)H::user()['id'],$_POST['reason']??'');H::flash('success','Founder recognition updated.');} catch(Throwable $error){H::flash('error',$error instanceof \DomainException?$error->getMessage():'Recognition was not changed.');}
            } elseif (in_array($action, ['disable', 'inactive', 'delete'], true)) {
                $owner = DB::row('select user_id from designers where id=?', [$id]);
                $status = ['disable' => 'disabled', 'inactive' => 'inactive', 'delete' => 'deleted'][$action];
                $reason = ['disable' => 'store_disabled', 'inactive' => 'store_inactive', 'delete' => 'store_deleted'][$action];
                DB::begin();
                try {
                    if (!$owner) {
                        throw new \DomainException('Seller was not found.');
                    }
                    $commissionStopped = (new SellerReferralCommissionService())->permanentlyStop((int)$owner['user_id'], $reason);
                    DB::exec('update designers set status=?, updated_at=now() where id=?', [$status, $id]);
                    DB::commit();
                    if ($commissionStopped) {
                        (new SellerReferralCommissionService())->notifyPermanentStop((int)$owner['user_id']);
                    }
                    H::flash('success', 'Seller status updated. Referral commission cannot restart.');
                } catch (Throwable $error) {
                    DB::rollBack();
                    H::flash('error', 'Seller status was not changed.');
                }
            } elseif ($action === 'enable') {
                DB::exec('update designers set status="approved", updated_at=now() where id=?',[$id]);
                H::flash('success','Seller enabled.');
            } else {
                H::flash('error','Invalid seller action.');
            }
            H::redirect('/admin/designers');
        }
        $status = $_GET['status'] ?? 'approved';
        $allowed = ['approved','disabled','all'];
        if (!in_array($status, $allowed, true)) {
            $status = 'approved';
        }
        $where = '';
        $params = [];
        if ($status !== 'all') {
            $where = ' where d.status=?';
            $params[] = $status;
        }
        H::view('admin/designers',[
            'status'=>$status,
            'designers'=>DB::rows('select d.*,u.email from designers d join users u on u.id=d.user_id'.$where.' order by d.updated_at desc', $params)
        ]);

    }
    public function products()
    {
        $this->gate();
        if($_POST)
        {
            $action = $_POST['action'] ?? '';
            if ($action === 'bulk_approve') {
                $ids = array_values(array_filter(array_map('intval', $_POST['product_ids'] ?? [])));
                $approved = 0;
                $skippedNotPending = 0;
                $skippedIpReview = 0;
                $repo = new IpRiskRepository();
                foreach ($ids as $productId) {
                    $p = DB::row('select id,status from products where id=?', [$productId]);
                    if (!$p || ($p['status'] ?? '') !== 'pending_review') {
                        $skippedNotPending++;
                        continue;
                    }
                    if ($repo->productRequiresIpRiskReview((int)$productId)) {
                        $skippedIpReview++;
                        continue;
                    }
                    if ($this->moderateProduct((int)$p['id'], 'approve', '', false)) {
                        $approved++;
                    } else {
                        $skippedNotPending++;
                    }
                }
                H::flash('success', 'Bulk approval complete: '.$approved.' approved, '.$skippedNotPending.' skipped because no longer pending, '.$skippedIpReview.' skipped because IP review is required.');
            } else {
                $this->moderateProduct((int)$_POST['id'], $action, trim($_POST['reason']??''));
            }
            H::redirect('/admin/products?status='.urlencode($_GET['status'] ?? 'pending_review'));

        }
        $status=$_GET['status']??'pending_review';
        $allowed=['all','draft','pending_review','approved','published','rejected','disabled','archived','deleted'];
        $where='';
        $params=[];
        if(in_array($status,$allowed,true) && $status !== 'all')
        {
            $where=' where p.status=?';
            $params[]=$status;

        }
        H::view('admin/products',['status'=>$status,'products'=>DB::rows('select p.*,coalesce(irs.review_status,"clear") ip_review_status,(select count(*) from product_ip_risk_detections ipd where ipd.product_id=p.id and ipd.scan_id=irs.latest_scan_id and ipd.is_active=1) ip_active_match_count,d.display_name,d.store_slug,c.name category_name,(select count(*) from order_items oi join orders o on o.id=oi.order_id where oi.product_id=p.id and o.payment_status in ("paid","partially_refunded")) completed_order_count,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) thumbnail from products p join designers d on d.id=p.designer_id left join categories c on c.id=p.category_id left join product_ip_risk_states irs on irs.product_id=p.id left join product_ip_risk_scans s on s.id=irs.latest_scan_id'.$where.' order by p.updated_at desc',$params)]);

    }
    private function moderateProduct(int $id, string $action, string $reason='', bool $flashSuccess = true): bool
    {
        $status=['approve'=>'approved','reject'=>'rejected','disable'=>'disabled','archive'=>'archived','restore'=>'draft','mark_deleted'=>'deleted'][$action]??'';
        if(!$status)
        {
           H::flash('error','Invalid product action.');
            return false;

        }
        if($status === 'approved' && (new IpRiskRepository())->productRequiresIpRiskReview($id))
        {
           H::flash('error','IP Review Required: review the product’s IP / Protected Content Risk section before approving this flagged product.');
            return false;

        }
        if($status==='rejected' && trim($reason)==='')
        {
           H::flash('error','Rejection Reason is required.');
            return false;

        }
        $before=DB::row('select status,rejection_reason from products where id=?',[$id]);
        DB::exec('update products set status=?, rejection_reason=?, updated_at=now() where id=?',[$status,$status==='rejected'?$reason:null,$id]);
        $transitionId=$this->log($status.'_product','product',$id,['status'=>$status]);
        $meaningfulTransition=($before['status']??null)!==$status||($status==='rejected'&&($before['rejection_reason']??'')!==$reason);
        try { if($meaningfulTransition){
            $owner=DB::row('select d.user_id,u.email,u.name,p.title,p.rejection_reason from products p join designers d on d.id=p.designer_id join users u on u.id=d.user_id where p.id=?',[$id]);
            if($owner){$event="product:$id:moderation:$transitionId";$message='Your product “'.$owner['title'].'” is now '.$status.'.';if($status==='rejected'&&!empty($owner['rejection_reason']))$message.=' Reason: '.mb_substr(strip_tags($owner['rejection_reason']),0,500);NotificationService::create((int)$owner['user_id'],'product_'.$status,'designer','Product status updated',$message,$event,'/seller/product/'.$id);if(in_array($status,['approved','rejected'],true))EmailQueueService::foundationSellerEmail($owner['email'],'product_'.$status,['name'=>$owner['name'],'title'=>'Product '.ucfirst($status),'message'=>$message,'action_url'=>'/seller/product/'.$id],$event.':email');}}
        } catch(Throwable $e) { NotificationService::reportFailure('product_moderation',$e); }
        if ($flashSuccess) {
            H::flash('success', $status === 'approved' ? 'Product approved and published.' : 'Product status updated.');
        }
        return true;

    }

    private function productHasCompletedOrders(int $productId): bool
    {
        return (bool)DB::row('select oi.id from order_items oi join orders o on o.id=oi.order_id where oi.product_id=? and o.payment_status in ("paid","partially_refunded") limit 1', [$productId]);
    }

    private function permanentlyDeleteProduct(int $productId): void
    {
        (new IpRiskRepository())->cleanupProductRecords($productId);
        DB::exec('delete from cart_items where product_id=?', [$productId]);
        DB::exec('delete from wishlists where product_id=?', [$productId]);
        DB::exec('delete from product_tags where product_id=?', [$productId]);
        DB::exec('delete from product_license_types where product_id=?', [$productId]);
        $this->cleanupProductUploadRowsAndFiles($productId);
        DB::exec('delete from products where id=?', [$productId]);
    }

    private function deleteProductPreviewImage(int $imageId, int $productId): void
    {
        $img = DB::row('select image_path,original_image_path from product_images where id=? and product_id=?', [$imageId, $productId]);
        if ($img) {
            if (!empty($img['image_path'])) {
                $path = public_path(ltrim((string)$img['image_path'], '/'));
                $base = realpath(public_path('uploads/product_previews'));
                $real = realpath($path);
                if ($base && $real && is_file($real) && ($real === $base || str_starts_with($real, $base . DIRECTORY_SEPARATOR))) {
                    @unlink($real);
                }
            }
            if (!empty($img['original_image_path'])) {
                $originalPath = app_path('storage/app/private/' . ltrim((string)$img['original_image_path'], '/'));
                $originalBase = realpath(app_path('storage/app/private/product_previews'));
                $originalReal = realpath($originalPath);
                if ($originalBase && $originalReal && is_file($originalReal) && ($originalReal === $originalBase || str_starts_with($originalReal, $originalBase . DIRECTORY_SEPARATOR))) {
                    @unlink($originalReal);
                }
            }
            DB::exec('delete from product_images where id=? and product_id=?', [$imageId, $productId]);
        }
    }

    private function deleteProductDownloadFile(int $fileId, int $productId): void
    {
        $file = DB::row('select storage_path from product_files where id=? and product_id=?', [$fileId, $productId]);
        if ($file) {
            $path = app_path('storage/protected_uploads/' . ltrim((string)$file['storage_path'], '/'));
            $base = realpath(app_path('storage/protected_uploads/products'));
            $real = realpath($path);
            if ($base && $real && is_file($real) && ($real === $base || str_starts_with($real, $base . DIRECTORY_SEPARATOR))) {
                @unlink($real);
            }
            DB::exec('delete from product_files where id=? and product_id=?', [$fileId, $productId]);
        }
    }

    private function cleanupProductUploadRowsAndFiles(int $productId): void
    {
        foreach (DB::rows('select id from product_images where product_id=?', [$productId]) as $img) {
            $this->deleteProductPreviewImage((int)$img['id'], $productId);
        }
        foreach (DB::rows('select id from product_files where product_id=?', [$productId]) as $file) {
            $this->deleteProductDownloadFile((int)$file['id'], $productId);
        }
    }

    public function bulkProductCleanup(): void
    {
        $this->gate();
        $action = $_POST['bulk_action'] ?? '';
        $ids = array_values(array_filter(array_map('intval', $_POST['product_ids'] ?? [])));
        if (!$ids || !in_array($action, ['archive','delete'], true)) {
            H::flash('error', 'Choose products and a cleanup action.');
            H::redirect('/admin/products');
        }
        $archived = 0;
        $deleted = 0;
        $skipped = 0;
        foreach ($ids as $productId) {
            $p = DB::row('select id,status from products where id=?', [$productId]);
            if (!$p) { $skipped++; continue; }
            if ($action === 'archive') {
                if (($p['status'] ?? '') === 'deleted') {
                    $skipped++;
                    continue;
                }
                DB::exec('update products set status="archived",updated_at=now() where id=?', [$productId]);
                $archived++;
                $this->log('bulk_archived_test_product','product',$productId);
                continue;
            }
            if ($this->productHasCompletedOrders($productId)) {
                DB::exec('update products set status="archived",updated_at=now() where id=?', [$productId]);
                $archived++;
                $this->log('bulk_archived_ordered_product_instead_of_delete','product',$productId);
                continue;
            }
            if (in_array($p['status'], ['draft','rejected','archived','disabled','deleted'], true)) {
                $this->permanentlyDeleteProduct($productId);
                $deleted++;
                $this->log('bulk_permanently_deleted_test_product','product',$productId);
            } else {
                $skipped++;
            }
        }
        H::flash('success', 'Cleanup complete: '.$archived.' archived, '.$deleted.' permanently deleted, '.$skipped.' skipped. Products with completed orders are archived, not deleted.');
        H::redirect('/admin/products?status=all');
    }

    public function productDetail($id)
    {
        $this->gate();
        if($_POST)
        {
           if (($_POST['action'] ?? '') === 'regenerate_watermark') {
               $img = DB::row('select * from product_images where id=? and product_id=?', [(int)($_POST['image_id'] ?? 0), (int)$id]);
               if ($img && !empty($img['original_image_path'])) {
                   $result = WatermarkService::regenerate($img['original_image_path'], $img['image_path']);
                   DB::exec('update product_images set watermark_status=?,watermark_error=?,updated_at=now() where id=? and product_id=?', [$result['ok'] ? WatermarkService::STATUS_WATERMARKED : WatermarkService::STATUS_FAILED, $result['ok'] ? null : $result['message'], (int)$img['id'], (int)$id]);
                   H::flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Watermark regenerated from the private original preview.' : 'Watermark regeneration failed: ' . $result['message']);
               } else H::flash('error', 'Original private preview image is unavailable.');
           } else {
               $this->moderateProduct((int)$id,$_POST['action']??'',trim($_POST['reason']??''));
           }
            H::redirect('/admin/products/'.(int)$id);

        }
        $p=DB::row('select p.*,(select count(*) from order_items oi join orders o on o.id=oi.order_id where oi.product_id=p.id and o.payment_status in ("paid","partially_refunded")) completed_order_count,d.display_name,d.store_slug,d.user_id,u.email designer_email,c.name category_name,c.slug category_slug from products p join designers d on d.id=p.designer_id join users u on u.id=d.user_id left join categories c on c.id=p.category_id where p.id=?',[$id])??H::abort(404);
        $repo = new IpRiskRepository();
        H::view('admin/product_detail',['p'=>$p,'ipState'=>$repo->state((int)$id),'ipDetections'=>$repo->detections((int)$id),'ipConfirmations'=>$repo->confirmations((int)$id),'ipHistory'=>$repo->reviewHistory((int)$id),'images'=>DB::rows('select * from product_images where product_id=? order by sort_order,id',[$id]),'files'=>DB::rows('select * from product_files where product_id=? order by created_at desc',[$id]),'tags'=>DB::rows('select t.* from tags t join product_tags pt on pt.tag_id=t.id where pt.product_id=? order by t.name',[$id]),'licenses'=>LicenseService::productLicenses($p)]);

    }
    public function productIpRiskReview($id): void
    {
        $this->gate();
        $productId = (int)$id;
        $action = $_POST['ip_action'] ?? '';
        $note = trim($_POST['admin_note'] ?? '');
        try {
            $result = (new IpRiskRepository())->applyAdminReviewTransition($productId, $action, $note, (int)H::user()['id']);
            H::flash('success', $result['message'] ?? 'IP risk review updated.');
        } catch (\InvalidArgumentException $e) {
            H::flash('error', $e->getMessage());
        }
        H::redirect('/admin/products/'.$productId);
    }

    public function categories()
    {
        $this->gate();
        if($_POST) DB::exec('insert into categories (name,slug,description,is_active) values (?,?,?,1) on duplicate key update name=values(name),description=values(description),is_active=values(is_active)',[$_POST['name'],H::slug($_POST['slug']),$_POST['description']]);
        H::view('admin/categories',['cats'=>DB::rows('select * from categories')]);

    }
    public function orders()
    {
        $this->gate();
        H::view('admin/orders',['orders'=>DB::rows('select o.*,u.email buyer_email from orders o join users u on u.id=o.user_id order by o.created_at desc')]);

    }
    public function orderDetail($id)
    {
        $this->gate();
        if ($_POST && ($_POST['action'] ?? '') === 'override_fulfillment') {
            $status = $_POST['manual_delivery_status'] ?? '';
            $orderItemId = (int)($_POST['order_item_id'] ?? 0);
            $target = DB::row('select id from order_items where id=? and order_id=? and fulfillment_type="google_drive" limit 1', [$orderItemId, (int)$id]);
            if (!$target) {
                H::flash('error','Manual delivery item not found for this order.');
                H::redirect('/admin/order/'.(int)$id);
            }
            if (in_array($status, ['pending_delivery','buyer_email_needed','ready_for_seller_delivery','delivered','cancelled_refunded'], true)) {
                DB::exec('update order_items set manual_delivery_status=?, delivery_notes=?, delivered_at=case when ?="delivered" then coalesce(delivered_at,now()) else null end where id=? and order_id=? and fulfillment_type="google_drive"', [$status, trim($_POST['delivery_notes'] ?? ''), $status, $orderItemId, (int)$id]);
                $this->log('overrode_fulfillment_status','order_item',$orderItemId,['status'=>$status]);
                H::flash('success','Fulfillment status updated.');
            }
            H::redirect('/admin/order/'.(int)$id);
        }
        $order=DB::row('select o.*,u.email buyer_email,u.name buyer_name from orders o join users u on u.id=o.user_id where o.id=?',[(int)$id])??H::abort(404);
        $items=DB::rows('select oi.*,coalesce(oi.product_title,p.title) title,d.display_name designer_name,d.stripe_account_status,d.stripe_connect_account_id,d.stripe_details_submitted,d.stripe_payouts_enabled,u.email designer_email,se.seller_earning,pc.commission_amount,sp.id seller_payout_id,sp.payout_status ledger_payout_status,sp.stripe_transfer_id ledger_transfer_id,sp.stripe_transfer_error ledger_transfer_error,sp.platform_credit_settled_at,sp.platform_credit_settled_by from order_items oi join products p on p.id=oi.product_id join designers d on d.id=oi.designer_id join users u on u.id=d.user_id left join seller_earnings se on se.order_id=oi.order_id and se.product_id=oi.product_id left join platform_commissions pc on pc.order_id=oi.order_id and pc.product_id=oi.product_id left join seller_payouts sp on sp.order_id=oi.order_id and sp.designer_id=oi.designer_id where oi.order_id=?',[$order['id']]);
        H::view('admin/order_detail',['order'=>$order,'items'=>$items,'transactions'=>DB::rows('select * from payment_transactions where order_id=? order by created_at desc',[$order['id']]),'events'=>DB::rows('select * from stripe_events order by created_at desc limit 20')]);

    }

    public function paymentLogs()
    {
        $this->gate();
        $issue = in_array($_GET['issue'] ?? '', ['failed_transfers','webhook_issues','platform_credit_holds'], true) ? $_GET['issue'] : '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            $id = (int)($_POST['id'] ?? 0);
            $note = mb_substr(trim((string)($_POST['resolution_note'] ?? '')), 0, 500);

            try {
                if ($action === 'resolve_transfer_issue') {
                    $row = DB::row('select id from seller_payouts where id=? and admin_resolved_at is null and (payout_status="transfer_failed" or stripe_transfer_error is not null)', [$id]);
                    if (!$row) {
                        H::flash('warning', 'That open seller-transfer issue was not found.');
                    } else {
                        DB::exec('update seller_payouts set admin_resolved_at=now(),admin_resolved_by=?,admin_resolution_note=?,updated_at=now() where id=?', [(int)H::user()['id'], $note !== '' ? $note : null, $id]);
                        $this->log('resolved_seller_transfer_issue', 'seller_payout', $id, ['resolution_note_provided'=>$note !== '']);
                        H::flash('success', 'Seller-transfer issue marked resolved.');
                    }
                } elseif ($action === 'resolve_webhook_issue') {
                    $row = DB::row('select id from stripe_events where id=? and admin_resolved_at is null and (processing_status="failed" or processing_error is not null)', [$id]);
                    if (!$row) {
                        H::flash('warning', 'That open webhook issue was not found.');
                    } else {
                        DB::exec('update stripe_events set admin_resolved_at=now(),admin_resolved_by=?,admin_resolution_note=? where id=?', [(int)H::user()['id'], $note !== '' ? $note : null, $id]);
                        $this->log('resolved_webhook_issue', 'stripe_event', $id, ['resolution_note_provided'=>$note !== '']);
                        H::flash('success', 'Webhook issue marked resolved.');
                    }
                } else {
                    H::flash('warning', 'Choose a valid issue action.');
                }
            } catch (Throwable $e) {
                H::flash('error', 'The issue could not be updated. Please try again.');
            }

            H::redirect('/admin/payment-logs'.($issue !== '' ? '?issue='.$issue : ''));
        }

        $summary = DB::row('select
            (select count(distinct o.id) from orders o where o.payment_status in ("paid","partially_refunded") and o.stripe_checkout_session_id like "cs_live_%") paid_orders,
            (select coalesce(round(sum(oi.total_price),2),0) from order_items oi join orders o on o.id=oi.order_id where o.payment_status in ("paid","partially_refunded") and o.stripe_checkout_session_id like "cs_live_%") gross_sales,
            (select coalesce(round(sum(o.tax_amount),2),0) from orders o where o.payment_status in ("paid","partially_refunded") and o.stripe_checkout_session_id like "cs_live_%") tax_collected,
            (select coalesce(round(sum(oi.platform_commission_amount),2),0) from order_items oi join orders o on o.id=oi.order_id where o.payment_status in ("paid","partially_refunded") and o.stripe_checkout_session_id like "cs_live_%") marketplace_commission,
            (select coalesce(round(sum(oi.seller_payout_amount),2),0) from order_items oi join orders o on o.id=oi.order_id where o.payment_status in ("paid","partially_refunded") and o.stripe_checkout_session_id like "cs_live_%") seller_payouts,
            (select coalesce(round(sum(coalesce(o.stripe_fee_total,0)),2),0) from orders o where o.payment_status in ("paid","partially_refunded") and o.stripe_checkout_session_id like "cs_live_%") stripe_fees_recorded,
            (select coalesce(round(sum(sp.seller_payout_amount),2),0) from seller_payouts sp join orders o on o.id=sp.order_id where sp.payout_status="transferred" and o.stripe_checkout_session_id like "cs_live_%") seller_transfers_sent,
            (select coalesce(round(sum(sp.seller_payout_amount),2),0) from seller_payouts sp join orders o on o.id=sp.order_id where sp.payout_status="transfer_failed" and sp.admin_resolved_at is null and o.stripe_checkout_session_id like "cs_live_%") seller_transfers_failed
        ');

        $commissionRows = DB::rows('select
            o.id order_id,
            o.payment_status,
            o.total order_total,
            o.tax_amount order_tax_amount,
            o.platform_commission_total order_commission_total,
            o.stripe_fee_total,
            o.stripe_charge_id,
            o.paid_at,
            buyer.email buyer_email,
            oi.product_title,
            oi.total_price item_total,
            oi.commission_rate,
            oi.platform_commission_amount,
            oi.seller_payout_amount,
            oi.seller_payout_status,
            oi.stripe_transfer_id item_transfer_id,
            oi.stripe_transfer_error item_transfer_error,
            d.display_name seller_name,
            seller.email seller_email,
            sp.payout_status ledger_payout_status,
            sp.stripe_transfer_id ledger_transfer_id,
            sp.stripe_transfer_error ledger_transfer_error
        from orders o
        join order_items oi on oi.order_id=o.id
        join users buyer on buyer.id=o.user_id
        join designers d on d.id=oi.designer_id
        join users seller on seller.id=d.user_id
        left join seller_payouts sp on sp.order_id=o.id and sp.designer_id=oi.designer_id
        where o.payment_status in ("paid","partially_refunded")
          and o.stripe_checkout_session_id like "cs_live_%"
        order by o.id desc, oi.id desc
        limit 200');

        $transferIssues = DB::rows('select sp.*,o.id order_id,d.display_name seller_name,u.email seller_email from seller_payouts sp join orders o on o.id=sp.order_id join designers d on d.id=sp.designer_id join users u on u.id=d.user_id where (sp.payout_status="transfer_failed" or sp.stripe_transfer_error is not null) and sp.admin_resolved_at is null order by sp.updated_at desc,sp.id desc limit 100');
        $platformCreditHolds = DB::rows('select sp.*,o.id order_id,o.internally_completed,o.manual_review_required,o.stripe_charge_id,d.display_name seller_name,d.stripe_connect_account_id,d.stripe_payouts_enabled,d.stripe_details_submitted,u.email seller_email from seller_payouts sp join orders o on o.id=sp.order_id join designers d on d.id=sp.designer_id join users u on u.id=d.user_id where sp.payout_status="platform_credit_hold" order by sp.updated_at desc,sp.id desc limit 100');
        $webhookIssues = DB::rows('select * from stripe_events where (processing_status="failed" or processing_error is not null) and admin_resolved_at is null order by created_at desc,id desc limit 100');

        H::view('admin/payment_logs',[
            'summary'=>$summary,
            'issue'=>$issue,
            'transferIssues'=>$transferIssues,
            'platformCreditHolds'=>$platformCreditHolds,
            'webhookIssues'=>$webhookIssues,
            'commissionRows'=>$commissionRows,
            'transactions'=>DB::rows('select pt.*,u.email buyer_email from payment_transactions pt left join orders o on o.id=pt.order_id left join users u on u.id=o.user_id order by pt.created_at desc limit 200'),
            'events'=>DB::rows('select * from stripe_events order by created_at desc limit 200')
        ]);
    }

    public function downloads()
    {
        $this->gate();
        H::view('admin/table',['title'=>'Download logs','rows'=>DB::rows('select dl.id,dl.order_id,dl.order_item_id,dl.product_id,dl.product_file_id,dl.status,dl.message,u.email user_email,dl.ip_address,dl.created_at from downloads dl join users u on u.id=dl.user_id order by dl.created_at desc limit 200')]);
    }
    public function homepage()
    {
        $this->gate();

        if ($_POST) {
            H::verifyCsrf();

            $action = trim((string)($_POST['action'] ?? 'add'));

            if ($action === 'delete') {
                $featureRecordId = (int)($_POST['feature_record_id'] ?? 0);
                $feature = DB::row(
                    'select id from homepage_features where id=?',
                    [$featureRecordId]
                );

                if (!$feature) {
                    H::flash('error', 'Homepage feature not found.');
                    H::redirect('/admin/homepage');
                }

                DB::exec(
                    'delete from homepage_features where id=?',
                    [$featureRecordId]
                );

                H::flash('success', 'Homepage feature removed.');
                H::redirect('/admin/homepage');
            }

            if ($action === 'update') {
                $featureRecordId = (int)($_POST['feature_record_id'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                $feature = DB::row(
                    'select id from homepage_features where id=?',
                    [$featureRecordId]
                );

                if (!$feature) {
                    H::flash('error', 'Homepage feature not found.');
                    H::redirect('/admin/homepage');
                }

                DB::exec(
                    'update homepage_features
                     set is_active=?,updated_at=now()
                     where id=?',
                    [$isActive, $featureRecordId]
                );

                H::flash('success', 'Homepage feature updated.');
                H::redirect('/admin/homepage');
            }

            if ($action === 'reorder') {
                $featureType = trim((string)($_POST['feature_type'] ?? ''));
                $allowedTypes = ['product', 'designer', 'category'];

                if (!in_array($featureType, $allowedTypes, true)) {
                    H::flash('error', 'Invalid homepage feature type.');
                    H::redirect('/admin/homepage');
                }

                $rawOrder = trim((string)($_POST['feature_order'] ?? ''));
                $ids = array_values(array_unique(array_filter(
                    array_map(
                        static fn($value) => ctype_digit(trim($value))
                            ? (int)trim($value)
                            : 0,
                        explode(',', $rawOrder)
                    ),
                    static fn($value) => $value > 0
                )));

                $existingRows = DB::rows(
                    'select id
                     from homepage_features
                     where feature_type=?
                     order by sort_order,id',
                    [$featureType]
                );

                $existingIds = array_map(
                    static fn($row) => (int)$row['id'],
                    $existingRows
                );

                $submittedIds = $ids;
                sort($existingIds);
                sort($submittedIds);

                if ($existingIds !== $submittedIds) {
                    H::flash(
                        'error',
                        'The homepage order could not be saved. Refresh and try again.'
                    );
                    H::redirect('/admin/homepage');
                }

                try {
                    DB::begin();

                    foreach ($ids as $position => $id) {
                        DB::exec(
                            'update homepage_features
                             set sort_order=?,updated_at=now()
                             where id=? and feature_type=?',
                            [$position, $id, $featureType]
                        );
                    }

                    DB::commit();
                    H::flash('success', 'Homepage order saved.');
                } catch (Throwable $error) {
                    DB::rollBack();
                    H::flash('error', 'The homepage order could not be saved.');
                }

                H::redirect('/admin/homepage');
            }

            $target = trim((string)($_POST['feature_target'] ?? ''));

            [$featureType, $rawId] = array_pad(explode(':', $target, 2), 2, '');
            $allowedTypes = ['product', 'designer', 'category'];

            if (!in_array($featureType, $allowedTypes, true) || !ctype_digit($rawId) || (int)$rawId < 1) {
                H::flash('error', 'Please choose a valid homepage feature.');
                H::redirect('/admin/homepage');
            }

            $featureId = (int)$rawId;

            $exists = match ($featureType) {
                'designer' => DB::row(
                    'select id from designers where id=? and status="approved"',
                    [$featureId]
                ),
                'product' => DB::row(
                    'select id from products where id=? and status in ("approved","published")',
                    [$featureId]
                ),
                'category' => DB::row(
                    'select id from categories where id=? and is_active=1',
                    [$featureId]
                ),
            };

            if (!$exists) {
                H::flash('error', 'That item is not available to feature.');
                H::redirect('/admin/homepage');
            }

            $duplicate = DB::row(
                'select id from homepage_features where feature_type=? and feature_id=? limit 1',
                [$featureType, $featureId]
            );

            if ($duplicate) {
                H::flash('warning', 'That item is already listed as a homepage feature.');
                H::redirect('/admin/homepage');
            }

            $nextOrder = (int)(
                DB::row(
                    'select coalesce(max(sort_order),-1)+1 next_order
                     from homepage_features
                     where feature_type=?',
                    [$featureType]
                )['next_order'] ?? 0
            );

            DB::exec(
                'insert into homepage_features
                 (feature_type,feature_id,sort_order,is_active)
                 values (?,?,?,1)',
                [$featureType, $featureId, $nextOrder]
            );

            H::flash('success', 'Homepage feature added.');
            H::redirect('/admin/homepage');
        }

        $features = DB::rows(
            'select hf.*,
                    case
                        when hf.feature_type="designer" then coalesce(d.display_name,d.store_slug,concat("Designer #",hf.feature_id))
                        when hf.feature_type="product" then coalesce(p.title,concat("Product #",hf.feature_id))
                        when hf.feature_type="category" then coalesce(c.name,concat("Category #",hf.feature_id))
                        else concat("Item #",hf.feature_id)
                    end feature_name
             from homepage_features hf
             left join designers d on hf.feature_type="designer" and d.id=hf.feature_id
             left join products p on hf.feature_type="product" and p.id=hf.feature_id
             left join categories c on hf.feature_type="category" and c.id=hf.feature_id
             order by hf.sort_order,hf.id'
        );

        $groupedFeatures = [
            'product' => [],
            'designer' => [],
            'category' => [],
        ];

        foreach ($features as $feature) {
            $type = $feature['feature_type'];

            if (isset($groupedFeatures[$type])) {
                $groupedFeatures[$type][] = $feature;
            }
        }

        H::view('admin/homepage', [
            'features' => $features,
            'groupedFeatures' => $groupedFeatures,
            'designers' => DB::rows(
                'select id,coalesce(display_name,store_slug,concat("Designer #",id)) label
                 from designers where status="approved" order by label'
            ),
            'products' => DB::rows(
                'select p.id,concat(coalesce(p.title,concat("Product #",p.id))," — ",coalesce(d.display_name,d.store_slug,"Unknown seller")) label
                 from products p
                 left join designers d on d.id=p.designer_id
                 where p.status in ("approved","published")
                 order by label'
            ),
            'categories' => DB::rows(
                'select id,coalesce(name,concat("Category #",id)) label
                 from categories where is_active=1 order by label'
            ),
        ]);
    }
    public function ads()
    {
        $this->gate();
        if($_POST) DB::exec('insert into ads (product_id,designer_id,placement,start_date,end_date,status) values (?,?,?,?,?,?)',[$_POST['product_id'],$_POST['designer_id'],$_POST['placement'],$_POST['start_date'],$_POST['end_date'],$_POST['status']]);
        H::view('admin/ads',['ads'=>DB::rows('select * from ads')]);

    }

    private function couponRestrictions(int $id): array
    {
        $out = ['seller'=>'','product'=>'','category'=>''];
        foreach (DB::rows('select restrictable_type, group_concat(restrictable_id order by restrictable_id) ids from coupon_restrictions where coupon_id=? group by restrictable_type', [$id]) as $r) $out[$r['restrictable_type']] = $r['ids'];
        return $out;
    }

    private function saveRestrictions(int $couponId, array $values): void
    {
        DB::exec('delete from coupon_restrictions where coupon_id=?', [$couponId]);
        foreach (['seller'=>'seller_ids','product'=>'product_ids','category'=>'category_ids'] as $type=>$field) {
            foreach ($this->validRestrictionIds($type, $field) as $rid) {
                DB::exec('insert ignore into coupon_restrictions (coupon_id,restrictable_type,restrictable_id) values (?,?,?)', [$couponId,$type,$rid]);
            }
        }
    }

    private function nullablePositiveInt(string $field, array &$errors): ?int
    {
        $raw = trim((string)($_POST[$field] ?? ''));
        if ($raw === '') return null;
        if (!ctype_digit($raw) || (int)$raw < 1) { $errors[] = str_replace('_',' ', $field) . ' must be blank or a positive integer.'; return null; }
        return (int)$raw;
    }

    private function validRestrictionIds(string $type, string $field): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', preg_split('/\s*,\s*/', trim($_POST[$field] ?? ''), -1, PREG_SPLIT_NO_EMPTY)))));
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($type === 'seller') $rows = DB::rows('select id from designers where status="approved" and id in (' . $placeholders . ')', $ids);
        elseif ($type === 'product') $rows = DB::rows('select id from products where id in (' . $placeholders . ')', $ids);
        else $rows = DB::rows('select id from categories where id in (' . $placeholders . ')', $ids);
        return array_map('intval', array_column($rows, 'id'));
    }

    private function couponValues(array &$errors): array
    {
        $code = \App\Services\CouponService::normalizeCode($_POST['code'] ?? '');
        $type = in_array($_POST['discount_type'] ?? '', ['percent','fixed'], true) ? $_POST['discount_type'] : 'percent';
        $scope = in_array($_POST['scope'] ?? '', ['platform','seller'], true) ? $_POST['scope'] : 'platform';
        $sellerId = $scope === 'seller' ? (int)($_POST['seller_id'] ?? 0) : null;
        $value = (float)($_POST['discount_value'] ?? 0);
        $starts = $_POST['starts_at'] ?: null;
        $ends = $_POST['ends_at'] ?: null;
        if ($code === '') $errors[] = 'Coupon code is required.';
        if ($value <= 0 || ($type === 'percent' && $value > 100)) $errors[] = 'Discount value is invalid.';
        if ($starts && $ends && $ends < $starts) $errors[] = 'End date cannot be before start date.';
        if ($scope === 'seller' && (!$sellerId || !DB::row('select id from designers where id=? and status="approved"', [$sellerId]))) $errors[] = 'Seller scope requires an approved seller.';
        return [$code,$scope,$sellerId,$type,max(0.01,$value),$starts,$ends,isset($_POST['is_active']) ? 1 : 0,max(0,(float)($_POST['min_cart_amount'] ?? 0)),$this->nullablePositiveInt('usage_limit',$errors),$this->nullablePositiveInt('per_user_limit',$errors)];
    }

    public function coupons($id = null)
    {
        $this->gate();
        $creating = ($id === 'new');
        if ($creating) $id = null;
        if ($_POST) {
            $errors = [];
            [$code,$scope,$sellerId,$type,$value,$starts,$ends,$active,$min,$limit,$userLimit] = $this->couponValues($errors);
            if ($errors) H::flash('error', implode(' ', $errors));
            else {
                try { DB::begin();
                    if ($id) DB::exec('update coupons set code=?,scope=?,seller_id=?,discount_type=?,discount_value=?,starts_at=?,ends_at=?,is_active=?,min_cart_amount=?,usage_limit=?,per_user_limit=? where id=?', [$code,$scope,$sellerId,$type,$value,$starts,$ends,$active,$min,$limit,$userLimit,(int)$id]);
                    else { DB::exec('insert into coupons (code,scope,seller_id,discount_type,discount_value,starts_at,ends_at,is_active,min_cart_amount,usage_limit,per_user_limit,created_by) values (?,?,?,?,?,?,?,?,?,?,?,?)', [$code,$scope,$sellerId,$type,$value,$starts,$ends,$active,$min,$limit,$userLimit,H::user()['id']]); $id = DB::id(); }
                    $this->saveRestrictions((int)$id, $_POST); DB::commit(); H::flash('success','Coupon saved.'); H::redirect('/admin/coupons');
                } catch (Throwable $e) { DB::rollBack(); H::flash('error','Coupon code already exists or could not be saved.'); }
            }
        }
        if ($creating || $id) H::view('admin/coupon_form',['coupon'=>$id ? (DB::row('select * from coupons where id=?',[(int)$id]) ?? H::abort(404)) : [],'sellers'=>DB::rows('select id,display_name from designers where status="approved" order by display_name'),'restrictions'=>$id ? $this->couponRestrictions((int)$id) : ['seller'=>'','product'=>'','category'=>'']]);
        else H::view('admin/coupons',['coupons'=>DB::rows('select c.*,d.display_name seller_name,(select group_concat(concat(restrictable_type,":",restrictable_id) separator ", ") from coupon_restrictions cr where cr.coupon_id=c.id) restriction_summary from coupons c left join designers d on d.id=c.seller_id order by c.created_at desc')]);
    }

}
