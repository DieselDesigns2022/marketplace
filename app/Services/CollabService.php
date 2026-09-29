<?php

namespace App\Services;

use App\Core\Database as DB;
use App\Repositories\CollabRepository;
use DomainException;
use Throwable;
use ZipArchive;

final class CollabService
{
    public function __construct(private ?CollabRepository $repo = null)
    {
        $this->repo ??= new CollabRepository();
    }

    public static function validateDates(string $deadline, string $start, string $close): void
    {
        $deadlineAt = strtotime($deadline);
        $startAt = strtotime($start);
        $closeAt = strtotime($close.' 23:59:59');
        if (!$deadlineAt || !$startAt || !$closeAt || $deadlineAt > $startAt || $startAt > $closeAt) {
            throw new DomainException('Dates must follow File Upload Deadline ≤ Sale Start ≤ end of Sale Close Date.');
        }
    }

    public static function safeArchiveName(string $name, int $id): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\pL\pN._ -]+/u', '_', $name) ?: 'file';
        return sprintf('%06d-%s', $id, trim($name, '. ') ?: 'file');
    }

    public function canSell(array $collab, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable('now');
        $start = new \DateTimeImmutable($collab['sale_starts_at']);
        $close = new \DateTimeImmutable($collab['sale_close_date'].' 23:59:59');
        $available = $collab['status'] === 'ready'
            && !empty($collab['snapshot_at'])
            && !empty($collab['final_zip_path'])
            && in_array($collab['ip_risk_state'], ['clear','approved'], true)
            && $now >= $start && $now <= $close;

        if (!$available) return false;

        if (!empty($collab['quantity_limit'])) {
            $sold = (int)(DB::row(
                'select count(*) c from order_items oi join orders o on o.id=oi.order_id where oi.collab_id=? and o.payment_status in ("paid","partially_refunded")',
                [(int)$collab['id']]
            )['c'] ?? 0);

            if ($sold >= (int)$collab['quantity_limit']) return false;
        }

        return true;
    }

    public function processDue(?int $onlyId = null): array
    {
        foreach (DB::rows('select c.id,c.title,cp.designer_id,d.user_id from collab_events c join collab_participants cp on cp.collab_id=c.id and cp.membership_status in ("host","accepted") join designers d on d.id=cp.designer_id where c.snapshot_at is null and c.upload_deadline>now() and c.upload_deadline<=now()+interval 24 hour') as $upcoming) {
            try {
                NotificationService::create((int)$upcoming['user_id'], 'collab_deadline_upcoming', 'designer', 'Collab deadline approaching', 'The upload deadline for “'.$upcoming['title'].'” is within 24 hours.', 'collab:'.$upcoming['id'].':deadline-upcoming:'.$upcoming['designer_id'], '/seller/collabs/'.$upcoming['id']);
            } catch (Throwable $error) {
                NotificationService::reportFailure('collab_deadline_upcoming', $error);
            }
        }
        $params = $onlyId ? [$onlyId] : [];
        $filter = $onlyId ? ' and id=?' : '';
        $rows = DB::rows(
            'select id from collab_events where upload_deadline<=now() and status in ("collecting","processing","failed")'.$filter.' order by id',
            $params
        );
        $results = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            try {
                $this->finalize($id);
                $results[$id] = DB::row('select status from collab_events where id=?', [$id])['status'] ?? 'processing';
            } catch (CollabTerminalException $error) {
                $results[$id] = 'ineligible';
            } catch (Throwable $error) {
                DB::exec('update collab_events set status="failed",zip_error=?,zip_attempts=zip_attempts+1 where id=? and status<>"ready"', [substr($error->getMessage(),0,500),$id]);
                $results[$id] = 'failed';
                $this->notifyHost($id, 'collab_zip_failed', 'Collab ZIP needs attention', 'The final bundle could not be built and will be retried.', 'zip-failed');
            }
        }
        $ended = DB::rows('select id from collab_events where status="ready" and sale_close_date<current_date');
        DB::exec('update collab_events set status="ended",ended_at=coalesce(ended_at,now()) where status="ready" and sale_close_date<current_date');
        foreach ($ended as $row) $this->notifyHost((int)$row['id'], 'collab_ended', 'Collab ended', 'Your collab sale period has ended.', 'ended');
        return $results;
    }

    public function finalize(int $id): void
    {
        $scan = (new CollabIpRiskWorkflow())->scan($id);

        if (!in_array((string)$scan['state'], ['clear','approved'], true)) {
            return;
        }

        $this->snapshotOnce($id);
        $this->buildZip($id);
        $this->generateCover($id);
    }

    private function snapshotOnce(int $id): void
    {
        $ownsTransaction = !DB::pdo()->inTransaction();
        if ($ownsTransaction) DB::begin();
        try {
            $collab = DB::row('select * from collab_events where id=? for update', [$id]);
            if (!$collab) throw new DomainException('Collab not found.');
            if ($collab['snapshot_at'] !== null) { if ($ownsTransaction) DB::commit(); return; }
            if (strtotime($collab['upload_deadline']) > time()) throw new DomainException('Upload deadline has not passed.');
            $participants = DB::rows(
                'select cp.*,
                        sum(f.file_kind="contribution") contribution_count,
                        sum(
                            f.file_kind="contribution"
                            and f.category_id is null
                        ) uncategorized_contribution_count,
                        sum(f.file_kind="terms") terms_count,
                        sum(f.file_kind="mockup") mockup_count
                 from collab_participants cp
                 left join collab_files f on f.participant_id=cp.id
                 where cp.collab_id=?
                   and cp.membership_status in ("host","accepted")
                 group by cp.id
                 order by cp.designer_id',
                [$id]
            );
            $eligible = [];
            foreach ($participants as $participant) {
                $count = (int)$participant['contribution_count'];
                $reason =
                    $count < (int)$collab['minimum_file_count']
                        ? 'minimum_contribution_not_met'
                        : (
                            (int)$participant['uncategorized_contribution_count'] > 0
                                ? 'missing_contribution_category'
                                : (
                                    (int)$participant['terms_count'] < 1
                                        ? 'missing_terms_file'
                                        : (
                                            !empty($collab['require_mockup'])
                                            && (int)$participant['mockup_count'] < 1
                                                ? 'missing_mockup_file'
                                                : null
                                        )
                                )
                        );
                $isEligible = $reason === null;
                DB::exec('update collab_participants set eligibility=?,qualifying_file_count=?,exclusion_reason=?,eligibility_snapshotted_at=now() where id=?', [$isEligible?'eligible':'excluded',$count,$reason,$participant['id']]);
                if ($isEligible) $eligible[] = (int)$participant['id'];
            }
            DB::exec('update collab_files set included_in_snapshot=0 where collab_id=?', [$id]);
            if ($eligible) {
                $marks = implode(',', array_fill(0, count($eligible), '?'));
                DB::exec("update collab_files set included_in_snapshot=1 where collab_id=? and participant_id in ($marks)", array_merge([$id], $eligible));
            }
            $status = $eligible ? 'processing' : 'ineligible';
            $reason = $eligible ? null : 'No participant met both contribution and Terms requirements.';
            DB::exec('update collab_events set snapshot_at=now(),eligible_count=?,status=?,zip_error=? where id=?', [count($eligible),$status,$reason,$id]);
            if ($ownsTransaction) DB::commit();
            foreach ($participants as $participant) {
                if (!in_array((int)$participant['id'], $eligible, true)) {
                    if ((int)$participant['contribution_count'] < (int)$collab['minimum_file_count']) {
                        $reason = 'minimum_contribution_not_met';
                    } elseif ((int)$participant['uncategorized_contribution_count'] > 0) {
                        $reason = 'missing_contribution_category';
                    } elseif ((int)$participant['terms_count'] < 1) {
                        $reason = 'missing_terms_file';
                    } else {
                        $reason = 'missing_mockup_file';
                    }
                    $this->notifyParticipant((int)$participant['designer_id'], $id, $reason);
                }
            }
            $this->notifyHost($id, 'collab_deadline_reached', 'Collab upload deadline reached', 'Contributions are locked and the final eligibility snapshot was created.', 'deadline-reached');
            if (!$eligible) throw new CollabTerminalException('No participant met both contribution and Terms requirements.');
        } catch (Throwable $error) {
            if ($ownsTransaction && DB::pdo()->inTransaction()) DB::rollBack();
            throw $error;
        }
    }

    private function buildZip(int $id): void
    {
        $lock = 'creative_moth_collab_zip_'.$id;
        $acquired = (int)(DB::row('select get_lock(?,0) acquired', [$lock])['acquired'] ?? 0) === 1;
        if (!$acquired) return;
        try {
            $this->buildZipLocked($id);
        } finally {
            DB::row('select release_lock(?) released', [$lock]);
        }
    }

    private function buildZipLocked(int $id): void
    {
        if (!class_exists(ZipArchive::class)) throw new DomainException('PHP ZipArchive extension is required.');
        $collab = $this->repo->event($id) ?? throw new DomainException('Collab not found.');
        if ($collab['status'] === 'ready' && $this->validExistingArchive($collab)) return;
        if (empty($collab['snapshot_at'])) throw new DomainException('Eligibility snapshot is required.');
        DB::exec('update collab_events set status="processing",zip_error=null where id=? and status<>"ready"', [$id]);
        $directory = app_path('storage/protected_uploads/collabs/final');
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new DomainException('Final archive directory could not be created.');
        foreach (glob($directory.'/collab-'.$id.'-*.tmp') ?: [] as $orphan) @unlink($orphan);
        $target = $directory.'/collab-'.$id.'-'.hash('sha256',(string)$collab['snapshot_at']).'.zip';
        $files = DB::rows('select f.*,d.store_slug from collab_files f join designers d on d.id=f.designer_id where f.collab_id=? and f.included_in_snapshot=1 order by f.designer_id,f.id', [$id]);
        $manifest = $this->snapshotManifest($collab, $files);
        if (is_file($target) && $this->archiveMatchesSnapshot($target, $manifest)) {
            DB::exec('update collab_events set final_zip_path=?,final_zip_sha256=?,status="ready",ready_at=coalesce(ready_at,now()),zip_error=null,zip_attempts=zip_attempts+1 where id=?', ['collabs/final/'.basename($target),hash_file('sha256',$target),$id]);
            $this->notifyHost($id, 'collab_zip_ready', 'Collab bundle ready', 'The final protected ZIP is ready.', 'zip-ready');
            return;
        }
        /*
         * Keep the currently downloadable ZIP in place while the
         * replacement archive is built. rename() happens only after
         * the new ZIP has completed successfully.
         */
        $temporary = $target.'.'.bin2hex(random_bytes(6)).'.tmp';
        $zip = new ZipArchive();
        if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new DomainException('Final archive could not be opened.');
        try {
            foreach ($files as $file) {
                $real = $this->protectedRealPath((string)$file['storage_path']);
                if (!$real || !hash_equals((string)$file['sha256'], hash_file('sha256', $real))) throw new DomainException('A snapshotted contribution failed its integrity check.');
                if (!$zip->addFile($real, $file['store_slug'].'/'.self::safeArchiveName($file['original_name'],(int)$file['id']))) throw new DomainException('A contribution could not be archived.');
            }
            $snapshotFingerprint =
                'creative-moth-snapshot-sha256:'.
                hash('sha256', $manifest);

            if (!$zip->setArchiveComment($snapshotFingerprint)) {
                throw new DomainException(
                    'The archive snapshot fingerprint could not be written.'
                );
            }
            if (!$zip->close()) throw new DomainException('Final archive could not be closed.');
            if (!rename($temporary, $target)) throw new DomainException('Final archive could not be committed.');
            $hash = hash_file('sha256', $target);
            DB::exec('update collab_events set final_zip_path=?,final_zip_sha256=?,status="ready",ready_at=coalesce(ready_at,now()),zip_error=null,zip_attempts=zip_attempts+1 where id=?', ['collabs/final/'.basename($target),$hash,$id]);
            $this->notifyHost($id, 'collab_zip_ready', 'Collab bundle ready', 'The final protected ZIP is ready.', 'zip-ready');
        } catch (Throwable $error) {
            $zip->close(); @unlink($temporary); throw $error;
        }
    }


    public function generateCover(int $id): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }

        $collab = DB::row(
            'select id,title,snapshot_at
             from collab_events
             where id=?',
            [$id]
        );

        if (!$collab || empty($collab['snapshot_at'])) {
            return null;
        }

        $rows = DB::rows(
            'select
                f.id,
                f.designer_id,
                f.storage_path,
                f.mime_type,
                f.sha256
             from collab_files f
             where f.collab_id=?
               and f.file_kind="mockup"
               and f.included_in_snapshot=1
             order by
                f.designer_id,
                f.id',
            [$id]
        );

        /*
         * Round-robin mockups by designer so one seller does not
         * dominate the first preview image when several designers
         * supplied mockups.
         */
        $byDesigner = [];

        foreach ($rows as $row) {
            $byDesigner[(int)$row['designer_id']][] = $row;
        }

        $ordered = [];

        while ($byDesigner) {
            foreach (array_keys($byDesigner) as $designerId) {
                if (!$byDesigner[$designerId]) {
                    unset($byDesigner[$designerId]);
                    continue;
                }

                $ordered[] = array_shift(
                    $byDesigner[$designerId]
                );

                if (!$byDesigner[$designerId]) {
                    unset($byDesigner[$designerId]);
                }
            }
        }

        $usable = [];

        foreach ($ordered as $row) {
            $real = $this->protectedRealPath(
                (string)$row['storage_path']
            );

            if (
                !$real ||
                !is_file($real) ||
                !hash_equals(
                    (string)$row['sha256'],
                    (string)hash_file('sha256',$real)
                )
            ) {
                continue;
            }

            $info = @getimagesize($real);

            if (!$info) {
                continue;
            }

            $usable[] = [
                'path' => $real,
                'type' => (int)$info[2],
            ];
        }

        /*
         * No mockups: keep the generic single-cover fallback.
         */
        if (!$usable) {
            $usable = [
                [
                    'path' => null,
                    'type' => null,
                ],
            ];
        }

        $groups = array_chunk(
            $usable,
            4
        );

        $directory = app_path(
            'public/uploads/collab-covers'
        );

        if (
            !is_dir($directory) &&
            !mkdir(
                $directory,
                0755,
                true
            ) &&
            !is_dir($directory)
        ) {
            return null;
        }

        $generated = [];

        foreach ($groups as $pageIndex => $group) {
            $size = 800;

            $canvas = imagecreatetruecolor(
                $size,
                $size
            );

            if (!$canvas) {
                return null;
            }

            $background = imagecolorallocate(
                $canvas,
                247,
                243,
                252
            );

            imagefilledrectangle(
                $canvas,
                0,
                0,
                $size,
                $size,
                $background
            );

            $realImages = array_values(
                array_filter(
                    $group,
                    static fn(array $item): bool =>
                        $item['path'] !== null
                )
            );

            if ($realImages) {
                $layouts = match (count($realImages)) {
                    1 => [
                        [0,0,800,800],
                    ],

                    2 => [
                        [0,0,400,800],
                        [400,0,400,800],
                    ],

                    3 => [
                        [0,0,400,800],
                        [400,0,400,400],
                        [400,400,400,400],
                    ],

                    default => [
                        [0,0,400,400],
                        [400,0,400,400],
                        [0,400,400,400],
                        [400,400,400,400],
                    ],
                };

                foreach ($realImages as $index => $item) {
                    $source = match ($item['type']) {
                        IMAGETYPE_JPEG =>
                            @imagecreatefromjpeg($item['path']),

                        IMAGETYPE_PNG =>
                            @imagecreatefrompng($item['path']),

                        IMAGETYPE_WEBP =>
                            function_exists('imagecreatefromwebp')
                                ? @imagecreatefromwebp($item['path'])
                                : false,

                        default => false,
                    };

                    if (!$source) {
                        continue;
                    }

                    [
                        $destX,
                        $destY,
                        $destW,
                        $destH
                    ] = $layouts[$index];

                    $srcW = imagesx($source);
                    $srcH = imagesy($source);

                    $destRatio = $destW / $destH;
                    $srcRatio = $srcW / $srcH;

                    if ($srcRatio > $destRatio) {
                        $cropH = $srcH;
                        $cropW = (int)round(
                            $srcH * $destRatio
                        );

                        $srcX = (int)round(
                            ($srcW - $cropW) / 2
                        );

                        $srcY = 0;
                    } else {
                        $cropW = $srcW;

                        $cropH = (int)round(
                            $srcW / $destRatio
                        );

                        $srcX = 0;

                        $srcY = (int)round(
                            ($srcH - $cropH) / 2
                        );
                    }

                    imagecopyresampled(
                        $canvas,
                        $source,
                        $destX,
                        $destY,
                        $srcX,
                        $srcY,
                        $destW,
                        $destH,
                        $cropW,
                        $cropH
                    );

                    imagedestroy($source);
                }
            } else {
                $purple = imagecolorallocate(
                    $canvas,
                    118,
                    51,
                    214
                );

                $ink = imagecolorallocate(
                    $canvas,
                    35,
                    25,
                    66
                );

                imagefilledrectangle(
                    $canvas,
                    0,
                    0,
                    $size,
                    130,
                    $purple
                );

                imagestring(
                    $canvas,
                    5,
                    40,
                    45,
                    'CREATIVE MOTH COLLAB BUNDLE',
                    imagecolorallocate(
                        $canvas,
                        255,
                        255,
                        255
                    )
                );

                imagestring(
                    $canvas,
                    5,
                    40,
                    370,
                    mb_substr(
                        (string)$collab['title'],
                        0,
                        55
                    ),
                    $ink
                );
            }

            /*
             * Page 1 keeps the existing filename because the homepage
             * and storefront cards already use it.
             */
            $filename =
                $pageIndex === 0
                    ? 'collab-'.$id.'.jpg'
                    : 'collab-'.$id.'-'.($pageIndex + 1).'.jpg';

            $destination =
                $directory.
                '/'.
                $filename;

            $temporary =
                $destination.
                '.'.
                bin2hex(random_bytes(6)).
                '.tmp';

            $saved = imagejpeg(
                $canvas,
                $temporary,
                84
            );

            imagedestroy($canvas);

            if (!$saved) {
                @unlink($temporary);
                return null;
            }

            $watermarkTemporary =
                $destination.
                '.watermarked.'.
                bin2hex(random_bytes(6)).
                '.jpg';

            $watermarkResult =
                \App\Services\WatermarkService::createProtectedImagePreview(
                    $temporary,
                    $watermarkTemporary,
                    false,
                    null,
                    null
                );

            if (empty($watermarkResult['ok'])) {
                @unlink($temporary);
                @unlink($watermarkTemporary);
                return null;
            }

            @unlink($temporary);

            if (!@rename(
                $watermarkTemporary,
                $destination
            )) {
                @unlink($watermarkTemporary);
                return null;
            }

            @chmod(
                $destination,
                0644
            );

            $generated[] = $destination;
        }

        /*
         * Remove stale extra preview pages if the collab now has fewer
         * mockups than a previous generation.
         */
        foreach (
            glob(
                $directory.
                '/collab-'.
                $id.
                '-*.jpg'
            ) ?: []
            as $existing
        ) {
            if (
                !in_array(
                    $existing,
                    $generated,
                    true
                )
            ) {
                @unlink($existing);
            }
        }

        return
            '/uploads/collab-covers/collab-'.
            $id.
            '.jpg';
    }

    public static function coverUrl(int $id): string
    {
        return
            '/uploads/collab-covers/collab-'.
            $id.
            '.jpg';
    }

    public static function coverUrls(int $id): array
    {
        $urls = [];

        $primary = app_path(
            'public/uploads/collab-covers/collab-'.
            $id.
            '.jpg'
        );

        if (is_file($primary)) {
            $urls[] =
                '/uploads/collab-covers/collab-'.
                $id.
                '.jpg';
        }

        $extras = glob(
            app_path(
                'public/uploads/collab-covers/collab-'.
                $id.
                '-*.jpg'
            )
        ) ?: [];

        natsort($extras);

        foreach ($extras as $path) {
            $urls[] =
                '/uploads/collab-covers/'.
                basename($path);
        }

        return array_values(
            array_unique($urls)
        );
    }

    public function protectedRealPath(string $storagePath): ?string
    {
        $base = realpath(app_path('storage/protected_uploads/collabs'));
        $real = realpath(app_path('storage/protected_uploads/'.ltrim($storagePath, '/')));
        return $base && $real && str_starts_with($real, $base.DIRECTORY_SEPARATOR) && is_file($real) ? $real : null;
    }

    private function validExistingArchive(array $collab): bool
    {
        $real = $this->protectedRealPath((string)$collab['final_zip_path']);
        return $real && hash_equals((string)$collab['final_zip_sha256'], hash_file('sha256',$real));
    }

    private function snapshotManifest(array $collab, array $files): string
    {
        $snapshot = ['collab_id'=>(int)$collab['id'], 'snapshot_at'=>(string)$collab['snapshot_at'], 'files'=>[]];
        foreach ($files as $file) {
            $real = $this->protectedRealPath((string)$file['storage_path']);
            if (!$real || !hash_equals((string)$file['sha256'], hash_file('sha256',$real))) {
                throw new DomainException('A snapshotted contribution failed its integrity check.');
            }
            $snapshot['files'][] = [
                'id'=>(int)$file['id'],
                'designer_id'=>(int)$file['designer_id'],
                'kind'=>$file['file_kind'],
                'category_id'=>$file['category_id']!==null?(int)$file['category_id']:null,
                'sha256'=>$file['sha256']
            ];
        }
        return json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function archiveMatchesSnapshot(string $path, string $expectedManifest): bool
    {
        if (!is_file($path) || !class_exists(ZipArchive::class)) return false;

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) return false;

        $actual = $zip->getArchiveComment();
        $zip->close();

        $expected =
            'creative-moth-snapshot-sha256:'.
            hash('sha256', $expectedManifest);

        return
            is_string($actual) &&
            hash_equals($expected, $actual);
    }

    public static function itemDownloadable(string $orderStatus, int $totalCents, int $refundedCents): bool
    {
        return in_array($orderStatus, ['paid','partially_refunded'], true)
            && $totalCents > 0
            && $refundedCents < $totalCents;
    }

    private function notifyParticipant(int $designerId, int $collabId, string $reason): void
    {
        $user = DB::row('select user_id from designers where id=?', [$designerId]);
        $message = match ($reason) {
            'missing_contribution_category' =>
                'You were excluded because one or more contribution files did not have a required marketplace category.',
            'missing_terms_file' =>
                'You were excluded because your Terms & Conditions & About Me files were missing.',
            'missing_mockup_file' =>
                'You were excluded because this collab required a mockup and none was uploaded.',
            default =>
                'You were excluded because the minimum contribution count was not met.',
        };
        try { NotificationService::create((int)$user['user_id'], 'collab_excluded', 'designer', 'Excluded from collab', $message, 'collab:'.$collabId.':excluded:'.$designerId, '/seller/collabs/'.$collabId); } catch (Throwable $error) { NotificationService::reportFailure('collab_exclusion', $error); }
    }

    private function notifyHost(int $id, string $type, string $title, string $message, string $suffix): void
    {
        $row = DB::row('select d.user_id from collab_events c join designers d on d.id=c.host_designer_id where c.id=?', [$id]);
        if (!$row) return;
        try { NotificationService::create((int)$row['user_id'], $type, 'designer', $title, $message, 'collab:'.$id.':'.$suffix, '/seller/collabs/'.$id); } catch (Throwable $error) { NotificationService::reportFailure('collab_host', $error); }
    }
}
