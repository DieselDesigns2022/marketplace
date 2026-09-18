<?php

namespace App\Services;

use App\Core\Database as DB;
use DomainException;
use PDO;
use Throwable;

class AccountMergeService
{
    public const ADMIN_EMAIL = 'diesel.designs.contact@gmail.com';
    public const SELLER_EMAIL = 'test@dieseldesigns.co';
    public const CANONICAL_EMAIL = 'angela@creativemoth.com';

    private const SIMPLE_OWNERSHIP = [
        'orders' => 'user_id', 'downloads' => 'user_id', 'reviews' => 'user_id',
        'coupon_usages' => 'user_id', 'designer_applications' => 'user_id',
        'email_campaign_recipients' => 'user_id', 'email_digest_content_claims' => 'user_id',
        'custom_orders' => 'buyer_user_id', 'custom_order_files' => 'uploader_user_id',
        'custom_order_status_history' => 'actor_user_id', 'seller_earnings' => 'buyer_id',
        'product_ip_risk_scans' => 'seller_id', 'product_ip_rights_confirmations' => 'seller_id',
    ];

    /** User references which are historical/administrative rather than source-owned identity. */
    private const NON_OWNERSHIP_REFERENCES = [
        'users.merged_into_user_id', 'coupons.created_by', 'seller_payouts.admin_resolved_by',
        'seller_payouts.platform_credit_settled_by', 'creator_badge_history.admin_user_id',
        'credit_transactions.admin_user_id', 'admin_logs.admin_user_id', 'ip_risk_terms.created_by_admin_id',
        'email_campaigns.created_by', 'seller_referral_admin_audits.admin_user_id',
        'message_reports.moderator_user_id', 'admin_access_profiles.granted_by',
        'designers.rank_override_admin_id', 'designers.founder_override_admin_id',
        'ip_risk_terms.updated_by_admin_id', 'product_ip_risk_states.reviewed_by_admin_id',
        'product_ip_risk_review_history.admin_id', 'seller_financial_adjustments.resolved_by',
        'admin_permission_grants.granted_by', 'admin_permission_audits.admin_user_id',
        'admin_permission_audits.acting_admin_user_id', 'account_merge_audits.source_user_id',
        'account_merge_audits.target_user_id', 'account_merge_audits.acting_admin_user_id',
    ];

    public function preflight(): array
    {
        $completed = $this->completedAudit();
        if ($completed) return ['completed' => true, 'audit' => $completed, 'counts' => []];

        $target = $this->userByEmail(self::ADMIN_EMAIL);
        $source = $this->userByEmail(self::SELLER_EMAIL);
        $conflict = $this->userByEmail(self::CANONICAL_EMAIL);
        if (!$target || $target['role'] !== 'admin' || $target['status'] !== 'active') throw new DomainException('Expected active Admin identity was not found.');
        if (!$source || $source['status'] !== 'active' || $source['role'] !== 'designer') throw new DomainException('Expected active Seller identity was not found.');
        if ($conflict) throw new DomainException('The final canonical email is already in use.');
        if ((int)$target['id'] === (int)$source['id']) throw new DomainException('Source and target accounts must be different.');
        $designer = DB::row('select * from designers where user_id=? and status="approved"', [(int)$source['id']]);
        if (!$designer) throw new DomainException('The Seller does not own the expected approved designer record.');
        if (DB::row('select id from designers where user_id=?', [(int)$target['id']])) throw new DomainException('The Admin already owns a designer record; ownership cannot be reconciled safely.');

        $ids = [(int)$source['id'], (int)$target['id']];
        $this->assertMessagingSafe(...$ids);
        $this->assertCreditSafe(...$ids);
        $this->assertReferralsSafe(...$ids);
        $this->assertKnownUserReferences((int)$source['id']);

        $counts = ['designer_id' => (int)$designer['id']];
        foreach (self::SIMPLE_OWNERSHIP as $table => $column) if ($this->tableExists($table)) {
            $counts[$table] = (int)DB::row("select count(*) n from `$table` where `$column`=?", [(int)$source['id']])['n'];
        }
        foreach (['cart_items','wishlists','follows','notifications','message_conversations','conversation_messages','message_blocks','message_reports'] as $table) if ($this->tableExists($table)) {
            $counts[$table] = $this->sourceCount($table, (int)$source['id']);
        }
        return ['completed' => false, 'target' => $target, 'source' => $source, 'designer' => $designer, 'counts' => $counts];
    }

    public function merge(string $newPassword, ?int $actingAdminId = null): array
    {
        if (!AccountSecurityService::validPassword($newPassword)) throw new DomainException('Password does not meet the application password policy.');
        $existing = $this->completedAudit();
        if ($existing) return ['completed' => true, 'audit' => $existing];
        DB::begin();
        try {
            $plan = $this->preflight();
            $sourceId = (int)$plan['source']['id']; $targetId = (int)$plan['target']['id'];
            $actor = $actingAdminId ?? $targetId;
            if ($actor !== $targetId || !DB::row('select id from users where id=? and role="admin" and status="active" for update', [$actor])) {
                throw new DomainException('The merge must be performed by the expected active Admin.');
            }
            DB::row('select id from users where id in (?,?) for update', [$sourceId, $targetId]);
            $reconciliation = [];
            $reconciliation['deduplicated'] = $this->moveDeduplicated($sourceId, $targetId);
            foreach (self::SIMPLE_OWNERSHIP as $table => $column) $this->updateIfExists($table, $column, $sourceId, $targetId);
            $this->mergeEmailPreferences($sourceId, $targetId);
            $this->moveMessaging($sourceId, $targetId);
            $this->moveReferrals($sourceId, $targetId);
            $reconciliation['credit'] = $this->transferCredit($sourceId, $targetId, $actor);

            DB::exec('update designers set user_id=? where id=?', [$targetId, (int)$plan['designer']['id']]);
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            if (!is_string($hash)) throw new DomainException('Password hashing failed.');
            DB::exec('update users set email=?,password_hash=?,role="admin",status="active",merged_into_user_id=null,merged_at=null where id=?', [self::CANONICAL_EMAIL, $hash, $targetId]);
            DB::exec('update users set status="disabled",merged_into_user_id=?,merged_at=now() where id=?', [$targetId, $sourceId]);
            (new AdminPermissionService())->bootstrapCanonicalMergeOwner($targetId, $sourceId);
            DB::exec('insert into account_merge_audits(source_user_id,target_user_id,designer_id,source_seller_email,previous_admin_email,final_canonical_email,acting_admin_user_id,counts_summary,reconciliation_summary,source_disabled,password_reset_confirmed) values(?,?,?,?,?,?,?,?,?,1,1)', [
                $sourceId, $targetId, (int)$plan['designer']['id'], self::SELLER_EMAIL, self::ADMIN_EMAIL,
                self::CANONICAL_EMAIL, $actor, json_encode($plan['counts'], JSON_THROW_ON_ERROR), json_encode($reconciliation, JSON_THROW_ON_ERROR),
            ]);
            $auditId = (int)DB::id();
            DB::commit();
            return ['completed' => true, 'audit_id' => $auditId, 'counts' => $plan['counts'], 'reconciliation' => $reconciliation];
        } catch (Throwable $e) {
            if (DB::pdo()->inTransaction()) DB::rollBack();
            throw $e;
        }
    }

    private function userByEmail(string $email): ?array
    { return DB::row('select * from users where lower(email)=lower(?) limit 1', [$email]); }

    private function completedAudit(): ?array
    {
        if (!$this->tableExists('account_merge_audits')) return null;
        return DB::row('select * from account_merge_audits where source_seller_email=? and previous_admin_email=? and final_canonical_email=? order by id desc limit 1', [self::SELLER_EMAIL, self::ADMIN_EMAIL, self::CANONICAL_EMAIL]);
    }

    private function assertMessagingSafe(int $source, int $target): void
    {
        if (!$this->tableExists('message_conversations')) return;
        if (DB::row('select id from message_conversations where (buyer_user_id=? and seller_user_id=?) or (buyer_user_id=? and seller_user_id=?) limit 1', [$source,$target,$target,$source])) throw new DomainException('Merge would create a self-conversation.');
        $rows = DB::rows('select id,buyer_user_id,seller_user_id,context_key from message_conversations where buyer_user_id=? or seller_user_id=?', [$source,$source]);
        $seen = [];
        foreach ($rows as $row) {
            $key = $this->rewrittenContextKey($row, $source, $target);
            if (isset($seen[$key]) || DB::row('select id from message_conversations where context_key=? and id<>?', [$key,(int)$row['id']])) throw new DomainException('Merge would create a messaging context-key collision.');
            $seen[$key] = true;
        }
        if ($this->tableExists('message_reports') && DB::row('select r.id from message_reports r join message_reports t on t.conversation_id=r.conversation_id and t.reporter_user_id=? where r.reporter_user_id=? limit 1', [$target,$source])) throw new DomainException('Message report identities cannot be reconciled safely.');
        if ($this->tableExists('message_blocks') && DB::row('select id from message_blocks where (blocker_user_id=? and blocked_user_id=?) or (blocker_user_id=? and blocked_user_id=?) limit 1', [$source,$target,$target,$source])) throw new DomainException('Merge would create a self message-block relationship.');
    }

    private function rewrittenContextKey(array $row, int $source, int $target): string
    {
        $buyer = (int)$row['buyer_user_id'] === $source ? $target : (int)$row['buyer_user_id'];
        $seller = (int)$row['seller_user_id'] === $source ? $target : (int)$row['seller_user_id'];
        $parts = explode(':', (string)$row['context_key'], 3);
        if (count($parts) !== 3) throw new DomainException('A messaging context key has an unexpected format.');
        return 'b'.$buyer.':s'.$seller.':'.$parts[2];
    }

    private function assertCreditSafe(int $source, int $target): void
    {
        if (!$this->tableExists('marketplace_credits')) return;
        if ($this->tableExists('credit_transactions')) {
            $debitKey = "phase13.1:account-merge:$source:$target:debit";
            $creditKey = "phase13.1:account-merge:$source:$target:credit";
            if (DB::row('select id from credit_transactions where idempotency_key in (?,?) limit 1', [$debitKey,$creditKey])) {
                throw new DomainException('A partial or previous credit transfer already exists without a completed merge audit.');
            }
        }
        $credit = DB::row('select cast(total_balance as char) total_balance,cast(reserved_balance as char) reserved_balance from marketplace_credits where user_id=?', [$source]);
        if ($credit && CreditService::parseCents((string)$credit['reserved_balance']) !== 0) throw new DomainException('Source marketplace credit is reserved and cannot be transferred safely.');
        if ($credit && CreditService::parseCents((string)$credit['total_balance']) < 0) throw new DomainException('Negative source marketplace credit cannot be transferred safely.');
        if ($this->tableExists('orders') && DB::row('select id from orders where user_id=? and credit_payment_status="reserved" and credit_reserved>0 limit 1', [$source])) throw new DomainException('Source account has an order with reserved credit.');
    }

    private function assertReferralsSafe(int $source, int $target): void
    {
        if (!$this->tableExists('referrals')) return;
        if (DB::row('select id from referrals where (referrer_user_id=? and referred_user_id=?) or (referrer_user_id=? and referred_user_id=?) limit 1', [$source,$target,$target,$source])) throw new DomainException('Merge would create a self-referral.');
        $sourceReferred = DB::row('select id from referrals where referred_user_id=?', [$source]);
        $targetReferred = DB::row('select id from referrals where referred_user_id=?', [$target]);
        if ($sourceReferred && $targetReferred) throw new DomainException('Both accounts have referral attribution; deterministic reconciliation is not possible.');
        if ($this->tableExists('seller_referral_payout_batches') && DB::row('select s.id from seller_referral_payout_batches s join seller_referral_payout_batches t on t.referrer_user_id=? and t.period_start=s.period_start and t.period_end=s.period_end and t.sequence_no=s.sequence_no where s.referrer_user_id=? limit 1', [$target,$source])) throw new DomainException('Seller referral payout batches would collide.');
    }

    private function assertKnownUserReferences(int $source): void
    {
        $known = self::NON_OWNERSHIP_REFERENCES;
        foreach (self::SIMPLE_OWNERSHIP as $table => $column) $known[] = "$table.$column";
        array_push($known, 'designers.user_id','cart_items.user_id','wishlists.user_id','follows.user_id','notifications.user_id','email_preferences.user_id','marketplace_credits.user_id','credit_transactions.user_id','referrals.referrer_user_id','referrals.referred_user_id','seller_referral_payout_batches.referrer_user_id','message_conversations.buyer_user_id','message_conversations.seller_user_id','conversation_messages.sender_user_id','message_blocks.blocker_user_id','message_blocks.blocked_user_id','message_reports.reporter_user_id');
        $refs = DB::rows('select table_name,column_name from information_schema.key_column_usage where referenced_table_schema=database() and referenced_table_name="users"');
        foreach ($refs as $ref) {
            $key = $ref['table_name'].'.'.$ref['column_name'];
            if (in_array($key, $known, true)) continue;
            $row = DB::row('select 1 present from `'.$ref['table_name'].'` where `'.$ref['column_name'].'`=? limit 1', [$source]);
            if ($row) throw new DomainException("Unrecognized populated user reference blocks merge: $key.");
        }
    }

    private function moveDeduplicated(int $source, int $target): array
    {
        $result=[];
        foreach ([['cart_items',['product_id','license_type']],['wishlists',['product_id']],['follows',['designer_id']],['notifications',['event_key']]] as [$table,$keys]) {
            if(!$this->tableExists($table))continue;$join=implode(' and ',array_map(fn($k)=>"t.`$k` <=> s.`$k`",$keys));
            $rows=DB::rows("select s.id source_id,t.id target_id".($this->columnExists($table,'created_at')?',s.created_at source_created,t.created_at target_created':'').($table==='notifications'?',s.read_at source_read,t.read_at target_read':'')." from `$table` s join `$table` t on t.user_id=? and $join where s.user_id=? order by s.id",[$target,$source]);
            foreach($rows as $row){$sourceOlder=isset($row['source_created'])&&$row['source_created']!==$row['target_created']?$row['source_created']<$row['target_created']:(int)$row['source_id']<(int)$row['target_id'];$keep=$sourceOlder?'source':'target';if($table==='notifications'&&($row['source_read']===null||$row['target_read']===null))DB::exec('update notifications set read_at=null where id=?',[$keep==='source'?$row['source_id']:$row['target_id']]);DB::exec("delete from `$table` where id=?",[$keep==='source'?$row['target_id']:$row['source_id']]);$result[$table]=($result[$table]??0)+1;}
            DB::exec("update `$table` set user_id=? where user_id=?",[$target,$source]);$result[$table]??=0;
        }
        return $result;
    }

    private function mergeEmailPreferences(int $source, int $target): void
    {
        if (!$this->tableExists('email_preferences')) return;
        $s=DB::row('select * from email_preferences where user_id=?',[$source]); if(!$s)return;
        $t=DB::row('select * from email_preferences where user_id=?',[$target]);
        if(!$t){DB::exec('update email_preferences set user_id=? where user_id=?',[$target,$source]);return;}
        DB::exec('update email_preferences set marketing_opt_in=?,weekly_emails=?,monthly_emails=?,favorite_shop_emails=?,marketing_opted_out_at=case when ?=0 then coalesce(marketing_opted_out_at,?,now()) else marketing_opted_out_at end where user_id=?', [
            min((int)$s['marketing_opt_in'],(int)$t['marketing_opt_in']), min((int)$s['weekly_emails'],(int)$t['weekly_emails']), min((int)$s['monthly_emails'],(int)$t['monthly_emails']), min((int)$s['favorite_shop_emails'],(int)$t['favorite_shop_emails']), min((int)$s['marketing_opt_in'],(int)$t['marketing_opt_in']), $s['marketing_opted_out_at']??null, $target]);
        DB::exec('delete from email_preferences where user_id=?',[$source]);
    }

    private function moveMessaging(int $source, int $target): void
    {
        if ($this->tableExists('message_conversations')) {
            foreach(DB::rows('select id,buyer_user_id,seller_user_id,context_key from message_conversations where buyer_user_id=? or seller_user_id=?',[$source,$source]) as $row) {
                DB::exec('update message_conversations set buyer_user_id=?,seller_user_id=?,context_key=? where id=?', [(int)$row['buyer_user_id']===$source?$target:(int)$row['buyer_user_id'],(int)$row['seller_user_id']===$source?$target:(int)$row['seller_user_id'],$this->rewrittenContextKey($row,$source,$target),(int)$row['id']]);
            }
        }
        foreach ([['conversation_messages','sender_user_id'],['message_blocks','blocker_user_id'],['message_blocks','blocked_user_id'],['message_reports','reporter_user_id']] as [$table,$column]) $this->updateIfExists($table,$column,$source,$target);
    }

    private function moveReferrals(int $source, int $target): void
    {
        foreach ([['referrals','referrer_user_id'],['referrals','referred_user_id'],['seller_referral_payout_batches','referrer_user_id']] as [$table,$column]) $this->updateIfExists($table,$column,$source,$target);
    }

    private function transferCredit(int $source, int $target, int $actor): array
    {
        if(!$this->tableExists('marketplace_credits'))return ['amount'=>'0.00','transferred'=>false];
        DB::exec('insert into marketplace_credits(user_id,total_balance,reserved_balance) values(?,"0.00","0.00") on duplicate key update user_id=values(user_id)',[$target]);
        $rows=DB::rows('select user_id,cast(total_balance as char) total_balance,cast(reserved_balance as char) reserved_balance from marketplace_credits where user_id in (?,?) order by user_id for update',[$source,$target]);$by=[];foreach($rows as $r)$by[(int)$r['user_id']]=$r;
        $sourceRow=$by[$source]??null;$targetRow=$by[$target]??null;$sourceCents=CreditService::parseCents((string)($sourceRow['total_balance']??'0.00'));$targetCents=CreditService::parseCents((string)($targetRow['total_balance']??'0.00'));$sourceReserved=CreditService::parseCents((string)($sourceRow['reserved_balance']??'0.00'));$targetReserved=CreditService::parseCents((string)($targetRow['reserved_balance']??'0.00'));
        if($sourceReserved!==0)throw new DomainException('Source marketplace credit is reserved and cannot be transferred safely.');if($sourceCents<0||$targetReserved<0||$targetReserved>$targetCents)throw new DomainException('Marketplace credit balances are not safe to reconcile.');if($sourceCents===0)return ['amount'=>'0.00','transferred'=>false];
        $debitKey="phase13.1:account-merge:$source:$target:debit";$creditKey="phase13.1:account-merge:$source:$target:credit";$prior=DB::rows('select id,idempotency_key from credit_transactions where idempotency_key in (?,?) for update',[$debitKey,$creditKey]);if($prior)throw new DomainException('A partial or previous credit transfer already exists without a completed merge audit.');
        if($sourceCents>CreditService::MAX_CENTS-$targetCents)throw new DomainException('Combined marketplace credit exceeds the supported maximum.');
        $amount=CreditService::formatCents($sourceCents);$combined=CreditService::formatCents($sourceCents+$targetCents);DB::exec('update marketplace_credits set total_balance="0.00",reserved_balance="0.00" where user_id=?',[$source]);DB::exec('update marketplace_credits set total_balance=? where user_id=?',[$combined,$target]);
        DB::exec('insert into credit_transactions(user_id,amount,type,status,idempotency_key,admin_user_id,description,finalized_at) values(?, ?,"account_merge_transfer_out","finalized",?,?,"Phase 13.1 reconciled account merge credit transfer",now())',[$source,CreditService::formatCents(-$sourceCents),$debitKey,$actor]);$debit=(int)DB::id();DB::exec('insert into credit_transactions(user_id,amount,type,status,idempotency_key,admin_user_id,related_transaction_id,description,finalized_at) values(?, ?,"account_merge_transfer_in","finalized",?,?,?,?,now())',[$target,$amount,$creditKey,$actor,$debit,'Phase 13.1 reconciled account merge credit transfer']);$credit=(int)DB::id();DB::exec('update credit_transactions set related_transaction_id=? where id=?',[$credit,$debit]);return ['amount'=>$amount,'transferred'=>true,'debit_transaction_id'=>$debit,'credit_transaction_id'=>$credit,'combined_balance'=>$combined];
    }

    private function updateIfExists(string $table,string $column,int $source,int $target): void
    { if($this->tableExists($table)&&$this->columnExists($table,$column))DB::exec("update `$table` set `$column`=? where `$column`=?",[$target,$source]); }
    private function tableExists(string $table): bool
    { return (bool)DB::row('select 1 from information_schema.tables where table_schema=database() and table_name=?',[$table]); }
    private function columnExists(string $table,string $column): bool
    { return (bool)DB::row('select 1 from information_schema.columns where table_schema=database() and table_name=? and column_name=?',[$table,$column]); }
    private function sourceCount(string $table,int $source): int
    {
        $clauses=['cart_items'=>'user_id=?','wishlists'=>'user_id=?','follows'=>'user_id=?','notifications'=>'user_id=?','message_conversations'=>'buyer_user_id=? or seller_user_id=?','conversation_messages'=>'sender_user_id=?','message_blocks'=>'blocker_user_id=? or blocked_user_id=?','message_reports'=>'reporter_user_id=?'];
        $n=substr_count($clauses[$table],'?'); return (int)DB::row("select count(*) n from `$table` where ".$clauses[$table],array_fill(0,$n,$source))['n'];
    }
}
