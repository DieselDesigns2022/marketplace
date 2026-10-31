ALTER TABLE designers
    ADD COLUMN IF NOT EXISTS country_code CHAR(2) NULL AFTER bio,
    ADD COLUMN IF NOT EXISTS region VARCHAR(120) NULL AFTER country_code;

UPDATE designers d
JOIN designer_applications da
  ON da.user_id=d.user_id
 AND da.status='approved'
SET
  d.country_code=
    CASE
      WHEN CHAR_LENGTH(TRIM(da.country))=2
        THEN UPPER(TRIM(da.country))
      WHEN UPPER(TRIM(da.country)) IN (
        'UNITED STATES',
        'UNITED STATES OF AMERICA',
        'USA',
        'U.S.',
        'U.S.A.'
      )
        THEN 'US'
      WHEN UPPER(TRIM(da.country)) IN (
        'UNITED KINGDOM',
        'UK',
        'GREAT BRITAIN'
      )
        THEN 'GB'
      ELSE d.country_code
    END,
  d.region=COALESCE(
    NULLIF(TRIM(da.region),''),
    d.region
  )
WHERE
  (d.country_code IS NULL OR d.country_code='')
  AND da.country IS NOT NULL
  AND TRIM(da.country)<>'';
