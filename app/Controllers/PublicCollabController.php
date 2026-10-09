<?php

namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Repositories\CollabRepository;
use App\Services\CollabCheckoutService;
use App\Services\CollabService;
use App\Services\CreditService;

final class PublicCollabController
{
    public function show($slug): void
    {
        $repository = new CollabRepository();
        $collab = $repository->bySlug((string)$slug) ?? H::abort(404);
        if (!(new CollabService($repository))->canSell($collab)) H::abort(404);
        $storefront = isset($_GET['store']) ? (int)$_GET['store'] : null;
        if ($storefront && (($repository->participant((int)$collab['id'], $storefront)['eligibility'] ?? '') !== 'eligible')) $storefront = null;
        $contributionCounts = DB::rows(
            'select
                coalesce(cat.name,"Uncategorized") category_name,
                count(*) file_count
             from collab_files f
             left join categories cat
               on cat.id=f.category_id
             where f.collab_id=?
               and f.file_kind="contribution"
               and f.included_in_snapshot=1
             group by
                cat.id,
                cat.name
             order by
                cat.name',
            [(int)$collab['id']]
        );

        $totalContributionFiles = array_sum(
            array_map(
                static fn(array $row): int =>
                    (int)$row['file_count'],
                $contributionCounts
            )
        );

        $utc = new \DateTimeZone('UTC');

        $hostTimezoneName =
            trim((string)($collab['host_timezone'] ?? ''))
                ?: 'America/New_York';

        try {
            $hostTimezone = new \DateTimeZone(
                $hostTimezoneName
            );
        } catch (\Throwable) {
            $hostTimezone = new \DateTimeZone(
                'America/New_York'
            );
        }

        $saleStartsDisplay =
            (new \DateTimeImmutable(
                (string)$collab['sale_starts_at'],
                $utc
            ))
            ->setTimezone($hostTimezone)
            ->format('F j, Y \\a\\t g:i A T');

        $saleCloseDisplay =
            (new \DateTimeImmutable(
                (string)$collab['sale_close_date'].' 23:59:59',
                $hostTimezone
            ))
            ->format('F j, Y \\a\\t g:i A T');

        H::view('collabs/public', [
            'collab'=>$collab,
            'storefront'=>$storefront,
            'participants'=>array_values(array_filter($repository->participants((int)$collab['id']), fn($participant) => $participant['eligibility'] === 'eligible')),
            'contributionCounts'=>$contributionCounts,
            'totalContributionFiles'=>$totalContributionFiles,
            'saleStartsDisplay'=>$saleStartsDisplay,
            'saleCloseDisplay'=>$saleCloseDisplay,
        ]);
    }

    public function checkout($slug): void
    {
        H::requireLogin();
        $repository = new CollabRepository();
        $collab = $repository->bySlug((string)$slug) ?? H::abort(404);
        if (!(new CollabService($repository))->canSell($collab)) H::abort(404);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            H::verifyCsrf();
            try {
                $result = (new CollabCheckoutService())->create((int)$collab['id'], (int)H::user()['id'], $_POST, isset($_POST['storefront_designer_id']) ? (int)$_POST['storefront_designer_id'] : null);
                header('Location: '.$result['url'], true, 303);
                exit;
            } catch (\DomainException|\InvalidArgumentException $error) {
                H::flash('error', $error->getMessage());
                H::redirect('/collab/'.$collab['slug'].'/checkout');
            } catch (\Throwable $error) {
                H::flash('error', 'Checkout could not be started. Please try again.');
                H::redirect('/collab/'.$collab['slug'].'/checkout');
            }
        }
        H::view('collabs/checkout', [
            'collab'=>$collab,
            'balances'=>(new CreditService())->balances((int)H::user()['id']),
            'storefront'=>isset($_GET['store']) ? (int)$_GET['store'] : null,
        ]);
    }

    public function download($item): void
    {
        H::requireLogin();
        $row = DB::row('select c.*,oi.id order_item_id,oi.order_id,oi.total_price,o.payment_status,coalesce((select sum(a.merchandise_refund_cents) from marketplace_refund_allocations a where a.order_item_id=oi.id),0) refunded_cents from order_items oi join collab_events c on c.id=oi.collab_id join orders o on o.id=oi.order_id where oi.id=? and o.user_id=? limit 1', [(int)$item,H::user()['id']]);
        if (!$row) H::abort(403);
        if (!CollabService::itemDownloadable((string)$row['payment_status'], \App\Services\CreditService::parseCents((string)$row['total_price'], false), (int)$row['refunded_cents'])) H::abort(403);
        $service = new CollabService();
        $real = $service->protectedRealPath(
            (string)$row['final_zip_path']
        );
        $temporaryArchive = null;

        if (
            !$real
            || !hash_equals(
                (string)$row['final_zip_sha256'],
                hash_file('sha256', $real)
            )
        ) {
            if (($row['status'] ?? '') !== 'ended') {
                H::abort(404);
            }

            try {
                $temporaryArchive =
                    $service->buildTemporaryDownloadArchive(
                        (int)$row['id']
                    );
                $real = $temporaryArchive;
            } catch (\Throwable $error) {
                H::abort(404);
            }
        }

        if ($temporaryArchive !== null) {
            register_shutdown_function(
                static function () use ($temporaryArchive): void {
                    if (is_file($temporaryArchive)) {
                        @unlink($temporaryArchive);
                    }
                }
            );
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.H::slug($row['title']).'.zip"');
        header('Content-Length: '.filesize($real));
        $delivered = readfile($real);
        if ($delivered === false) {
            try { DB::exec('insert into downloads(user_id,order_id,order_item_id,product_id,product_file_id,collab_id,status,message,ip_address,user_agent) values(?,?,?,null,null,?,"denied","Protected collab ZIP delivery failed.",?,?)', [H::user()['id'],$row['order_id'],$row['order_item_id'],$row['id'],$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']); } catch (\Throwable) {}
            exit;
        }
        DB::exec('insert into downloads(user_id,order_id,order_item_id,product_id,product_file_id,collab_id,status,ip_address,user_agent) values(?,?,?,null,null,?,"served",?,?)', [H::user()['id'],$row['order_id'],$row['order_item_id'],$row['id'],$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']);
        DB::exec('update order_items set download_count=download_count+1 where id=?', [$row['order_item_id']]);
        exit;
    }
}
