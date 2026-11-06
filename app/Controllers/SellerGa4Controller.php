<?php
namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\SellerGa4Service;

final class SellerGa4Controller
{
    public function save(): void
    {
        H::requireSeller();
        H::verifyCsrf();

        $user=H::user();

        $seller=$user ? DB::row(
            'SELECT id FROM designers
             WHERE user_id=? AND status="approved"
             LIMIT 1',
            [(int)$user['id']]
        ) : null;

        if (!$seller) {
            H::abort(403);
            return;
        }

        $id=strtoupper(trim(
            (string)($_POST['ga4_measurement_id']??'')
        ));

        if ($id!=='' && !SellerGa4Service::valid($id)) {
            H::flash(
                'error',
                'Enter a valid GA4 Measurement ID, such as G-ABC1234567.'
            );
            H::redirect('/seller/store#google-analytics');
            return;
        }

        if ($id==='') {
            DB::exec(
                'DELETE FROM seller_ga4_settings
                 WHERE designer_id=?',
                [(int)$seller['id']]
            );

            H::flash(
                'success',
                'Google Analytics tracking disconnected.'
            );
        } else {
            DB::exec(
                'INSERT INTO seller_ga4_settings
                   (designer_id,measurement_id)
                 VALUES (?,?)
                 ON DUPLICATE KEY UPDATE
                   measurement_id=VALUES(measurement_id)',
                [(int)$seller['id'],$id]
            );

            H::flash(
                'success',
                'Google Analytics 4 Measurement ID saved.'
            );
        }

        H::redirect('/seller/store#google-analytics');
    }
}
