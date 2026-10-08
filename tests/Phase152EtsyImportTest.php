<?php
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\CsvProductImportService;
use App\Services\ProductScheduleService;
$fail=0;
$check=function(bool $ok,string $name)use(&$fail){echo ($ok?'PASS':'FAIL').": $name\n";if(!$ok)$fail++;};
$service=new CsvProductImportService();$fixtures=__DIR__.'/fixtures/phase152/';
$records=$service->parse($fixtures.'etsy-listings.csv','etsy')['records'];
$check(count($records)===2&&!$records[0]['errors']&&!$records[1]['errors'],'ordinary Etsy exports support blank IDs and SKUs');
$check($records[0]['title']==='Botanical, "Moon" Printable'&&$records[0]['description']==="First line, with commas.\nSecond line says \"hello\".",'commas, escaped quotes and multiline descriptions survive');
$check($records[0]['sku']==='MOON-1'&&$records[0]['tags']===['botanical','moon','printable']&&$records[0]['price']==='4.50','SKU, tags, description and price are recovered');
$check($records[1]['source_id']==='title:Minimal Fern Printable'&&!empty($records[1]['warnings']),'missing source identity uses conservative, disclosed title duplicate protection');
$variation=$service->parse($fixtures.'etsy-variations.csv','etsy')['records'][0];
$check($variation['title']==='Moon Art'&&$variation['sku']==='MOON-V'&&count($variation['images'])===2,'common normalized headers and numbered images work');
$check($service->parse($fixtures.'etsy-variations.csv','etsy',['images'=>''])['records'][0]['images']===[],'manual opt-out of images is respected');
$minimal=$service->parse($fixtures.'etsy-minimal.csv','etsy')['records'][0];
$check(!$minimal['errors']&&$minimal['images']===[]&&$minimal['sku']===null,'missing optional fields remain empty');
$check($service->parse($fixtures.'etsy-manual.csv','etsy')['needs_mapping'],'unknown headers retain the manual mapping workflow');
$manual=$service->parse($fixtures.'etsy-manual.csv','etsy',['source_id'=>'External Key','title'=>'Artwork Name','description'=>'Copy','price'=>'Amount','tags'=>'Keywords','sku'=>'Reference']);
$check(!$manual['needs_mapping']&&$manual['records'][0]['sku']==='MAN-1'&&$manual['records'][0]['price']==='6.50','manual mapping imports all mapped fields');
foreach(['etsy-orders.csv'=>'product/listing','etsy-malformed.csv'=>'different number'] as $file=>$message){try{$service->parse($fixtures.$file,'etsy');$ok=false;}catch(RuntimeException $e){$ok=str_contains($e->getMessage(),$message);}$check($ok,"$file is rejected with a corrective error");}
$tmp=tempnam(sys_get_temp_dir(),'etsy-');
file_put_contents($tmp,"TITLE,DESCRIPTION,PRICE,IMAGE1,IMAGE2\nSame,One,3,not-an-image,ftp://example.com/file\nSame,Two,4,,\n");
$duplicate=$service->parse($tmp,'etsy')['records'][0];
$check(!empty($duplicate['errors'])&&$duplicate['images']===[],'ambiguous same-title rows and invalid image URLs are not imported');
file_put_contents($tmp,"TITLE,DESCRIPTION,PRICE\nTitle,Description,\"3,50\"\n");$invalidPrice=$service->parse($tmp,'etsy')['records'][0];$check($invalidPrice['price']===null&&$invalidPrice['price_requires_review'],'ambiguous localized Etsy price requires review rather than a guessed amount');
file_put_contents($tmp,"TITLE,DESCRIPTION,PRICE\nTitle,Description,\"3");
try{$service->parse($tmp,'etsy');$ok=false;}catch(RuntimeException $e){$ok=str_contains($e->getMessage(),'unfinished quoted');}$check($ok,'unterminated quoted CSV cells are rejected explicitly');
unlink($tmp);
$now=new DateTimeImmutable('2026-01-01T00:00:00Z');
$check(ProductScheduleService::toUtc('2026-07-01T12:00','America/Detroit',$now)==='2026-07-01 16:00:00','seller daylight-saving timezone converts to UTC');
$check(ProductScheduleService::toUtc('2026-01-02T12:00','Asia/Kolkata',$now)==='2026-01-02 06:30:00','fractional timezone offset converts to UTC');
foreach([['2025-12-31T12:00','UTC'],['2026-03-08T02:30','America/Detroit'],['2026-11-01T01:30','America/Detroit'],['2026-02-30T10:00','UTC'],['2026-07-01T12:00','invalid']] as [$local,$zone]){try{ProductScheduleService::toUtc($local,$zone,$now);$ok=false;}catch(DomainException){$ok=true;}$check($ok,"invalid, past or ambiguous schedule rejected: $local $zone");}
exit($fail?1:0);
