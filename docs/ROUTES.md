# Routes

Routes are registered in `public/index.php`.

## Public routes

| Method | Route | Controller | Protection |
|---|---|---|---|
| GET | `/` | `PublicController::home` | Public |
| GET | `/browse` | `PublicController::browse` | Public |
| GET | `/sell` | `PublicController::sell` | Public |
| GET | `/category/{slug}` | `PublicController::category` | Public |
| GET | `/product/{slug}` | `PublicController::product` | Public |
| GET | `/store/{slug}` | `PublicController::store` | Public |
| GET | `/about` | `PublicController::static` | Public |
| GET | `/contact` | `PublicController::static` | Public |
| GET | `/terms` | `PublicController::static` | Public |
| GET | `/privacy` | `PublicController::static` | Public |
| GET | `/licensing-help` | `PublicController::static` | Public |
| GET | `/seller-faq` | `PublicController::static` | Public |
| GET | `/buyer-faq` | `PublicController::static` | Public |

## Auth routes

| Method | Route | Controller | Protection |
|---|---|---|---|
| GET/POST | `/register` | `AuthController::register` | Public |
| GET/POST | `/login` | `AuthController::login` | Public |
| POST | `/logout` | `AuthController::logout` | Logged-in session action |
| GET | `/forgot-password` | `AuthController::forgot` | Public |
| GET/POST | `/account` | `AuthController::account` | Protected |

## Buyer routes

| Method | Route | Controller | Protection |
|---|---|---|---|
| GET | `/dashboard` | `BuyerController::home` | Protected |
| GET | `/dashboard/purchases` | `BuyerController::purchases` | Protected |
| GET | `/dashboard/downloads` | `BuyerController::downloads` | Protected |
| GET | `/dashboard/order/{id}` | `BuyerController::order` | Protected |
| GET | `/dashboard/wishlist` | `BuyerController::wishlist` | Protected |
| GET | `/dashboard/following` | `BuyerController::following` | Protected |
| GET | `/dashboard/referrals` | `BuyerController::referrals` | Protected |
| POST | `/product/{id}/wishlist` | `BuyerController::toggleWishlist` | Protected |
| POST | `/store/{id}/follow` | `BuyerController::toggleFollow` | Protected |

## Seller routes

| Method | Route | Controller | Protection |
|---|---|---|---|
| GET/POST | `/apply` | `SellerController::apply` | Logged-in application workflow |
| GET | `/seller` | `SellerController::home` | Seller/admin protected |
| GET/POST | `/seller/store` | `SellerController::storeSettings` | Seller/admin protected |
| GET | `/seller/products` | `SellerController::products` | Seller/admin protected |
| GET/POST | `/seller/product/new` | `SellerController::editProduct` | Seller/admin protected |
| GET/POST | `/seller/product/{id}` | `SellerController::editProduct` | Seller/admin protected |
| POST | `/seller/product/{id}/submit` | `SellerController::submitProduct` | Seller/admin protected |
| POST | `/seller/product/{id}/disable` | `SellerController::disableProduct` | Seller/admin protected |
| GET | `/seller/sales` | `SellerController::sales` | Seller/admin protected |
| GET | `/seller/referrals` | `SellerController::referrals` | Seller/admin protected |
| GET | `/seller/rank` | `SellerController::rank` | Seller/admin protected |

## Admin routes

| Method | Route | Controller | Protection |
|---|---|---|---|
| GET | `/admin` | `AdminController::home` | Admin protected |
| GET/POST | `/admin/users` | `AdminController::users` | Admin protected |
| GET/POST | `/admin/applications` | `AdminController::applications` | Admin protected |
| GET/POST | `/admin/applications/{id}` | `AdminController::applications` | Admin protected |
| GET/POST | `/admin/designers` | `AdminController::designers` | Admin protected |
| GET/POST | `/admin/products` | `AdminController::products` | Admin protected |
| GET/POST | `/admin/products/{id}` | `AdminController::productDetail` | Admin protected |
| GET/POST | `/admin/categories` | `AdminController::categories` | Admin protected |
| GET | `/admin/orders` | `AdminController::orders` | Admin protected |
| GET | `/admin/order/{id}` | `AdminController::orderDetail` | Admin protected |
| GET | `/admin/referrals` | `AdminController::referrals` | Admin protected |
| GET/POST | `/admin/homepage` | `AdminController::homepage` | Admin protected |
| GET/POST | `/admin/ads` | `AdminController::ads` | Admin protected |

## Cart, checkout, and download routes

| Method | Route | Controller | Protection |
|---|---|---|---|
| GET | `/cart` | `CartController::show` | Protected buyer/cart workflow |
| POST | `/cart/add/{id}` | `CartController::add` | Protected |
| POST | `/cart/remove/{id}` | `CartController::remove` | Protected |
| POST | `/cart/update` | `CartController::update` | Protected |
| GET/POST | `/checkout` | `CartController::checkout` | Protected |
| GET | `/download/{file}` | `BuyerController::download` | Protected purchased-file access |
