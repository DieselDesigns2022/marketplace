SET @wheel_index_columns = (
    SELECT GROUP_CONCAT(
        COLUMN_NAME
        ORDER BY SEQ_IN_INDEX
        SEPARATOR ','
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='prize_wheel_spins'
      AND INDEX_NAME='prize_wheel_order_campaign_uq'
);

SET @wheel_index_sql = CASE
    WHEN @wheel_index_columns =
        'campaign_key,qualifying_order_id,storefront_designer_id'
    THEN
        'SELECT 1'
    WHEN @wheel_index_columns IS NULL
    THEN
        'ALTER TABLE prize_wheel_spins
         ADD UNIQUE KEY prize_wheel_order_campaign_uq
         (
            campaign_key,
            qualifying_order_id,
            storefront_designer_id
         )'
    ELSE
        'ALTER TABLE prize_wheel_spins
         DROP INDEX prize_wheel_order_campaign_uq,
         ADD UNIQUE KEY prize_wheel_order_campaign_uq
         (
            campaign_key,
            qualifying_order_id,
            storefront_designer_id
         )'
END;

PREPARE wheel_index_stmt FROM @wheel_index_sql;
EXECUTE wheel_index_stmt;
DEALLOCATE PREPARE wheel_index_stmt;
