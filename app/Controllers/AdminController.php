<?php

namespace App\Controllers;
use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\LicenseService;
use App\Services\WatermarkService;
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
    private function log(string $action, string $entityType, int $entityId, array $metadata=[]): void
    {
        DB::exec('insert into admin_logs (admin_user_id,action,entity_type,entity_id,metadata) values (?,?,?,?,?)',[H::user()['id'],$action,$entityType,$entityId,json_encode($metadata)]);

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
        H::view('admin/home',['s'=>DB::row('select (select count(*) from users) users,(select count(*) from designers) designers,(select count(*) from designer_applications where status="pending") apps,(select count(*) from products where status="pending_review") products,(select count(*) from orders) orders,(select coalesce(sum(total),0) from orders) gross,(select coalesce(sum(commission_amount),0) from platform_commissions) commission')]);

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
        if($_POST) DB::exec('update designers set creator_rank=? where id=?',[$_POST['creator_rank'],$_POST['id']]);
        H::view('admin/designers',['designers'=>DB::rows('select d.*,u.email from designers d join users u on u.id=d.user_id order by d.updated_at desc')]);

    }
    public function products()
    {
        $this->gate();
        if($_POST)
        {
           $this->moderateProduct((int)$_POST['id'],$_POST['action']??'',trim($_POST['reason']??''));
            H::redirect('/admin/products');

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
        H::view('admin/products',['status'=>$status,'products'=>DB::rows('select p.*,d.display_name,d.store_slug,c.name category_name,(select count(*) from order_items oi join orders o on o.id=oi.order_id where oi.product_id=p.id and o.payment_status in ("paid","partially_refunded")) completed_order_count from products p join designers d on d.id=p.designer_id left join categories c on c.id=p.category_id'.$where.' order by p.updated_at desc',$params)]);

    }
    private function moderateProduct(int $id, string $action, string $reason=''): void
    {
        $status=['approve'=>'approved','reject'=>'rejected','disable'=>'disabled','archive'=>'archived','restore'=>'draft','mark_deleted'=>'deleted'][$action]??'';
        if(!$status)
        {
           H::flash('error','Invalid product action.');
            return;

        }
        if($status==='rejected' && mb_strlen($reason)<5)
        {
           H::flash('error','Rejection Reason is required.');
            return;

        }
        DB::exec('update products set status=?, rejection_reason=?, updated_at=now() where id=?',[$status,$status==='rejected'?$reason:null,$id]);
        $this->log($status.'_product','product',$id);
        H::flash('success','Product status updated.');

    }

    private function productHasCompletedOrders(int $productId): bool
    {
        return (bool)DB::row('select oi.id from order_items oi join orders o on o.id=oi.order_id where oi.product_id=? and o.payment_status in ("paid","partially_refunded") limit 1', [$productId]);
    }

    private function permanentlyDeleteProduct(int $productId): void
    {
        DB::exec('delete from cart_items where product_id=?', [$productId]);
        DB::exec('delete from wishlists where product_id=?', [$productId]);
        DB::exec('delete from product_tags where product_id=?', [$productId]);
        DB::exec('delete from product_license_types where product_id=?', [$productId]);
        DB::exec('delete from product_images where product_id=?', [$productId]);
        DB::exec('delete from product_files where product_id=?', [$productId]);
        DB::exec('delete from products where id=?', [$productId]);
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
        H::view('admin/product_detail',['p'=>$p,'images'=>DB::rows('select * from product_images where product_id=? order by sort_order,id',[$id]),'files'=>DB::rows('select * from product_files where product_id=? order by created_at desc',[$id]),'tags'=>DB::rows('select t.* from tags t join product_tags pt on pt.tag_id=t.id where pt.product_id=? order by t.name',[$id]),'licenses'=>LicenseService::productLicenses($p)]);

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
        H::view('admin/payment_logs',['transactions'=>DB::rows('select pt.*,u.email buyer_email from payment_transactions pt left join orders o on o.id=pt.order_id left join users u on u.id=o.user_id order by pt.created_at desc limit 200'),'events'=>DB::rows('select * from stripe_events order by created_at desc limit 200')]);
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

}
