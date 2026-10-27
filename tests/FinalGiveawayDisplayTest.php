<?php
// Standalone rendering checks: intentionally no database/bootstrap or real session.
final class H {
    public static int $tokens=0;
    public static function user():array{return ['id'=>1,'role'=>'admin'];}
    public static function canAdmin(string $permission):bool{return true;}
    public static function csrf():string{++self::$tokens;return 'fixture-token';}
    public static function e(string $text):string{return htmlspecialchars($text,ENT_QUOTES,'UTF-8');}
}
$render=static function(bool $operator):string{
    $wheelOperator=$operator;
    ob_start();require __DIR__.'/../app/Views/public/final_giveaway.php';return ob_get_clean();
};
{
    H::$tokens=0;$public=$render(false);
    if(str_contains($public,'id="winner-remove"')||str_contains($public,'fixture-token')||H::$tokens!==0)throw new RuntimeException('Logged-in admin public view leaks operator controls.');
    $operator=$render(true);
    if(!str_contains($operator,'id="winner-remove"')||!str_contains($operator,'fixture-token'))throw new RuntimeException('Operator view missing removal controls.');
    if(str_contains($public,'--pink')||str_contains($operator,'--pink'))throw new RuntimeException('Old pink palette remains.');
}
echo "PASS: public/admin rendering separation for the live drawing\n";
