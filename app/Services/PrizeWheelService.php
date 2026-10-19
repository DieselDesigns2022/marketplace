<?php

namespace App\Services;

use App\Core\Database as DB;

final class PrizeWheelService
{
    public function settings(): array
    {
        return DB::row(
            'select *
             from prize_wheel_settings
             where id=1
             limit 1'
        ) ?? [
            'id'=>1,
            'is_enabled'=>0,
            'heading'=>'Grand Opening Prize Wheel',
            'description'=>
                'Spin the Creative Moth Grand Opening Prize Wheel for a chance to win!',
        ];
    }

    public function globallyEnabled(): bool
    {
        return !empty($this->settings()['is_enabled']);
    }

    public function storefrontVisible(array $designer): bool
    {
        return
            ($designer['status'] ?? '') === 'approved'
            && !empty($designer['prize_wheel_opt_in'])
            && $this->globallyEnabled();
    }

    public function updateSettings(
        bool $enabled,
        string $heading,
        string $description,
        int $adminId
    ): void {
        $heading=trim($heading);
        $description=trim($description);

        if($heading===''){
            $heading='Grand Opening Prize Wheel';
        }

        $heading=mb_substr($heading,0,190);
        $description=mb_substr($description,0,1000);

        DB::exec(
            'insert into prize_wheel_settings
             (
                id,
                is_enabled,
                heading,
                description,
                updated_by,
                enabled_at,
                disabled_at
             )
             values
             (
                1,?,?,?,?,?,
                case when ?=0 then now() else null end
             )
             on duplicate key update
                is_enabled=values(is_enabled),
                heading=values(heading),
                description=values(description),
                updated_by=values(updated_by),
                enabled_at=
                    case
                        when values(is_enabled)=1
                         and prize_wheel_settings.is_enabled=0
                        then now()
                        else prize_wheel_settings.enabled_at
                    end,
                disabled_at=
                    case
                        when values(is_enabled)=0
                         and prize_wheel_settings.is_enabled=1
                        then now()
                        else prize_wheel_settings.disabled_at
                    end',
            [
                $enabled?1:0,
                $heading,
                $description!==''?$description:null,
                $adminId,
                $enabled?date('Y-m-d H:i:s'):null,
                $enabled?1:0,
            ]
        );
    }

    public function optedInStoreCount(): int
    {
        $row=DB::row(
            'select count(*) total
             from designers
             where status="approved"
               and prize_wheel_opt_in=1'
        );

        return (int)($row['total']??0);
    }

    public function availableWheelPrizeCount(): int
    {
        $row=DB::row(
            'select coalesce(sum(quantity_remaining),0) total
             from prize_donations
             where status="available"
               and quantity_remaining>0
               and seller_authorized=1
               and (
                    usage_designations like ?
                    or usage_designations like ?
                    or usage_designations=?
               )',
            [
                '%"prize_wheel"%',
                "%'prize_wheel'%",
                'prize_wheel',
            ]
        );

        return (int)($row['total']??0);
    }
}
