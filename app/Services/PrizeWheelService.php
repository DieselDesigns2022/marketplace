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
                'Spin the Creative Moth Grand Opening Prize Wheel for a chance to win!',
            'campaign_key'=>'grand-opening-2026',
            'spins_per_user'=>1,
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
            && $this->globallyEnabled();
    }

    public function updateSettings(
        bool $enabled,
        string $heading,
        string $description,
        int $spinsPerUser,
        int $adminId
    ): void {
        $heading=trim($heading);
        $description=trim($description);

        if($heading===''){
            $heading='Grand Opening Prize Wheel';
        }

        if($spinsPerUser<1 || $spinsPerUser>25){
            throw new InvalidArgumentException(
                'Spins per person must be between 1 and 25.'
            );
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
                spins_per_user,
                updated_by,
                enabled_at,
                disabled_at
             )
             values
             (
                1,?,?,?,?,?,?,
                case when ?=0 then now() else null end
             )
             on duplicate key update
                is_enabled=values(is_enabled),
                heading=values(heading),
                description=values(description),
                spins_per_user=values(spins_per_user),
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
                $spinsPerUser,
                $adminId,
                $enabled?date('Y-m-d H:i:s'):null,
                $enabled?1:0,
            ]
        );
    }

    public function startNewRound(int $adminId): string
    {
        $settings=$this->settings();

        if(!empty($settings['is_enabled'])){
            throw new DomainException(
                'Turn the Prize Wheel OFF before starting a new giveaway round.'
            );
        }

        $campaign='wheel-'.date('Ymd').'-'.bin2hex(random_bytes(6));

        DB::exec(
            'update prize_wheel_settings
             set campaign_key=?,
                 updated_by=?,
                 updated_at=now()
             where id=1',
            [$campaign,$adminId]
        );

        return $campaign;
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
               and find_in_set(
                    "prize_wheel",
                    usage_designations
               )>0'
        );

        return (int)($row['total']??0);
    }

    public function remainingSpins(int $userId): int
    {
        $settings=$this->settings();
        $limit=max(1,(int)($settings['spins_per_user']??1));

        $row=DB::row(
            'select count(*) used
             from prize_wheel_spins
             where campaign_key=?
               and user_id=?',
            [
                (string)$settings['campaign_key'],
                $userId,
            ]
        );

        return max(
            0,
            $limit-(int)($row['used']??0)
        );
    }

    public function publicState(
        array $designer,
        ?int $userId
    ): array {
        return [
            'visible'=>$this->storefrontVisible($designer),
            'available_prizes'=>$this->availableWheelPrizeCount(),
            'spins_remaining'=>$userId
                ? $this->remainingSpins($userId)
                : null,
            'spins_per_user'=>
                max(1,(int)($this->settings()['spins_per_user']??1)),
        ];
    }

    private function inventory(): array
    {
        return DB::rows(
            'select
                d.id,
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
             where d.status="available"
               and d.quantity_remaining>0
               and d.seller_authorized=1
               and find_in_set(
                    "prize_wheel",
                    d.usage_designations
               )>0
             order by d.id'
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
                'All Prize Wheel prizes have been claimed.'
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
                ws.storefront_designer_id
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
                'The Prize Wheel is not currently active.'
            );
        }

        $store=DB::row(
            'select id,user_id,display_name,store_slug,status,prize_wheel_opt_in
             from designers
             where id=?
             limit 1',
            [$storefrontDesignerId]
        );

        if(!$store || !$this->storefrontVisible($store)){
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

        if(!$user || ($user['status']??'')!=='active'){
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

        $campaign=(string)($settings['campaign_key']??'grand-opening-2026');
        $limit=max(1,(int)($settings['spins_per_user']??1));

        $lockName=
            'creative_moth_wheel_'.
            substr(hash('sha256',$campaign.':'.$userId),0,40);

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
            $used=(int)(
                DB::row(
                    'select count(*) used
                     from prize_wheel_spins
                     where campaign_key=?
                       and user_id=?',
                    [$campaign,$userId]
                )['used']??0
            );

            if($used>=$limit){
                throw new DomainException(
                    'You have already used all of your Prize Wheel spins for this giveaway.'
                );
            }

            $spinNumber=$used+1;

            $idempotency=
                'prize-wheel:'.
                $campaign.
                ':user:'.
                $userId.
                ':spin:'.
                $spinNumber;

            $existingAssignment=DB::row(
                'select id
                 from prize_assignments
                 where idempotency_key=?
                 limit 1',
                [$idempotency]
            );

            if($existingAssignment){
                $assignmentId=(int)$existingAssignment['id'];

                (new PrizeDonationService())->award(
                    $assignmentId,
                    $adminId,
                    $userId,
                    (string)$user['name'],
                    (string)$user['email']
                );

                DB::exec(
                    'insert ignore into prize_wheel_spins
                     (
                        campaign_key,
                        user_id,
                        storefront_designer_id,
                        prize_donation_id,
                        prize_assignment_id,
                        spin_number,
                        result_title,
                        result_preview_path
                     )
                     select
                        ?,?,?,a.prize_donation_id,a.id,?,
                        d.title,d.preview_image_path
                     from prize_assignments a
                     join prize_donations d
                       on d.id=a.prize_donation_id
                     where a.id=?',
                    [
                        $campaign,
                        $userId,
                        $storefrontDesignerId,
                        $spinNumber,
                        $assignmentId,
                    ]
                );

                $spin=DB::row(
                    'select id
                     from prize_wheel_spins
                     where prize_assignment_id=?',
                    [$assignmentId]
                );

                return $this->resultByAssignment(
                    $assignmentId,
                    (int)($spin['id']??0)
                );
            }

            $donationService=new PrizeDonationService();
            $assignmentId=0;

            for($attempt=1;$attempt<=8;$attempt++){
                $inventory=$this->inventory();

                if(!$inventory){
                    throw new DomainException(
                        'All Prize Wheel prizes have been claimed.'
                    );
                }

                $selected=$this->choosePrize($inventory);

                try{
                    $assignmentId=$donationService->assign(
                        (int)$selected['id'],
                        1,
                        'prize_wheel',
                        'wheel:'.
                            $campaign.
                            ':store:'.
                            $storefrontDesignerId.
                            ':user:'.
                            $userId,
                        $adminId,
                        $idempotency
                    );

                    break;
                }catch(DomainException $error){
                    $message=$error->getMessage();

                    if(
                        str_contains($message,'available prize')
                        || str_contains($message,'inventory remains')
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

            $result=$this->resultByAssignment($assignmentId);

            DB::exec(
                'insert into prize_wheel_spins
                 (
                    campaign_key,
                    user_id,
                    storefront_designer_id,
                    prize_donation_id,
                    prize_assignment_id,
                    spin_number,
                    result_title,
                    result_preview_path
                 )
                 values (?,?,?,?,?,?,?,?)',
                [
                    $campaign,
                    $userId,
                    $storefrontDesignerId,
                    (int)$result['prize_donation_id'],
                    $assignmentId,
                    $spinNumber,
                    (string)$result['title'],
                    $result['preview_image_path']?:null,
                ]
            );

            $spinId=(int)DB::id();
            $result['spin_id']=$spinId;

            try{
                NotificationService::create(
                    $userId,
                    'prize_wheel_win',
                    'buyer',
                    'You won a Prize Wheel prize!',
                    'You won "'.
                        mb_substr(strip_tags((string)$result['title']),0,140).
                        '" on the Creative Moth Prize Wheel.',
                    'prize-wheel-win:'.$spinId,
                    '/store/'.
                        rawurlencode((string)$store['store_slug']).
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

    public function recentSpins(int $limit=50): array
    {
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

    public function winnerFile(int $spinId,int $userId): array
    {
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
               and pa.status in ("awarded","fulfilled")
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
