<?php
namespace App\Core {
    final class Helpers {
        public static bool $manage = true;
        public static function canAdmin(string $permission): bool { return self::$manage; }
        public static function e(?string $value): string {
            return htmlspecialchars($value??'',ENT_QUOTES,'UTF-8');
        }
        public static function csrf(): string { return 'test-csrf'; }
    }
}
namespace {
    use App\Core\Database as DB;
    use App\Core\Helpers as H;
    use App\Services\GrandOpeningSaleService as Sale;

    $root = $argv[1]??dirname(__DIR__);
    $viewPath = $argv[2]??$root.'/app/Views/admin/grand_opening.php';
    $dsn = getenv('CM_SALE_TEST_DSN');
    if (!$dsn || !preg_match('/dbname=cm_sale_test_[a-z0-9_]+$/D',$dsn)) {
        throw new RuntimeException('An isolated cm_sale_test_ database is required.');
    }
    require $root.'/app/Core/Database.php';
    require $root.'/app/Services/GrandOpeningSaleService.php';
    $pdo = new PDO($dsn,'root','',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    $property = new ReflectionProperty(DB::class,'pdo');
    $property->setAccessible(true);
    $property->setValue(null,$pdo);

    $checks = 0;
    $check = static function(bool $ok,string $label) use (&$checks): void {
        if (!$ok) throw new RuntimeException($label);
        $checks++;
    };
    $service = new Sale();
    $valid = ['starts_at'=>'2026-12-01T12:00','ends_at'=>'2026-12-08T12:00'];
    $service->saveEvent($valid,7);
    $saved = $service->event();
    $check($saved['starts_at']==='2026-12-01 17:00:00','Persisted UTC start');
    $check($saved['ends_at']==='2026-12-08 17:00:00','Persisted UTC end');
    $check((int)$saved['is_enabled']===0,'Date save does not activate event');
    $check(Sale::localInput($saved['starts_at'])===$valid['starts_at'],'Eastern display round trip');
    $audit = DB::row('select * from grand_opening_sale_audits order by id desc limit 1');
    $check($audit['action']==='event_settings','Date save audited');
    $after = json_decode($audit['after_snapshot'],true,512,JSON_THROW_ON_ERROR);
    $check($after['starts_at']===$saved['starts_at'],'Audit preserves saved dates');

    foreach ([
        ['starts_at'=>'2026-12-01T12:00','ends_at'=>'2026-12-08T11:59'],
        ['starts_at'=>'2026-12-01T12:00','ends_at'=>''],
        ['starts_at'=>'2026-02-30T12:00','ends_at'=>'2026-03-10T12:00'],
        ['starts_at'=>'','ends_at'=>'','is_enabled'=>'1'],
    ] as $invalid) {
        $caught = false;
        try { $service->saveEvent($invalid,7); }
        catch (DomainException $e) { $caught = true; }
        $check($caught,'Invalid save rejected');
        $check($service->event()['starts_at']===$saved['starts_at'],'Rejected save retains prior dates');
    }
    $count = (int)DB::row('select count(*) n from grand_opening_sale_audits')['n'];
    $check($count===1,'Rejected saves do not write success audits');

    $render = static function(array $data) use ($viewPath): string {
        extract($data);
        ob_start();
        try {
            include $viewPath;
            return ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    };
    $data = [
        'event'=>$saved,'dateError'=>'','savedNotice'=>false,
        'formDates'=>$valid,'saleSellers'=>[],
    ];
    $html = $render($data);
    $check(str_contains($html,'Creative Moth Grand Opening'),'Saved event is listed');
    $check(str_contains($html,'Dec 1, 2026 at 12:00 PM EST'),'Saved date is displayed in Eastern time');
    $check(str_contains($html,'Participating sellers (0)'),'Empty participation count');
    $check(str_contains($html,'No sellers have joined'),'Empty participation explanation');

    $data['dateError']='The event must run at least seven days. Your dates were not saved.';
    $data['formDates']['ends_at']='2026-12-02T12:00';
    $html = $render($data);
    $check(str_contains($html,'role="alert"'),'Save error announced');
    $check(str_contains($html,'value="2026-12-02T12:00"'),'Rejected input preserved');
    $check(str_contains($html,'Dates not saved'),'Failure clearly shown');

    $data['dateError']='';
    $data['savedNotice']=true;
    $html=$render($data);
    $check(str_contains($html,'dates saved successfully.'),'Success clearly shown');

    H::$manage=false;
    $html=$render($data);
    $check(!str_contains($html,'<form'),'Read-only admin cannot edit');
    H::$manage=true;

    $data['event']=array_merge($saved,['starts_at'=>null,'ends_at'=>null]);
    $data['savedNotice']=false;
    $html=$render($data);
    $check(str_contains($html,'No event dates have been saved yet'),'Unscheduled state clear');

    // Force an audit-write failure in the isolated database.
    $pdo->exec('rename table grand_opening_sale_audits to unavailable_sale_audits');
    $failed=false;
    try {
        $service->saveEvent(
            ['starts_at'=>'2026-12-02T12:00','ends_at'=>'2026-12-09T12:00'],7
        );
    } catch (Throwable $e) {
        $failed=true;
    }
    $check($failed,'Audit failure rejects save');
    $check($service->event()['starts_at']===$saved['starts_at'],'Audit failure rolls dates back');
    $check(!$pdo->inTransaction(),'Failed save closes transaction');
    echo "PASS: $checks Grand Opening admin/database checks\n";
}
