ALTER TABLE waitlist_entries
    MODIFY interest_type VARCHAR(80) NOT NULL;

UPDATE waitlist_entries
SET interest_type = 'seller,buyer,tester'
WHERE interest_type = 'all';

UPDATE waitlist_entries
SET interest_type = 'seller,buyer'
WHERE interest_type = 'both';

ALTER TABLE waitlist_entries
    MODIFY interest_type SET('seller','buyer','tester') NOT NULL;
