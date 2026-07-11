-- Remove DTF Files from active marketplace category lists.
-- Idempotent: safe to run more than once.

UPDATE categories
SET is_active=0, updated_at=now()
WHERE slug IN ('dtf','dtf-files')
   OR lower(name) IN ('dtf','dtf files');
