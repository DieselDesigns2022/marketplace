<?php
namespace App\Services;

use App\Core\Database as DB;

/** Shared persistence/payment boundary used by cart and custom-service checkout. */
final class CheckoutOrderService
{
    public function calculateTax(array $items,array $billing,string $identity):array
    { return StripeService::calculateTax($items,$billing,$identity); }

    public function createOrder(array $v):int
    {
        DB::exec('insert into orders (user_id,status,payment_processor,payment_mode,payment_provider,payment_status,subtotal,tax_amount,tax_provider,tax_status,tax_liability_owner,tax_snapshot,tax_calculation_id,tax_transaction_status,billing_address_snapshot,credits_applied,coupon_discount,coupon_id,coupon_code,coupon_snapshot,total,fulfillment_status,phase9_foundation_order,stripe_currency,stripe_amount_total,stripe_paid_amount,platform_commission_total) values (?,"pending","stripe","checkout","stripe","pending",?, ?,"stripe_tax","calculated","platform",?, ?,"pending",?, ?,?, ?,?, ?,?,"pending",1,?, ?,?,?)',[$v['user_id'],$v['subtotal'],$v['tax_amount'],$v['tax_snapshot'],$v['tax_calculation_id'],$v['billing_snapshot'],$v['credits']??'0.00',$v['coupon_discount']??'0.00',$v['coupon_id']??null,$v['coupon_code']??null,$v['coupon_snapshot']??null,$v['total'],$v['currency'],$v['amount_cents'],$v['stripe_paid_amount']??$v['total'],$v['commission_total']??0]);
        return (int)DB::id();
    }

    public function addItem(array $v):int
    {
        DB::exec('insert into order_items (order_id,product_id,custom_service_id,product_title,product_slug,product_image,designer_id,seller_name,license_type,license_name,license_price,license_description,license_snapshot,fulfillment_type,delivery_instructions_snapshot,buyer_google_drive_email,manual_delivery_status,unit_price,commercial_license_price,total_price,commission_rate,purchased_file_version,seller_receipt_note_snapshot,seller_receipt_image_path_snapshot) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$v['order_id'],$v['product_id']??null,$v['custom_service_id']??null,$v['title'],$v['slug']??null,$v['image']??null,$v['designer_id'],$v['seller_name']??null,$v['license_type'],$v['license_name'],$v['license_price']??0,$v['license_description']??null,$v['license_snapshot']??null,$v['fulfillment_type'],$v['delivery_instructions']??null,$v['buyer_delivery_email']??null,$v['manual_status']??'not_applicable',$v['unit_price'],$v['commercial_price']??0,$v['total_price'],$v['commission_rate'],$v['file_version']??null,$v['receipt_note']??null,$v['receipt_image']??null]);
        return (int)DB::id();
    }

    public function checkout(array $order,array $items):array
    { return StripeService::createCheckoutSession($order,$items); }
}
