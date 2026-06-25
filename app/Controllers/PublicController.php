<?php

namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;

class PublicController
{
    public function home()
    {
        H::view('public/home', [
            'cats' => DB::rows('select * from categories where is_active=1 order by sort_order,name limit 8'),
            'products' => DB::rows(
                "select p.*,d.display_name,d.store_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image from products p join designers d on d.id=p.designer_id where p.status='approved' and p.is_featured=1 order by p.updated_at desc limit 8"
            ),
            'designers' => DB::rows('select * from designers where status="approved" and is_featured=1 limit 6'),
        ]);
    }

    public function sell()
    {
        H::view('public/sell');
    }

    public function browse()
    {
        $w = ["p.status='approved'"];
        $q = [];

        if ($_GET['q'] ?? '') {
            $w[] = '(p.title like ? or p.description like ?)';
            $q[] = '%' . $_GET['q'] . '%';
            $q[] = '%' . $_GET['q'] . '%';
        }

        if ($_GET['category'] ?? '') {
            $w[] = 'c.slug=?';
            $q[] = $_GET['category'];
        }

        if ($_GET['file_type'] ?? '') {
            $w[] = 'p.file_types like ?';
            $q[] = '%' . $_GET['file_type'] . '%';
        }

        if ($_GET['ai'] ?? '') {
            $w[] = 'p.ai_disclosure=?';
            $q[] = $_GET['ai'];
        }

        if (isset($_GET['pod']) && $_GET['pod'] !== '') {
            $w[] = 'p.pod_allowed=?';
            $q[] = (int) $_GET['pod'];
        }

        $sort = [
            'popular' => 'p.sales_count desc',
            'price_asc' => 'p.price asc',
            'price_desc' => 'p.price desc',
        ];
        $order = $sort[$_GET['sort'] ?? ''] ?? 'p.created_at desc';

        H::view('public/browse', [
            'products' => DB::rows(
                'select p.*,d.display_name,d.store_slug,c.name category_name,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image from products p join designers d on d.id=p.designer_id left join categories c on c.id=p.category_id where ' . implode(' and ', $w) . ' order by ' . $order,
                $q
            ),
            'cats' => DB::rows('select * from categories where is_active=1'),
        ]);
    }

    public function category($slug)
    {
        $cat = DB::row('select * from categories where slug=?', [$slug]) ?? H::abort(404);
        $_GET['category'] = $slug;
        $this->browse();
    }

    public function product($slug)
    {
        $p = DB::row(
            'select p.*,d.display_name,d.store_slug,c.name category_name,c.slug category_slug from products p join designers d on d.id=p.designer_id left join categories c on c.id=p.category_id where p.slug=? and p.status="approved"',
            [$slug]
        ) ?? H::abort(404);
        $title = $p['seo_title'] ?: $p['title'];
        $description = $p['seo_description'] ?: ($p['short_description'] ?: mb_substr(strip_tags($p['description']), 0, 160));
        $canonical = ($_ENV['APP_URL'] ?? '') . '/product/' . $p['slug'];
        $owned = H::user()
            ? (bool) DB::row(
                'select oi.id from order_items oi join orders o on o.id=oi.order_id where o.user_id=? and oi.product_id=? and o.status in ("paid","completed") limit 1',
                [H::user()['id'], $p['id']]
            )
            : false;

        H::view('public/product', [
            'p' => $p,
            'owned' => $owned,
            'files' => H::user() && $owned
                ? DB::rows('select id,original_name from product_files where product_id=? order by id', [$p['id']])
                : [],
            'images' => DB::rows('select * from product_images where product_id=? order by sort_order,id', [$p['id']]),
            'tags' => DB::rows(
                'select t.* from tags t join product_tags pt on pt.tag_id=t.id where pt.product_id=? order by t.name',
                [$p['id']]
            ),
            'more' => DB::rows(
                'select id,title,slug,price,(select image_path from product_images pi where pi.product_id=products.id order by pi.sort_order,pi.id limit 1) preview_image from products where designer_id=? and status="approved" and id<>? order by updated_at desc limit 4',
                [$p['designer_id'], $p['id']]
            ),
            'related' => DB::rows(
                'select id,title,slug,price,(select image_path from product_images pi where pi.product_id=products.id order by pi.sort_order,pi.id limit 1) preview_image from products where category_id <=> ? and status="approved" and id<>? order by updated_at desc limit 4',
                [$p['category_id'], $p['id']]
            ),
            'meta' => [
                'title' => $title,
                'description' => $description,
                'canonical' => $canonical,
                'og_title' => $title,
                'og_description' => $description,
            ],
        ]);
    }

    public function store($slug)
    {
        $d = DB::row('select * from designers where store_slug=? and status="approved"', [$slug]) ?? H::abort(404);
        $products = DB::rows(
            'select p.*,d.display_name,d.store_slug,c.name category_name,c.slug category_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image from products p join designers d on d.id=p.designer_id left join categories c on c.id=p.category_id where p.designer_id=? and p.status="approved" order by p.created_at desc',
            [$d['id']]
        );
        $followerCount = DB::row('select count(*) c from follows where designer_id=?', [$d['id']])['c'] ?? 0;
        DB::exec('update designers set follower_count=? where id=?', [$followerCount, $d['id']]);
        $isFollowing = false;
        $isOwner = false;

        if (H::user()) {
            $isFollowing = (bool) DB::row('select id from follows where user_id=? and designer_id=?', [H::user()['id'], $d['id']]);
            $isOwner = (int) $d['user_id'] === (int) H::user()['id'];
        }

        $title = $d['seo_title'] ?: ($d['display_name'] . ' | Digital Design Store');
        $description = $d['seo_description'] ?: ('Shop digital design files from ' . $d['display_name'] . ', including SVGs, PNGs, templates, sublimation designs, and more.');
        $canonical = ($_ENV['APP_URL'] ?? '') . '/store/' . $d['store_slug'];

        H::view('public/store', [
            'd' => $d,
            'products' => $products,
            'followers' => $followerCount,
            'isFollowing' => $isFollowing,
            'isOwner' => $isOwner,
            'productCount' => count($products),
            'salesCount' => $d['sales_count'] ?? array_sum(array_column($products, 'sales_count')),
            'meta' => [
                'title' => $title,
                'description' => $description,
                'canonical' => $canonical,
                'og_title' => $title,
                'og_description' => $description,
                'og_image' => $d['banner_path'] ?: $d['avatar_path'],
            ],
        ]);
    }

    public function static()
    {
        $page = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        H::view('static/page', ['title' => ucwords(str_replace('-', ' ', $page))]);
    }
}
