<?php

namespace App\Services;

use App\Core\Database as DB;

final class CollabIpRiskWorkflow
{
    public function scan(int $collabId): array
    {
        $ownsTransaction = !DB::pdo()->inTransaction();
        if ($ownsTransaction) DB::begin();
        try {
            $collab = DB::row('select * from collab_events where id=? for update', [$collabId]);
            if (!$collab) throw new \DomainException('Collab not found.');
            $terms = DB::rows('select * from ip_risk_terms where is_enabled=1');
            foreach ($terms as &$term) {
                $term['aliases'] = DB::rows('select * from ip_risk_term_aliases where risk_term_id=? and is_enabled=1', [$term['id']]);
            }
            unset($term);
            $input = [
                'title'=>$collab['title'],
                'description'=>$collab['description'],
                'file_names'=>array_column(DB::rows('select original_name from collab_files where collab_id=? order by id', [$collabId]), 'original_name'),
            ];
            $fingerprint = hash('sha256', json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $matches = (new IpRiskScanner())->scan($input, $terms);
            $oldFingerprint = (string)($collab['ip_content_fingerprint'] ?? '');
            $state = self::stateAfterScan((string)$collab['ip_risk_state'], $oldFingerprint, $fingerprint, (bool)$matches);
            DB::exec('update collab_ip_risk_detections set is_active=0 where collab_id=?', [$collabId]);
            foreach ($matches as $match) {
                DB::exec(
                    'insert into collab_ip_risk_detections (collab_id,risk_term_id,matched_term,matched_alias,source_field,content_fingerprint) values (?,?,?,?,?,?)',
                    [$collabId,$match['risk_term_id'],$match['matched_term'],$match['matched_alias'],$match['source_field'],$fingerprint]
                );
            }
            DB::exec('update collab_events set ip_risk_state=?,ip_content_fingerprint=? where id=?', [$state,$fingerprint,$collabId]);
            if ($ownsTransaction) DB::commit();
        } catch (\Throwable $error) {
            if ($ownsTransaction && DB::pdo()->inTransaction()) DB::rollBack();
            throw $error;
        }
        if ($matches && $fingerprint !== $oldFingerprint) {
            $host = DB::row('select u.id from users u join designers d on d.user_id=u.id where d.id=?', [$collab['host_designer_id']]);
            $this->notifySafely(function () use ($host, $collab, $collabId, $fingerprint): void {
                NotificationService::create((int)$host['id'], 'collab_ip_risk', 'designer', 'Collab requires IP review', 'Your collab “'.$collab['title'].'” requires IP-risk review.', 'collab:'.$collabId.':ip:'.$fingerprint.':host', '/seller/collabs/'.$collabId);
                NotificationService::admins('collab_ip_risk', 'Collab requires IP review', 'A collab has protected-content matches to review.', 'collab:'.$collabId.':ip:'.$fingerprint.':admin', '/admin/collabs/'.$collabId);
            });
        }
        return ['state'=>$state, 'fingerprint'=>$fingerprint, 'matches'=>$matches];
    }

    public static function invalidate(int $collabId): void
    {
        DB::exec('update collab_events set ip_risk_state="review_required",ip_content_fingerprint=null where id=?', [$collabId]);
    }

    public static function stateAfterScan(string $currentState, string $oldFingerprint, string $newFingerprint, bool $hasMatches): string
    {
        if ($oldFingerprint !== '' && hash_equals($oldFingerprint, $newFingerprint)) {
            return in_array($currentState, ['approved','rejected'], true)
                ? $currentState
                : ($hasMatches ? 'review_required' : 'clear');
        }
        return $hasMatches ? 'review_required' : 'clear';
    }

    public function review(int $collabId, int $adminId, string $decision, string $expectedFingerprint, string $notes = ''): void
    {
        if (!in_array($decision, ['approved','rejected'], true)) {
            throw new \InvalidArgumentException('Choose approve or reject.');
        }
        $ownsTransaction = !DB::pdo()->inTransaction();
        if ($ownsTransaction) DB::begin();
        try {
            $collab = DB::row('select * from collab_events where id=? for update', [$collabId]);
            if (!$collab || $collab['ip_risk_state'] !== 'review_required') throw new \DomainException('This collab has no unresolved review.');
            if ($expectedFingerprint === '' || !hash_equals((string)$collab['ip_content_fingerprint'], $expectedFingerprint)) throw new \DomainException('The collab content changed. Reload and review the current matches.');
            DB::exec('insert into collab_ip_risk_reviews(collab_id,admin_user_id,decision,content_fingerprint,notes) values(?,?,?,?,?)', [$collabId,$adminId,$decision,$expectedFingerprint,trim($notes)]);
            DB::exec('update collab_events set ip_risk_state=? where id=? and ip_content_fingerprint=?', [$decision,$collabId,$expectedFingerprint]);
            if ($ownsTransaction) DB::commit();
        } catch (\Throwable $error) {
            if ($ownsTransaction && DB::pdo()->inTransaction()) DB::rollBack();
            throw $error;
        }
        $host = DB::row('select u.id from users u join designers d on d.user_id=u.id where d.id=?', [$collab['host_designer_id']]);
        $this->notifySafely(fn() => NotificationService::create((int)$host['id'], 'collab_ip_reviewed', 'designer', 'Collab IP review '.$decision, 'The IP review for “'.$collab['title'].'” was '.$decision.'.', 'collab:'.$collabId.':review:'.$collab['ip_content_fingerprint'], '/seller/collabs/'.$collabId));
    }

    private function notifySafely(callable $callback): void
    {
        try { $callback(); } catch (\Throwable $error) { NotificationService::reportFailure('collab_notification', $error); }
    }
}
