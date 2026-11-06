<?php
namespace App\Controllers;

use App\Core\Helpers as H;
use App\Services\SellerAudienceService;

final class SellerAudienceController
{
    public function index(): void
    {
        H::requireSeller();

        $user=H::user();

        if (!$user) {
            H::abort(403);
        }

        $followersPage=max(
            1,
            (int)($_GET['followers_page']??1)
        );

        $favoritesPage=max(
            1,
            (int)($_GET['favorites_page']??1)
        );

        try {
            $report=SellerAudienceService::reportForUser(
                (int)$user['id'],
                $followersPage,
                $favoritesPage
            );
        } catch (\DomainException $e) {
            H::abort(403);
            return;
        }

        header('X-Robots-Tag: noindex, nofollow');

        H::view('seller/followers',[
            'report'=>$report,
            'meta'=>[
                'title'=>'Followers & Favorites | Creative Moth',
                'robots'=>'noindex,nofollow'
            ]
        ]);
    }
}
