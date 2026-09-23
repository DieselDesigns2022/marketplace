<?php
namespace App\Services;

final class ProductPublicationTransitionService
{
    public static function isPublishedStatus(?string $status): bool
    {
        return in_array($status, ['approved','published'], true);
    }

    public static function shouldDispatch(?string $before, ?string $after): bool
    {
        return !self::isPublishedStatus($before) && self::isPublishedStatus($after);
    }

    public static function dispatchAfterCommit(int $productId, ?string $before, ?string $after, ?callable $publisher = null): bool
    {
        if (!self::shouldDispatch($before, $after)) return false;
        try {
            ($publisher ?? static fn(int $id) => (new SocialPublishingService())->autoPostNewlyPublished($id))($productId);
        } catch (\Throwable $e) {
            NotificationService::reportFailure('social_auto_post_dispatch', $e);
        }
        return true;
    }
}
