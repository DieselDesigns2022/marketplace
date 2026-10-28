<?php
namespace App\Services;
use App\Core\Database as DB;

final class GrandOpeningDashboardService
{
    /** Read-only summaries. Every section uses its existing admin permission. */
    public function summary(callable $can): array
    {
        $cards=[];$warnings=[];$event=(new GrandOpeningSaleService())->event();
        $count=static fn(string $sql,array $args=[]):int=>(int)(DB::row($sql,$args)['n']??0);
        if ($can('homepage.view')) {
            $n=$count('select count(*) n from grand_opening_sales s join designers d on d.id=s.designer_id where s.event_id=1 and s.is_participating=1 and d.status="approved"');
            $cards[]=['title'=>'Grand Opening Sale','url'=>'/admin/grand-opening#saved-event','values'=>['Status'=>ucfirst(GrandOpeningSaleService::state($event)),'Approved participating shops'=>$n]];
            if (!$n) $warnings[]='No approved shops are participating in the sale yet.';
        }
        if ($can('prizes.view')) {
            $wheel=DB::row('select * from prize_wheel_settings where id=1')??[];
            $inventory=$count('select count(*) n from prize_wheel_prizes where is_active=1');
            $cards[]=['title'=>'Purchase Prize Wheel','url'=>'/admin/prize-wheel','values'=>['Status'=>ucfirst(PrizeWheelService::promotionState($wheel)),'Approved opted-in shops'=>$count('select count(*) n from designers where status="approved" and prize_wheel_opt_in=1'),'Active prize choices'=>$inventory,'Spins in current campaign'=>$count('select count(*) n from prize_wheel_spins where campaign_key=?',[$wheel['campaign_key']??''])]];
            if (!$inventory) $warnings[]='The purchase wheel has no active prize choices.';
            $donations=DB::row('select coalesce(sum(quantity_remaining),0) available from prize_donations where status="available" and seller_authorized=1 and find_in_set("giveaway",usage_designations)>0');
            $cards[]=['title'=>'Donated Prizes','url'=>'/admin/prize-donations','values'=>['Giveaway prizes remaining'=>(int)$donations['available'],'Awaiting approval'=>$count('select count(*) n from prize_donations where status="submitted"'),'Awarded, awaiting fulfillment'=>$count('select count(*) n from prize_assignments where status="awarded"')]];
            if (!(int)$donations['available']) $warnings[]='No approved donation-pool prizes are available for the final giveaway.';
            $live=DB::row('select is_live from final_giveaway_settings where id=1');
            $cards[]=['title'=>'Final Giveaway','url'=>'/admin/final-giveaway','values'=>['Public viewing'=>!empty($live['is_live'])?'Live':'Hidden until Go live','Prizes drawn'=>$count('select count(*) n from final_giveaway_draws'),'Waiting for Remove entry'=>$count('select count(*) n from final_giveaway_draws where removed_at is null')]];
            $failed=$count('select count(*) n from final_giveaway_draws d join email_messages e on e.deduplication_key=concat("final-giveaway:",d.id,":email") where e.status="failed"');
            if($failed) $warnings[]=$failed.' final giveaway prize emails failed. Review delivery history.';
            $cards[]=['title'=>'Grand Opening Passport','url'=>'/admin/final-giveaway','values'=>['People with stamps'=>$count('select count(distinct user_id) n from grand_opening_passport_stamps'),'Stamps earned'=>$count('select count(*) n from grand_opening_passport_stamps'),'Passport giveaway entries'=>$count('select count(*) n from grand_opening_giveaway_entries where campaign_key=? and source_type="passport"',[$event['campaign_key']])]];
            foreach(DB::rows('select id,title,status,current_round,signup_closes_at,first_round_opens_at from battle_events order by id desc limit 10') as $battle) {
                $cards[]=['title'=>'Battle: '.$battle['title'],'url'=>'/admin/battles','values'=>['Status'=>ucfirst($battle['status']),'Current round'=>$battle['current_round']??'Not started','Signup closes'=>AdvertisingCreditScheduleService::display($battle['signup_closes_at']),'Voting begins'=>AdvertisingCreditScheduleService::display($battle['first_round_opens_at'])]];
            }
        }
        if ($can('referrals.view')) {
            $settings=DB::row('select * from buyer_referral_giveaway_settings where id=1')??[];
            $values=['Status'=>ucfirst(GrandOpeningSaleService::state($settings))];
            foreach(DB::rows('select status,count(*) n from buyer_referral_giveaway_entries group by status') as $r) $values[ucfirst($r['status']).' entries']=(int)$r['n'];
            $cards[]=['title'=>'Buyer Referral Giveaway','url'=>'/admin/referral-giveaway','values'=>$values];
        }
        if ($can('credits.view')) {
            $cards[]=['title'=>'Prize Credit Participation','url'=>'/admin/credits','values'=>['Approved participating shops'=>$count('select count(*) n from designers where status="approved" and prize_credit_mode<>"none"'),'Credit grants'=>$count('select count(*) n from prize_credit_grants')]];
        }
        if ($can('social_content.view')) $cards[]=['title'=>'Social Media Planner','url'=>'/admin/social-content','values'=>['Spotlights and planned posts'=>'Open planner to review and mark posted content done']];
        return ['cards'=>$cards,'warnings'=>$warnings];
    }
}
