ALTER TABLE custom_design_services
ADD COLUMN turnaround_min_days SMALLINT UNSIGNED NOT NULL DEFAULT 1
AFTER price;

UPDATE custom_design_services
SET turnaround_min_days = turnaround_days;

ALTER TABLE custom_orders
ADD COLUMN turnaround_min_days SMALLINT UNSIGNED NOT NULL DEFAULT 1
AFTER agreed_price;

UPDATE custom_orders
SET turnaround_min_days = turnaround_days;
