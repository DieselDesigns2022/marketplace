<?php

namespace App\Services;

use RuntimeException;

/** HTTPS downloader with DNS pinning and validation for CSV-supplied previews. */
class RemoteProductImageService
{
    public const MAX_BYTES = 25 * 1024 * 1024;
    public const MAX_REDIRECTS = 3;
    private $resolver;
    private $transport;

    public function __construct(?callable $resolver = null, ?callable $transport = null)
    {
        $this->resolver = $resolver ?? [$this, 'resolve'];
        $this->transport = $transport ?? [$this, 'request'];
    }

    /** @return array{path:string,extension:string} Caller must unlink path. */
    public function fetch(string $url): array
    {
        for ($redirects = 0; ; $redirects++) {
            [$host,$port,$ips] = $this->validateDestination($url);
            $response = ($this->transport)($url,$host,$port,$ips,self::MAX_BYTES);
            $status = (int)($response['status'] ?? 0);
            if ($status >= 300 && $status < 400) {
                $this->cleanup($response['path'] ?? null);
                if ($redirects >= self::MAX_REDIRECTS) throw new RuntimeException('Remote image exceeded the redirect limit.');
                $location = trim((string)($response['location'] ?? ''));
                if ($location === '') throw new RuntimeException('Remote image returned an invalid redirect.');
                $url = $this->redirectUrl($url,$location);
                continue;
            }
            if ($status < 200 || $status >= 300) { $this->cleanup($response['path'] ?? null); throw new RuntimeException('Remote image server returned an unsuccessful response.'); }
            $path = (string)($response['path'] ?? '');
            if (!is_file($path)) throw new RuntimeException('Remote image could not be downloaded.');
            try { return ['path'=>$path,'extension'=>$this->validateImage($path)]; }
            catch (RuntimeException $e) { $this->cleanup($path); throw $e; }
        }
    }

    /** Process URLs independently and preserve source-order warning numbers. */
    public function processUrls(array $urls, callable $consumer): array
    {
        $warnings=[];$succeeded=0;
        foreach(array_values($urls) as $index=>$url){$download=null;try{$download=$this->fetch((string)$url);$consumer($download,$index);$succeeded++;}catch(RuntimeException $e){$warnings[]='Image '.($index+1).': '.$e->getMessage();}finally{if($download&&is_file($download['path']))@unlink($download['path']);}}
        return ['succeeded'=>$succeeded,'warnings'=>$warnings];
    }

    public function validateDestination(string $url): array
    {
        if ($url === '' || !filter_var($url,FILTER_VALIDATE_URL)) throw new RuntimeException('Image URL was malformed.');
        $parts=parse_url($url);
        if (strtolower((string)($parts['scheme']??''))!=='https') throw new RuntimeException('Image URL was not HTTPS.');
        if (isset($parts['user'])||isset($parts['pass'])) throw new RuntimeException('Image URL may not contain credentials.');
        $host=strtolower(rtrim(trim((string)($parts['host']??''),'[]'),'.'));
        if ($host===''||$host==='localhost'||str_ends_with($host,'.localhost')) throw new RuntimeException('Image host was not allowed.');
        $port=(int)($parts['port']??443); if($port<1||$port>65535) throw new RuntimeException('Image URL port was invalid.');
        $ips=filter_var($host,FILTER_VALIDATE_IP)?[$host]:(array)($this->resolver)($host);
        if(!$ips) throw new RuntimeException('Image host could not be resolved.');
        foreach(array_unique($ips) as $ip) if(!$this->isPublicIp((string)$ip)) throw new RuntimeException('Image host resolved to a blocked network address.');
        return [$host,$port,array_values(array_unique($ips))];
    }

    public function isPublicIp(string $ip): bool
    {
        if (!filter_var($ip,FILTER_VALIDATE_IP)) return false;
        if (filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)===false) return false;
        foreach(['100.64.0.0/10','192.0.0.0/24','192.0.2.0/24','198.18.0.0/15','198.51.100.0/24','203.0.113.0/24','224.0.0.0/4','240.0.0.0/4','2001:db8::/32'] as $cidr) if($this->inCidr($ip,$cidr)) return false;
        return true;
    }

    private function inCidr(string $ip,string $cidr): bool
    {
        [$network,$bits]=explode('/',$cidr); $packedIp=@inet_pton($ip); $packedNetwork=@inet_pton($network);
        if($packedIp===false||$packedNetwork===false||strlen($packedIp)!==strlen($packedNetwork)) return false;
        $bits=(int)$bits; $bytes=intdiv($bits,8); $remainder=$bits%8;
        if($bytes&&substr($packedIp,0,$bytes)!==substr($packedNetwork,0,$bytes)) return false;
        if(!$remainder)return true; $mask=(0xff<<(8-$remainder))&0xff;
        return (ord($packedIp[$bytes])&$mask)===(ord($packedNetwork[$bytes])&$mask);
    }

    private function resolve(string $host): array
    {
        $ips=[];
        foreach((array)@dns_get_record($host,DNS_A|DNS_AAAA) as $record) {
            if(isset($record['ip']))$ips[]=$record['ip']; if(isset($record['ipv6']))$ips[]=$record['ipv6'];
        }
        return array_values(array_unique($ips));
    }

    private function request(string $url,string $host,int $port,array $ips,int $max): array
    {
        $dir=app_path('storage/app/private/csv_imports/tmp');
        if(!is_dir($dir)&&!mkdir($dir,0750,true)) throw new RuntimeException('Remote image temporary storage was unavailable.');
        $path=$dir.'/'.bin2hex(random_bytes(20)).'.tmp';$out=null;$ch=null;$keep=false;
        try{
            $out=@fopen($path,'wb');if(!$out)throw new RuntimeException('Remote image temporary storage was unavailable.');
            $headers=[];$tooLarge=false;$written=0;$ch=curl_init($url);if($ch===false)throw new RuntimeException('Remote image download could not be initialized.');
            $configured=curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'AssetMoth-CSV-Image-Importer/1.0',CURLOPT_RESOLVE=>[$host.':'.$port.':'.$ips[0]],CURLOPT_HEADERFUNCTION=>static function($ch,$line)use(&$headers){$p=strpos($line,':');if($p!==false)$headers[strtolower(trim(substr($line,0,$p)))]=trim(substr($line,$p+1));return strlen($line);},CURLOPT_WRITEFUNCTION=>static function($ch,$data)use($out,$max,&$tooLarge,&$written){$written+=strlen($data);if($written>$max){$tooLarge=true;return 0;}return fwrite($out,$data);},]);
            if(!$configured)throw new RuntimeException('Remote image download could not be configured safely.');
            $ok=curl_exec($ch);$errno=curl_errno($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
            if($tooLarge)throw new RuntimeException('Remote image exceeded the 25MB preview-image limit.');
            if(!$ok)throw new RuntimeException(in_array($errno,[CURLE_OPERATION_TIMEDOUT,CURLE_COULDNT_CONNECT],true)?'Remote image server timed out or could not be reached.':'Remote image download failed safely.');
            $keep=true;return ['status'=>$status,'location'=>$headers['location']??null,'path'=>$path];
        }catch(RuntimeException $e){throw $e;}catch(\Throwable){throw new RuntimeException('Remote image download failed safely.');}
        finally{if(is_resource($out))fclose($out);if($ch!==null&&$ch!==false)curl_close($ch);if(!$keep&&is_file($path))@unlink($path);}
    }

    private function validateImage(string $path): string
    {
        $size=filesize($path); if($size===false||$size<1||$size>self::MAX_BYTES) throw new RuntimeException('Remote image exceeded the allowed size or was empty.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path)?:''; $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if(!isset($types[$mime])) throw new RuntimeException('Remote file was not a supported JPG, PNG, or WEBP image.');
        $bytes=@file_get_contents($path); $info=$bytes===false?false:@getimagesizefromstring($bytes);
        if(!$info||($info['mime']??'')!==$mime||$info[0]<1||$info[1]<1||$info[0]*$info[1]>40000000||!SellerReceiptService::hasExactImageContainer($bytes,$mime)) throw new RuntimeException('Remote image data was invalid or corrupt.');
        return $types[$mime];
    }

    private function redirectUrl(string $base,string $location): string
    {
        if(filter_var($location,FILTER_VALIDATE_URL)) return $location;
        if(str_starts_with($location,'//')) return 'https:'.$location;
        $p=parse_url($base); if(!isset($p['host'])||!str_starts_with($location,'/')) throw new RuntimeException('Remote image returned an invalid redirect.');
        return 'https://'.$p['host'].(isset($p['port'])?':'.$p['port']:'').$location;
    }
    private function cleanup(?string $path): void { if($path&&is_file($path))@unlink($path); }
}
