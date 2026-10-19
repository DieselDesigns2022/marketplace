<?php

namespace App\Services;

use App\Core\Database as DB;
use DomainException;
use InvalidArgumentException;
use Throwable;

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
                'Spend $5 or more at this participating store to unlock a Prize Wheel spin!',
            'campaign_key'=>'grand-opening-2026',
            'minimum_purchase_cents'=>500,
            'campaign_started_at'=>null,
            'updated_by'=>null,
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
            && $this->globallyEnabled()
            && $this->availableWheelPrizeCount(
                (int)$designer['id']
            ) > 0;
    }

    public function updateSettings(
        bool $enabled,
        string $heading,
        string $description,
        int $minimumPurchaseCents,
        int $adminId
    ): void {
        $heading=trim($heading);
        $description=trim($description);

        if($heading===''){
            $heading='Grand Opening Prize Wheel';
        }

        if($minimumPurchaseCents<100){
            throw new InvalidArgumentException(
                'Minimum qualifying purchase must be at least $1.00.'
            );
        }

        if($minimumPurchaseCents>100000){
            throw new InvalidArgumentException(
                'Minimum qualifying purchase cannot exceed $1,000.00.'
            );
        }

        $current=$this->settings();
        $wasEnabled=!empty($current['is_enabled']);

        $heading=mb_substr($heading,0,190);
        $description=mb_substr($description,0,1000);

        DB::exec(
            'update prize_wheel_settings
             set is_enabled=?,
                 heading=?,
                 description=?,
                 minimum_purchase_cents=?,
                 updated_by=?,
                 enabled_at=
                    case
                        when ?=1 and is_enabled=0
                        then now()
                        else enabled_at
                    end,
                 campaign_started_at=
                    case
                        when ?=1
                         and is_enabled=0
                         and campaign_started_at is null
                        then now()
                        else campaign_started_at
                    end,
                 disabled_at=
                    case
                        when ?=0 and is_enabled=1
                        then now()
                        else disabled_at
                    end,
                 updated_at=now()
             where id=1',
            [
                $enabled?1:0,
                $heading,
                $description!==''?$description:null,
                $minimumPurchaseCents,
                $adminId,
                $enabled?1:0,
                $enabled?1:0,
                $enabled?1:0,
            ]
        );
    }

    public function startNewRound(int $adminId): string
    {
        $settings=$this->settings();

        if(!empty($settings['is_enabled'])){
            throw new DomainException(
                'Turn the Prize Wheel OFF before starting a new promotion round.'
            );
        }

        $campaign=
            'wheel-'.
            date('Ymd').
            '-'.
            bin2hex(random_bytes(6));

        DB::exec(
            'update prize_wheel_settings
             set campaign_key=?,
                 campaign_started_at=now(),
                 updated_by=?,
                 updated_at=now()
             where id=1',
            [
                $campaign,
                $adminId,
            ]
        );

        return $campaign;
    }

    public function participatingStores(): array
    {
        return DB::rows(
            'select
                d.id,
                d.display_name,
                d.store_slug,
                d.prize_wheel_opt_in,
                coalesce(
                    sum(
                        case
                            when pd.status="available"
                             and pd.quantity_remaining>0
                             and pd.seller_authorized=1
                             and find_in_set(
                                "prize_wheel",
                                pd.usage_designations
                             )>0
                            then pd.quantity_remaining
                            else 0
                        end
                    ),
                    0
                ) available_prize_units,
                count(
                    distinct case
                        when pd.status="available"
                         and pd.quantity_remaining>0
                         and pd.seller_authorized=1
                         and find_in_set(
                            "prize_wheel",
                            pd.usage_designations
                         )>0
                        then pd.id
                        else null
                    end
                ) available_prizes
             from designers d
             left join prize_donations pd
               on pd.designer_id=d.id
             where d.status="approved"
               and d.prize_wheel_opt_in=1
             group by
                d.id,
                d.display_name,
                d.store_slug,
                d.prize_wheel_opt_in
             order by d.display_name,d.id'
        );
    }

    public function optedInStoreCount(): int
    {
        return count($this->participatingStores());
    }

    public function availableWheelPrizeCount(
        ?int $designerId=null
    ): int {
        $where='
            status="available"
            and quantity_remaining>0
            and seller_authorized=1
            and find_in_set(
                "prize_wheel",
                usage_designations
            )>0
        ';

        $args=[];

        if($designerId!==null){
            $where.=' and designer_id=?';
            $args[]=$designerId;
        }

        $row=DB::row(
            'select coalesce(sum(quantity_remaining),0) total
             from prize_donations
             where '.$where,
            $args
        );

        return (int)($row['total']??0);
    }

    private function qualifyingMinimumCents(): int
    {
        return max(
            100,
            (int)(
                $this->settings()['minimum_purchase_cents']
                ??500
            )
        );
    }

    public function eligibleOrders(
        int $storefrontDesignerId,
        int $userId
    ): array {
        $settings=$this->settings();

        $campaign=(string)(
            $settings['campaign_key']
            ??'grand-opening-2026'
        );

        $startedAt=
            $settings['campaign_started_at']
            ?: $settings['enabled_at']
            ?: '1970-01-01 00:00:00';

        $minimum=$this->qualifyingMinimumCents();

        return DB::rows(
            'select
                o.id order_id,
                o.paid_at,
                round(
                    sum(
                        greatest(
                            0,
                            (
                                oi.total_price
                                - coalesce(
                                    oi.prize_credit_applied,
                                    0
                                )
                            )
                        )
                    )*100
                ) qualifying_cents
             from orders o
             join order_items oi
               on oi.order_id=o.id
             left join prize_wheel_spins ws
               on ws.qualifying_order_id=o.id
              and ws.campaign_key=?
              and ws.storefront_designer_id=?
             where o.user_id=?
               and oi.designer_id=?
               and o.payment_status="paid"
               and coalesce(
                    o.paid_at,
                    o.finalized_at,
                    o.created_at
               )>=?
               and ws.id is null
             group by
                o.id,
                o.paid_at
             having qualifying_cents>=?
             order by o.id',
            [
                $campaign,
                $storefrontDesignerId,
                $userId,
                $storefrontDesignerId,
                $startedAt,
                $minimum,
            ]
        );
    }

    public function remainingSpins(
        int $storefrontDesignerId,
        int $userId
    ): int {
        return count(
            $this->eligibleOrders(
                $storefrontDesignerId,
                $userId
            )
        );
    }

    public function publicState(
        array $designer,
        ?int $userId
    ): array {
        $designerId=(int)$designer['id'];

        return [
            'visible'=>$this->storefrontVisible($designer),

            'available_prizes'=>
                $this->availableWheelPrizeCount(
                    $designerId
                ),

            'spins_remaining'=>$userId
                ? $this->remainingSpins(
                    $designerId,
                    $userId
                )
                : null,

            'minimum_purchase_cents'=>
                $this->qualifyingMinimumCents(),

            'campaign_key'=>
                (string)(
                    $this->settings()['campaign_key']
                    ??''
                ),
        ];
    }

    private function inventory(
        int $designerId
    ): array {
        return DB::rows(
            'select
                d.id,
                d.designer_id,
                d.title,
                d.description,
                d.preview_image_path,
                d.quantity_remaining,
                d.prize_type,
                d.approximate_retail_value,
                d.fulfillment_instructions,
                d.restrictions,
                s.display_name seller_name
             from prize_donations d
             join designers s
               on s.id=d.designer_id
             where d.designer_id=?
               and d.status="available"
               and d.quantity_remaining>0
               and d.seller_authorized=1
               and find_in_set(
                    "prize_wheel",
                    d.usage_designations
               )>0
             order by d.id',
            [$designerId]
        );
    }

    private function choosePrize(array $inventory): array
    {
        $total=0;

        foreach($inventory as $prize){
            $total+=max(
                0,
                (int)$prize['quantity_remaining']
            );
        }

        if($total<1){
            throw new DomainException(
                'This seller currently has no Prize Wheel prizes remaining.'
            );
        }

        $ticket=random_int(1,$total);

        foreach($inventory as $prize){
            $ticket-=max(
                0,
                (int)$prize['quantity_remaining']
            );

            if($ticket<=0){
                return $prize;
            }
        }

        throw new DomainException(
            'Prize Wheel inventory could not be selected.'
        );
    }

    private function resultByAssignment(
        int $assignmentId,
        int $spinId=0
    ): array {
        $row=DB::row(
            'select
                a.id assignment_id,
                a.status assignment_status,
                d.id prize_donation_id,
                d.designer_id,
                d.title,
                d.description,
                d.preview_image_path,
                d.prize_type,
                d.approximate_retail_value,
                d.fulfillment_instructions,
                d.restrictions,
                d.prize_file_original_name,
                s.display_name seller_name,
                ws.id spin_id,
                ws.storefront_designer_id,
                ws.qualifying_order_id
             from prize_assignments a
             join prize_donations d
               on d.id=a.prize_donation_id
             join designers s
               on s.id=d.designer_id
             left join prize_wheel_spins ws
               on ws.prize_assignment_id=a.id
             where a.id=?
             limit 1',
            [$assignmentId]
        );

        if(!$row){
            throw new DomainException(
                'Prize Wheel result was not found.'
            );
        }

        if($spinId>0){
            $row['spin_id']=$spinId;
        }

        return $row;
    }

    public function spin(
        int $storefrontDesignerId,
        int $userId
    ): array {
        $settings=$this->settings();

        if(empty($settings['is_enabled'])){
            throw new DomainException(
                'The Prize Wheel promotion is not currently active.'
            );
        }

        $store=DB::row(
            'select
                id,
                user_id,
                display_name,
                store_slug,
                status,
                prize_wheel_opt_in
             from designers
             where id=?
             limit 1',
            [$storefrontDesignerId]
        );

        if(
            !$store
            || !$this->storefrontVisible($store)
        ){
            throw new DomainException(
                'The Prize Wheel is not available on this storefront.'
            );
        }

        $user=DB::row(
            'select id,name,email,status
             from users
             where id=?
             limit 1',
            [$userId]
        );

        if(
            !$user
            || ($user['status']??'')!=='active'
        ){
            throw new DomainException(
                'An active Creative Moth account is required to spin.'
            );
        }

        $adminId=(int)($settings['updated_by']??0);

        if($adminId<1){
            throw new DomainException(
                'The Prize Wheel must be activated by an administrator before spins can be awarded.'
            );
        }

        $campaign=(string)(
            $settings['campaign_key']
            ??'grand-opening-2026'
        );

        $lockName=
            'creative_moth_wheel_'.
            substr(
                hash(
                    'sha256',
                    $campaign.
                    ':'.
                    $storefrontDesignerId.
                    ':'.
                    $userId
                ),
                0,
                40
            );

        $lock=DB::row(
            'select get_lock(?,5) acquired',
            [$lockName]
        );

        if((int)($lock['acquired']??0)!==1){
            throw new DomainException(
                'Your Prize Wheel spin is already being processed.'
            );
        }

        try{
            $eligible=$this->eligibleOrders(
                $storefrontDesignerId,
                $userId
            );

            if(!$eligible){
                $minimum=
                    $this->qualifyingMinimumCents()/100;

                throw new DomainException(
                    'Spend at least $'.
                    number_format($minimum,2).
                    ' at this store during the promotion to unlock a spin.'
                );
            }

            $orderId=(int)$eligible[0]['order_id'];

            $spinNumber=(int)(
                DB::row(
                    'select count(*) used
                     from prize_wheel_spins
                     where campaign_key=?
                       and user_id=?',
                    [$campaign,$userId]
                )['used']??0
            )+1;

            $idempotency=
                'prize-wheel:'.
                $campaign.
                ':store:'.
                $storefrontDesignerId.
                ':order:'.
                $orderId;

            $existingAssignment=DB::row(
                'select id
                 from prize_assignments
                 where idempotency_key=?
                 limit 1',
                [$idempotency]
            );

            if($existingAssignment){
                $assignmentId=(int)$existingAssignment['id'];

                $spin=DB::row(
                    'select id
                     from prize_wheel_spins
                     where campaign_key=?
                       and qualifying_order_id=?
                       and storefront_designer_id=?',
                    [
                        $campaign,
                        $orderId,
                        $storefrontDesignerId,
                    ]
                );

                return $this->resultByAssignment(
                    $assignmentId,
                    (int)($spin['id']??0)
                );
            }

            $donationService=
                new PrizeDonationService();

            $assignmentId=0;

            for($attempt=1;$attempt<=8;$attempt++){
                $inventory=$this->inventory(
                    $storefrontDesignerId
                );

                if(!$inventory){
                    throw new DomainException(
                        'This seller currently has no Prize Wheel prizes remaining.'
                    );
                }

                $selected=
                    $this->choosePrize($inventory);

                if(
                    (int)$selected['designer_id']
                    !==$storefrontDesignerId
                ){
                    throw new DomainException(
                        'Prize Wheel seller inventory mismatch.'
                    );
                }

                try{
                    $assignmentId=
                        $donationService->assign(
                            (int)$selected['id'],
                            1,
                            'prize_wheel',
                            'wheel:'.
                                $campaign.
                                ':store:'.
                                $storefrontDesignerId.
                                ':order:'.
                                $orderId,
                            $adminId,
                            $idempotency
                        );

                    break;
                }catch(DomainException $error){
                    $message=$error->getMessage();

                    if(
                        str_contains(
                            $message,
                            'available prize'
                        )
                        || str_contains(
                            $message,
                            'inventory remains'
                        )
                    ){
                        continue;
                    }

                    throw $error;
                }
            }

            if($assignmentId<1){
                throw new DomainException(
                    'Prize inventory changed during the spin. Please try again.'
                );
            }

            $donationService->award(
                $assignmentId,
                $adminId,
                $userId,
                (string)$user['name'],
                (string)$user['email']
            );

            $result=
                $this->resultByAssignment(
                    $assignmentId
                );

            if(
                (int)$result['designer_id']
                !==$storefrontDesignerId
            ){
                throw new DomainException(
                    'Prize Wheel awarded a prize from the wrong seller.'
                );
            }

            DB::exec(
                'insert into prize_wheel_spins
                 (
                    campaign_key,
                    user_id,
                    storefront_designer_id,
                    qualifying_order_id,
                    prize_donation_id,
                    prize_assignment_id,
                    spin_number,
                    result_title,
                    result_preview_path
                 )
                 values (?,?,?,?,?,?,?,?,?)',
                [
                    $campaign,
                    $userId,
                    $storefrontDesignerId,
                    $orderId,
                    (int)$result[
                        'prize_donation_id'
                    ],
                    $assignmentId,
                    $spinNumber,
                    (string)$result['title'],
                    $result[
                        'preview_image_path'
                    ]?:null,
                ]
            );

            $spinId=(int)DB::id();
            $result['spin_id']=$spinId;
            $result['qualifying_order_id']=$orderId;

            try{
                NotificationService::create(
                    $userId,
                    'prize_wheel_win',
                    'buyer',
                    'You won a Prize Wheel prize!',
                    'You won "'.
                        mb_substr(
                            strip_tags(
                                (string)$result['title']
                            ),
                            0,
                            140
                        ).
                        '" from '.
                        $store['display_name'].
                        '.',
                    'prize-wheel-win:'.$spinId,
                    '/store/'.
                        rawurlencode(
                            (string)$store['store_slug']
                        ).
                        '#creative-moth-prize-wheel'
                );
            }catch(Throwable $error){
                NotificationService::reportFailure(
                    'Prize Wheel winner notification',
                    $error
                );
            }

            return $result;
        }finally{
            DB::row(
                'select release_lock(?) released',
                [$lockName]
            );
        }
    }

    public function unlockedStoresForOrder(
        int $orderId,
        int $userId
    ): array {
        $settings=$this->settings();

        if(empty($settings['is_enabled'])){
            return [];
        }

        $order=DB::row(
            'select
                id,
                user_id,
                payment_status,
                paid_at,
                finalized_at,
                created_at
             from orders
             where id=?
               and user_id=?
             limit 1',
            [$orderId,$userId]
        );

        if(
            !$order
            || ($order['payment_status']??'')!=='paid'
        ){
            return [];
        }

        $campaign=(string)(
            $settings['campaign_key']
            ??''
        );

        $startedAt=
            $settings['campaign_started_at']
            ?: $settings['enabled_at']
            ?: '1970-01-01 00:00:00';

        $paidAt=
            $order['paid_at']
            ?: $order['finalized_at']
            ?: $order['created_at'];

        if(
            strtotime((string)$paidAt)
            < strtotime((string)$startedAt)
        ){
            return [];
        }

        $minimum=$this->qualifyingMinimumCents();

        return DB::rows(
            'select
                d.id designer_id,
                d.display_name,
                d.store_slug,
                round(
                    sum(
                        greatest(
                            0,
                            oi.total_price
                            - coalesce(
                                oi.prize_credit_applied,
                                0
                            )
                        )
                    )*100
                ) qualifying_cents,
                coalesce(
                    (
                        select sum(pd.quantity_remaining)
                        from prize_donations pd
                        where pd.designer_id=d.id
                          and pd.status="available"
                          and pd.quantity_remaining>0
                          and pd.seller_authorized=1
                          and find_in_set(
                            "prize_wheel",
                            pd.usage_designations
                          )>0
                    ),
                    0
                ) available_prize_units,
                (
                    select count(*)
                    from prize_wheel_spins ws
                    where ws.campaign_key=?
                      and ws.qualifying_order_id=?
                      and ws.storefront_designer_id=d.id
                ) spin_used
             from order_items oi
             join designers d
               on d.id=oi.designer_id
             where oi.order_id=?
               and d.status="approved"
               and d.prize_wheel_opt_in=1
             group by
                d.id,
                d.display_name,
                d.store_slug
             having qualifying_cents>=?
                and available_prize_units>0
                and spin_used=0
             order by d.display_name,d.id',
            [
                $campaign,
                $orderId,
                $orderId,
                $minimum,
            ]
        );
    }

    public function recentSpins(
        int $limit=50
    ): array {
        $limit=max(1,min(200,$limit));

        return DB::rows(
            'select
                ws.*,
                u.name winner_name,
                u.email winner_email,
                d.display_name storefront_name,
                d.store_slug,
                pd.title prize_title,
                pd.prize_type,
                pd.approximate_retail_value,
                pa.status assignment_status
             from prize_wheel_spins ws
             join users u
               on u.id=ws.user_id
             join designers d
               on d.id=ws.storefront_designer_id
             join prize_donations pd
               on pd.id=ws.prize_donation_id
             join prize_assignments pa
               on pa.id=ws.prize_assignment_id
             order by ws.id desc
             limit '.$limit
        );
    }

    public function winnerFile(
        int $spinId,
        int $userId
    ): array {
        $row=DB::row(
            'select
                ws.id spin_id,
                ws.user_id,
                ws.prize_assignment_id,
                pa.status assignment_status,
                pa.winner_user_id,
                pd.prize_file_storage_path,
                pd.prize_file_original_name,
                pd.prize_file_mime_type,
                pd.prize_file_size
             from prize_wheel_spins ws
             join prize_assignments pa
               on pa.id=ws.prize_assignment_id
             join prize_donations pd
               on pd.id=ws.prize_donation_id
             where ws.id=?
               and ws.user_id=?
               and pa.winner_user_id=?
               and pa.status in (
                    "awarded",
                    "fulfilled"
               )
             limit 1',
            [$spinId,$userId,$userId]
        );

        if(!$row){
            throw new DomainException(
                'Prize file is not available for this account.'
            );
        }

        return $row;
    }
}
