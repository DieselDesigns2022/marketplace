<?php
namespace App\Services;

final class ResendEmailTransport
{
    /** @var null|callable */
    private static $testClient = null;

    public static function setTestClient(?callable $client): void
    {
        self::$testClient = $client;
    }

    public static function send(string $recipient,string $subject,string $html): void
    {
        $apiKey=trim((string)($_ENV['RESEND_API_KEY']??''));
        $fromAddress=trim((string)($_ENV['MAIL_FROM_ADDRESS']??''));
        $fromName=trim((string)($_ENV['MAIL_FROM_NAME']??'Asset Moth'));
        if($apiKey===''||!filter_var($fromAddress,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$fromName.$fromAddress))throw new \RuntimeException('Resend mail transport is not configured');
        if(!EmailQueueService::validEnvelope($recipient,$subject))throw new \RuntimeException('Invalid email envelope');

        $payload=['from'=>($fromName!==''?$fromName.' <'.$fromAddress.'>':$fromAddress),'to'=>[$recipient],'subject'=>$subject,'html'=>$html];
        $headers=['Authorization: Bearer '.$apiKey,'Content-Type: application/json'];
        [$status,$response]=self::$testClient!==null
            ?(self::$testClient)('https://api.resend.com/emails',$headers,$payload)
            :self::request($headers,$payload);
        $decoded=is_string($response)?json_decode($response,true):$response;
        if($status<200||$status>=300||!is_array($decoded)||trim((string)($decoded['id']??''))==='')throw new \RuntimeException('Resend did not accept the email');
    }

    private static function request(array $headers,array $payload): array
    {
        if(!function_exists('curl_init'))throw new \RuntimeException('Resend mail transport is unavailable');
        $body=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        $ch=curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>$body,CURLOPT_TIMEOUT=>30]);
        $response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        if($response===false){curl_close($ch);throw new \RuntimeException('Resend API request failed');}
        curl_close($ch);return [$status,$response];
    }
}
