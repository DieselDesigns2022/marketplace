INSERT INTO ip_risk_terms
(term,normalized_term,category,internal_note,is_enabled)
SELECT 'Halloweentown','halloweentown','movie',
       'Added during IP-risk catalog cleanup 2026-10-20.',1
WHERE NOT EXISTS (
    SELECT 1 FROM ip_risk_terms WHERE normalized_term='halloweentown'
);

INSERT INTO ip_risk_terms
(term,normalized_term,category,internal_note,is_enabled)
SELECT 'Alani Nu','alani nu','brand',
       'Added during IP-risk catalog cleanup 2026-10-20.',1
WHERE NOT EXISTS (
    SELECT 1 FROM ip_risk_terms WHERE normalized_term='alani nu'
);

INSERT INTO ip_risk_terms
(term,normalized_term,category,internal_note,is_enabled)
SELECT 'The Great Pumpkin','the great pumpkin','franchise',
       'Added during IP-risk catalog cleanup 2026-10-20.',1
WHERE NOT EXISTS (
    SELECT 1 FROM ip_risk_terms WHERE normalized_term='the great pumpkin'
);

INSERT IGNORE INTO ip_risk_term_aliases
(ip_risk_term_id,alias,normalized_alias,is_enabled)
SELECT id,'green guy','green guy',1
FROM ip_risk_terms
WHERE normalized_term='the grinch';

INSERT IGNORE INTO ip_risk_term_aliases
(ip_risk_term_id,alias,normalized_alias,is_enabled)
SELECT id,'Great Pumpkin','great pumpkin',1
FROM ip_risk_terms
WHERE normalized_term='the great pumpkin';

INSERT IGNORE INTO ip_risk_term_aliases
(ip_risk_term_id,alias,normalized_alias,is_enabled)
SELECT id,'Alani','alani',1
FROM ip_risk_terms
WHERE normalized_term='alani nu';

UPDATE ip_risk_term_aliases
SET is_enabled=0
WHERE normalized_alias='the green guy'
  AND ip_risk_term_id=(
      SELECT id FROM ip_risk_terms
      WHERE normalized_term='the grinch'
      LIMIT 1
  );
