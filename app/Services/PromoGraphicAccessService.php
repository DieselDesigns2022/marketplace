<?php
namespace App\Services;
use App\Core\Helpers as H;
final class PromoGraphicAccessService
{
    public static function can(string $permission): bool
    {
        return match($permission) {
            'promotions.view'=>H::canAdmin('promotions.view') || H::canAdmin('social_content.view'),
            'promotions.manage'=>H::canAdmin('promotions.manage') || (H::canAdmin('social_content.view') && H::canAdmin('social_content.manage')),
            default=>false,
        };
    }
}
