<?php
namespace App\Services;

use App\Core\Database as DB;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Throwable;

final class GrandOpeningSaleService
{
    public const PRICING_READY = false;

    public static function basisPoints(string $value): int
    {
        $value = trim($value);
        if (!preg_match('/^(?:0|[1-9][0-9]?)(?:\.[0-9]{1,2})?$/D', $value)) {
            throw new DomainException('Enter a percentage from 0.01 to 99.99.');
        }
        [$whole,$fraction] = array_pad(explode('.', $value, 2), 2, '');
        $points = (int)$whole * 100 + (int)str_pad($fraction, 2, '0');
        if ($points < 1 || $points > 9999) {
            throw new DomainException('Enter a percentage from 0.01 to 99.99.');
        }
        return $points;
    }

    public static function discountedCents(int $base, int $points): int
    {
        if ($base < 0 || $points < 1 || $points > 9999) {
            throw new DomainException('Invalid sale price input.');
        }
        return $base - intdiv($base * $points + 5000, 10000);
    }

    public static function utcInput(string $input): ?string
    {
        if ($input === '') return null;
        $zone = new DateTimeZone('America/Detroit');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $input, $zone);
        if (!$date || $date->format('Y-m-d\TH:i') !== $input) {
            throw new DomainException('Enter a valid Eastern date and time.');
        }
        foreach ([-3600,3600] as $offset) {
            $other = (new DateTimeImmutable('@'.($date->getTimestamp()+$offset)))
                ->setTimezone($zone);
            if ($other->format('Y-m-d\TH:i') === $input) {
                throw new DomainException('Choose a time outside the repeated daylight-saving hour.');
            }
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function localInput(?string $utc): string
    {
        return $utc
            ? (new DateTimeImmutable($utc,new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone('America/Detroit'))->format('Y-m-d\TH:i')
            : '';
    }

    public static function state(array $event, ?int $now = null): string
    {
        if (empty($event['starts_at']) || empty($event['ends_at'])) return 'unscheduled';
        $zone = new DateTimeZone('UTC');
        $start = (new DateTimeImmutable($event['starts_at'],$zone))->getTimestamp();
        $end = (new DateTimeImmutable($event['ends_at'],$zone))->getTimestamp();
        $now ??= time();
        if ($now >= $end) return 'ended';
        if (empty($event['is_enabled'])) return 'draft';
        return $now < $start ? 'upcoming' : 'live';
    }

    public function event(): array
    {
        $event = DB::row('select * from grand_opening_events where id=1');
        if (!$event) throw new DomainException('Grand Opening configuration is missing.');
        return $event;
    }

    public function seller(int $designer): array
    {
        $sale = DB::row(
            'select * from grand_opening_sales where event_id=1 and designer_id=?',
            [$designer]
        ) ?? ['is_participating'=>0,'discount_basis_points'=>1000,'product_scope'=>'shop'];
        $sale['product_ids'] = array_map('intval',array_column(DB::rows(
            'select product_id from grand_opening_sale_products
             where event_id=1 and designer_id=? order by product_id',
            [$designer]
        ),'product_id'));
        return $sale;
    }

    private function audit(?int $designer,int $actor,string $action,array $before,array $after): void
    {
        DB::exec(
            'insert into grand_opening_sale_audits
             (event_id,designer_id,actor_user_id,action,before_snapshot,after_snapshot)
             values(1,?,?,?,?,?)',
            [$designer,$actor,$action,
             json_encode($before,JSON_THROW_ON_ERROR),
             json_encode($after,JSON_THROW_ON_ERROR)]
        );
    }

    public function saveEvent(array $input,int $actor): void
    {
        $start = self::utcInput(trim((string)($input['starts_at']??'')));
        $end = self::utcInput(trim((string)($input['ends_at']??'')));
        if (($start===null)!==($end===null)) {
            throw new DomainException('Enter both dates, or leave both blank.');
        }
        if ($start && $end) {
            $zone = new DateTimeZone('UTC');
            $a = new DateTimeImmutable($start,$zone);
            $b = new DateTimeImmutable($end,$zone);
            $minimum = $a->setTimezone(new DateTimeZone('America/Detroit'))->modify('+7 days');
            if ($b < $minimum) throw new DomainException('The event must run at least seven days.');
        }
        $enabled = isset($input['is_enabled']) ? 1 : 0;
        if ($enabled && (!self::PRICING_READY || !$start || !$end)) {
            throw new DomainException('Activation unlocks after checkout and homepage integration is verified.');
        }
        DB::begin();
        try {
            $before = DB::row('select * from grand_opening_events where id=1 for update');
            if (!$before) throw new DomainException('Grand Opening configuration is missing.');
            DB::exec(
                'update grand_opening_events set starts_at=?,ends_at=?,is_enabled=? where id=1',
                [$start,$end,$enabled]
            );
            $this->audit(null,$actor,'event_settings',$before,$this->event());
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function saveSeller(int $designer,int $actor,array $input): void
    {
        $points = self::basisPoints((string)($input['discount_percent']??''));
        $scope = (string)($input['product_scope']??'');
        if (!in_array($scope,['shop','selected'],true)) {
            throw new DomainException('Choose shop-wide or selected products.');
        }
        $raw = $input['product_ids']??[];
        if (!is_array($raw)) throw new DomainException('Invalid product selection.');
        $ids = [];
        foreach ($raw as $id) {
            if (!is_scalar($id) || !preg_match('/^[1-9][0-9]*$/D',(string)$id)) {
                throw new DomainException('Invalid product selection.');
            }
            $ids[] = (int)$id;
        }
        $ids = array_values(array_unique($ids));
        sort($ids);
        if ($scope==='shop') $ids=[];
        $participating = isset($input['is_participating']) ? 1 : 0;
        if ($participating && $scope==='selected' && !$ids) {
            throw new DomainException('Select at least one product.');
        }
        DB::begin();
        try {
            if (!DB::row(
                'select id from designers where id=? and user_id=? and status="approved" for update',
                [$designer,$actor]
            )) throw new DomainException('An approved store is required.');
            if (!DB::row('select id from grand_opening_events where id=1 for update')) {
                throw new DomainException('Grand Opening configuration is missing.');
            }
            $before = $this->seller($designer);
            if ($ids) {
                $marks = implode(',',array_fill(0,count($ids),'?'));
                $owned = DB::rows(
                    'select id from products where designer_id=? and id in ('.$marks.')
                     and status not in ("deleted","archived","disabled") for update',
                    array_merge([$designer],$ids)
                );
                if (count($owned)!==count($ids)) {
                    throw new DomainException('Choose available products belonging to your store.');
                }
            }
            DB::exec(
                'insert into grand_opening_sales
                 (event_id,designer_id,is_participating,discount_basis_points,product_scope)
                 values(1,?,?,?,?)
                 on duplicate key update is_participating=values(is_participating),
                 discount_basis_points=values(discount_basis_points),product_scope=values(product_scope)',
                [$designer,$participating,$points,$scope]
            );
            DB::exec('delete from grand_opening_sale_products where event_id=1 and designer_id=?',[$designer]);
            foreach ($ids as $id) {
                DB::exec(
                    'insert into grand_opening_sale_products(event_id,designer_id,product_id) values(1,?,?)',
                    [$designer,$id]
                );
            }
            $this->audit($designer,$actor,'seller_settings',$before,$this->seller($designer));
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
