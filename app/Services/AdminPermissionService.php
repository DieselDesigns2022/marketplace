<?php
namespace App\Services;
use App\Core\Database as DB;
use App\Core\Helpers as H;
use DomainException;
use InvalidArgumentException;
use Throwable;

class AdminPermissionService
{
 public const CANONICAL_EMAIL='angela@creativemoth.com';
 public const PERMISSIONS=['dashboard.view','users.view','users.manage','applications.view','applications.manage','designers.view','designers.manage','products.view','products.manage','ip_risk.view','ip_risk.manage','categories.view','categories.manage','coupons.view','coupons.manage','orders.view','orders.manage','custom_orders.view','custom_orders.manage','downloads.view','payments.view','payments.manage','credits.view','credits.adjust','payouts.manage','referrals.view','homepage.view','homepage.manage','promotions.view','promotions.manage','messages.view','messages.moderate','waitlist.view','waitlist.manage','waitlist.export','email_campaigns.view','email_campaigns.manage'];
 public function can(int $userId,string $permission):bool
 {if(!in_array($permission,self::PERMISSIONS,true))return false;if(!DB::row('select id from users where id=? and role="admin" and status="active"',[$userId]))return false;$p=DB::row('select full_access from admin_access_profiles where user_id=?',[$userId]);return (int)($p['full_access']??0)===1||(bool)DB::row('select user_id from admin_permission_grants where user_id=? and permission_key=?',[$userId,$permission]);}
 public function isFullAccess(int $userId):bool
 {return (bool)DB::row('select p.user_id from admin_access_profiles p join users u on u.id=p.user_id where p.user_id=? and p.full_access=1 and u.role="admin" and u.status="active"',[$userId]);}
 public function require(int $userId,string $permission):void{if(!$this->can($userId,$permission))H::abort(403);}
 public function requireFullAccess(int $userId):void{$this->assertFullActor($userId);}
 public function canonicalOwnerUserId():?int
 { $row=DB::row('select target_user_id from account_merge_audits where final_canonical_email=? and source_disabled=1 and password_reset_confirmed=1 order by id desc limit 1',[self::CANONICAL_EMAIL]);return $row?(int)$row['target_user_id']:null; }
 public function isCanonicalOwner(int $userId):bool
 { $owner=$this->canonicalOwnerUserId();return $owner!==null&&$owner===$userId; }
 public function configureAdmin(int $userId,array $permissions,bool $fullAccess,int $actor):void
 { $this->assertFullActor($actor);$target=DB::row('select id,role,status from users where id=?',[$userId]);if(!$target||$target['status']!=='active')throw new DomainException('Only an active account can be promoted to Admin.');$selected=array_values(array_unique(array_intersect(self::PERMISSIONS,$permissions)));$this->atomic(function()use($userId,$target,$selected,$fullAccess,$actor){if($target['role']!=='admin')DB::exec('update users set role="admin" where id=?',[$userId]);$this->setFullAccess($userId,$fullAccess,$actor);$existing=array_column(DB::rows('select permission_key from admin_permission_grants where user_id=?',[$userId]),'permission_key');foreach(array_diff($selected,$existing)as$key)$this->grant($userId,$key,$actor);foreach(array_diff($existing,$selected)as$key)$this->revoke($userId,$key,$actor);}); }
 public function grant(int $userId,string $permission,int $actor):bool
 { $this->assertPermission($permission);$this->assertActiveAdmin($userId);$this->assertFullActor($actor);if(DB::row('select user_id from admin_permission_grants where user_id=? and permission_key=?',[$userId,$permission]))return false;return $this->atomic(function()use($userId,$permission,$actor){DB::exec('insert into admin_permission_grants(user_id,permission_key,granted_by) values(?,?,?)',[$userId,$permission,$actor]);DB::exec('insert into admin_permission_audits(admin_user_id,permission_key,action,acting_admin_user_id) values(?, ?,"permission_granted",?)',[$userId,$permission,$actor]);return true;}); }
 public function revoke(int $userId,string $permission,int $actor):bool
 { $this->assertPermission($permission);$this->assertActiveAdmin($userId);$this->assertFullActor($actor);if(!DB::row('select user_id from admin_permission_grants where user_id=? and permission_key=?',[$userId,$permission]))return false;return $this->atomic(function()use($userId,$permission,$actor){DB::exec('delete from admin_permission_grants where user_id=? and permission_key=?',[$userId,$permission]);DB::exec('insert into admin_permission_audits(admin_user_id,permission_key,action,acting_admin_user_id) values(?, ?,"permission_revoked",?)',[$userId,$permission,$actor]);return true;}); }
 public function setFullAccess(int $userId,bool $enabled,int $actor):bool
 { $this->assertActiveAdmin($userId);$this->assertFullActor($actor);if(!$enabled&&$this->isCanonicalOwner($userId))throw new DomainException('The canonical owner must retain full access.');$current=(int)(DB::row('select full_access from admin_access_profiles where user_id=?',[$userId])['full_access']??0);if($current===($enabled?1:0))return false;return $this->atomic(function()use($userId,$enabled,$actor){DB::exec('insert into admin_access_profiles(user_id,full_access,granted_by) values(?,?,?) on duplicate key update full_access=values(full_access),granted_by=values(granted_by)',[$userId,$enabled?1:0,$actor]);DB::exec('insert into admin_permission_audits(admin_user_id,permission_key,action,acting_admin_user_id) values(?,null,?,?)',[$userId,$enabled?'profile_full_access_enabled':'profile_full_access_disabled',$actor]);return true;}); }
 /** Only AccountMergeService may call this after it has atomically changed both controlled identities. */
 public function bootstrapCanonicalMergeOwner(int $targetId,int $sourceId):void
 { $target=DB::row('select id,email,role,status from users where id=?',[$targetId]);$source=DB::row('select id,email,status,merged_into_user_id from users where id=?',[$sourceId]);if(!$target||strcasecmp($target['email'],self::CANONICAL_EMAIL)!==0||$target['role']!=='admin'||$target['status']!=='active'||!$source||strcasecmp($source['email'],AccountMergeService::SELLER_EMAIL)!==0||$source['status']!=='disabled'||(int)$source['merged_into_user_id']!==$targetId)throw new DomainException('Controlled canonical-owner bootstrap conditions were not met.');if(DB::row('select user_id from admin_access_profiles where user_id=?',[$targetId]))throw new DomainException('Canonical-owner profile already exists before controlled bootstrap.');DB::exec('insert into admin_access_profiles(user_id,full_access,granted_by) values(?,1,?)',[$targetId,$targetId]);DB::exec('insert into admin_permission_audits(admin_user_id,permission_key,action,acting_admin_user_id,metadata) values(?,null,"profile_full_access_enabled",?,?)',[$targetId,$targetId,json_encode(['source'=>'controlled_account_merge'],JSON_THROW_ON_ERROR)]); }
 private function assertActiveAdmin(int $id):void{if(!DB::row('select id from users where id=? and role="admin" and status="active"',[$id]))throw new InvalidArgumentException('Admin permissions require an active admin account.');}
 private function assertFullActor(int $id):void{$this->assertActiveAdmin($id);if(!$this->isFullAccess($id))throw new DomainException('Only a full-access Admin may change Admin permissions.');}
 private function assertPermission(string $p):void{if(!in_array($p,self::PERMISSIONS,true))throw new InvalidArgumentException('Unknown admin permission.');}
 private function atomic(callable $fn):mixed{$own=!DB::pdo()->inTransaction();if($own)DB::begin();try{$v=$fn();if($own)DB::commit();return$v;}catch(Throwable $e){if($own&&DB::pdo()->inTransaction())DB::rollBack();throw$e;}}
}
