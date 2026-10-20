UPDATE ip_risk_terms
SET is_enabled=0,
    internal_note=CONCAT(
        COALESCE(internal_note,''),
        CASE WHEN COALESCE(internal_note,'')='' THEN '' ELSE '\n' END,
        'Disabled 2026-10-20: common platform/service reference creates false positives in seller instructions and compatibility text.'
    )
WHERE normalized_term IN (
    'adobe',
    'amazon',
    'ebay',
    'instagram',
    'paypal',
    'pinterest',
    'shopify',
    'tiktok',
    'venmo'
);
