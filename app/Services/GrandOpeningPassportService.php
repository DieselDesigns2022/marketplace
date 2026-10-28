<?php

namespace App\Services;

use App\Core\Database as DB;

final class GrandOpeningPassportService
{
    public const TOTAL_STAMPS = 8;

    private const STAMPS = [
        'account' => [
            'title' => 'Create Your Account',
            'description' => 'Have an active Creative Moth account.',
        ],
        'follow_3_stores' => [
            'title' => 'Follow 3 Stores',
            'description' => 'Follow at least three Creative Moth sellers.',
        ],
        'wishlist_item' => [
            'title' => 'Wishlist an Item',
            'description' => 'Save at least one product to your wishlist.',
        ],
        'battle_vote' => [
            'title' => 'Vote in Battle of the Designers',
            'description' => 'Cast a vote in a Battle of the Designers matchup.',
        ],
        'purchase' => [
            'title' => 'Make a Purchase',
            'description' => 'Complete a purchase on Creative Moth.',
        ],
        'refer_friend' => [
            'title' => 'Refer a Friend',
            'description' => 'Have a friend join Creative Moth using your referral.',
        ],
        'visit_5_stores' => [
            'title' => 'Visit 5 Stores',
            'description' => 'Explore five different Creative Moth seller stores.',
        ],
        'leave_review' => [
            'title' => 'Leave a Review',
            'description' => 'Leave a review for an eligible Creative Moth purchase.',
        ],
    ];

    public function recordStoreVisit(
        int $userId,
        int $designerId
    ): void {
        if($userId < 1 || $designerId < 1){
            return;
        }

        $designer=DB::row(
            'select id,user_id
             from designers
             where id=?
               and status="approved"',
            [$designerId]
        );

        if(
            !$designer ||
            (int)$designer['user_id']===$userId
        ){
            return;
        }

        DB::exec(
            'insert ignore into grand_opening_store_visits
             (user_id,designer_id)
             values (?,?)',
            [$userId,$designerId]
        );

        $this->sync($userId);
    }

    public function progress(int $userId): array
    {
        $this->sync($userId);

        $completedRows=DB::rows(
            'select stamp_key,completed_at
             from grand_opening_passport_stamps
             where user_id=?',
            [$userId]
        );

        $completed=[];

        foreach($completedRows as $row){
            $completed[(string)$row['stamp_key']]=
                $row['completed_at'];
        }

        $visitCount=(int)(
            DB::row(
                'select count(*) c
                 from grand_opening_store_visits
                 where user_id=?',
                [$userId]
            )['c']??0
        );

        $followCount=(int)(
            DB::row(
                'select count(*) c
                 from follows
                 where user_id=?',
                [$userId]
            )['c']??0
        );

        $stamps=[];

        foreach(self::STAMPS as $key=>$definition){
            $stamps[]=[
                'key'=>$key,
                'title'=>$definition['title'],
                'description'=>$definition['description'],
                'complete'=>array_key_exists($key,$completed),
                'completed_at'=>$completed[$key]??null,
            ];
        }

        $completeCount=count($completed);

        $entry=DB::row(
            'select id,created_at
             from grand_opening_giveaway_entries
             where user_id=?
               and source_type="passport"
               and source_key="passport-complete"
             order by id
             limit 1',
            [$userId]
        );

        return [
            'stamps'=>$stamps,
            'completed'=>$completeCount,
            'total'=>self::TOTAL_STAMPS,
            'percent'=>(int)round(
                ($completeCount/self::TOTAL_STAMPS)*100
            ),
            'follow_count'=>$followCount,
            'visit_count'=>$visitCount,
            'complete'=>
                $completeCount>=self::TOTAL_STAMPS,
            'giveaway_entry'=>$entry,
        ];
    }

    private function sync(int $userId): void
    {
        if($userId < 1){
            return;
        }

        $user=DB::row(
            'select id,status
             from users
             where id=?',
            [$userId]
        );

        if(!$user || $user['status']!=='active'){
            return;
        }

        $this->stamp($userId,'account');

        $followCount=(int)(
            DB::row(
                'select count(*) c
                 from follows
                 where user_id=?',
                [$userId]
            )['c']??0
        );

        if($followCount>=3){
            $this->stamp($userId,'follow_3_stores');
        }

        if(DB::row(
            'select id
             from wishlists
             where user_id=?
             limit 1',
            [$userId]
        )){
            $this->stamp($userId,'wishlist_item');
        }

        if(DB::row(
            'select id
             from battle_votes
             where user_id=?
             limit 1',
            [$userId]
        )){
            $this->stamp($userId,'battle_vote');
        }

        if(DB::row(
            'select id
             from orders
             where user_id=?
               and payment_status in
                   ("paid","partially_refunded")
             limit 1',
            [$userId]
        )){
            $this->stamp($userId,'purchase');
        }

        if(DB::row(
            'select id
             from referrals
             where referrer_user_id=?
               and referred_user_id is not null
             limit 1',
            [$userId]
        )){
            $this->stamp($userId,'refer_friend');
        }

        $visitCount=(int)(
            DB::row(
                'select count(*) c
                 from grand_opening_store_visits
                 where user_id=?',
                [$userId]
            )['c']??0
        );

        if($visitCount>=5){
            $this->stamp($userId,'visit_5_stores');
        }

        if(DB::row(
            'select id
             from seller_reviews
             where buyer_id=?
             limit 1',
            [$userId]
        )){
            $this->stamp($userId,'leave_review');
        }

        $completed=(int)(
            DB::row(
                'select count(*) c
                 from grand_opening_passport_stamps
                 where user_id=?',
                [$userId]
            )['c']??0
        );

        if($completed>=self::TOTAL_STAMPS){
            $this->awardGiveawayEntry($userId);
        }
    }

    private function stamp(
        int $userId,
        string $stampKey
    ): void {
        if(!isset(self::STAMPS[$stampKey])){
            return;
        }

        DB::exec(
            'insert ignore into grand_opening_passport_stamps
             (user_id,stamp_key)
             values (?,?)',
            [$userId,$stampKey]
        );
    }

    private function awardGiveawayEntry(int $userId): void
    {
        // Preserve stamps and progress; award event entries only during the scheduled event.
        $event=(new GrandOpeningSaleService())->event();
        if(GrandOpeningSaleService::state($event)!=='live')return;
        $settings=(new PrizeWheelService())->settings();

        $campaign=trim(
            (string)(
                $settings['campaign_key']
                ??'grand-opening-2026'
            )
        );

        if($campaign===''){
            $campaign='grand-opening-2026';
        }

        DB::exec(
            'insert ignore into grand_opening_giveaway_entries
             (
                campaign_key,
                user_id,
                storefront_designer_id,
                source_type,
                source_spin_id,
                source_key
             )
             values (?,?,null,"passport",null,"passport-complete")',
            [$campaign,$userId]
        );
    }
}
