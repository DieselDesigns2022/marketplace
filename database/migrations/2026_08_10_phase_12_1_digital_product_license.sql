-- Phase 12.1: optional per-product Digital Product License permission.
-- The upsert is repeatable and preserves every product/seller configuration row.
INSERT INTO license_types (license_key,name,description,sort_order,is_active) VALUES
('digital-product','Digital Product License','A buyer may use the purchased asset as part of a finished digital product they create and sell. The purchased asset must be modified or incorporated into a new completed design before it is sold as a digital product.

The buyer may not resell the seller''s original purchased file as-is, redistribute it, share it, sublicense it, give it away, or upload or provide the original file as-is.

A finished product may not give the end customer access to the seller''s original source file as a separate or extractable file. The buyer may not represent the seller''s original asset as their own standalone digital file.',105,1)
ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),sort_order=VALUES(sort_order),is_active=VALUES(is_active);
