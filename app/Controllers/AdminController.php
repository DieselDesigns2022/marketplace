<?php

namespace App\Controllers;
use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\LicenseService;
use App\Services\WatermarkService;
use App\Repositories\IpRiskRepository;
use App\Services\NotificationService;
use App\Services\EmailQueueService;
use Throwable;
class AdminController
{
    private function gate()
    {
        H::requireRole('admin');

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
        H::view('admin/home',['s'=>DB::row('select
            (select count(*) from users where status="active") active_users,
            (select count(*) from designers where status="approved") approved_designers,
            (select count(*) from designer_applications where status="pending") pending_apps,
            (select count(*) from products where status="pending_review") pending_products,
            (select count(*) from orders where payment_status in ("paid","partially_refunded") and stripe_checkout_session_id like "cs_live_%") live_paid_orders,
            (select coalesce(round(sum(total),2),0) from orders where payment_status in ("paid","partially_refunded") and stripe_checkout_session_id like "cs_live_%") live_gross_sales,
            (select coalesce(round(sum(platform_commission_amount),2),0) from order_items oi join orders o on o.id=oi.order_id where o.payment_status in ("paid","partially_refunded") and o.stripe_checkout_session_id like "cs_live_%") asset_moth_commission
        ')]);

    }
    public function users()
    {
        $this->gate();
        if($_POST) DB::exec('update users set status=? where id=?',[$_POST['status'],$_POST['id']]);
        H::view('admin/table',['title'=>'Users','rows'=>DB::rows('select id,name,email,role,status,created_at from users order by created_at desc')]);

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
            $id = (int)($_POST['id'] ?? 0);
            $action = $_POST['action'] ?? 'change_rank';
            if ($action === 'change_rank') {
                $rank = $_POST['creator_rank'] ?? 'Bronze';
                if (!in_array($rank, ['Bronze','Silver','Gold','Platinum','Legend'], true)) {
                    H::flash('error','Invalid creator rank.');
                } else {
                    DB::exec('update designers set creator_rank=?, updated_at=now() where id=?',[$rank,$id]);
                    H::flash('success','Seller rank updated.');
                }
            } elseif ($action === 'disable') {
                DB::exec('update designers set status="disabled", updated_at=now() where id=?',[$id]);
                H::flash('success','Seller disabled.');
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
        $items=DB::rows('select oi.*,coalesce(oi.product_title,p.title) title,d.display_name designer_name,d.stripe_account_status,d.stripe_connect_account_id,d.stripe_details_submitted,d.stripe_payouts_enabled,u.email designer_email,se.seller_earning,pc.commission_amount,sp.payout_status ledger_payout_status,sp.stripe_transfer_id ledger_transfer_id,sp.stripe_transfer_error ledger_transfer_error from order_items oi join products p on p.id=oi.product_id join designers d on d.id=oi.designer_id join users u on u.id=d.user_id left join seller_earnings se on se.order_id=oi.order_id and se.product_id=oi.product_id left join platform_commissions pc on pc.order_id=oi.order_id and pc.product_id=oi.product_id left join seller_payouts sp on sp.order_id=oi.order_id and sp.designer_id=oi.designer_id where oi.order_id=?',[$order['id']]);
        H::view('admin/order_detail',['order'=>$order,'items'=>$items,'transactions'=>DB::rows('select * from payment_transactions where order_id=? order by created_at desc',[$order['id']]),'events'=>DB::rows('select * from stripe_events order by created_at desc limit 20')]);

    }

    public function paymentLogs()
    {
        $this->gate();

        $summary = DB::row('select
            (select count(distinct o.id) from orders o where o.payment_status in ("paid","partially_refunded") and o.stripe_checkout_session_id like "cs_live_%") paid_orders,
            (select coalesce(round(sum(oi.total_price),2),0) from order_items oi join orders o on o.id=oi.order_id where o.payment_status in ("paid","partially_refunded") and o.stripe_checkout_session_id like "cs_live_%") gross_sales,
            (select coalesce(round(sum(o.tax_amount),2),0) from orders o where o.payment_status in ("paid","partially_refunded") and o.stripe_checkout_session_id like "cs_live_%") tax_collected,
            (select coalesce(round(sum(oi.platform_commission_amount),2),0) from order_items oi join orders o on o.id=oi.order_id where o.payment_status in ("paid","partially_refunded") and o.stripe_checkout_session_id like "cs_live_%") marketplace_commission,
            (select coalesce(round(sum(oi.seller_payout_amount),2),0) from order_items oi join orders o on o.id=oi.order_id where o.payment_status in ("paid","partially_refunded") and o.stripe_checkout_session_id like "cs_live_%") seller_payouts,
            (select coalesce(round(sum(coalesce(o.stripe_fee_total,0)),2),0) from orders o where o.payment_status in ("paid","partially_refunded") and o.stripe_checkout_session_id like "cs_live_%") stripe_fees_recorded,
            (select coalesce(round(sum(sp.seller_payout_amount),2),0) from seller_payouts sp join orders o on o.id=sp.order_id where sp.payout_status="transferred" and o.stripe_checkout_session_id like "cs_live_%") seller_transfers_sent,
            (select coalesce(round(sum(sp.seller_payout_amount),2),0) from seller_payouts sp join orders o on o.id=sp.order_id where sp.payout_status="transfer_failed" and o.stripe_checkout_session_id like "cs_live_%") seller_transfers_failed
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

        H::view('admin/payment_logs',[
            'summary'=>$summary,
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
    public function referrals()
    {
        $this->gate();
        H::view('admin/table',['title'=>'Referrals','rows'=>DB::rows('select * from referrals order by created_at desc')]);

    }
    public function homepage()
    {
        $this->gate();
        if($_POST)
        {
           DB::exec('insert into homepage_features (feature_type,feature_id,sort_order,is_active) values (?,?,?,1)',[$_POST['feature_type'],$_POST['feature_id'],$_POST['sort_order']]);

        }
        H::view('admin/homepage',['features'=>DB::rows('select * from homepage_features')]);

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
