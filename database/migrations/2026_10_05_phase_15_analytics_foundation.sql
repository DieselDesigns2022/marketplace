-- Phase 15: minimal, privacy-conscious foundations for future truthful attribution and search reporting.
ALTER TABLE orders
  ADD COLUMN traffic_source VARCHAR(40) NULL AFTER payment_mode,
  ADD COLUMN traffic_attributed_at TIMESTAMP NULL AFTER traffic_source,
  ADD KEY orders_traffic_source_paid(traffic_source,paid_at);

CREATE TABLE search_events (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  normalized_query VARCHAR(190) NOT NULL,
  result_count INT UNSIGNED NOT NULL,
  searched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY search_events_period(searched_at),
  KEY search_events_query_period(normalized_query,searched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
