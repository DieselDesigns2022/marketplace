<?php
namespace App\Services;

use App\Core\Database as DB;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Throwable;

/** Ordinary listing schedules use UTC DATETIME, independent of the DB session timezone. */
final class ProductScheduleService
{
    public static function toUtc(string $local, string $timezone, ?DateTimeImmutable $now = null): string
    {
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) throw new DomainException('Choose your timezone before scheduling.');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $local)) throw new DomainException('Enter a valid publication date/time.');
        $zone = new DateTimeZone($timezone);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $local, $zone);
        if (!$date || $date->format('Y-m-d\TH:i') !== $local) throw new DomainException('Enter a valid date/time in your timezone; daylight-saving gaps are not valid.');
        // Reject repeated local times rather than silently choosing one DST offset.
        $wall = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $local, new DateTimeZone('UTC'))->getTimestamp();
        $matches = [];
        foreach ($zone->getTransitions($wall - 86400, $wall + 86400) ?: [] as $transition) {
            $candidate = $wall - $transition['offset'];
            if ((new DateTimeImmutable('@'.$candidate))->setTimezone($zone)->format('Y-m-d\TH:i') === $local) $matches[$candidate] = true;
        }
        if (count($matches) > 1) throw new DomainException('This time occurs twice when daylight saving ends. Choose an unambiguous time.');
        $utc = $date->setTimezone(new DateTimeZone('UTC'));
        if ($utc <= ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))) throw new DomainException('Scheduled publication must be in the future.');
        return $utc->format('Y-m-d H:i:s');
    }

    public static function approvedStatus(array $product): string
    {
        // Even overdue schedules go through the worker's eligibility checks.
        return !empty($product['scheduled_publish_at']) ? 'scheduled' : 'approved';
    }

    public function processDue(?DateTimeImmutable $now = null): array
    {
        $now = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $result = ['published'=>0, 'blocked'=>0];
        $ids = DB::rows('select id from products where status="scheduled" and scheduled_publish_at<=? order by schedule_checked_at,scheduled_publish_at,id limit 100', [$now]);
        foreach ($ids as $item) {
            try {
                DB::begin();
                $p = DB::row('select p.*,d.user_id,d.status seller_status from products p join designers d on d.id=p.designer_id where p.id=? for update', [$item['id']]);
                if (!$p || $p['status'] !== 'scheduled' || !$p['scheduled_publish_at'] || $p['scheduled_publish_at'] > $now) { DB::rollBack(); continue; }
                $errors = (new ProductSubmissionService())->validationErrors($p);
                if ($p['seller_status'] !== 'approved') $errors[] = 'Your seller account must be approved.';
                $risk = (new ProductIpRiskWorkflow())->scanProduct((int)$p['id'], (int)$p['user_id']);
                if ($risk['matches'] && !in_array($risk['state']['review_status'] ?? '', ['approved','published_flagged'], true)) $errors[] = 'IP review is required before publication.';
                if ($errors) {
                    DB::exec('update products set schedule_error=?,schedule_checked_at=? where id=?', [mb_substr(implode(' ', array_unique($errors)),0,1000),$now,$p['id']]);
                    DB::commit(); $result['blocked']++; continue;
                }
                DB::exec('update products set status="approved",scheduled_publish_at=null,schedule_error=null,schedule_checked_at=null,updated_at=now() where id=?', [$p['id']]);
                DB::commit();
                $result['published']++;
                ProductPublicationTransitionService::dispatchAfterCommit((int)$p['id'], 'scheduled', 'approved');
            } catch (Throwable $e) {
                if (DB::pdo()->inTransaction()) DB::rollBack();
                throw $e;
            }
        }
        return $result;
    }
}
