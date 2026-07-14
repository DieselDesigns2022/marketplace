<?php
namespace App\Repositories;

use App\Core\Database as DB;
use App\Services\IpRiskScanner;
use PDOException;
use Throwable;

class IpRiskRepository
{
    public const CATEGORIES = [
        'brand', 'company', 'product_line', 'celebrity', 'public_figure', 'character',
        'sports_team', 'sports_league', 'movie', 'tv_show', 'video_game', 'music_artist',
        'song_title', 'song_lyric', 'book', 'franchise', 'slogan', 'other_protected_term',
    ];

    public const REVIEW_STATUSES = ['clear', 'pending_review', 'approved', 'rejected', 'archived', 'published_flagged'];

    public function enabledTermsWithAliases(): array
    {
        $terms = DB::rows('select * from ip_risk_terms where is_enabled=1 order by term');
        if (!$terms) {
            return [];
        }
        $ids = array_column($terms, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $aliasesByTerm = [];
        foreach (DB::rows('select * from ip_risk_term_aliases where is_enabled=1 and ip_risk_term_id in (' . $placeholders . ') order by alias', $ids) as $alias) {
            $aliasesByTerm[$alias['ip_risk_term_id']][] = $alias;
        }
        foreach ($terms as &$term) {
            $term['aliases'] = $aliasesByTerm[$term['id']] ?? [];
        }
        return $terms;
    }

    public function terms(): array
    {
        return DB::rows('select t.*,(select count(*) from ip_risk_term_aliases a where a.ip_risk_term_id=t.id) alias_count from ip_risk_terms t order by t.is_enabled desc,t.category,t.term');
    }

    public function term(int $id): ?array
    {
        return DB::row('select * from ip_risk_terms where id=?', [$id]);
    }

    public function aliases(int $termId): array
    {
        return DB::rows('select * from ip_risk_term_aliases where ip_risk_term_id=? order by alias', [$termId]);
    }

    public function saveTerm(array $values, int $adminId, ?int $id = null): array
    {
        $prepared = $this->prepareTermValues($values, $id);
        if ($prepared['errors']) {
            return $prepared['errors'];
        }

        try {
            DB::begin();
            if ($id) {
                if (!$this->term($id)) {
                    DB::rollBack();
                    return ['IP risk term was not found.'];
                }
                DB::exec(
                    'update ip_risk_terms set term=?,normalized_term=?,category=?,internal_note=?,is_enabled=?,updated_by_admin_id=?,updated_at=now() where id=?',
                    [$prepared['term'], $prepared['normalized'], $prepared['category'], $prepared['internal_note'], $prepared['enabled'], $adminId, $id]
                );
            } else {
                DB::exec(
                    'insert into ip_risk_terms (term,normalized_term,category,internal_note,is_enabled,created_by_admin_id,updated_by_admin_id) values (?,?,?,?,?,?,?)',
                    [$prepared['term'], $prepared['normalized'], $prepared['category'], $prepared['internal_note'], $prepared['enabled'], $adminId, $adminId]
                );
                $id = (int)DB::id();
            }

            DB::exec('update ip_risk_term_aliases set is_enabled=0,updated_at=now() where ip_risk_term_id=?', [$id]);
            foreach ($prepared['aliases'] as $normalizedAlias => $alias) {
                DB::exec(
                    'insert into ip_risk_term_aliases (ip_risk_term_id,alias,normalized_alias,is_enabled) values (?,?,?,1) on duplicate key update alias=values(alias),is_enabled=1,updated_at=now()',
                    [$id, $alias, $normalizedAlias]
                );
            }
            DB::commit();
            return [];
        } catch (PDOException $e) {
            $this->rollBackIfActive();
            if ($this->isDuplicateError($e)) {
                return ['A canonical term or alias with the same normalized text already exists.'];
            }
            throw $e;
        } catch (Throwable $e) {
            $this->rollBackIfActive();
            throw $e;
        }
    }

    public function setTermEnabled(int $id, bool $enabled, int $adminId): bool
    {
        if (!$this->term($id)) {
            return false;
        }
        DB::exec('update ip_risk_terms set is_enabled=?,updated_by_admin_id=?,updated_at=now() where id=?', [$enabled ? 1 : 0, $adminId, $id]);
        return true;
    }

    public function saveScan(int $productId, int $sellerId, string $contentFingerprint, string $matchFingerprint, array $matches): int
    {
        $ownsTransaction = !DB::pdo()->inTransaction();
        try {
            if ($ownsTransaction) {
                DB::begin();
            }
            DB::exec('update product_ip_risk_detections set is_active=0,updated_at=now() where product_id=?', [$productId]);
            DB::exec(
                'insert into product_ip_risk_scans (product_id,seller_id,content_fingerprint,match_fingerprint,active_match_count,scanned_at) values (?,?,?,?,?,now())',
                [$productId, $sellerId, $contentFingerprint, $matchFingerprint, count($matches)]
            );
            $scanId = (int)DB::id();
            foreach ($matches as $match) {
                $firstDetectedAt = $this->firstDetectedAt($productId, (int)$match['risk_term_id'], (string)$match['matched_value_key'], (string)$match['source_field']);
                DB::exec(
                    'insert into product_ip_risk_detections (product_id,scan_id,ip_risk_term_id,matched_term,matched_alias,matched_value_key,category,source_field,is_active,first_detected_at,last_detected_at) values (?,?,?,?,?,?,?,?,1,coalesce(?,now()),now())',
                    [$productId, $scanId, $match['risk_term_id'], $match['matched_term'], $match['matched_alias'], $match['matched_value_key'], $match['category'], $match['source_field'], $firstDetectedAt]
                );
            }
            $previous = $this->state($productId);
            $newStatus = count($matches) ? ((($previous['review_status'] ?? '') === 'approved' && ($previous['latest_match_fingerprint'] ?? '') === $matchFingerprint) ? 'approved' : 'pending_review') : 'clear';
            DB::exec(
                'insert into product_ip_risk_states (product_id,latest_scan_id,review_status,latest_match_fingerprint,created_at,updated_at) values (?,?,?,?,now(),now()) on duplicate key update latest_scan_id=values(latest_scan_id),review_status=values(review_status),latest_match_fingerprint=values(latest_match_fingerprint),updated_at=now()',
                [$productId, $scanId, $newStatus, $matchFingerprint]
            );
            if ($ownsTransaction) {
                DB::commit();
            }
            return $scanId;
        } catch (Throwable $e) {
            if ($ownsTransaction) {
                $this->rollBackIfActive();
            }
            throw $e;
        }
    }


    public function productRequiresIpRiskReview(int $productId): bool
    {
        $row = DB::row(
            'select p.status product_status,s.review_status,s.latest_scan_id from products p left join product_ip_risk_states s on s.product_id=p.id where p.id=? and exists (select 1 from product_ip_risk_detections d where d.product_id=p.id and d.is_active=1 and (s.latest_scan_id is null or d.scan_id=s.latest_scan_id) limit 1) limit 1',
            [$productId]
        );
        if (!$row) {
            return false;
        }
        if (!in_array(($row['product_status'] ?? ''), ['pending_review'], true)) {
            return false;
        }
        return !in_array(($row['review_status'] ?? ''), ['approved', 'published_flagged'], true);
    }

    public function activeMatchCount(int $productId): int
    {
        $row = DB::row(
            'select count(*) c from product_ip_risk_detections where product_id=? and is_active=1',
            [$productId]
        );
        return (int)($row['c'] ?? 0);
    }

    public function cleanupProductRecords(int $productId): void
    {
        DB::exec('delete from product_ip_risk_review_history where product_id=?', [$productId]);
        DB::exec('delete from product_ip_rights_confirmations where product_id=?', [$productId]);
        DB::exec('delete from product_ip_risk_detections where product_id=?', [$productId]);
        DB::exec('delete from product_ip_risk_states where product_id=?', [$productId]);
        DB::exec('delete from product_ip_risk_scans where product_id=?', [$productId]);
    }

    public function state(int $productId): ?array
    {
        return DB::row('select * from product_ip_risk_states where product_id=?', [$productId]);
    }

    public function activeDetections(int $productId): array
    {
        return DB::rows('select * from product_ip_risk_detections where product_id=? and is_active=1 order by category,matched_term,source_field', [$productId]);
    }

    public function detections(int $productId): array
    {
        return DB::rows('select d.*,s.scanned_at from product_ip_risk_detections d join product_ip_risk_scans s on s.id=d.scan_id where d.product_id=? order by d.created_at desc,d.id desc', [$productId]);
    }

    public function confirmations(int $productId): array
    {
        return DB::rows('select c.*,u.email seller_email from product_ip_rights_confirmations c join users u on u.id=c.seller_id where c.product_id=? order by c.confirmed_at desc', [$productId]);
    }

    public function hasConfirmation(int $productId, int $scanId, int $sellerUserId): bool
    {
        return (bool)DB::row('select id from product_ip_rights_confirmations where product_id=? and scan_id=? and seller_id=?', [$productId, $scanId, $sellerUserId]);
    }

    public function recordConfirmation(int $productId, int $scanId, int $sellerUserId, string $text): void
    {
        DB::exec('insert ignore into product_ip_rights_confirmations (product_id,scan_id,seller_id,confirmation_text,confirmed_at) values (?,?,?,?,now())', [$productId, $scanId, $sellerUserId, $text]);
    }

    public function reviewHistory(int $productId): array
    {
        return DB::rows('select h.*,u.email admin_email from product_ip_risk_review_history h join users u on u.id=h.admin_id where h.product_id=? order by h.created_at desc', [$productId]);
    }


    public function applyAdminReviewTransition(int $productId, string $requestedAction, ?string $note, int $adminId): array
    {
        $allowedActions = ['pending', 'approve', 'published_flagged', 'reject', 'archive'];
        if (!in_array($requestedAction, $allowedActions, true)) {
            throw new \InvalidArgumentException('Invalid IP risk review action.');
        }

        try {
            DB::begin();
            $product = DB::row('select id,status,rejection_reason from products where id=? for update', [$productId]);
            if (!$product) {
                throw new \InvalidArgumentException('Product not found.');
            }

            $state = DB::row('select * from product_ip_risk_states where product_id=? for update', [$productId]);
            if (!$state || empty($state['latest_scan_id'])) {
                throw new \InvalidArgumentException('A current IP risk scan is required before review action can be applied.');
            }

            $latestScanId = (int)$state['latest_scan_id'];
            $activeRow = DB::row(
                'select count(*) c from product_ip_risk_detections where product_id=? and scan_id=? and is_active=1',
                [$productId, $latestScanId]
            );
            $activeCount = (int)($activeRow['c'] ?? 0);
            if ($activeCount <= 0) {
                throw new \InvalidArgumentException('IP review actions require a current scan with active matches.');
            }

            $previousProductStatus = (string)$product['status'];
            $previousIpStatus = (string)($state['review_status'] ?? 'clear');
            $newProductStatus = $previousProductStatus;
            $newIpStatus = $this->ipStatusForAction($requestedAction);
            $productStatusUpdate = null;
            $rejectionReason = null;

            $this->validateProductStatusForIpAction($requestedAction, $previousProductStatus);

            if ($requestedAction === 'approve') {
                // IP approval preserves normal product status.
            } elseif ($requestedAction === 'published_flagged') {
                if (in_array($previousProductStatus, ['approved', 'published'], true)) {
                    // Preserve current published status.
                } elseif ($previousProductStatus === 'pending_review') {
                    $productStatusUpdate = 'approved';
                    $newProductStatus = 'approved';
                } else {
                    throw new \InvalidArgumentException('Only published products or pending products being approved can be left published while flagged.');
                }
            } elseif ($requestedAction === 'reject') {
                if (trim((string)$note) === '') {
                    throw new \InvalidArgumentException('Rejection Reason is required.');
                }
                $productStatusUpdate = 'rejected';
                $rejectionReason = trim((string)$note);
                $newProductStatus = 'rejected';
            } elseif ($requestedAction === 'archive') {
                $productStatusUpdate = 'archived';
                $newProductStatus = 'archived';
            }

            if ($productStatusUpdate !== null) {
                DB::exec(
                    'update products set status=?,rejection_reason=?,updated_at=now() where id=?',
                    [$productStatusUpdate, $productStatusUpdate === 'rejected' ? $rejectionReason : null, $productId]
                );
            }

            DB::exec(
                'insert into product_ip_risk_states (product_id,latest_scan_id,review_status,admin_note,reviewed_by_admin_id,reviewed_at,created_at,updated_at) values (?,?,?,?,?,now(),now(),now()) on duplicate key update latest_scan_id=values(latest_scan_id),review_status=values(review_status),admin_note=values(admin_note),reviewed_by_admin_id=values(reviewed_by_admin_id),reviewed_at=now(),updated_at=now()',
                [$productId, $latestScanId, $newIpStatus, $note ?: null, $adminId]
            );
            DB::exec(
                'insert into product_ip_risk_review_history (product_id,scan_id,previous_review_status,new_review_status,previous_product_status,new_product_status,admin_id,admin_note) values (?,?,?,?,?,?,?,?)',
                [$productId, $latestScanId, $previousIpStatus, $newIpStatus, $previousProductStatus, $newProductStatus, $adminId, $note ?: null]
            );
            $logAction = 'ip_risk_' . $newIpStatus;
            DB::exec(
                'insert into admin_logs (admin_user_id,action,entity_type,entity_id,metadata) values (?,?,?,?,?)',
                [$adminId, $logAction, 'product', $productId, json_encode(['requested_action' => $requestedAction, 'previous_ip_status' => $previousIpStatus, 'new_ip_status' => $newIpStatus, 'previous_product_status' => $previousProductStatus, 'new_product_status' => $newProductStatus])]
            );
            DB::commit();

            return [
                'previous_product_status' => $previousProductStatus,
                'new_product_status' => $newProductStatus,
                'previous_ip_status' => $previousIpStatus,
                'new_ip_status' => $newIpStatus,
                'message' => 'IP risk review updated.',
            ];
        } catch (Throwable $e) {
            $this->rollBackIfActive();
            throw $e;
        }
    }

    private function validateProductStatusForIpAction(string $action, string $productStatus): void
    {
        $allowed = [
            'pending' => ['draft', 'pending_review', 'approved', 'published'],
            'approve' => ['draft', 'pending_review', 'approved', 'published'],
            'published_flagged' => ['pending_review', 'approved', 'published'],
            'reject' => ['draft', 'pending_review', 'approved', 'published'],
            'archive' => ['draft', 'pending_review', 'approved', 'published', 'rejected', 'disabled'],
        ];
        if (!in_array($productStatus, $allowed[$action] ?? [], true)) {
            throw new \InvalidArgumentException('This product status cannot use the requested IP review action. Use the normal product recovery workflow first if needed.');
        }
    }

    private function ipStatusForAction(string $requestedAction): string
    {
        return [
            'pending' => 'pending_review',
            'approve' => 'approved',
            'published_flagged' => 'published_flagged',
            'reject' => 'rejected',
            'archive' => 'archived',
        ][$requestedAction] ?? throw new \InvalidArgumentException('Invalid IP risk review action.');
    }

    private function prepareTermValues(array $values, ?int $termId): array
    {
        $term = trim($values['term'] ?? '');
        $normalized = IpRiskScanner::normalize($term);
        $category = $values['category'] ?? '';
        $errors = [];

        if ($term === '') {
            $errors[] = 'Canonical term is required.';
        }
        if (($message = IpRiskScanner::validateTermText($term)) !== null) {
            $errors[] = $message;
        }
        if (!in_array($category, self::CATEGORIES, true)) {
            $errors[] = 'Category is invalid.';
        }
        $errors = array_merge($errors, $this->collisionErrors($normalized, null, $termId));

        $aliases = [];
        foreach (preg_split('/[\r\n,]+/', (string)($values['aliases'] ?? '')) as $alias) {
            $alias = trim($alias);
            if ($alias === '') {
                continue;
            }
            $normalizedAlias = IpRiskScanner::normalize($alias);
            if ($normalizedAlias === $normalized) {
                $errors[] = 'Alias cannot match the canonical term.';
                continue;
            }
            if (($message = IpRiskScanner::validateTermText($alias)) !== null) {
                $errors[] = 'Alias "' . $alias . '": ' . $message;
                continue;
            }
            if (!isset($aliases[$normalizedAlias])) {
                $errors = array_merge($errors, $this->collisionErrors($normalizedAlias, 'alias', $termId));
            }
            $aliases[$normalizedAlias] = $alias;
        }

        return [
            'term' => $term,
            'normalized' => $normalized,
            'category' => $category,
            'internal_note' => trim($values['internal_note'] ?? '') ?: null,
            'enabled' => !empty($values['is_enabled']) ? 1 : 0,
            'aliases' => $aliases,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    private function collisionErrors(string $normalized, ?string $checkingAlias, ?int $termId): array
    {
        if ($normalized === '') {
            return [];
        }
        $errors = [];
        $params = [$normalized];
        $sql = 'select id from ip_risk_terms where normalized_term=?';
        if ($termId) {
            $sql .= ' and id<>?';
            $params[] = $termId;
        }
        if (DB::row($sql . ' limit 1', $params)) {
            $errors[] = $checkingAlias ? 'Alias duplicates an existing canonical term.' : 'Canonical term duplicates an existing canonical term.';
        }

        $params = [$normalized];
        $sql = 'select ip_risk_term_id from ip_risk_term_aliases where normalized_alias=?';
        if ($termId) {
            $sql .= ' and ip_risk_term_id<>?';
            $params[] = $termId;
        }
        if (DB::row($sql . ' limit 1', $params)) {
            $errors[] = $checkingAlias ? 'Alias duplicates an alias on another term.' : 'Canonical term duplicates an existing alias.';
        }
        return $errors;
    }

    private function firstDetectedAt(int $productId, int $termId, string $matchKey, string $sourceField): ?string
    {
        $row = DB::row(
            'select min(first_detected_at) first_seen from product_ip_risk_detections where product_id=? and ip_risk_term_id=? and matched_value_key=? and source_field=?',
            [$productId, $termId, $matchKey, $sourceField]
        );
        return $row['first_seen'] ?? null;
    }

    private function isDuplicateError(PDOException $e): bool
    {
        $info = $e->errorInfo;
        return ($info[0] ?? '') === '23000' && (int)($info[1] ?? 0) === 1062;
    }

    private function rollBackIfActive(): void
    {
        if (DB::pdo()->inTransaction()) {
            DB::rollBack();
        }
    }
}
