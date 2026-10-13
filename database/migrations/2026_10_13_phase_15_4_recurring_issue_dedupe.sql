-- Phase 15.4 recurring newsletter issue deduplication.
--
-- A recurring definition may generate only one campaign snapshot
-- for a given scheduled UTC occurrence.
--
-- NULL recurring_definition_id values remain unrestricted, so
-- one-off newsletters are unaffected.

ALTER TABLE email_campaigns
    ADD UNIQUE KEY uq_email_campaign_recurring_issue (
        recurring_definition_id,
        scheduled_for
    );
