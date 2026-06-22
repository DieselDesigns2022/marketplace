<?php
require __DIR__ . '/../app/bootstrap.php';

use App\Core\Router;
use App\Controllers\PublicController;
use App\Controllers\AuthController;
use App\Controllers\BuyerController;
use App\Controllers\SellerController;
use App\Controllers\AdminController;
use App\Controllers\CartController;

$router = new Router();
$router->get('/', [PublicController::class, 'home']);
$router->get('/browse', [PublicController::class, 'browse']);
$router->get('/category/{slug}', [PublicController::class, 'category']);
$router->get('/product/{slug}', [PublicController::class, 'product']);
$router->post('/product/{id}/wishlist', [BuyerController::class, 'toggleWishlist']);
$router->get('/store/{slug}', [PublicController::class, 'store']);
$router->post('/store/{id}/follow', [BuyerController::class, 'toggleFollow']);
foreach (['about','contact','terms','privacy','licensing-help','seller-faq','buyer-faq'] as $page) $router->get('/'.$page, [PublicController::class, 'static']);

$router->match(['GET','POST'], '/register', [AuthController::class, 'register']);
$router->match(['GET','POST'], '/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/forgot-password', [AuthController::class, 'forgot']);
$router->match(['GET','POST'], '/account', [AuthController::class, 'account']);

$router->get('/dashboard', [BuyerController::class, 'home']);
$router->get('/dashboard/purchases', [BuyerController::class, 'purchases']);
$router->get('/dashboard/downloads', [BuyerController::class, 'downloads']);
$router->get('/download/{file}', [BuyerController::class, 'download']);
$router->get('/dashboard/wishlist', [BuyerController::class, 'wishlist']);
$router->get('/dashboard/following', [BuyerController::class, 'following']);
$router->get('/dashboard/referrals', [BuyerController::class, 'referrals']);
$router->match(['GET','POST'], '/apply', [SellerController::class, 'apply']);

$router->get('/seller', [SellerController::class, 'home']);
$router->match(['GET','POST'], '/seller/store', [SellerController::class, 'storeSettings']);
$router->get('/seller/products', [SellerController::class, 'products']);
$router->match(['GET','POST'], '/seller/product/new', [SellerController::class, 'editProduct']);
$router->match(['GET','POST'], '/seller/product/{id}', [SellerController::class, 'editProduct']);
$router->post('/seller/product/{id}/disable', [SellerController::class, 'disableProduct']);
$router->get('/seller/sales', [SellerController::class, 'sales']);
$router->get('/seller/referrals', [SellerController::class, 'referrals']);
$router->get('/seller/rank', [SellerController::class, 'rank']);

$router->get('/cart', [CartController::class, 'show']);
$router->post('/cart/add/{id}', [CartController::class, 'add']);
$router->post('/cart/remove/{id}', [CartController::class, 'remove']);
$router->post('/cart/license/{id}', [CartController::class, 'license']);
$router->match(['GET','POST'], '/checkout', [CartController::class, 'checkout']);

$router->get('/admin', [AdminController::class, 'home']);
$router->match(['GET','POST'], '/admin/users', [AdminController::class, 'users']);
$router->match(['GET','POST'], '/admin/applications', [AdminController::class, 'applications']);
$router->match(['GET','POST'], '/admin/applications/{id}', [AdminController::class, 'applications']);
$router->match(['GET','POST'], '/admin/designers', [AdminController::class, 'designers']);
$router->match(['GET','POST'], '/admin/products', [AdminController::class, 'products']);
$router->match(['GET','POST'], '/admin/categories', [AdminController::class, 'categories']);
$router->get('/admin/orders', [AdminController::class, 'orders']);
$router->get('/admin/referrals', [AdminController::class, 'referrals']);
$router->match(['GET','POST'], '/admin/homepage', [AdminController::class, 'homepage']);
$router->match(['GET','POST'], '/admin/ads', [AdminController::class, 'ads']);

$router->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
