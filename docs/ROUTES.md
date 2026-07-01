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

## SEO and indexing routes

| Method | Route | Controller | Protection | Indexing |
|---|---|---|---|---|
| GET | `/sitemap.xml` | `PublicController::sitemap` | Public | XML sitemap of public indexable URLs only |

Filtered `/browse` URLs are public but render `noindex,follow` and canonicalize to `/browse`. Private auth, account, dashboard, seller, admin, cart, checkout, apply, and download routes are treated as noindex/private workflow routes.


## Phase 7 route note

No new routes were added for Phase 7. Header and footer polish use existing routes only: `/`, `/browse`, `/sell`, `/about`, `/contact`, `/terms`, `/privacy`, `/buyer-faq`, `/seller-faq`, and `/licensing-help`.

## Phase 8 browse/search query parameters

`GET /browse` and `GET /category/{slug}` support SQL-backed public browsing of approved products only. Supported query parameters are:

- `q`: keyword search. Multi-word terms are split and matched partially across title, short description, description, tags, category, file types, and creator/store fields.
- `category`: category slug on `/browse`; category route paths keep their category in the path.
- `creator`: approved designer `store_slug`.
- `min_price` / `max_price`: numeric price range. Inverted ranges return an empty result safely.
- `ai`: one of the existing product AI disclosure values.
- `pod`: `1` for POD allowed or `0` for no POD.
- `featured`: `1` for featured products.
- `new`: `1` for products created in the last 30 days.
- `file_type`: matches existing `products.file_types` text.
- `commercial`: `1` for the existing `commercial_license_enabled` product flag.
- `sort`: `relevance`, `newest`, `oldest`, `price_asc`, `price_desc`, `title_asc`, `title_desc`, or `featured`.
- `page`: one-based pagination page.

Filtered or paginated browse/category URLs remain public but should render `noindex,follow` with canonical URLs to the base browse/category route.

## Phase 8.5 licensing behavior
- `GET|POST /seller/product/new` and `GET|POST /seller/product/{id}` include seller license configuration fields for included permissions. Personal is always included; sellers enable or disable additional included permissions such as Commercial, POD, Wholesale, Fabric, VA, and Extended Commercial.
- `GET /product/{slug}` displays enabled included license options with product base price only and posts one or more selected license keys to `POST /cart/add/{id}`.
- `POST /cart/update` can update a cart item's selected included licenses; cart storage uses normalized selected license keys.
- Checkout validates every selected license server-side and order items snapshot all selected included licenses with `license_price` retained as `0.00` compatibility data.
- `GET /dashboard/order/{id}` and `GET /admin/order/{id}` show purchased included license snapshot details without buyer-facing license pricing.
- `GET|POST /admin/products/{id}` shows enabled product license permissions for admin review.
