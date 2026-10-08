<?php

namespace App\Services;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use JsonException;
use RuntimeException;

/** Shared CSV reader; adapters only define source-specific product columns. */
class CsvProductImportService
{
    public const SOURCES=['shopify'=>'Shopify','etsy'=>'Etsy','payhip'=>'Payhip','square'=>'Square','squarespace'=>'Squarespace','wix'=>'Wix','weebly'=>'Weebly','woocommerce'=>'WordPress / WooCommerce'];
    public const MAX_BYTES=52_428_800;
    public const MAX_ROWS=50_000;
    public const MAP_FIELDS=['source_id','source_url','sku','title','short_description','description','price','sale_price','currency','tags','categories','product_type','seo_title','seo_description','images'];
    private const ALIASES=[
        'shopify'=>['source_id'=>['handle'],'source_url'=>['url handle'],'title'=>['title'],'description'=>['body html','body (html)','description'],'price'=>['variant price','price'],'sku'=>['variant sku','sku'],'tags'=>['tags'],'categories'=>['product category'],'product_type'=>['type'],'seo_title'=>['seo title'],'seo_description'=>['seo description'],'images'=>['image src','image url']],
        'etsy'=>['source_id'=>['listing id','listingid'],'source_url'=>['listing url','listing link','url'],'title'=>['title','listing title','listing name'],'description'=>['description','listing description'],'price'=>['price'],'currency'=>['currency code','currency'],'sku'=>['sku numbers','sku number','skus','sku'],'tags'=>['tags'],'product_type'=>['type'],'images'=>['image urls','image url','image 1','image1']],
        'payhip'=>['source_id'=>['product key','id'],'source_url'=>['product url','link','url'],'title'=>['title','product name'],'description'=>['description'],'price'=>['price'],'sku'=>['sku','reference'],'tags'=>['tags'],'product_type'=>['product type'],'images'=>['image url','cover url']],
        'square'=>['source_id'=>['token','reference handle','item id'],'source_url'=>['permalink'],'title'=>['item name'],'description'=>['description'],'price'=>['price'],'sale_price'=>['online sale price'],'sku'=>['sku'],'categories'=>['category','reporting category'],'product_type'=>['item type'],'seo_title'=>['seo title'],'seo_description'=>['seo description']],
        'squarespace'=>['source_id'=>['product id','product url'],'source_url'=>['product url'],'title'=>['title'],'description'=>['description'],'price'=>['price'],'sale_price'=>['sale price'],'sku'=>['sku'],'tags'=>['tags'],'categories'=>['categories'],'product_type'=>['product type'],'images'=>['hosted image urls','image url']],
        'wix'=>['source_id'=>['handleid','handle id','handle'],'source_url'=>['product url'],'title'=>['name','product name'],'description'=>['description'],'price'=>['price'],'sku'=>['sku'],'categories'=>['collection','category'],'product_type'=>['product type'],'images'=>['media url','image url']],
        'weebly'=>['source_id'=>['product id'],'title'=>['title'],'description'=>['description'],'price'=>['price'],'sale_price'=>['sale price'],'sku'=>['sku'],'categories'=>['categories'],'product_type'=>['product type'],'images'=>['image']],
        'woocommerce'=>['source_id'=>['id'],'source_url'=>['external url'],'title'=>['name'],'short_description'=>['short description'],'description'=>['description'],'price'=>['regular price'],'sale_price'=>['sale price'],'sku'=>['sku'],'tags'=>['tags'],'categories'=>['categories'],'product_type'=>['type'],'images'=>['images']],
    ];
    private const VARIANT_HEADERS=[
        'shopify'=>['variant sku','variant price','option1 name','option1 value','option2 name','option2 value','option3 name','option3 value','image position'],
        'etsy'=>['quantity','materials'],
        'square'=>['variation name','sku','price','option name','option value'],
        'squarespace'=>['variant id','sku','price','sale price','option name','option value'],
        'wix'=>['fieldtype','field type','sku','price','product option name','product option value','media url'],
        'weebly'=>['sku','price','sale price','option name','option type','option value'],
        'woocommerce'=>['id','type','sku','parent','attribute 1 name','attribute 1 value(s)','attribute 2 name','attribute 2 value(s)'],
        'payhip'=>['sku','product type'],
    ];

    public function importDirectory(): string { return app_path('storage/app/private/csv_imports'); }
    public function cleanupStaleFiles(int $age=86400): void
    {
        $dir=$this->importDirectory(); if(!is_dir($dir))return; $cutoff=time()-$age;
        foreach((array)glob($dir.'/*.csv') as $file)if(is_file($file)&&filemtime($file)<$cutoff)@unlink($file);
    }
    public function removeTemporaryFile(?string $path): void
    {
        if(!$path)return; $dir=realpath($this->importDirectory()); $file=realpath($path);
        if($dir&&$file&&is_file($file)&&dirname($file)===$dir&&str_ends_with($file,'.csv'))@unlink($file);
    }
    public function storeUpload(array $file): array
    {
        $this->cleanupStaleFiles();
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($file['tmp_name']??'')))throw new RuntimeException('Choose a CSV file to upload.');
        $size=(int)($file['size']??0); if($size<1||$size>self::MAX_BYTES)throw new RuntimeException('The CSV must be non-empty and 50 MB or smaller.');
        if(strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION))!=='csv')throw new RuntimeException('Only .csv files are accepted.');
        $bytes=file_get_contents($file['tmp_name']);
        if($bytes===false||str_contains($bytes,"\0")||!mb_check_encoding($bytes,'UTF-8'))throw new RuntimeException('The CSV must be valid UTF-8 text.');
        $dir=$this->importDirectory(); if(!is_dir($dir)&&!mkdir($dir,0750,true))throw new RuntimeException('The protected import folder is unavailable.');
        $path=$dir.'/'.bin2hex(random_bytes(20)).'.csv'; if(!move_uploaded_file($file['tmp_name'],$path))throw new RuntimeException('The CSV could not be stored safely.');
        return ['path'=>$path,'filename'=>mb_substr(preg_replace('/[^a-zA-Z0-9._ -]/','',basename((string)$file['name'])),0,190)?:'products.csv'];
    }
    public function parse(string $path,string $source,array $manual=[]): array
    {
        if(!isset(self::SOURCES[$source])||!is_file($path))throw new RuntimeException('The import file or source is invalid.');
        if(filesize($path)>self::MAX_BYTES)throw new RuntimeException('The CSV must be 50 MB or smaller.');
        $bytes=file_get_contents($path); if($bytes===false||strlen($bytes)>self::MAX_BYTES||str_contains($bytes,"\0")||!mb_check_encoding($bytes,'UTF-8'))throw new RuntimeException('The CSV must be valid UTF-8 text.');
        $this->validateEnclosures($bytes);
        $fh=fopen($path,'rb'); $raw=fgetcsv($fh,0,',','"',''); if(!$raw||count($raw)<2){fclose($fh);throw new RuntimeException('The CSV needs a header row and product columns.');}
        $raw[0]=preg_replace('/^\xEF\xBB\xBF/','',(string)$raw[0]); $headers=array_map([$this,'header'],$raw);
        if(in_array('',$headers,true)||count(array_unique($headers))!==count($headers)){fclose($fh);throw new RuntimeException('The CSV contains blank or repeated column headings.');}
        $this->rejectWrongExport($source,$headers); $mapping=$this->mapping($source,$headers,$manual);
        if(empty($mapping['title'])||($source!=='etsy'&&empty($mapping['source_id'])&&empty($mapping['sku'])&&empty($mapping['source_url']))){fclose($fh);return ['needs_mapping'=>true,'headers'=>$raw,'mapping'=>$mapping,'records'=>[]];}
        $rows=[];$line=1;
        while(($row=fgetcsv($fh,0,',','"',''))!==false){$line++;if($line>self::MAX_ROWS+1){fclose($fh);throw new RuntimeException('This CSV exceeds the 50,000-row safety limit. Split it into smaller product CSVs.');}if($row===[null]||!array_filter($row,fn($v)=>trim((string)$v)!==''))continue;if(count($row)!==count($headers)){fclose($fh);throw new RuntimeException("Row $line has a different number of columns and cannot be read safely.");}$rows[]=array_merge(array_combine($headers,array_map([$this,'cell'],$row)),['_line'=>$line]);}
        fclose($fh);if(!$rows)throw new RuntimeException('No product rows were found in this CSV.');
        return ['needs_mapping'=>false,'headers'=>$raw,'mapping'=>$mapping,'records'=>$this->normalize($source,$rows,$mapping,!array_key_exists('images',$manual))];
    }
    /** fgetcsv accepts an unterminated final enclosure; fail instead of inventing a cell. */
    private function validateEnclosures(string $csv): void
    {
        $csv=preg_replace('/^\xEF\xBB\xBF/','',$csv);
        $quoted=false;$fieldStart=true;$afterQuote=false;
        for($i=0,$length=strlen($csv);$i<$length;$i++){
            $c=$csv[$i];
            if($quoted){
                if($c==='"'){
                    if(($csv[$i+1]??'')==='"'){$i++;continue;}
                    $quoted=false;$afterQuote=true;
                }
                continue;
            }
            if($c===','||$c==="\n"||$c==="\r"){$fieldStart=true;$afterQuote=false;continue;}
            if($c===' '||$c==="\t")continue;
            if($afterQuote)throw new RuntimeException('The CSV has text after a closing quote. Export the listings again with properly quoted cells.');
            if($fieldStart&&$c==='"'){$quoted=true;$fieldStart=false;continue;}
            $fieldStart=false;
        }
        if($quoted)throw new RuntimeException('The CSV has an unfinished quoted cell. Export the listings again with properly quoted cells.');
    }
    public function json(array $value): string { try{return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(JsonException){throw new RuntimeException('Import metadata could not be stored safely.');} }
    private function header(string $v): string{return trim(preg_replace('/\s+/',' ',strtolower(str_replace(['-','_'],' ',trim($v)))));}
    private function cell($v): string{return trim((string)$v);}
    private function mapping(string $source,array $headers,array $manual): array
    {
        $out=[];foreach(self::ALIASES[$source] as $field=>$aliases)foreach($aliases as $alias)if(in_array($this->header($alias),$headers,true)){$out[$field]=$this->header($alias);break;}
        if($source==='etsy'&&!isset($out['images']))foreach($headers as $header)if(preg_match('/^image ?[1-9][0-9]*$/',$header)){$out['images']=$header;break;}
        foreach($manual as $field=>$header)if(in_array($field,self::MAP_FIELDS,true)){if($header===''){unset($out[$field]);continue;}if(in_array($this->header((string)$header),$headers,true))$out[$field]=$this->header((string)$header);}
        if(count($out)!==count(array_unique($out)))throw new RuntimeException('Each CSV column can be mapped to only one import field.');return $out;
    }
    private function rejectWrongExport(string $source,array $h): void
    {
        $s=' '.implode(' ',$h).' ';$wrong=match($source){'etsy'=>preg_match('/\b(order id|payment id|buyer email|sale date)\b/',$s),'payhip'=>preg_match('/\b(customer email|subscriber|transaction id|sale date)\b/',$s),'square'=>preg_match('/\b(inventory history|order id|customer id|payment id)\b/',$s),'woocommerce'=>preg_match('/\b(order id|billing address|customer email)\b/',$s),default=>false};
        if($wrong)throw new RuntimeException('This appears to be an orders, payments, customers, subscribers, or inventory-history export. Please upload the product/listing export.');
    }
    private function normalize(string $source,array $rows,array $map,bool $includeNumberedImages=true): array
    {
        $groups=[];foreach($rows as $r){$get=fn($f)=>isset($map[$f])?trim((string)($r[$map[$f]]??'')):'';$id=$get('source_id');if($source==='woocommerce'){$id=preg_replace('/^id:\s*/i','',$id);if(strtolower($r['type']??'')==='variation'&&trim($r['parent']??'')!=='')$id=preg_replace('/^id:\s*/i','',trim($r['parent']));}$identity=$id!==''?'id:'.$id:($get('source_url')!==''?'url:'.$this->canonicalUrl($get('source_url')):'sku:'.$get('sku'));if($identity==='sku:')$identity=$source==='etsy'&&$get('title')!==''?'title:'.$get('title'):'missing:'.$r['_line'];$groups[$identity][]=$r;}
        $records=[];foreach($groups as $identity=>$group){$get=fn($r,$f)=>isset($map[$f])?trim((string)($r[$map[$f]]??'')):'';$vals=[];foreach(self::MAP_FIELDS as $f)$vals[$f]=array_values(array_unique(array_filter(array_map(fn($r)=>$get($r,$f),$group),fn($v)=>$v!=='')));$warnings=[];$errors=[];if(str_starts_with($identity,'title:')){$warnings[]='Etsy supplied no listing ID, SKU, or URL; duplicate protection uses the exact title. Renamed listings require manual duplicate review.';if(count($group)>1)$errors[]='Multiple Etsy rows share a title without a stable ID. Supply unique listing IDs or SKUs and map them before importing.';}$title=$vals['title'][0]??'';if($title==='')$errors[]='A product title is required.';if(mb_strlen($title)>190)$errors[]='Product title must be shortened to 190 characters or fewer before import.';if(str_starts_with($identity,'missing:'))$errors[]='Map a stable product ID, SKU, or product URL.';
            $prices=$vals['price'];$price=count($prices)===1?($source==='etsy'?$this->etsyPrice($prices[0]):$this->price($prices[0])):null;if(count($prices)>1){$price=null;$warnings[]='Variants have different prices; review and enter the Creative Moth price.';}if($prices&&$price===null)$warnings[]='The source price requires seller review.';if($vals['currency']&&strtoupper($vals['currency'][0])!==strtoupper($_ENV['MARKETPLACE_CURRENCY']??'USD')){$price=null;$warnings[]='Source currency differs from Creative Moth; no conversion was performed.';}
            $type=$vals['product_type'][0]??null;if($this->nonDigitalType($type))$warnings[]='Source product type "'.$type.'" requires fulfillment review; no source digital file was recovered.';if($source==='wix')$warnings[]='Wix native store CSV exports do not include digital source files; Creative Moth cannot recover data absent from the CSV.';
            $tags=[];foreach($vals['tags'] as $list)$tags=array_merge($tags,$this->splitList($list));$categories=[];foreach($vals['categories'] as $list)$categories=array_merge($categories,$this->splitList($list));$images=[];foreach($group as $r)foreach($this->imageValues($source,$get($r,'images')) as $url)if(!in_array($url,$images,true))$images[]=$url;
            if($source==='etsy'&&isset($map['images'])){
                foreach($group as $r)foreach($r as $header=>$value){
                    if($header!==$map['images']&&!($includeNumberedImages&&preg_match('/^image ?[1-9][0-9]*$/',$header)))continue;
                    $urls=$this->imageValues($source,(string)$value);
                    if(trim((string)$value)!==''&&!$urls)$warnings[]='An Etsy image value is not a valid HTTP(S) URL and was skipped. Add preview images before publication.';
                    foreach($urls as $url)if(!in_array($url,$images,true))$images[]=$url;
                }
            }
            $description=$vals['description'][0]??'';$description=trim(html_entity_decode(strip_tags(preg_replace('#<(script|style)[^>]*>.*?</\1>#is','',$description)),ENT_QUOTES|ENT_HTML5,'UTF-8'));
            $variants=[];foreach($group as $r){$v=[];foreach(self::VARIANT_HEADERS[$source] as $header){$key=$this->header($header);if(isset($r[$key])&&trim((string)$r[$key])!=='')$v[$key]=trim((string)$r[$key]);}if($v&&!in_array($v,$variants,true))$variants[]=$v;}
            $display=mb_substr(preg_replace('/[\x00-\x1F\x7F]/u','',$identity),0,190);$sku=mb_substr($vals['sku'][0]??'',0,190);
            $records[]=['source_platform'=>$source,'source_id'=>$display,'source_fingerprint'=>hash('sha256',$identity),'source_url'=>$vals['source_url'][0]??null,'sku'=>$sku?:null,'source_skus'=>$vals['sku'],'title'=>$title,'source_title_display'=>mb_substr(preg_replace('/[\x00-\x1F\x7F]/u',' ',$title),0,190),'short_description'=>$vals['short_description'][0]??null,'description'=>$description,'price'=>$price,'source_sale_prices'=>array_values(array_filter(array_map(fn($value)=>$this->price($value),$vals['sale_price']))),'price_requires_review'=>$price===null,'currency'=>$vals['currency'][0]??null,'tags'=>array_values(array_unique($tags)),'categories'=>array_values(array_unique($categories)),'product_type'=>$type,'source_type_requires_review'=>$this->nonDigitalType($type),'seo_title'=>$vals['seo_title'][0]??null,'seo_description'=>$vals['seo_description'][0]??null,'images'=>$images,'variants'=>$variants,'warnings'=>array_values(array_unique($warnings)),'errors'=>array_values(array_unique($errors))];
        }return $records;
    }
    private function etsyPrice(string $value): ?string
    {
        // Etsy prices use a decimal point; ambiguous localized values require seller review.
        if(!preg_match('/^\s*(?:[A-Z]{3}\s*|[$€£]\s*)?([0-9]+(?:\.[0-9]{1,2})?)\s*(?:[A-Z]{3})?\s*$/u',$value,$match))return null;
        if((float)$match[1]>99999999.99)return null;
        return number_format((float)$match[1],2,'.','');
    }
    private function price(string $v): ?string{$v=preg_replace('/[^0-9.\-]/','',$v);return is_numeric($v)&&(float)$v>=0?number_format((float)$v,2,'.',''):null;}
    private function splitList(string $v): array{return array_values(array_filter(array_map('trim',preg_split('/[|,;]/',$v))));}
    private function imageValues(string $source,string $v): array{if($v==='')return[];$parts=in_array($source,['woocommerce','etsy'],true)?preg_split($source==='etsy'?'/\s*(?:[|;]|,(?=\s*https?:\/\/))\s*/i':'/\s*,\s*(?=https?:\/\/)/i',$v):(($source==='shopify'||filter_var($v,FILTER_VALIDATE_URL))?[$v]:preg_split('/\s*[|;]\s*/',$v));return array_values(array_filter(array_map('trim',$parts),fn($url)=>filter_var($url,FILTER_VALIDATE_URL)&&in_array(strtolower(parse_url($url,PHP_URL_SCHEME)??''),['http','https'],true)));}
    private function canonicalUrl(string $url): string{$p=parse_url(trim($url));if(!$p||empty($p['host']))return trim($url);return strtolower($p['scheme']??'https').'://'.strtolower($p['host']).(isset($p['port'])?':'.$p['port']:'').($p['path']??'/').(isset($p['query'])?'?'.$p['query']:'');}
    private function nonDigitalType(?string $type): bool{return $type!==null&&$type!==''&&(bool)preg_match('/physical|service|membership|subscription|event|food/i',$type);}
    public function categoryId(array $names): ?int{foreach($names as $name){$row=DB::row('select id from categories where is_active=1 and (lower(name)=lower(?) or slug=?) limit 1',[$name,H::slug($name)]);if($row)return(int)$row['id'];}return null;}
    public function uniqueSlug(string $title): string{$base=mb_substr(H::slug($title)?:'imported-product',0,190);$slug=$base;$i=2;while(DB::row('select id from products where slug=?',[$slug])){$suffix='-'.$i++;$slug=mb_substr($base,0,190-mb_strlen($suffix)).$suffix;}return $slug;}
}
