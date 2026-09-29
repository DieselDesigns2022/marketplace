<?php

namespace App\Services;

use App\Core\Database as DB;
use DomainException;
use RuntimeException;
use Throwable;

final class CollabFileUpdateService
{
    public function hostApprove(
        int $requestId,
        int $hostDesignerId
    ): array {
        $row = DB::row(
            'select
                r.*,
                c.host_designer_id,
                c.title collab_title,
                c.ip_risk_state,
                c.ip_content_fingerprint,
                c.snapshot_at,
                f.sha256 current_sha256,
                f.original_name current_name
             from collab_file_update_requests r
             join collab_events c
               on c.id=r.collab_id
             join collab_files f
               on f.id=r.collab_file_id
             where r.id=?
               and r.status="pending"',
            [$requestId]
        );

        if (!$row) {
            throw new DomainException(
                'That update request is no longer pending.'
            );
        }

        if (
            (int)$row['host_designer_id'] !==
            $hostDesignerId
        ) {
            throw new DomainException(
                'Only the collab host can approve this update.'
            );
        }

        if (
            !hash_equals(
                (string)$row['base_sha256'],
                (string)$row['current_sha256']
            )
        ) {
            throw new DomainException(
                'The original file changed after this request was submitted. The seller must submit a new update request.'
            );
        }

        $existingReview = DB::row(
            'select id
             from collab_file_update_requests
             where collab_id=?
               and status="ip_review"
               and id<>?
             limit 1',
            [
                (int)$row['collab_id'],
                $requestId
            ]
        );

        if ($existingReview) {
            throw new DomainException(
                'Another file update for this collab is already waiting for Admin IP review.'
            );
        }

        $saleCount = $this->saleCount(
            (int)$row['collab_id']
        );

        /*
         * Every proposed replacement is IP-checked BEFORE it replaces
         * the current live file. Sales do not change this rule.
         */
        $candidate = $this->candidateRisk(
            (int)$row['collab_id'],
            (int)$row['collab_file_id'],
            (string)$row['original_name']
        );

        $candidateState =
            CollabIpRiskWorkflow::stateAfterScan(
                (string)$row['ip_risk_state'],
                (string)($row['ip_content_fingerprint'] ?? ''),
                (string)$candidate['fingerprint'],
                !empty($candidate['matches'])
            );

        if (
            !in_array(
                $candidateState,
                ['clear','approved'],
                true
            )
        ) {
            $this->stageIpReview(
                $row,
                $hostDesignerId,
                $candidate
            );

            return [
                'state' => 'ip_review',
                'sales' => $saleCount,
            ];
        }

        $result = $this->applyNow(
            $requestId,
            $hostDesignerId,
            false
        );

        return [
            'state' => $result['state'],
            'sales' => $saleCount,
        ];
    }

    public function afterAdminReview(
        int $collabId,
        string $fingerprint,
        string $decision
    ): void {
        $request = DB::row(
            'select *
             from collab_file_update_requests
             where collab_id=?
               and status="ip_review"
               and candidate_fingerprint=?
             order by id
             limit 1',
            [
                $collabId,
                $fingerprint
            ]
        );

        if (!$request) {
            return;
        }

        if ($decision === 'approved') {
            $this->applyNow(
                (int)$request['id'],
                null,
                true
            );

            return;
        }

        if ($decision === 'rejected') {
            $this->rejectAfterAdminReview(
                (int)$request['id']
            );
        }
    }

    private function stageIpReview(
        array $request,
        int $hostDesignerId,
        array $candidate
    ): void {
        DB::begin();

        try {
            $locked = DB::row(
                'select *
                 from collab_file_update_requests
                 where id=?
                   and status="pending"
                 for update',
                [(int)$request['id']]
            );

            if (!$locked) {
                throw new DomainException(
                    'That update request is no longer pending.'
                );
            }

            $collab = DB::row(
                'select *
                 from collab_events
                 where id=?
                 for update',
                [(int)$request['collab_id']]
            );

            if (!$collab) {
                throw new DomainException(
                    'Collab not found.'
                );
            }

            DB::exec(
                'update collab_file_update_requests
                 set status="ip_review",
                     reviewed_by_designer_id=?,
                     reviewed_at=now(),
                     candidate_fingerprint=?,
                     previous_ip_state=?,
                     previous_ip_fingerprint=?
                 where id=?',
                [
                    $hostDesignerId,
                    $candidate['fingerprint'],
                    $collab['ip_risk_state'],
                    $collab['ip_content_fingerprint'],
                    (int)$request['id']
                ]
            );

            DB::exec(
                'update collab_ip_risk_detections
                 set is_active=0
                 where collab_id=?',
                [(int)$request['collab_id']]
            );

            foreach ($candidate['matches'] as $match) {
                DB::exec(
                    'insert into collab_ip_risk_detections(
                        collab_id,
                        risk_term_id,
                        matched_term,
                        matched_alias,
                        source_field,
                        content_fingerprint
                     ) values(?,?,?,?,?,?)',
                    [
                        (int)$request['collab_id'],
                        $match['risk_term_id'],
                        $match['matched_term'],
                        $match['matched_alias'],
                        $match['source_field'],
                        $candidate['fingerprint']
                    ]
                );
            }

            DB::exec(
                'update collab_events
                 set ip_risk_state="review_required",
                     ip_content_fingerprint=?
                 where id=?',
                [
                    $candidate['fingerprint'],
                    (int)$request['collab_id']
                ]
            );

            DB::commit();

        } catch (Throwable $error) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }

            throw $error;
        }

        try {
            NotificationService::admins(
                'collab_ip_risk',
                'Collab file update requires IP review',
                'A host-approved collab file replacement requires IP review before it can replace the current buyer file.',
                'collab:'.
                    (int)$request['collab_id'].
                    ':file-update-ip:'.
                    $candidate['fingerprint'],
                '/admin/collabs/'.
                    (int)$request['collab_id']
            );
        } catch (Throwable $error) {
            NotificationService::reportFailure(
                'collab_file_update_ip_notification',
                $error
            );
        }
    }

    private function applyNow(
        int $requestId,
        ?int $hostDesignerId,
        bool $adminApproved
    ): array {
        $oldStorage = null;
        $collabId = 0;
        $hadSnapshot = false;
        $saleCount = 0;

        DB::begin();

        try {
            $request = DB::row(
                'select *
                 from collab_file_update_requests
                 where id=?
                   and status in ("pending","ip_review")
                 for update',
                [$requestId]
            );

            if (!$request) {
                throw new DomainException(
                    'That update request is no longer available.'
                );
            }

            $collab = DB::row(
                'select *
                 from collab_events
                 where id=?
                 for update',
                [(int)$request['collab_id']]
            );

            if (!$collab) {
                throw new DomainException(
                    'Collab not found.'
                );
            }

            if (
                !$adminApproved &&
                (
                    $hostDesignerId === null ||
                    (int)$collab['host_designer_id'] !==
                    $hostDesignerId
                )
            ) {
                throw new DomainException(
                    'Only the collab host can approve this update.'
                );
            }

            if ($adminApproved) {
                if (
                    $request['status'] !== 'ip_review' ||
                    $collab['ip_risk_state'] !== 'approved' ||
                    empty($request['candidate_fingerprint']) ||
                    !hash_equals(
                        (string)$request['candidate_fingerprint'],
                        (string)$collab['ip_content_fingerprint']
                    )
                ) {
                    throw new DomainException(
                        'The approved IP review no longer matches this proposed replacement.'
                    );
                }
            }

            $file = DB::row(
                'select *
                 from collab_files
                 where id=?
                   and collab_id=?
                   and designer_id=?
                 for update',
                [
                    (int)$request['collab_file_id'],
                    (int)$request['collab_id'],
                    (int)$request['designer_id']
                ]
            );

            if (!$file) {
                throw new DomainException(
                    'The original collab file no longer exists.'
                );
            }

            if (
                !hash_equals(
                    (string)$request['base_sha256'],
                    (string)$file['sha256']
                )
            ) {
                throw new DomainException(
                    'The original file changed after this request was submitted.'
                );
            }

            $service = new CollabService();

            $candidateReal = $service->protectedRealPath(
                (string)$request['storage_path']
            );

            if (
                !$candidateReal ||
                !hash_equals(
                    (string)$request['sha256'],
                    hash_file('sha256',$candidateReal)
                )
            ) {
                throw new RuntimeException(
                    'The proposed replacement failed its protected-file integrity check.'
                );
            }

            $oldStorage =
                (string)$file['storage_path'];

            $collabId =
                (int)$request['collab_id'];

            $hadSnapshot =
                !empty($collab['snapshot_at']);

            $saleCount =
                $this->saleCount($collabId);

            DB::exec(
                'update collab_files
                 set original_name=?,
                     stored_name=?,
                     storage_path=?,
                     mime_type=?,
                     byte_size=?,
                     sha256=?
                 where id=?',
                [
                    $request['original_name'],
                    $request['stored_name'],
                    $request['storage_path'],
                    $request['mime_type'],
                    $request['byte_size'],
                    $request['sha256'],
                    (int)$file['id']
                ]
            );

            DB::exec(
                'update collab_file_update_requests
                 set status="applied",
                     reviewed_by_designer_id=
                        coalesce(
                            reviewed_by_designer_id,
                            ?
                        ),
                     reviewed_at=
                        coalesce(
                            reviewed_at,
                            now()
                        ),
                     previous_storage_path=?,
                     applied_at=now()
                 where id=?',
                [
                    $hostDesignerId,
                    $oldStorage,
                    $requestId
                ]
            );

            /*
             * If this bundle was already built, leave final_zip_path
             * pointing at the currently downloadable ZIP while the
             * replacement is scanned/rebuilt.
             */
            if ($hadSnapshot) {
                DB::exec(
                    'update collab_events
                     set status="processing",
                         zip_error=null
                     where id=?',
                    [$collabId]
                );
            }

            /*
             * Admin-approved staged reviews already carry the exact
             * approved candidate fingerprint. Do not destroy that
             * approval before the authoritative scan sees the same
             * fingerprint.
             */
            if (!$adminApproved) {
                CollabIpRiskWorkflow::invalidate(
                    $collabId
                );
            }

            DB::commit();

        } catch (Throwable $error) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }

            throw $error;
        }

        $scan =
            (new CollabIpRiskWorkflow())
                ->scan($collabId);

        if (
            !in_array(
                (string)$scan['state'],
                ['clear','approved'],
                true
            )
        ) {
            return [
                'state' => $scan['state'],
                'rebuilt' => false,
            ];
        }

        $event = DB::row(
            'select *
             from collab_events
             where id=?',
            [$collabId]
        );

        /*
         * Existing snapshots rebuild immediately.
         * Unsnapshotted collabs finalize only after their deadline.
         */
        if (
            !empty($event['snapshot_at']) ||
            strtotime(
                (string)$event['upload_deadline']
            ) <= time()
        ) {
            (new CollabService())->finalize(
                $collabId
            );
        }

        $after = DB::row(
            'select *
             from collab_events
             where id=?',
            [$collabId]
        );

        $rebuilt =
            !empty($after) &&
            $after['status'] === 'ready' &&
            !empty($after['final_zip_path']);

        if (
            $rebuilt &&
            $oldStorage !== null
        ) {
            $this->unlinkOldSource(
                $oldStorage
            );
        }

        if (
            $rebuilt &&
            $saleCount > 0
        ) {
            $this->notifyBuyers(
                $collabId,
                $requestId
            );
        }

        return [
            'state' => (string)$scan['state'],
            'rebuilt' => $rebuilt,
        ];
    }

    private function rejectAfterAdminReview(
        int $requestId
    ): void {
        DB::begin();

        try {
            $request = DB::row(
                'select *
                 from collab_file_update_requests
                 where id=?
                   and status="ip_review"
                 for update',
                [$requestId]
            );

            if (!$request) {
                DB::commit();
                return;
            }

            DB::exec(
                'update collab_file_update_requests
                 set status="denied"
                 where id=?',
                [$requestId]
            );

            DB::exec(
                'update collab_events
                 set ip_risk_state=?,
                     ip_content_fingerprint=?
                 where id=?',
                [
                    $request['previous_ip_state']
                        ?: 'review_required',
                    $request['previous_ip_fingerprint'],
                    (int)$request['collab_id']
                ]
            );

            DB::commit();

        } catch (Throwable $error) {
            if (DB::pdo()->inTransaction()) {
                DB::rollBack();
            }

            throw $error;
        }

        $this->unlinkCandidate(
            (string)$request['storage_path']
        );

        /*
         * Restore authoritative detections for the unchanged live
         * bundle. If its prior fingerprint was approved, the normal
         * workflow preserves that approval.
         */
        (new CollabIpRiskWorkflow())
            ->scan(
                (int)$request['collab_id']
            );
    }

    private function candidateRisk(
        int $collabId,
        int $fileId,
        string $replacementName
    ): array {
        $collab = DB::row(
            'select *
             from collab_events
             where id=?',
            [$collabId]
        );

        if (!$collab) {
            throw new DomainException(
                'Collab not found.'
            );
        }

        $terms = DB::rows(
            'select *
             from ip_risk_terms
             where is_enabled=1'
        );

        foreach ($terms as &$term) {
            $term['aliases'] = DB::rows(
                'select *
                 from ip_risk_term_aliases
                 where ip_risk_term_id=?
                   and is_enabled=1',
                [$term['id']]
            );
        }

        unset($term);

        $rows = DB::rows(
            'select id,original_name
             from collab_files
             where collab_id=?
             order by id',
            [$collabId]
        );

        $names = [];

        foreach ($rows as $row) {
            $names[] =
                (int)$row['id'] === $fileId
                    ? $replacementName
                    : (string)$row['original_name'];
        }

        $input = [
            'title' =>
                $collab['title'],

            'description' =>
                $collab['description'],

            'file_names' =>
                $names,
        ];

        $fingerprint = hash(
            'sha256',
            json_encode(
                $input,
                JSON_UNESCAPED_UNICODE |
                JSON_THROW_ON_ERROR
            )
        );

        $matches =
            (new IpRiskScanner())
                ->scan(
                    $input,
                    $terms
                );

        return [
            'fingerprint' => $fingerprint,
            'matches' => $matches,
        ];
    }

    private function saleCount(
        int $collabId
    ): int {
        return (int)(
            DB::row(
                'select count(distinct oi.order_id) c
                 from order_items oi
                 join orders o
                   on o.id=oi.order_id
                 where oi.collab_id=?
                   and o.payment_status in (
                       "paid",
                       "partially_refunded"
                   )',
                [$collabId]
            )['c'] ?? 0
        );
    }

    private function notifyBuyers(
        int $collabId,
        int $requestId
    ): void {
        $collab = DB::row(
            'select title
             from collab_events
             where id=?',
            [$collabId]
        );

        if (!$collab) {
            return;
        }

        $buyers = DB::rows(
            'select distinct
                o.id order_id,
                o.user_id,
                u.email,
                u.name
             from order_items oi
             join orders o
               on o.id=oi.order_id
             join users u
               on u.id=o.user_id
             where oi.collab_id=?
               and o.payment_status in (
                   "paid",
                   "partially_refunded"
               )
               and coalesce(
                   (
                       select sum(
                           a.merchandise_refund_cents
                       )
                       from marketplace_refund_allocations a
                       where a.order_item_id=oi.id
                   ),
                   0
               ) < round(oi.total_price * 100)',
            [$collabId]
        );

        foreach ($buyers as $buyer) {
            $key =
                'collab:'.
                $collabId.
                ':update:'.
                $requestId.
                ':order:'.
                (int)$buyer['order_id'];

            try {
                NotificationService::create(
                    (int)$buyer['user_id'],
                    'collab_bundle_updated',
                    'buyer',
                    'Your collab bundle was updated',
                    'Updated files are now available for “'.
                        $collab['title'].'”.',
                    $key.':notification',
                    '/dashboard/order/'.
                        (int)$buyer['order_id']
                );

                EmailQueueService::queue(
                    'transactional',
                    (string)$buyer['email'],
                    'Updated Creative Moth collab files are ready',
                    'collab_updated',
                    [
                        'name' =>
                            $buyer['name'],

                        'collab_title' =>
                            $collab['title'],

                        'order_id' =>
                            (int)$buyer['order_id'],
                    ],
                    $key.':email'
                );

            } catch (Throwable $error) {
                NotificationService::reportFailure(
                    'collab_file_update_buyer_notification',
                    $error
                );
            }
        }

        DB::exec(
            'update collab_file_update_requests
             set buyer_notified_at=now()
             where id=?',
            [$requestId]
        );
    }

    private function unlinkOldSource(
        string $storagePath
    ): void {
        $real =
            (new CollabService())
                ->protectedRealPath(
                    $storagePath
                );

        if ($real && is_file($real)) {
            @unlink($real);
        }
    }

    private function unlinkCandidate(
        string $storagePath
    ): void {
        $this->unlinkOldSource(
            $storagePath
        );
    }
}
