<?php
namespace App\Services;

use App\Core\Database as DB;

final class SellerGa4Service
{
    public static function valid(string $id): bool
    {
        return (bool)preg_match(
            '/^G-[A-Z0-9]{6,14}$/D',
            $id
        );
    }

    public static function forDesigner(int $designerId): string
    {
        if ($designerId < 1) return '';

        $row=DB::row(
            'SELECT g.measurement_id
             FROM seller_ga4_settings g
             JOIN designers d ON d.id=g.designer_id
             WHERE g.designer_id=?
               AND d.status="approved"
             LIMIT 1',
            [$designerId]
        );

        $id=(string)($row['measurement_id']??'');

        return self::valid($id) ? $id : '';
    }
}
