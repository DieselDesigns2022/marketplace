INSERT INTO categories
(
    name,
    slug,
    description,
    is_active,
    sort_order
)
VALUES
(
    'Color Palettes & Swatches',
    'color-palettes-swatches',
    'Color palettes, swatch files, and digital color collections.',
    1,
    22
)
ON DUPLICATE KEY UPDATE
    name=VALUES(name),
    description=VALUES(description),
    is_active=1,
    sort_order=22;
