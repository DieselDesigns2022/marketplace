<?php
namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\IpAuditWarningService;
use DomainException;

final class AdminIpAuditController
{
    public function index(): void
    {
        H::requireAdminPermission('ip_risk.view');

        $status = (string)($_GET['status'] ?? 'pending');
        if (!in_array(
            $status,
            ['pending','dismissed','violation','all'],
            true
        )) {
            $status = 'pending';
        }

        $filter = $status === 'all'
            ? ' where p.status in ("approved","published")'
            : ' where p.status in ("approved","published")'
              . ' and f.review_status=?';

        $params = $status === 'all' ? [] : [$status];

        $findings = DB::rows(
            'select f.*,p.title,p.status product_status,
                    d.display_name shop_name,d.id designer_id,
                    (select image_path from product_images pi
                     where pi.product_id=p.id
                     order by pi.sort_order,pi.id limit 1)
                    thumbnail
             from ip_audit_findings f
             join products p on p.id=f.product_id
             join designers d on d.id=p.designer_id'
             . $filter .
            ' order by
              case when p.status in
                 ("approved","published","scheduled")
                   then 0 else 1 end,
              f.last_scanned_at desc,f.product_id desc
              limit 300',
            $params
        );

        $warnings = DB::rows(
            'select w.*,d.display_name shop_name,p.title,
                    u.name admin_name
             from seller_ip_warnings w
             join designers d on d.id=w.designer_id
             join products p on p.id=w.product_id
             join users u on u.id=w.issued_by_admin_id
             order by w.issued_at desc,w.id desc
             limit 300'
        );

        $totals = DB::row(
            'select count(*) total,
                    sum(review_status="pending") pending,
                    sum(review_status="dismissed") dismissed,
                    sum(review_status="violation") violations
             from ip_audit_findings f
             join products p on p.id=f.product_id
             where p.status in ("approved","published")'
        ) ?: [];

        H::view('admin/ip_audit',compact(
            'status','findings','warnings','totals'
        ));
    }

    public function rescan(): void
    {
        H::requireAdminPermission('ip_risk.manage');
        H::verifyCsrf();

        try {
            $stats = (new IpAuditWarningService())->rescanAll();

            H::flash(
                'success',
                'Full audit finished: ' .
                $stats['scanned'] . ' public products scanned; ' .
                $stats['matched'] . ' matched; ' .
                $stats['pending'] . ' awaiting review; ' .
                $stats['public_pending'] .
                ' public products awaiting review.'
            );
        } catch (\Throwable $e) {
            error_log('IP audit rescan failed: '.$e->getMessage());
            H::flash(
                'error',
                'The IP audit could not finish. No products were ' .
                'removed and no warnings were issued.'
            );
        }

        H::redirect('/admin/ip-audit');
    }


    public function confirmGroup(): void
    {
        H::requireAdminPermission('ip_risk.manage');
        H::verifyCsrf();

        $ids = $_POST['product_ids'] ?? [];
        $note = trim((string)($_POST['note'] ?? ''));

        try {
            if (!is_array($ids)) {
                throw new \DomainException('Invalid product selection.');
            }
            $message = (new IpAuditWarningService())->confirmGroup(
                $ids,
                (int)H::user()['id'],
                $note
            );
            H::flash('success', $message);
        } catch (\DomainException $e) {
            H::flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            error_log('Grouped IP warning error: '.$e->getMessage());
            H::flash('error', 'The grouped warning could not be saved.');
        }

        H::redirect('/admin/ip-audit?status=pending');
    }

    public function decision($id): void
    {
        H::requireAdminPermission('ip_risk.manage');
        H::verifyCsrf();

        $action = (string)($_POST['decision'] ?? '');
        $note = trim((string)($_POST['note'] ?? ''));

        try {
            $message = (new IpAuditWarningService())->review(
                (int)$id,
                (int)H::user()['id'],
                $action,
                $note
            );

            H::flash('success',$message);
        } catch (DomainException $e) {
            H::flash('error',$e->getMessage());
        } catch (\Throwable $e) {
            error_log('IP audit decision error: '.$e->getMessage());
            H::flash('error','The review could not be saved.');
        }

        H::redirect('/admin/ip-audit');
    }
}
