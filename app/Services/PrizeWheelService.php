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
            'select * from prize_wheel_settings where id=1 limit 1'
        ) ?? [
            'id'=>1,
            'is_enabled'=>0,
            'heading'=>'Grand Opening Prize Wheel',
            'description'=>'Spend $5 or more at this participating store to unlock a Prize Wheel spin!',
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
            && $this->availableWheelPrizeCount() > 0;
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

        DB::exec(
            'update prize_wheel_settings
             set is_enabled=?,
                 heading=?,
                 description=?,
                 minimum_purchase_cents=?,
                 updated_by=?,
                 enabled_at=
                    case
                        when ?=1 and is_enabled=0 then now()
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
                        when ?=0 and is_enabled=1 then now()
                        else disabled_at
                    end,
                 updated_at=now()
             where id=1',
            [
                $enabled?1:0,
                mb_substr($heading,0,190),
                $description!==''?mb_substr($description,0,1000):null,
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
            [$campaign,$adminId]
        );

        return $campaign;
    }

    public function participatingStores(): array
    {
        return DB::rows(
            'select d.*
             from designers d
             where d.status="approved"
               and d.prize_wheel_opt_in=1
             order by d.display_name,d.id'
        );
    }

    public function optedInStoreCount(): int
    {
        return count($this->participatingStores());
    }

    public function prizes(bool $includeInactive=true): array
    {
        $where=$includeInactive?'':'where is_active=1';

        return DB::rows(
            'select *
             from prize_wheel_prizes
             '.$where.'
             order by sort_order,id'
        );
    }

    public function availableWheelPrizeCount(?int $designerId=null): int
    {
        $row=DB::row(
            'select count(*) total
             from prize_wheel_prizes
             where is_active=1
               and weight>0'
        );

        return (int)($row['total']??0);
    }

    public function savePrize(
        ?int $id,
        string $title,
        string $type,
        ?string $value,
        int $weight,
        int $sortOrder,
        bool $active,
        int $adminId
    ): int {
        $title=trim($title);

        $allowed=[
            'seller_fixed_coupon',
            'seller_percent_coupon',
            'spin_again',
            'grand_opening_entry',
        ];

        if($title===''){
            throw new InvalidArgumentException(
                'Prize title is required.'
            );
        }

        if(!in_array($type,$allowed,true)){
            throw new InvalidArgumentException(
                'Prize type is invalid.'
            );
        }

        $amount=null;

        if(in_array(
            $type,
            ['seller_fixed_coupon','seller_percent_coupon'],
            true
        )){
            $raw=trim((string)$value);

            if(
                !preg_match(
                    '/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/',
                    $raw
                )
            ){
                throw new InvalidArgumentException(
                    'Enter a valid prize value.'
                );
            }

            $amount=(float)$raw;

            if($amount<=0){
                throw new InvalidArgumentException(
                    'Prize value must be greater than zero.'
                );
            }

            if(
                $type==='seller_percent_coupon'
                && $amount>100
            ){
                throw new InvalidArgumentException(
                    'Percentage prizes cannot exceed 100%.'
                );
            }
        }

        $weight=max(1,min(100000,$weight));
        $sortOrder=max(-100000,min(100000,$sortOrder));

        if($id){
            DB::exec(
                'update prize_wheel_prizes
                 set title=?,
                     prize_type=?,
                     prize_value=?,
                     weight=?,
                     sort_order=?,
                     is_active=?,
                     updated_at=now()
                 where id=?',
                [
                    mb_substr($title,0,190),
                    $type,
                    $amount,
                    $weight,
                    $sortOrder,
                    $active?1:0,
                    $id,
                ]
            );

            return $id;
        }

        DB::exec(
            'insert into prize_wheel_prizes
             (
                title,
                prize_type,
                prize_value,
                weight,
                sort_order,
                is_active,
                created_by
             )
             values (?,?,?,?,?,?,?)',
            [
                mb_substr($title,0,190),
                $type,
                $amount,
                $weight,
                $sortOrder,
                $active?1:0,
                $adminId,
            ]
        );

        return (int)DB::id();
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
                    greatest(
                        0,
                        (
                            coalesce(o.stripe_paid_amount,0)
                            - coalesce(o.tax_amount,0)
                        ) * 100
                    )
                    *
                    (
                        sum(
                            greatest(
                                0,
                                oi.total_price
                                - coalesce(
                                    oi.prize_credit_applied,
                                    0
                                )
                            )
                        )
                        /
                        nullif(
                            (
                                select sum(
                                    greatest(
                                        0,
                                        all_items.total_price
                                        - coalesce(
                                            all_items.prize_credit_applied,
                                            0
                                        )
                                    )
                                )
                                from order_items all_items
                                where all_items.order_id=o.id
                            ),
                            0
                        )
                    )
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
               and coalesce(o.stripe_paid_amount,0)>0
               and coalesce(
                    o.paid_at,
                    o.finalized_at,
                    o.created_at
               )>=?
               and ws.id is null
             group by
                o.id,
                o.paid_at,
                o.stripe_paid_amount,
                o.tax_amount
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

    private function availableBonusSpin(
        string $campaign,
        int $storefrontDesignerId,
        int $userId
    ): ?array {
        return DB::row(
            'select *
             from prize_wheel_bonus_spins
             where campaign_key=?
               and storefront_designer_id=?
               and user_id=?
               and status="available"
             order by id
             limit 1',
            [
                $campaign,
                $storefrontDesignerId,
                $userId,
            ]
        );
    }

    public function remainingSpins(
        int $storefrontDesignerId,
        int $userId
    ): int {
        $settings=$this->settings();
        $campaign=(string)($settings['campaign_key']??'');

        $bonus=(int)(
            DB::row(
                'select count(*) c
                 from prize_wheel_bonus_spins
                 where campaign_key=?
                   and storefront_designer_id=?
                   and user_id=?
                   and status="available"',
                [
                    $campaign,
                    $storefrontDesignerId,
                    $userId,
                ]
            )['c']??0
        );

        return
            count(
                $this->eligibleOrders(
                    $storefrontDesignerId,
                    $userId
                )
            )
            +$bonus;
    }

    public function publicState(
        array $designer,
        ?int $userId
    ): array {
        $designerId=(int)$designer['id'];

        return [
            'visible'=>$this->storefrontVisible($designer),
            'available_prizes'=>$this->availableWheelPrizeCount(),
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

    private function choosePrize(): array
    {
        $inventory=$this->prizes(false);

        if(!$inventory){
            throw new DomainException(
                'The Prize Wheel currently has no active prizes.'
            );
        }

        $total=0;

        foreach($inventory as $prize){
            $total+=max(0,(int)$prize['weight']);
        }

        if($total<1){
            throw new DomainException(
                'The Prize Wheel prize weights are invalid.'
            );
        }

        $ticket=random_int(1,$total);

        foreach($inventory as $prize){
            $ticket-=max(0,(int)$prize['weight']);

            if($ticket<=0){
                return $prize;
            }
        }

        throw new DomainException(
            'Prize Wheel selection failed.'
        );
    }

    private function uniqueCouponCode(
        string $campaign,
        int $spinId
    ): string {
        for($attempt=0;$attempt<10;$attempt++){
            $code=
                'WHEEL-'.
                strtoupper(
                    substr(
                        preg_replace(
                            '/[^A-Z0-9]/',
                            '',
                            strtoupper($campaign)
                        ),
                        -8
                    )
                ).
                '-'.
                $spinId.
                '-'.
                strtoupper(bin2hex(random_bytes(3)));

            if(
                !DB::row(
                    'select id from coupons where code=? limit 1',
                    [$code]
                )
            ){
                return $code;
            }
        }

        throw new DomainException(
            'Prize coupon could not be generated.'
        );
    }

    private function fulfillReward(
        int $spinId,
        array $prize,
        int $userId,
        int $storefrontDesignerId,
        string $campaign,
        int $adminId
    ): array {
        $type=(string)$prize['prize_type'];
        $couponId=null;
        $message='';

        if(in_array(
            $type,
            ['seller_fixed_coupon','seller_percent_coupon'],
            true
        )){
            $code=$this->uniqueCouponCode(
                $campaign,
                $spinId
            );

            $discountType=
                $type==='seller_fixed_coupon'
                    ? 'fixed'
                    : 'percent';

            DB::exec(
                'insert into coupons
                 (
                    code,
                    scope,
                    seller_id,
                    discount_type,
                    discount_value,
                    starts_at,
                    ends_at,
                    is_active,
                    min_cart_amount,
                    usage_limit,
                    per_user_limit,
                    created_by
                 )
                 values (
                    ?,
                    "seller",
                    ?,
                    ?,
                    ?,
                    curdate(),
                    null,
                    1,
                    0.00,
                    1,
                    1,
                    ?
                 )',
                [
                    $code,
                    $storefrontDesignerId,
                    $discountType,
                    $prize['prize_value'],
                    $adminId,
                ]
            );

            $couponId=(int)DB::id();

            $message=
                'Your seller-specific prize code is '.
                $code.
                '. It may be used one time by your account at this store.';
        }

        DB::exec(
            'insert into prize_wheel_rewards
             (
                spin_id,
                wheel_prize_id,
                user_id,
                storefront_designer_id,
                reward_type,
                coupon_id,
                reward_value,
                fulfillment_status
             )
             values (?,?,?,?,?,?,?,"awarded")',
            [
                $spinId,
                $prize['id'],
                $userId,
                $storefrontDesignerId,
                $type,
                $couponId,
                $prize['prize_value'],
            ]
        );

        $rewardId=(int)DB::id();

        if($type==='spin_again'){
            DB::exec(
                'insert into prize_wheel_bonus_spins
                 (
                    campaign_key,
                    user_id,
                    storefront_designer_id,
                    source_spin_id,
                    status
                 )
                 values (?,?,?,?,"available")',
                [
                    $campaign,
                    $userId,
                    $storefrontDesignerId,
                    $spinId,
                ]
            );

            $message=
                'You earned another spin on this same seller\'s Prize Wheel.';
        }

        if($type==='grand_opening_entry'){
            DB::exec(
                'insert ignore into grand_opening_giveaway_entries
                 (
                    campaign_key,
                    user_id,
                    storefront_designer_id,
                    source_type,
                    source_spin_id
                 )
                 values (?,?,?,"prize_wheel",?)',
                [
                    $campaign,
                    $userId,
                    $storefrontDesignerId,
                    $spinId,
                ]
            );

            $message=
                'You received one entry into the main Grand Opening Giveaway.';
        }

        DB::exec(
            'update prize_wheel_spins
             set reward_id=?
             where id=?',
            [$rewardId,$spinId]
        );

        return [
            'reward_id'=>$rewardId,
            'coupon_id'=>$couponId,
            'coupon_code'=>$couponId?$code:null,
            'reward_message'=>$message,
        ];
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
            DB::begin();

            $eligible=$this->eligibleOrders(
                $storefrontDesignerId,
                $userId
            );

            $bonus=$this->availableBonusSpin(
                $campaign,
                $storefrontDesignerId,
                $userId
            );

            if(!$eligible && !$bonus){
                DB::rollBack();

                $minimum=
                    $this->qualifyingMinimumCents()/100;

                throw new DomainException(
                    'Spend at least $'.
                    number_format($minimum,2).
                    ' at this store after all coupons, credits, and discounts, excluding sales tax, to unlock a spin.'
                );
            }

            $usingBonus=
                !$eligible
                && !empty($bonus);

            $orderId=
                $eligible
                    ? (int)$eligible[0]['order_id']
                    : null;

            $spinNumber=(int)(
                DB::row(
                    'select count(*) used
                     from prize_wheel_spins
                     where campaign_key=?
                       and user_id=?',
                    [$campaign,$userId]
                )['used']??0
            )+1;

            $prize=$this->choosePrize();

            DB::exec(
                'insert into prize_wheel_spins
                 (
                    campaign_key,
                    user_id,
                    storefront_designer_id,
                    qualifying_order_id,
                    wheel_prize_id,
                    prize_donation_id,
                    prize_assignment_id,
                    spin_number,
                    result_title,
                    result_preview_path
                 )
                 values (?,?,?,?,?,null,null,?,?,null)',
                [
                    $campaign,
                    $userId,
                    $storefrontDesignerId,
                    $orderId,
                    $prize['id'],
                    $spinNumber,
                    $prize['title'],
                ]
            );

            $spinId=(int)DB::id();

            if($usingBonus){
                DB::exec(
                    'update prize_wheel_bonus_spins
                     set status="consumed",
                         consumed_spin_id=?,
                         consumed_at=now()
                     where id=?
                       and status="available"',
                    [
                        $spinId,
                        $bonus['id'],
                    ]
                );
            }

            $reward=$this->fulfillReward(
                $spinId,
                $prize,
                $userId,
                $storefrontDesignerId,
                $campaign,
                $adminId
            );

            DB::commit();

            $result=[
                'spin_id'=>$spinId,
                'qualifying_order_id'=>$orderId,
                'title'=>$prize['title'],
                'prize_type'=>$prize['prize_type'],
                'prize_value'=>$prize['prize_value'],
                'storefront_designer_id'=>$storefrontDesignerId,
                'seller_name'=>$store['display_name'],
                'coupon_code'=>$reward['coupon_code'],
                'reward_message'=>$reward['reward_message'],
            ];

            try{
                $body=
                    'You won "'.
                    mb_substr(
                        strip_tags((string)$prize['title']),
                        0,
                        140
                    ).
                    '" from '.
                    $store['display_name'].
                    '.';

                if($reward['reward_message']!==''){
                    $body.=' '.$reward['reward_message'];
                }

                NotificationService::create(
                    $userId,
                    'prize_wheel_win',
                    'buyer',
                    'You won a Prize Wheel prize!',
                    $body,
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
        }catch(Throwable $error){
            if(DB::pdo()->inTransaction()){
                DB::rollBack();
            }

            throw $error;
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
                created_at,
                stripe_paid_amount,
                tax_amount
             from orders
             where id=?
               and user_id=?
             limit 1',
            [$orderId,$userId]
        );

        if(
            !$order
            || ($order['payment_status']??'')!=='paid'
            || (float)($order['stripe_paid_amount']??0)<=0
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
                    greatest(
                        0,
                        (
                            coalesce(o.stripe_paid_amount,0)
                            - coalesce(o.tax_amount,0)
                        ) * 100
                    )
                    *
                    (
                        sum(
                            greatest(
                                0,
                                oi.total_price
                                - coalesce(
                                    oi.prize_credit_applied,
                                    0
                                )
                            )
                        )
                        /
                        nullif(
                            (
                                select sum(
                                    greatest(
                                        0,
                                        all_items.total_price
                                        - coalesce(
                                            all_items.prize_credit_applied,
                                            0
                                        )
                                    )
                                )
                                from order_items all_items
                                where all_items.order_id=o.id
                            ),
                            0
                        )
                    )
                ) qualifying_cents,
                (
                    select count(*)
                    from prize_wheel_spins ws
                    where ws.campaign_key=?
                      and ws.qualifying_order_id=?
                      and ws.storefront_designer_id=d.id
                ) spin_used
             from orders o
             join order_items oi
               on oi.order_id=o.id
             join designers d
               on d.id=oi.designer_id
             where o.id=?
               and o.user_id=?
               and o.payment_status="paid"
               and coalesce(o.stripe_paid_amount,0)>0
               and d.status="approved"
               and d.prize_wheel_opt_in=1
             group by
                d.id,
                d.display_name,
                d.store_slug,
                o.id,
                o.stripe_paid_amount,
                o.tax_amount
             having qualifying_cents>=?
                and spin_used=0
             order by d.display_name,d.id',
            [
                $campaign,
                $orderId,
                $orderId,
                $userId,
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
                wp.title prize_title,
                wp.prize_type,
                wp.prize_value,
                r.coupon_id,
                c.code coupon_code
             from prize_wheel_spins ws
             join users u
               on u.id=ws.user_id
             join designers d
               on d.id=ws.storefront_designer_id
             left join prize_wheel_prizes wp
               on wp.id=ws.wheel_prize_id
             left join prize_wheel_rewards r
               on r.id=ws.reward_id
             left join coupons c
               on c.id=r.coupon_id
             order by ws.id desc
             limit '.$limit
        );
    }


}
