START TRANSACTION;

-- Merge Tumbler Wraps + Libby Wraps into category ID 17.
UPDATE categories
SET
    name = 'Tumbler & Libby Wraps',
    slug = 'tumbler-libby-wraps',
    is_active = 1,
    sort_order = 17
WHERE id = 17;

-- Retire the old separate Libby Wraps category.
UPDATE categories
SET is_active = 0
WHERE id = 23;

-- Add approved new categories at the bottom.
INSERT INTO categories
(
    name,
    slug,
    description,
    is_active,
    sort_order
)
VALUES
('STL / 3D Print Files', 'stl-3d-print-files', NULL, 1, 28),
('Lineart & Outlines', 'lineart-outlines', NULL, 1, 29),
('$2 and Under Deals', '2-and-under-deals', NULL, 1, 30),
('Adobe Tools', 'adobe-tools', NULL, 1, 31)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    is_active = 1,
    sort_order = VALUES(sort_order);

COMMIT;
