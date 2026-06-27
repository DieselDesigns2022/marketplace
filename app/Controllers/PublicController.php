<?php

namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;

class PublicController
{
    private array $staticPages = [
        'about' => ['About Asset Moth', 'Learn how Asset Moth connects buyers with independent designers selling digital downloads.', 'AboutPage'],
        'contact' => ['Contact Asset Moth', 'Find buyer, seller, account, order, download, and general support guidance for Asset Moth.', 'ContactPage'],
        'terms' => ['Terms of Use', 'Read practical marketplace terms for accounts, purchases, licenses, seller responsibilities, moderation, and support.', 'WebPage'],
        'privacy' => ['Privacy Policy', 'Learn what information Asset Moth collects, how it is used, and the choices available to marketplace users.', 'PrivacyPolicy'],
        'licensing-help' => ['Digital Product Licensing Help', 'Understand personal use, commercial use, print-on-demand permission, and digital resale limits on Asset Moth.', 'WebPage'],
        'buyer-faq' => ['Buyer FAQ', 'Answers for buyers about finding products, accounts, purchases, downloads, licenses, POD use, refunds, and support.', 'FAQPage'],
        'seller-faq' => ['Seller FAQ', 'Answers for sellers about applying, storefronts, product review, SEO fields, licensing, AI disclosure, uploads, and support.', 'FAQPage'],
    ];

    private function pageMeta(string $title, string $description, string $path, array $schema = [], array $extra = []): array
    {
        $fullTitle = $title === H::SITE_NAME ? H::SITE_NAME . ' | Digital Design Marketplace' : $title . ' | ' . H::SITE_NAME;

        return array_merge([
            'title' => $fullTitle,
            'description' => $description,
            'canonical' => H::canonical($path),
            'og_title' => $fullTitle,
            'og_description' => $description,
            'twitter_title' => $fullTitle,
            'twitter_description' => $description,
            'schema' => $schema,
        ], $extra);
    }

    public function home(): void
    {
        $cats = DB::rows('select * from categories where is_active=1 order by sort_order,name limit 8');
        $products = DB::rows("select p.*,d.display_name,d.store_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image from products p join designers d on d.id=p.designer_id where p.status='approved' and p.is_featured=1 order by p.updated_at desc limit 8");
        $designers = DB::rows('select * from designers where status="approved" and is_featured=1 limit 6');
        $schema = ['@context'=>'https://schema.org','@type'=>'WebSite','name'=>'Asset Moth','url'=>H::baseUrl(),'potentialAction'=>['@type'=>'SearchAction','target'=>H::canonical('/browse').'?q={search_term_string}','query-input'=>'required name=search_term_string']];
        H::view('public/home', ['cats'=>$cats, 'products'=>$products, 'designers'=>$designers, 'meta'=>$this->pageMeta('Asset Moth', H::DEFAULT_DESCRIPTION, '/', $schema)]);
    }

    public function browse(): void
    {
        $w = ["p.status='approved'"];
        $q = [];
        foreach (['q','category','file_type','ai','pod'] as $key) {
            if (($_GET[$key] ?? '') === '') continue;
            if ($key === 'q') { $w[]='(p.title like ? or p.description like ?)'; $q[]='%'.$_GET[$key].'%'; $q[]='%'.$_GET[$key].'%'; }
            if ($key === 'category') { $w[]='c.slug=?'; $q[]=$_GET[$key]; }
            if ($key === 'file_type') { $w[]='p.file_types like ?'; $q[]='%'.$_GET[$key].'%'; }
            if ($key === 'ai') { $w[]='p.ai_disclosure=?'; $q[]=$_GET[$key]; }
            if ($key === 'pod') { $w[]='p.pod_allowed=?'; $q[]=(int)$_GET[$key]; }
        }
        $sort = ['popular'=>'p.sales_count desc','price_asc'=>'p.price asc','price_desc'=>'p.price desc'];
        $order = $sort[$_GET['sort'] ?? ''] ?? 'p.created_at desc';
        $products = DB::rows('select p.*,d.display_name,d.store_slug,c.name category_name,c.slug category_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image from products p join designers d on d.id=p.designer_id left join categories c on c.id=p.category_id where '.implode(' and ', $w).' order by '.$order, $q);
        $cats = DB::rows('select * from categories where is_active=1 order by sort_order,name');
        $filtered = array_intersect(array_keys($_GET), ['q','file_type','sort','ai','pod','category']) !== [];
        $schema = ['@context'=>'https://schema.org','@type'=>'CollectionPage','name'=>'Browse digital designs','url'=>H::canonical('/browse')];
        H::view('public/browse', ['products'=>$products, 'cats'=>$cats, 'category'=>null, 'meta'=>$this->pageMeta('Browse Digital Designs', 'Browse digital designs, templates, graphics, fonts, and creative files from independent designers on Asset Moth.', '/browse', $schema, $filtered ? ['robots'=>'noindex,follow'] : [])]);
    }

    public function sell(): void
    {
        $schema = ['@context'=>'https://schema.org','@type'=>'WebPage','name'=>'Sell on Asset Moth','url'=>H::canonical('/sell')];
        H::view('public/sell', ['meta'=>$this->pageMeta('Sell Digital Designs', 'Apply to sell digital designs through a reviewed storefront on Asset Moth.', '/sell', $schema)]);
    }

    public function category($slug): void
    {
        $cat = DB::row('select * from categories where slug=? and is_active=1', [$slug]) ?? H::abort(404);
        $products = DB::rows('select p.*,d.display_name,d.store_slug,c.name category_name,c.slug category_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image from products p join designers d on d.id=p.designer_id left join categories c on c.id=p.category_id where p.status="approved" and c.slug=? order by p.created_at desc', [$slug]);
        $cats = DB::rows('select * from categories where is_active=1 order by sort_order,name');
        $description = $cat['description'] ?: 'Shop approved digital design products in the '.$cat['name'].' category on Asset Moth.';
        $schema = ['@context'=>'https://schema.org','@type'=>'CollectionPage','name'=>$cat['name'],'description'=>$description,'url'=>H::canonical('/category/'.$cat['slug'])];
        H::view('public/browse', ['products'=>$products, 'cats'=>$cats, 'category'=>$cat, 'meta'=>$this->pageMeta($cat['name'].' Digital Designs', mb_substr(strip_tags($description), 0, 160), '/category/'.$cat['slug'], $schema)]);
    }

    public function product($slug): void
    {
        $p = DB::row('select p.*,d.display_name,d.store_slug,c.name category_name,c.slug category_slug from products p join designers d on d.id=p.designer_id left join categories c on c.id=p.category_id where p.slug=? and p.status="approved"', [$slug]) ?? H::abort(404);
        $images = DB::rows('select * from product_images where product_id=? order by sort_order,id', [$p['id']]);
        $preview = $images[0]['image_path'] ?? '';
        $title = $p['seo_title'] ?: $p['title'];
        $description = $p['seo_description'] ?: ($p['short_description'] ?: mb_substr(strip_tags($p['description']), 0, 160));
        $schema = ['@context'=>'https://schema.org','@type'=>'Product','name'=>$p['title'],'description'=>$description,'url'=>H::canonical('/product/'.$p['slug'])];
        if ($preview) $schema['image'] = H::assetUrl($preview);
        if ($p['price'] !== null) $schema['offers'] = ['@type'=>'Offer','price'=>(string)$p['price'],'priceCurrency'=>'USD','url'=>H::canonical('/product/'.$p['slug'])];
        $owned = H::user() ? (bool)DB::row('select oi.id from order_items oi join orders o on o.id=oi.order_id where o.user_id=? and oi.product_id=? and o.status in ("paid","completed") limit 1', [H::user()['id'], $p['id']]) : false;
        H::view('public/product', ['p'=>$p,'owned'=>$owned,'files'=>H::user()&&$owned?DB::rows('select id,original_name from product_files where product_id=? order by id',[$p['id']]):[],'images'=>$images,'tags'=>DB::rows('select t.* from tags t join product_tags pt on pt.tag_id=t.id where pt.product_id=? order by t.name',[$p['id']]),'more'=>DB::rows('select p.id,p.title,p.slug,p.price,p.ai_disclosure,p.pod_allowed,d.display_name,d.store_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image from products p join designers d on d.id=p.designer_id where p.designer_id=? and p.status="approved" and p.id<>? order by p.updated_at desc limit 4',[$p['designer_id'],$p['id']]),'related'=>DB::rows('select p.id,p.title,p.slug,p.price,p.ai_disclosure,p.pod_allowed,d.display_name,d.store_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image from products p join designers d on d.id=p.designer_id where p.category_id <=> ? and p.status="approved" and p.id<>? order by p.updated_at desc limit 4',[$p['category_id'],$p['id']]),'meta'=>$this->pageMeta($title, $description, '/product/'.$p['slug'], $schema, ['og_type'=>'product','og_image'=>$preview,'twitter_image'=>$preview])]);
    }

    public function store($slug): void
    {
        $d = DB::row('select * from designers where store_slug=? and status="approved"', [$slug]) ?? H::abort(404);
        $products = DB::rows('select p.*,d.display_name,d.store_slug,c.name category_name,c.slug category_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image from products p join designers d on d.id=p.designer_id left join categories c on c.id=p.category_id where p.designer_id=? and p.status="approved" order by p.created_at desc', [$d['id']]);
        $followerCount = DB::row('select count(*) c from follows where designer_id=?', [$d['id']])['c'] ?? 0;
        DB::exec('update designers set follower_count=? where id=?', [$followerCount, $d['id']]);
        $isFollowing = H::user() ? (bool)DB::row('select id from follows where user_id=? and designer_id=?', [H::user()['id'], $d['id']]) : false;
        $isOwner = H::user() ? (int)$d['user_id'] === (int)H::user()['id'] : false;
        $title = $d['seo_title'] ?: ($d['display_name'].' Digital Design Store');
        $description = $d['seo_description'] ?: ('Shop approved digital design files from '.$d['display_name'].' on Asset Moth.');
        $image = $d['banner_path'] ?: $d['avatar_path'];
        $schema = ['@context'=>'https://schema.org','@type'=>'ProfilePage','name'=>$d['display_name'],'description'=>$description,'url'=>H::canonical('/store/'.$d['store_slug'])];
        if ($image) $schema['image'] = H::assetUrl($image);
        H::view('public/store', ['d'=>$d,'products'=>$products,'followers'=>$followerCount,'isFollowing'=>$isFollowing,'isOwner'=>$isOwner,'productCount'=>count($products),'salesCount'=>$d['sales_count']??array_sum(array_column($products,'sales_count')),'meta'=>$this->pageMeta($title, $description, '/store/'.$d['store_slug'], $schema, ['og_image'=>$image,'twitter_image'=>$image])]);
    }

    public function static(): void
    {
        $page = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        if (!isset($this->staticPages[$page])) H::abort(404);
        [$title, $description, $type] = $this->staticPages[$page];
        $schema = ['@context'=>'https://schema.org','@type'=>$type,'name'=>$title,'description'=>$description,'url'=>H::canonical('/'.$page)];
        H::view('static/page', ['page'=>$page, 'title'=>$title, 'meta'=>$this->pageMeta($title, $description, '/'.$page, $schema)]);
    }

    public function sitemap(): never
    {
        header('Content-Type: application/xml; charset=utf-8');
        $urls = ['/', '/browse', '/sell', '/about', '/contact', '/terms', '/privacy', '/licensing-help', '/buyer-faq', '/seller-faq'];
        foreach (DB::rows('select slug from categories where is_active=1 order by sort_order,name') as $c) $urls[] = '/category/'.$c['slug'];
        foreach (DB::rows('select slug from products where status="approved" order by updated_at desc') as $p) $urls[] = '/product/'.$p['slug'];
        foreach (DB::rows('select store_slug from designers where status="approved" order by updated_at desc') as $d) $urls[] = '/store/'.$d['store_slug'];
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach (array_unique($urls) as $url) echo '  <url><loc>'.H::e(H::canonical($url))."</loc></url>\n";
        echo "</urlset>\n";
        exit;
    }
}
