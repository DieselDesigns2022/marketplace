ALTER TABLE seller_ip_warnings
  DROP INDEX seller_ip_warning_number,
  ADD INDEX seller_ip_warning_number (designer_id,warning_number);
