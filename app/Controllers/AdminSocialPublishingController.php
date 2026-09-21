<?php
namespace App\Controllers;
use App\Core\Database as DB;use App\Core\Helpers as H;use App\Services\SocialPublishingService;
final class AdminSocialPublishingController
{
 public function index():void{H::requireAdminPermission('promotions.view');H::view('admin/social_publishing',['integrations'=>DB::rows('select * from social_platform_integrations order by platform')]);}
 public function update(string $platform):void{H::requireAdminPermission('promotions.manage');H::verifyCsrf();if(!in_array($platform,SocialPublishingService::PLATFORMS,true))H::abort(404);$enabled=isset($_POST['enabled'])?1:0;$reason=trim((string)($_POST['reason']??''));if(!$enabled&&$reason==='')H::abort(422);DB::exec('update social_platform_integrations set is_enabled=?,disabled_reason=?,updated_by=? where platform=?',[$enabled,$enabled?null:mb_substr($reason,0,500),H::user()['id'],$platform]);H::flash('success',ucfirst($platform).' publishing setting updated.');H::redirect('/admin/social-publishing');}
}
