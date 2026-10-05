<?php
namespace App\Controllers;
use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\AnalyticsService;
use DomainException;

final class AnalyticsController
{
    public function admin():void { H::requireAdminPermission('dashboard.view');$this->render('admin/analytics',null); }
    public function adminCsv():void { H::requireAdminPermission('dashboard.view');$this->export(null,'marketplace-analytics'); }
    public function seller():void { H::requireSeller();$d=$this->designer();$this->render('seller/analytics',(int)$d['id']); }
    public function sellerCsv():void { H::requireSeller();$d=$this->designer();$this->export((int)$d['id'],'seller-analytics'); }
    private function designer():array { $d=DB::row('select id,display_name from designers where user_id=? and status="approved"',[H::user()['id']]);if(!$d)H::abort(403);return $d; }
    private function render(string $view,?int $designerId):void { try{$report=(new AnalyticsService())->report($_GET,$designerId);H::view($view,['report'=>$report,'error'=>null]);}catch(DomainException $e){H::view($view,['report'=>null,'error'=>$e->getMessage()]);} }
    private function export(?int $designerId,string $name):void { try{$report=(new AnalyticsService())->report($_GET,$designerId);}catch(DomainException){H::abort(422);}header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$name.'-'.$report['filters']['from'].'-'.$report['filters']['to'].'.csv"');echo "\xEF\xBB\xBF".AnalyticsService::csv($report); }
}
