<?php

namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Repositories\CollabRepository;
use App\Services\CollabIpRiskWorkflow;
use App\Services\CollabService;

final class AdminCollabController
{
    public function index(): void
    {
        H::requireAdminPermission('products.view');
        H::view('collabs/admin', ['collabs'=>DB::rows('select c.*,d.display_name host_name,(select count(distinct order_id) from collab_order_allocations a where a.collab_id=c.id) sale_count from collab_events c join designers d on d.id=c.host_designer_id order by c.created_at desc')]);
    }

    public function show($id): void
    {
        H::requireAdminPermission('products.view');
        $repository=new CollabRepository(); $collab=$repository->event((int)$id)??H::abort(404);
        H::view('collabs/admin_show', [
            'collab'=>$collab, 'participants'=>$repository->participants((int)$id),
            'detections'=>DB::rows('select * from collab_ip_risk_detections where collab_id=? and is_active=1 order by id',[$id]),
            'allocations'=>DB::rows('select a.*,d.display_name,sp.payout_status,sp.recovery_reserved_amount,sp.recovery_applied_amount from collab_order_allocations a join designers d on d.id=a.designer_id left join seller_payouts sp on sp.order_id=a.order_id and sp.designer_id=a.designer_id where a.collab_id=? order by a.order_id,a.designer_id',[$id]),
        ]);
    }

    public function retry($id): void { H::requireAdminPermission('products.manage'); H::verifyCsrf(); (new CollabService())->processDue((int)$id); H::redirect('/admin/collabs/'.$id); }
    public function ipReview($id): void
    {
        H::requireAdminPermission('ip_risk.manage');
        H::verifyCsrf();
        try {
            (new CollabIpRiskWorkflow())->review((int)$id,(int)H::user()['id'],(string)($_POST['decision']??''),(string)($_POST['fingerprint']??''),(string)($_POST['notes']??''));
            H::flash('success','Collab IP review saved.');
        } catch (\DomainException|\InvalidArgumentException $error) {
            H::flash('error',$error->getMessage());
        }
        H::redirect('/admin/collabs/'.$id);
    }
}
