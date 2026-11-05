<?php
namespace App\Services;

use App\Core\Database as DB;
use App\Repositories\IpRiskRepository;
use DomainException;
use RuntimeException;
use Throwable;

final class IpAuditWarningService
{
    public function rescanAll(): array
    {
        $scanner = new IpRiskScanner();
        $terms = (new IpRiskRepository())->enabledTermsWithAliases();

        if (!$terms) {
            throw new RuntimeException(
                'No enabled IP-risk terms are available. Scan cancelled.'
            );
        }

        $counts = [
            'scanned' => 0,
            'matched' => 0,
            'pending' => 0,
            'public_pending' => 0,
        ];

        $lastId = 0;

        while (true) {
            $products = DB::rows(
                'select p.*
                 from products p
                 where p.id>?
                   and p.status in ("approved","published")
                 order by p.id limit 100',
                [$lastId]
            );

            if (!$products) {
                break;
            }

            $ids = array_map(
                static fn($p) => (int)$p['id'],
                $products
            );

            $keys = implode(',', array_fill(0, count($ids), '?'));

            $tags = [];
            foreach (DB::rows(
                "select pt.product_id,t.name
                 from product_tags pt
                 join tags t on t.id=pt.tag_id
                 where pt.product_id in ($keys)",
                $ids
            ) as $row) {
                $tags[(int)$row['product_id']][] = $row['name'];
            }

            $files = [];
            foreach (DB::rows(
                "select product_id,original_name
                 from product_files
                 where product_id in ($keys)",
                $ids
            ) as $row) {
                $files[(int)$row['product_id']][] =
                    $row['original_name'];
            }

            foreach ($products as $p) {
                $id = (int)$p['id'];
                $lastId = $id;
                $counts['scanned']++;

                $input = [
                    'title' => (string)($p['title'] ?? ''),
                    'description' => trim(
                        (string)($p['short_description'] ?? '') . ' ' .
                        (string)($p['description'] ?? '')
                    ),
                    'tags' => $tags[$id] ?? [],
                    'seo_title' => (string)($p['seo_title'] ?? ''),
                    'seo_description' =>
                        (string)($p['seo_description'] ?? ''),
                    'file_names' => $files[$id] ?? [],
                ];

                $matches = $scanner->scan($input, $terms);

                $contentHash = hash(
                    'sha256',
                    json_encode(
                        $input,
                        JSON_UNESCAPED_UNICODE |
                        JSON_INVALID_UTF8_SUBSTITUTE
                    )
                );

                $matchHash = hash(
                    'sha256',
                    json_encode(
                        $matches,
                        JSON_UNESCAPED_UNICODE |
                        JSON_INVALID_UTF8_SUBSTITUTE
                    )
                );

                if (!$matches) {
                    // Keep review and warning history. Do not
                    // remove a previously confirmed violation.
                    $previous = DB::row(
                        'select review_status from ip_audit_findings
                         where product_id=?',
                        [$id]
                    );

                    if ($previous &&
                        $previous['review_status'] === 'pending') {
                        DB::exec(
                            'delete from ip_audit_findings
                             where product_id=?
                             and review_status="pending"',
                            [$id]
                        );
                    }

                    continue;
                }

                $counts['matched']++;

                DB::exec(
                    'insert into ip_audit_findings
                    (product_id,content_fingerprint,
                     match_fingerprint,matches_json,
                     match_count,review_status,last_scanned_at)
                    values (?,?,?,?,?,"pending",now())
                    on duplicate key update
                        review_status=case
                            when match_fingerprint<>values(match_fingerprint)
                              or content_fingerprint<>values(content_fingerprint)
                            then case
                                when review_status="violation"
                                then "violation"
                                else "pending"
                            end
                            else review_status
                        end,
                        reviewed_by_admin_id=case
                            when review_status="pending" then null
                            else reviewed_by_admin_id
                        end,
                        content_fingerprint=values(content_fingerprint),
                        match_fingerprint=values(match_fingerprint),
                        matches_json=values(matches_json),
                        match_count=values(match_count),
                        last_scanned_at=now()',
                    [
                        $id,
                        $contentHash,
                        $matchHash,
                        json_encode(
                            $matches,
                            JSON_UNESCAPED_UNICODE |
                            JSON_INVALID_UTF8_SUBSTITUTE
                        ),
                        count($matches)
                    ]
                );

                $finding = DB::row(
                    'select review_status from ip_audit_findings
                     where product_id=?',
                    [$id]
                );

                if (($finding['review_status'] ?? '') === 'pending') {
                    $counts['pending']++;

                    if (in_array(
                        $p['status'],
                        ['approved','published','scheduled'],
                        true
                    )) {
                        $counts['public_pending']++;
                    }
                }
            }
        }

        return $counts;
    }


    /**
     * One confirmed incident may include several products from ONE seller.
     * Each product receives its own publication lock, but every row uses
     * the same warning number and only one notification is sent.
     */
    public function confirmGroup(
        array $productIds,
        int $adminId,
        string $reason
    ): string {
        $productIds = array_values(array_unique(
            array_filter(array_map('intval', $productIds),
                static fn($id) => $id > 0)
        ));
        sort($productIds, SORT_NUMERIC);
        $reason = trim($reason);

        if (count($productIds) < 2 || count($productIds) > 30) {
            throw new \DomainException(
                'Select 2 to 30 products from the same seller.'
            );
        }
        if ($reason === '' || mb_strlen($reason, 'UTF-8') > 2000) {
            throw new \DomainException(
                'A review reason between 1 and 2000 characters is required.'
            );
        }

        $first = DB::row(
            'select designer_id from products where id=?',
            [$productIds[0]]
        );
        if (!$first) {
            throw new \DomainException('Product not found.');
        }
        $designerId = (int)$first['designer_id'];

        DB::begin();
        try {
            // Serializes warning numbers for this seller.
            $designer = DB::row(
                'select id,user_id,display_name from designers
                 where id=? for update',
                [$designerId]
            );
            if (!$designer) {
                throw new \DomainException('Seller not found.');
            }

            $products = [];
            foreach ($productIds as $productId) {
                $p = DB::row(
                    'select id,designer_id,title,status
                     from products where id=? for update',
                    [$productId]
                );
                $finding = DB::row(
                    'select review_status,match_count,matches_json
                     from ip_audit_findings
                     where product_id=? for update',
                    [$productId]
                );
                if (!$p || (int)$p['designer_id'] !== $designerId) {
                    throw new \DomainException(
                        'All selected products must belong to one seller.'
                    );
                }
                if (!$finding ||
                    $finding['review_status'] !== 'pending' ||
                    (int)$finding['match_count'] <= 0 ||
                    !json_decode((string)$finding['matches_json'], true)) {
                    throw new \DomainException(
                        'Every product must have a pending IP finding.'
                    );
                }
                if (DB::row(
                    'select id from seller_ip_warnings
                     where product_id=? limit 1',
                    [$productId]
                )) {
                    throw new \DomainException(
                        'A selected product has already received a warning.'
                    );
                }
                if (!in_array(
                    $p['status'],
                    ['approved','published'],
                    true
                )) {
                    throw new \DomainException(
                        'Only public products may receive a new ' .
                        'grouped IP warning.'
                    );
                }
                $products[] = $p;
            }

            $next = DB::row(
                'select coalesce(max(warning_number),0)+1 number
                 from seller_ip_warnings where designer_id=?',
                [$designerId]
            );
            $number = (int)$next['number'];

            foreach ($products as $p) {
                $id = (int)$p['id'];

                DB::exec(
                    'insert into seller_ip_warnings
                     (designer_id,product_id,warning_number,
                      reason,issued_by_admin_id)
                     values (?,?,?,?,?)',
                    [$designerId,$id,$number,$reason,$adminId]
                );

                DB::exec(
                    'update ip_audit_findings
                     set review_status="violation",
                         reviewed_by_admin_id=?,
                         reviewed_at=now(),admin_note=?
                     where product_id=?',
                    [$adminId,$reason,$id]
                );

                DB::exec(
                    'update products
                     set status="draft",
                         scheduled_publish_at=null,
                         schedule_error=null,
                         updated_at=now()
                     where id=?',
                    [$id]
                );

                DB::exec(
                    'insert into admin_logs
                     (admin_user_id,action,entity_type,entity_id,metadata)
                     values (?,?,?,?,?)',
                    [
                        $adminId,'ip_audit_group_confirm','product',$id,
                        json_encode([
                            'warning_number'=>$number,
                            'group_product_ids'=>$productIds,
                            'reason'=>$reason
                        ])
                    ]
                );
            }

            $productLabels = array_map(
                static fn($p) => '#' . $p['id'] . ' ' . $p['title'],
                $products
            );

            $notice =
                'Official IP Violation Warning #' . $number .
                ': The following products have been removed from public ' .
                'view and returned to Draft: ' .
                implode('; ', $productLabels) .
                '. Reason: ' . $reason .
                '. Changing character names, using alternate spellings, ' .
                'or disguising protected content to avoid detection does ' .
                'not make it acceptable. Further confirmed violations ' .
                'may result in your store being suspended or permanently ' .
                'banned from Creative Moth.';

            \App\Services\NotificationService::create(
                (int)$designer['user_id'],
                'seller_ip_warning',
                'designer',
                'Official IP Violation Warning #' . $number,
                $notice,
                'seller-ip-warning:group:' . $designerId .
                    ':' . $number,
                '/seller'
            );

            DB::commit();

            return 'ONE Warning #' . $number .
                ' issued for ' . count($products) .
                ' products. All selected products are now Draft.';
        } catch (\Throwable $e) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }
            throw $e;
        }
    }

    public function review(
        int $productId,
        int $adminId,
        string $action,
        string $note
    ): string {
        if (!in_array(
            $action,
            ['dismiss','confirm','clear'],
            true
        )) {
            throw new DomainException('Invalid audit decision.');
        }

        if ($action === 'confirm' && $note === '') {
            throw new DomainException(
                'Enter a reason before issuing a formal warning.'
            );
        }

        if ($action === 'clear' && $note === '') {
            throw new DomainException(
                'Enter the reason for clearing a violation.'
            );
        }

        DB::begin();

        try {
            $p = DB::row(
                'select p.*,d.user_id seller_user_id
                 from products p
                 join designers d on d.id=p.designer_id
                 where p.id=? for update',
                [$productId]
            );

            if (!$p) {
                throw new DomainException('Product not found.');
            }

            if ($action !== 'clear' &&
                !in_array(
                    $p['status'],
                    ['approved','published'],
                    true
                )) {
                throw new DomainException(
                    'Only currently public products can receive ' .
                    'new audit decisions.'
                );
            }

            $finding = DB::row(
                'select * from ip_audit_findings
                 where product_id=? for update',
                [$productId]
            );

            if (!$finding) {
                throw new DomainException(
                    'No audit finding exists for this product.'
                );
            }

            if ($action === 'dismiss') {
                if ($finding['review_status'] !== 'pending') {
                    throw new DomainException(
                        'Only pending findings may be dismissed.'
                    );
                }

                DB::exec(
                    'update ip_audit_findings
                     set review_status="dismissed",
                         reviewed_by_admin_id=?,
                         reviewed_at=now(),admin_note=?
                     where product_id=?',
                    [$adminId,$note ?: null,$productId]
                );

                $result = 'Finding dismissed. Product unchanged.';

            } elseif ($action === 'confirm') {
                if ($finding['review_status'] !== 'pending') {
                    throw new DomainException(
                        'Only pending findings may be confirmed.'
                    );
                }

                $existing = DB::row(
                    'select id from seller_ip_warnings
                     where product_id=?',
                    [$productId]
                );

                if ($existing) {
                    throw new DomainException(
                        'This product already has a formal warning. ' .
                        'Review the existing warning instead.'
                    );
                }

                // Serialize warning numbers per seller.
                DB::row(
                    'select id from designers
                     where id=? for update',
                    [(int)$p['designer_id']]
                );

                $next = DB::row(
                    'select coalesce(max(warning_number),0)+1 next_number
                     from seller_ip_warnings
                     where designer_id=?',
                    [(int)$p['designer_id']]
                );

                $number = (int)$next['next_number'];

                DB::exec(
                    'insert into seller_ip_warnings
                     (designer_id,product_id,warning_number,
                      reason,issued_by_admin_id)
                     values (?,?,?,?,?)',
                    [
                        (int)$p['designer_id'],
                        $productId,
                        $number,
                        $note,
                        $adminId
                    ]
                );

                DB::exec(
                    'update ip_audit_findings
                     set review_status="violation",
                         reviewed_by_admin_id=?,
                         reviewed_at=now(),admin_note=?
                     where product_id=?',
                    [$adminId,$note,$productId]
                );

                DB::exec(
                    'update products
                     set status="draft",
                         scheduled_publish_at=null,
                         schedule_error=null,
                         updated_at=now()
                     where id=?',
                    [$productId]
                );

                $message =
                    'Official IP Violation Warning #' . $number .
                    ': Your product "' . $p['title'] .
                    '" has been returned to Draft after admin review. ' .
                    'Reason: ' . $note . ' ' .
                    'Uploading protected content under altered names, ' .
                    'alternate spellings, or disguised references to ' .
                    'avoid detection is not permitted. Further confirmed ' .
                    'violations may result in store suspension or ' .
                    'permanent removal from Creative Moth.';

                NotificationService::create(
                    (int)$p['seller_user_id'],
                    'seller_ip_warning',
                    'designer',
                    'Official IP Violation Warning #' . $number,
                    $message,
                    'seller-ip-warning:product:' . $productId,
                    '/seller/products'
                );

                $result = 'Warning #' . $number .
                    ' issued. Product returned to Draft.';

            } else {
                $warning = DB::row(
                    'select * from seller_ip_warnings
                     where product_id=? for update',
                    [$productId]
                );

                if (!$warning || $warning['resolved_at'] !== null) {
                    throw new DomainException(
                        'No active warning exists to clear.'
                    );
                }

                DB::exec(
                    'update seller_ip_warnings
                     set resolved_at=now(),
                         resolved_by_admin_id=?,
                         resolution_note=?
                     where id=?',
                    [$adminId,$note,(int)$warning['id']]
                );

                DB::exec(
                    'update ip_audit_findings
                     set review_status="dismissed",
                         reviewed_by_admin_id=?,
                         reviewed_at=now(),admin_note=?
                     where product_id=?',
                    [$adminId,$note,$productId]
                );

                $result =
                    'Violation restriction cleared by admin. ' .
                    'Warning remains in the permanent history. ' .
                    'Product remains Draft.';
            }

            DB::exec(
                'insert into admin_logs
                 (admin_user_id,action,entity_type,entity_id,metadata)
                 values (?,?,?,?,?)',
                [
                    $adminId,
                    'ip_audit_'.$action,
                    'product',
                    $productId,
                    json_encode([
                        'action'=>$action,
                        'reason'=>$note
                    ])
                ]
            );

            DB::commit();

            return $result;

        } catch (Throwable $e) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }
            throw $e;
        }
    }
}
