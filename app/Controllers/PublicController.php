<?php

namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\LicenseService;

class PublicController
{
    private array $staticPages = [
        'about' => ['About Asset Moth', 'Asset Moth is a digital design marketplace built for buyers shopping creative files and independent designers selling downloadable products.', 'AboutPage'],
        'contact' => ['Contact Asset Moth', 'Find general support, buyer support, seller support, account help, order guidance, download help, and marketplace contact information for Asset Moth.', 'ContactPage'],
        'terms' => ['Terms & Conditions', 'Read the rules for using Asset Moth, including accounts, digital purchases, downloads, licensing, seller responsibilities, refunds, and marketplace content.', 'WebPage'],
        'privacy' => ['Privacy Policy', 'Learn how Asset Moth collects, uses, protects, and stores marketplace account, buyer, seller, order, upload, and download information.', 'PrivacyPolicy'],
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

    private const BROWSE_PAGE_SIZE = 12;

    public function home(): void
    {
        $cats = DB::rows('select * from categories where is_active=1 order by sort_order,name limit 8');
        $products = DB::rows("select p.*,d.display_name,d.store_slug,c.name category_name,c.slug category_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image from products p join designers d on d.id=p.designer_id left join categories c on c.id=p.category_id where p.status='approved' and d.status='approved' and p.is_featured=1 order by p.updated_at desc,p.id desc limit 8");
        $recentProducts = DB::rows("select p.*,d.display_name,d.store_slug,c.name category_name,c.slug category_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image from products p join designers d on d.id=p.designer_id left join categories c on c.id=p.category_id where p.status='approved' and d.status='approved' order by p.created_at desc,p.id desc limit 8");
        $designers = DB::rows('select * from designers where status="approved" and is_featured=1 order by updated_at desc,id desc limit 6');
        $schema = ['@context'=>'https://schema.org','@type'=>'WebSite','name'=>'Asset Moth','url'=>H::baseUrl(),'potentialAction'=>['@type'=>'SearchAction','target'=>H::canonical('/browse').'?q={search_term_string}','query-input'=>'required name=search_term_string']];
        H::view('public/home', ['cats'=>$cats, 'products'=>$products, 'recentProducts'=>$recentProducts, 'designers'=>$designers, 'meta'=>$this->pageMeta('Asset Moth', H::DEFAULT_DESCRIPTION, '/', $schema)]);
    }

    private function searchTerms(string $query): array
    {
        $parts = preg_split('/\s+/', trim($query)) ?: [];
        $terms = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part) >= 2) $terms[] = mb_substr($part, 0, 60);
        }
        return array_values(array_unique($terms));
    }

    private function browseState(?string $categorySlug = null): array
    {
        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'category' => $categorySlug ?: trim((string)($_GET['category'] ?? '')),
            'ai' => trim((string)($_GET['ai'] ?? '')),
            'pod' => trim((string)($_GET['pod'] ?? '')),
            'creator' => trim((string)($_GET['creator'] ?? '')),
            'min_price' => trim((string)($_GET['min_price'] ?? '')),
            'max_price' => trim((string)($_GET['max_price'] ?? '')),
            'featured' => trim((string)($_GET['featured'] ?? '')),
            'new' => trim((string)($_GET['new'] ?? '')),
            'file_type' => trim((string)($_GET['file_type'] ?? '')),
            'commercial' => trim((string)($_GET['commercial'] ?? '')),
        ];
        $sort = (string)($_GET['sort'] ?? 'newest');
        $allowedSorts = ['relevance','newest','oldest','price_asc','price_desc','title_asc','title_desc','featured'];
        if (!in_array($sort, $allowedSorts, true)) $sort = $filters['q'] !== '' ? 'relevance' : 'newest';
        if ($sort === 'relevance' && $filters['q'] === '') $sort = 'newest';
        $page = max(1, (int)($_GET['page'] ?? 1));
        return ['filters'=>$filters, 'sort'=>$sort, 'page'=>$page, 'terms'=>$this->searchTerms($filters['q'])];
    }

    private function browseQuery(array $filters, array $terms, string $sort, int $page): array
    {
        [$where, $whereParams] = $this->browseCountWhere($filters, $terms);
        $score = [];
        $scoreParams = [];
        if ($filters['q'] !== '') {
            $like = '%'.$filters['q'].'%';
            $score[] = 'case when p.title = ? then 120 else 0 end'; $scoreParams[] = $filters['q'];
            $score[] = 'case when p.title like ? then 80 else 0 end'; $scoreParams[] = $like;
            $score[] = 'case when p.tags_text like ? or exists (select 1 from product_tags pts join tags ts on ts.id=pts.tag_id where pts.product_id=p.id and ts.name like ?) then 55 else 0 end'; $scoreParams[] = $like; $scoreParams[] = $like;
            $score[] = 'case when c.name like ? then 45 else 0 end'; $scoreParams[] = $like;
            $score[] = 'case when d.display_name like ? or d.store_slug like ? then 45 else 0 end'; $scoreParams[] = $like; $scoreParams[] = $like;
            $score[] = 'case when p.short_description like ? or p.description like ? then 18 else 0 end'; $scoreParams[] = $like; $scoreParams[] = $like;
        }
        foreach ($terms as $term) {
            $like = '%'.$term.'%';
            $score[] = 'case when p.title like ? then 25 else 0 end'; $scoreParams[] = $like;
            $score[] = 'case when p.tags_text like ? or exists (select 1 from product_tags ptx join tags tx on tx.id=ptx.tag_id where ptx.product_id=p.id and tx.name like ?) then 20 else 0 end'; $scoreParams[] = $like; $scoreParams[] = $like;
            $score[] = 'case when c.name like ? or d.display_name like ? then 15 else 0 end'; $scoreParams[] = $like; $scoreParams[] = $like;
            $score[] = 'case when p.short_description like ? or p.description like ? then 6 else 0 end'; $scoreParams[] = $like; $scoreParams[] = $like;
        }
        $relevance = $score ? implode(' + ', $score) : '0';
        $from = ' from products p join designers d on d.id=p.designer_id left join categories c on c.id=p.category_id where '.implode(' and ', $where);
        $total = (int)(DB::row('select count(*) c'.$from, $whereParams)['c'] ?? 0);
        $pages = max(1, (int)ceil($total / self::BROWSE_PAGE_SIZE));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * self::BROWSE_PAGE_SIZE;
        $orders = [
            'relevance' => 'relevance desc,p.is_featured desc,p.created_at desc,p.id desc',
            'newest' => 'p.created_at desc,p.id desc',
            'oldest' => 'p.created_at asc,p.id asc',
            'price_asc' => 'p.price asc,p.created_at desc,p.id desc',
            'price_desc' => 'p.price desc,p.created_at desc,p.id desc',
            'title_asc' => 'p.title asc,p.created_at desc,p.id desc',
            'title_desc' => 'p.title desc,p.created_at desc,p.id desc',
            'featured' => 'p.is_featured desc,p.created_at desc,p.id desc',
        ];
        $order = $orders[$sort] ?? $orders['newest'];
        $sql = 'select p.*,d.display_name,d.store_slug,c.name category_name,c.slug category_slug,('.$relevance.') relevance,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image'.$from.' order by '.$order.' limit '.self::BROWSE_PAGE_SIZE.' offset '.$offset;
        return ['products'=>DB::rows($sql, array_merge($scoreParams, $whereParams)), 'total'=>$total, 'page'=>$page, 'pages'=>$pages, 'pageSize'=>self::BROWSE_PAGE_SIZE];
    }

    private function browseCountWhere(array $filters, array $terms): array
    {
        $where = ["p.status='approved'", "d.status='approved'"];
        $params = [];
        if ($filters['q'] !== '') {
            $like = '%'.$filters['q'].'%';
            $where[] = '(p.title like ? or p.short_description like ? or p.description like ? or p.tags_text like ? or p.file_types like ? or c.name like ? or d.display_name like ? or d.store_slug like ? or exists (select 1 from product_tags ptq join tags tq on tq.id=ptq.tag_id where ptq.product_id=p.id and tq.name like ?))';
            array_push($params, $like, $like, $like, $like, $like, $like, $like, $like, $like);
        }
        foreach ($terms as $term) {
            $like = '%'.$term.'%';
            $where[] = '(p.title like ? or p.short_description like ? or p.description like ? or p.tags_text like ? or p.file_types like ? or c.name like ? or d.display_name like ? or exists (select 1 from product_tags ptt join tags tt on tt.id=ptt.tag_id where ptt.product_id=p.id and tt.name like ?))';
            array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
        }
        if ($filters['category'] !== '') { $where[] = 'c.slug=?'; $params[] = $filters['category']; }
        if (in_array($filters['ai'], ['No AI Used','AI Assisted','AI Generated'], true)) { $where[] = 'p.ai_disclosure=?'; $params[] = $filters['ai']; }
        if ($filters['pod'] === '0' || $filters['pod'] === '1') { $where[] = 'p.pod_allowed=?'; $params[] = (int)$filters['pod']; }
        if ($filters['creator'] !== '') { $where[] = 'd.store_slug=?'; $params[] = $filters['creator']; }
        if ($filters['min_price'] !== '' && is_numeric($filters['min_price'])) { $where[] = 'p.price>=?'; $params[] = max(0, (float)$filters['min_price']); }
        if ($filters['max_price'] !== '' && is_numeric($filters['max_price'])) { $where[] = 'p.price<=?'; $params[] = max(0, (float)$filters['max_price']); }
        if ($filters['min_price'] !== '' && $filters['max_price'] !== '' && is_numeric($filters['min_price']) && is_numeric($filters['max_price']) && (float)$filters['min_price'] > (float)$filters['max_price']) $where[] = '1=0';
        if ($filters['featured'] === '1') $where[] = 'p.is_featured=1';
        if ($filters['new'] === '1') $where[] = 'p.created_at >= date_sub(now(), interval 30 day)';
        if ($filters['file_type'] !== '') { $where[] = 'p.file_types like ?'; $params[] = '%'.$filters['file_type'].'%'; }
        if ($filters['commercial'] === '1') $where[] = 'p.commercial_license_enabled=1';
        return [$where, $params];
    }

    public function browse(): void
    {
        $state = $this->browseState();
        $result = $this->browseQuery($state['filters'], $state['terms'], $state['sort'], $state['page']);
        $cats = DB::rows('select * from categories where is_active=1 order by sort_order,name');
        $creators = DB::rows('select id,display_name,store_slug from designers where status="approved" order by display_name limit 100');
        $fileTypes = DB::rows('select distinct p.file_types from products p join designers d on d.id=p.designer_id where p.status="approved" and d.status="approved" and p.file_types is not null and p.file_types<>"" order by p.file_types limit 100');
        $filtered = array_filter($state['filters'], fn($v) => $v !== '') || $state['sort'] !== 'newest' || $state['page'] > 1;
        $schema = ['@context'=>'https://schema.org','@type'=>'CollectionPage','name'=>'Browse digital designs','url'=>H::canonical('/browse')];
        H::view('public/browse', ['products'=>$result['products'], 'cats'=>$cats, 'creators'=>$creators, 'fileTypes'=>$fileTypes, 'filters'=>$state['filters'], 'sort'=>$state['sort'], 'pagination'=>$result, 'category'=>null, 'meta'=>$this->pageMeta('Browse Digital Designs', 'Browse digital designs, templates, graphics, fonts, and creative files from independent designers on Asset Moth.', '/browse', $schema, $filtered ? ['robots'=>'noindex,follow'] : [])]);
    }

    public function category($slug): void
    {
        $cat = DB::row('select * from categories where slug=? and is_active=1', [$slug]) ?? H::abort(404);
        $state = $this->browseState($slug);
        $result = $this->browseQuery($state['filters'], $state['terms'], $state['sort'], $state['page']);
        $cats = DB::rows('select * from categories where is_active=1 order by sort_order,name');
        $creators = DB::rows('select id,display_name,store_slug from designers where status="approved" order by display_name limit 100');
        $fileTypes = DB::rows('select distinct p.file_types from products p join designers d on d.id=p.designer_id where p.status="approved" and d.status="approved" and p.file_types is not null and p.file_types<>"" order by p.file_types limit 100');
        $description = $cat['description'] ?: 'Shop approved digital design products in the '.$cat['name'].' category on Asset Moth.';
        $schema = ['@context'=>'https://schema.org','@type'=>'CollectionPage','name'=>$cat['name'],'description'=>$description,'url'=>H::canonical('/category/'.$cat['slug'])];
        $filtered = array_filter(array_diff_key($state['filters'], ['category'=>true]), fn($v) => $v !== '') || $state['sort'] !== 'newest' || $state['page'] > 1;
        H::view('public/browse', ['products'=>$result['products'], 'cats'=>$cats, 'creators'=>$creators, 'fileTypes'=>$fileTypes, 'filters'=>$state['filters'], 'sort'=>$state['sort'], 'pagination'=>$result, 'category'=>$cat, 'meta'=>$this->pageMeta($cat['name'].' Digital Designs', mb_substr(strip_tags($description), 0, 160), '/category/'.$cat['slug'], $schema, $filtered ? ['robots'=>'noindex,follow'] : [])]);
    }

    public function sell(): void
    {
        $schema = ['@context'=>'https://schema.org','@type'=>'WebPage','name'=>'Sell on Asset Moth','url'=>H::canonical('/sell')];
        H::view('public/sell', ['meta'=>$this->pageMeta('Sell Digital Designs', 'Apply to sell digital designs through a reviewed storefront on Asset Moth.', '/sell', $schema)]);
    }

    public function product($slug): void
    {
        $p = DB::row('select p.*,d.display_name,d.store_slug,c.name category_name,c.slug category_slug from products p join designers d on d.id=p.designer_id left join categories c on c.id=p.category_id where p.slug=? and p.status="approved" and d.status="approved"', [$slug]) ?? H::abort(404);
        $images = DB::rows('select * from product_images where product_id=? order by sort_order,id', [$p['id']]);
        $preview = $images[0]['image_path'] ?? '';
        $title = $p['seo_title'] ?: $p['title'];
        $description = $p['seo_description'] ?: ($p['short_description'] ?: mb_substr(strip_tags($p['description']), 0, 160));
        $licenses = LicenseService::productLicenses($p);
        $defaultLicense = $licenses[0] ?? ['price' => (float)($p['price'] ?? 0), 'license_key' => 'personal'];
        foreach ($licenses as $license) if (!empty($license['is_default'])) $defaultLicense = $license;
        $schema = ['@context'=>'https://schema.org','@type'=>'Product','name'=>$p['title'],'description'=>$description,'url'=>H::canonical('/product/'.$p['slug'])];
        if ($preview) $schema['image'] = H::assetUrl($preview);
        if ($p['price'] !== null) $schema['offers'] = ['@type'=>'Offer','price'=>(string)$p['price'],'priceCurrency'=>'USD','url'=>H::canonical('/product/'.$p['slug'])];
        $owned = H::user() ? (bool)DB::row('select oi.id from order_items oi join orders o on o.id=oi.order_id where o.user_id=? and oi.product_id=? and o.status in ("paid","completed") limit 1', [H::user()['id'], $p['id']]) : false;
        H::view('public/product', ['p'=>$p,'owned'=>$owned,'licenses'=>$licenses,'defaultLicense'=>$defaultLicense,'files'=>H::user()&&$owned?DB::rows('select id,original_name from product_files where product_id=? order by id',[$p['id']]):[],'images'=>$images,'tags'=>DB::rows('select t.* from tags t join product_tags pt on pt.tag_id=t.id where pt.product_id=? order by t.name',[$p['id']]),'more'=>DB::rows('select p.id,p.title,p.slug,p.price,p.ai_disclosure,p.pod_allowed,p.commercial_license_enabled,p.file_types,p.is_featured,p.created_at,d.display_name,d.store_slug,c.name category_name,c.slug category_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image from products p join designers d on d.id=p.designer_id left join categories c on c.id=p.category_id where p.designer_id=? and p.status="approved" and d.status="approved" and p.id<>? order by p.updated_at desc,p.id desc limit 4',[$p['designer_id'],$p['id']]),'related'=>DB::rows('select p.id,p.title,p.slug,p.price,p.ai_disclosure,p.pod_allowed,p.commercial_license_enabled,p.file_types,p.is_featured,p.created_at,d.display_name,d.store_slug,c.name category_name,c.slug category_slug,(select image_path from product_images pi where pi.product_id=p.id order by pi.sort_order,pi.id limit 1) preview_image,(case when p.category_id <=> ? then 20 else 0 end + (select count(*)*10 from product_tags pt where pt.product_id=p.id and pt.tag_id in (select tag_id from product_tags where product_id=?))) related_score from products p join designers d on d.id=p.designer_id left join categories c on c.id=p.category_id where p.status="approved" and d.status="approved" and p.id<>? and (p.category_id <=> ? or exists (select 1 from product_tags pt2 where pt2.product_id=p.id and pt2.tag_id in (select tag_id from product_tags where product_id=?))) order by related_score desc,p.updated_at desc,p.id desc limit 4',[$p['category_id'],$p['id'],$p['id'],$p['category_id'],$p['id']]),'meta'=>$this->pageMeta($title, $description, '/product/'.$p['slug'], $schema, ['og_type'=>'product','og_image'=>$preview,'twitter_image'=>$preview])]);
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
