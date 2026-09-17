<?php

use App\Core\Helpers as H;

$role = H::user()['role'] ?? '';

$area = str_starts_with($path, '/buyer/messages')
    ? 'buyer'
    : (
        ($path === '/notifications' || $path === '/account')
            ? (
                $role === 'admin'
                    ? 'admin'
                    : ($role === 'designer' ? 'seller' : 'buyer')
            )
            : (
                str_starts_with($path, '/admin')
                    ? 'admin'
                    : (
                        str_starts_with($path, '/seller')
                            ? 'seller'
                            : 'buyer'
                    )
            )
    );

$isActive = static function(array $matches) use ($path): bool {
    foreach ($matches as $match) {
        if (
            $path === $match ||
            (
                str_ends_with($match, '/') &&
                str_starts_with($path, $match)
            )
        ) {
            return true;
        }
    }

    return false;
};

$groups = [];

if ($area === 'buyer') {

    $groups = [
        'Overview' => [
            ['/dashboard', 'Overview', ['/dashboard']],
            [
                '/buyer/messages',
                'Messages',
                ['/buyer/messages', '/buyer/messages/']
            ],
        ],

        'Orders & Files' => [
            [
                '/buyer/custom-orders',
                'Custom Designs',
                ['/buyer/custom-orders', '/buyer/custom-orders/']
            ],
            [
                '/dashboard/purchases',
                'Purchases',
                ['/dashboard/purchases', '/dashboard/order/']
            ],
            [
                '/dashboard/downloads',
                'Downloads',
                ['/dashboard/downloads']
            ],
        ],

        'Discover' => [
            [
                '/dashboard/wishlist',
                'Wishlist',
                ['/dashboard/wishlist']
            ],
            [
                '/dashboard/following',
                'Following',
                ['/dashboard/following']
            ],
            [
                '/dashboard/referrals',
                'Referrals',
                ['/dashboard/referrals']
            ],
        ],

        'Account' => [
            ['/account', 'Account', ['/account']],
            [
                '/notifications',
                'Notifications',
                ['/notifications']
            ],
        ],
    ];

} elseif (
    $area === 'seller' &&
    in_array($role, ['designer', 'admin'], true)
) {

    $groups = [
        'Overview' => [
            ['/seller', 'Seller Overview', ['/seller']],
            [
                '/seller/onboarding',
                'Readiness',
                ['/seller/onboarding']
            ],
            ['/seller/rank', 'Rank', ['/seller/rank']],
        ],

        'Listings' => [
            [
                '/seller/products',
                'Products',
                ['/seller/products', '/seller/product/']
            ],
            [
                '/seller/custom-designs',
                'Custom Designs',
                ['/seller/custom-designs', '/seller/custom-designs/']
            ],
            [
                '/seller/store',
                'Store & Licenses',
                ['/seller/store']
            ],
            [
                '/seller/coupons',
                'Coupons',
                ['/seller/coupons']
            ],
        ],

        'Orders' => [
            [
                '/seller/sales',
                'Sales / Orders',
                ['/seller/sales', '/seller/order-item/']
            ],
            [
                '/seller/custom-orders',
                'Custom Orders',
                ['/seller/custom-orders', '/seller/custom-orders/']
            ],
            [
                '/seller/messages',
                'Messages',
                ['/seller/messages', '/seller/messages/']
            ],
            [
                '/seller/receipt-settings',
                'Receipts',
                ['/seller/receipt-settings']
            ],
        ],

        'Business' => [
            ['/seller/promos', 'Promotions', ['/seller/promos']],
            [
                '/seller/stripe',
                'Payouts / Stripe',
                ['/seller/stripe']
            ],
            [
                '/seller/referrals',
                'Referrals',
                ['/seller/referrals']
            ],
            ['/account', 'Account', ['/account']],
            [
                '/notifications',
                'Notifications',
                ['/notifications']
            ],
        ],
    ];

} elseif ($area === 'admin' && $role === 'admin') {

    $groups = [
        'Overview' => [
            ['/admin', 'Admin Overview', ['/admin']],
            [
                '/admin/homepage',
                'Homepage',
                ['/admin/homepage']
            ],
            ['/admin/ads', 'Promotions', ['/admin/ads']],
            [
                '/admin/waitlist',
                'Waitlist',
                ['/admin/waitlist']
            ],
        ],

        'Marketplace' => [
            [
                '/admin/orders',
                'Orders',
                ['/admin/orders', '/admin/order/']
            ],
            [
                '/admin/custom-orders',
                'Custom Orders',
                ['/admin/custom-orders', '/admin/custom-orders/']
            ],
            [
                '/admin/products',
                'Products',
                ['/admin/products', '/admin/products/']
            ],
            [
                '/admin/categories',
                'Categories',
                ['/admin/categories']
            ],
            [
                '/admin/coupons',
                'Coupons',
                ['/admin/coupons', '/admin/coupons/']
            ],
        ],

        'Users & Safety' => [
            [
                '/admin/users',
                'Users',
                ['/admin/users']
            ],
            [
                '/admin/applications',
                'Applications',
                ['/admin/applications', '/admin/applications/']
            ],
            [
                '/admin/designers',
                'Sellers',
                ['/admin/designers']
            ],
            [
                '/admin/message-reports',
                'Message Reports',
                ['/admin/message-reports', '/admin/message-reports/']
            ],
            [
                '/admin/ip-risk-terms',
                'IP Risk Terms',
                ['/admin/ip-risk-terms', '/admin/ip-risk-terms/']
            ],
        ],

        'Business' => [
            [
                '/admin/payment-logs',
                'Payments / Stripe',
                ['/admin/payment-logs']
            ],
            [
                '/admin/referrals',
                'Credits & Referrals',
                ['/admin/referrals', '/admin/credits']
            ],
            [
                '/admin/email-campaigns',
                'Email Campaigns',
                ['/admin/email-campaigns', '/admin/email-campaigns/']
            ],
            ['/account', 'Account', ['/account']],
            [
                '/notifications',
                'Notifications',
                ['/notifications']
            ],
        ],
    ];
}

?>

<?php if ($groups): ?>

<nav
    class="dashboard-nav-grouped"
    aria-label="<?=H::e(ucfirst($area))?> dashboard sections"
>

    <?php foreach ($groups as $groupName => $links): ?>

        <?php
        $groupActive = false;
        $activeLabel = '';

        foreach ($links as [$href, $label, $matches]) {
            if ($isActive($matches)) {
                $groupActive = true;
                $activeLabel = $label;
                break;
            }
        }
        ?>

        <details
            class="dashboard-nav-group <?=$groupActive ? 'has-active' : ''?>"
        >

            <summary>
                <span class="dashboard-nav-group-name">
                    <?=H::e($groupName)?>
                </span>

                <?php if ($activeLabel): ?>
                    <small><?=H::e($activeLabel)?></small>
                <?php endif; ?>

                <span
                    class="dashboard-nav-chevron"
                    aria-hidden="true"
                >⌄</span>
            </summary>

            <div class="dashboard-nav-menu">

                <?php foreach ($links as [$href, $label, $matches]): ?>

                    <?php $active = $isActive($matches); ?>

                    <a
                        href="<?=$href?>"
                        class="<?=$active ? 'active' : ''?>"
                        <?=$active ? 'aria-current="page"' : ''?>
                    >
                        <?=H::e($label)?>
                    </a>

                <?php endforeach; ?>

            </div>

        </details>

    <?php endforeach; ?>

</nav>

<script>
(() => {
    const navs = document.querySelectorAll('.dashboard-nav-grouped');

    navs.forEach((nav) => {
        const groups = nav.querySelectorAll('.dashboard-nav-group');

        groups.forEach((group) => {
            group.addEventListener('toggle', () => {
                if (!group.open) return;

                groups.forEach((other) => {
                    if (other !== group) {
                        other.open = false;
                    }
                });
            });
        });
    });

    document.addEventListener('click', (event) => {
        document
            .querySelectorAll('.dashboard-nav-grouped')
            .forEach((nav) => {
                if (nav.contains(event.target)) return;

                nav
                    .querySelectorAll('.dashboard-nav-group[open]')
                    .forEach((group) => {
                        group.open = false;
                    });
            });
    });
})();
</script>

<?php endif; ?>
