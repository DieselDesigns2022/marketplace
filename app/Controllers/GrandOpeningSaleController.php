<?php
namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\GrandOpeningSaleService as Sale;
use DomainException;
use Throwable;

final class GrandOpeningSaleController
{
    public function seller(): void
    {
        H::requireSeller();
        $d = DB::row(
            'select * from designers where user_id=? and status="approved"',
            [H::user()['id']]
        );
        if (!$d) H::abort(403);
        $service = new Sale();
        $error = '';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            H::verifyCsrf();
            try {
                $service->saveSeller((int)$d['id'],(int)H::user()['id'],$_POST);
                H::flash('success','Grand Opening Sale settings saved.');
                H::redirect('/seller/grand-opening-sale');
            } catch (Throwable $e) {
                $error = $e instanceof DomainException ? $e->getMessage() : 'Settings could not be saved. Please try again.';
            }
        }
        H::view('seller/grand_opening_sale',[
            'event'=>$service->event(),'sale'=>$service->seller((int)$d['id']),'error'=>$error,
            'saleProducts'=>DB::rows(
                'select id,title,status from products where designer_id=?
                 and status not in ("deleted","archived","disabled") order by title,id',[$d['id']]
            ),
        ]);
    }

    public function admin(): void
    {
        H::requireAdminPermission('homepage.view');
        $service = new Sale();
        $dateError = '';
        $savedNotice = !empty($_SESSION['grand_opening_dates_saved']);
        unset($_SESSION['grand_opening_dates_saved']);
        $event = $service->event();
        $formDates = [
            'starts_at'=>Sale::localInput($event['starts_at']),
            'ends_at'=>Sale::localInput($event['ends_at']),
        ];
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            H::requireAdminPermission('homepage.manage');
            H::verifyCsrf();
            $savedNotice = false;
            foreach (['starts_at','ends_at'] as $field) {
                $formDates[$field] = is_string($_POST[$field]??null)
                    ? trim($_POST[$field])
                    : '';
            }
            try {
                $service->saveEvent($formDates,(int)H::user()['id']);
                $_SESSION['grand_opening_dates_saved'] = true;
                H::redirect('/admin/grand-opening#saved-event');
            } catch (Throwable $e) {
                if ($e instanceof DomainException) {
                    $dateError = $e->getMessage().' Your dates were not saved.';
                } else {
                    error_log('Grand Opening date save failed: '.get_class($e).': '.$e->getMessage());
                    $dateError = 'The dates could not be saved. Your entries are still below. Please try again.';
                }
            }
            $event = $service->event();
        }
        H::view('admin/grand_opening',[
            'event'=>$event,
            'dateError'=>$dateError,
            'savedNotice'=>$savedNotice,
            'formDates'=>$formDates,
            'saleSellers'=>DB::rows(
                'select d.id,d.display_name,d.store_slug,d.status,
                 s.is_participating,s.discount_basis_points,s.product_scope,
                 (select count(*) from grand_opening_sale_products p
                  where p.event_id=1 and p.designer_id=d.id) selected_count
                 from designers d join grand_opening_sales s
                 on s.designer_id=d.id and s.event_id=1
                 where s.is_participating=1
                 order by d.display_name,d.id'
            ),
        ]);
    }
}
