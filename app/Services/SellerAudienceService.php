<?php
namespace App\Services;

use App\Core\Database as DB;
use DomainException;

final class SellerAudienceService
{
    public static function reportForUser(
        int $userId,
        int $followersPage=1,
        int $favoritesPage=1
    ): array {
        if ($userId < 1) {
            throw new DomainException('Seller login required.');
        }

        $seller=DB::row(
            'SELECT id,display_name
             FROM designers
             WHERE user_id=? AND status="approved"
             LIMIT 1',
            [$userId]
        );

        if (!$seller) {
            throw new DomainException(
                'Approved seller account required.'
            );
        }

        $id=(int)$seller['id'];
        $followersPage=max(1,min(100000,$followersPage));
        $favoritesPage=max(1,min(100000,$favoritesPage));

        $limit=40;
        $fo=($followersPage-1)*$limit;
        $wo=($favoritesPage-1)*$limit;

        $counts=DB::row(
            'SELECT
              (SELECT COUNT(*)
               FROM follows
               WHERE designer_id=?) follower_count,
              (SELECT COUNT(*)
               FROM wishlists w
               JOIN products p ON p.id=w.product_id
               WHERE p.designer_id=?) favorite_count,
              (SELECT COUNT(DISTINCT w.user_id)
               FROM wishlists w
               JOIN products p ON p.id=w.product_id
               WHERE p.designer_id=?) favoriting_buyers',
            [$id,$id,$id]
        ) ?: [];

        $followers=DB::rows(
            'SELECT
                u.name,
                f.created_at,
                (
                    SELECT d2.store_slug
                    FROM designers d2
                    WHERE d2.user_id=u.id
                      AND d2.status="approved"
                      AND d2.store_slug IS NOT NULL
                      AND d2.store_slug<>""
                    LIMIT 1
                ) public_store_slug
             FROM follows f
             JOIN users u ON u.id=f.user_id
             WHERE f.designer_id=?
             ORDER BY f.created_at DESC,f.id DESC
             LIMIT 40 OFFSET '.$fo,
            [$id]
        );

        $favorites=DB::rows(
            'SELECT
                u.name,
                p.title,
                p.slug,
                w.created_at,
                (
                    SELECT d2.store_slug
                    FROM designers d2
                    WHERE d2.user_id=u.id
                      AND d2.status="approved"
                      AND d2.store_slug IS NOT NULL
                      AND d2.store_slug<>""
                    LIMIT 1
                ) public_store_slug
             FROM wishlists w
             JOIN products p ON p.id=w.product_id
             JOIN users u ON u.id=w.user_id
             WHERE p.designer_id=?
             ORDER BY w.created_at DESC,w.id DESC
             LIMIT 40 OFFSET '.$wo,
            [$id]
        );

        return [
            'seller'=>$seller,
            'follower_count'=>(int)($counts['follower_count']??0),
            'favorite_count'=>(int)($counts['favorite_count']??0),
            'favoriting_buyers'=>(int)($counts['favoriting_buyers']??0),
            'followers'=>$followers,
            'favorites'=>$favorites,
            'followers_page'=>$followersPage,
            'favorites_page'=>$favoritesPage,
            'page_size'=>$limit
        ];
    }
}
