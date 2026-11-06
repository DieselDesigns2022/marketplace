<?php

namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\ProductSaleService;
use App\Services\ProductScheduleService;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Throwable;

final class ProductSaleController
{
    public function manage($id): void
    {
        H::requireSeller();

        $designer = DB::row(
            'SELECT * FROM designers WHERE user_id=?',
            [(int)H::user()['id']]
        );

        if (!$designer || $designer['status'] !== 'approved') {
            H::abort(403);
        }

        $product = DB::row(
            'SELECT id,title,price,status FROM products
             WHERE id=? AND designer_id=?',
            [(int)$id, (int)$designer['id']]
        );

        if (!$product) {
            H::abort(404);
        }

        $timezone = (string)($designer['timezone'] ?: 'UTC');

        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            $timezone = 'UTC';
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            H::verifyCsrf();

            $action = (string)($_POST['sale_action'] ?? 'save');

            try {
                if ($action === 'disable') {
                    DB::exec(
                        'UPDATE product_sales SET is_active=0
                         WHERE product_id=? AND designer_id=?',
                        [(int)$id, (int)$designer['id']]
                    );
                    H::flash('success', 'Product sale turned off.');
                    H::redirect('/seller/product/'.$id.'/sale');
                    return;
                }

                if ($action !== 'save') {
                    throw new DomainException('Invalid sale action.');
                }

                if ($product['price'] === null || (float)$product['price'] <= 0) {
                    throw new DomainException(
                        'Set a paid base price before creating a sale.'
                    );
                }

                $type = trim((string)($_POST['discount_type'] ?? ''));
                $value = trim((string)($_POST['discount_value'] ?? ''));

                if (!in_array($type, ['percent','fixed'], true)) {
                    throw new DomainException('Choose a discount type.');
                }

                if (!preg_match(
                    '/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/D',
                    $value
                )) {
                    throw new DomainException('Enter a valid discount amount.');
                }

                $amountCents = \App\Services\CreditService::parseCents(
                    $value,
                    false
                );

                if ($amountCents < 1) {
                    throw new DomainException('Discount must be positive.');
                }

                if ($type === 'percent' && $amountCents > 10000) {
                    throw new DomainException(
                        'Percentage cannot exceed 100%.'
                    );
                }

                if ($type === 'fixed' &&
                    $amountCents > \App\Services\CreditService::parseCents(
                        (string)$product['price'],
                        false
                    )) {
                    throw new DomainException(
                        'Fixed discount cannot exceed the base price.'
                    );
                }

                ProductSaleService::discountedBaseCents(
                    \App\Services\CreditService::parseCents(
                        (string)$product['price'],
                        false
                    ),
                    $type,
                    $value
                );

                $startMode = (string)($_POST['start_mode'] ?? '');
                $endLocal = (string)($_POST['ends_local'] ?? '');

                $endsAt = ProductScheduleService::toUtc(
                    $endLocal,
                    $timezone
                );

                if ($startMode === 'now') {
                    $startsAt = gmdate('Y-m-d H:i:s');
                } elseif ($startMode === 'scheduled') {
                    $startsAt = ProductScheduleService::toUtc(
                        (string)($_POST['starts_local'] ?? ''),
                        $timezone
                    );
                } else {
                    throw new DomainException('Choose a sale start option.');
                }

                if ($endsAt <= $startsAt) {
                    throw new DomainException(
                        'Sale end must be after its start.'
                    );
                }

                DB::exec(
                    'INSERT INTO product_sales
                     (product_id,designer_id,discount_type,
                      discount_value,starts_at,ends_at,is_active)
                     VALUES (?,?,?,?,?,?,1)
                     ON DUPLICATE KEY UPDATE
                       designer_id=VALUES(designer_id),
                       discount_type=VALUES(discount_type),
                       discount_value=VALUES(discount_value),
                       starts_at=VALUES(starts_at),
                       ends_at=VALUES(ends_at),
                       is_active=1',
                    [
                        (int)$product['id'],
                        (int)$designer['id'],
                        $type,
                        $value,
                        $startsAt,
                        $endsAt
                    ]
                );

                H::flash(
                    'success',
                    'Product sale saved. It will apply during its scheduled dates.'
                );

                H::redirect('/seller/product/'.$id.'/sale');
                return;
            } catch (DomainException $e) {
                H::flash('error', $e->getMessage());
                H::redirect('/seller/product/'.$id.'/sale');
                return;
            } catch (Throwable $e) {
                error_log('Product sale save failed: '.$e->getMessage());
                H::flash('error', 'Product sale could not be saved.');
                H::redirect('/seller/product/'.$id.'/sale');
                return;
            }
        }

        $sale = DB::row(
            'SELECT * FROM product_sales
             WHERE product_id=? AND designer_id=?',
            [(int)$id, (int)$designer['id']]
        );

        H::view('seller/product_sale', [
            'product' => $product,
            'sale' => $sale,
            'timezone' => $timezone
        ]);
    }
}
