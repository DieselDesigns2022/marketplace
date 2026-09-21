CREATE TABLE social_platform_integrations (
  platform ENUM('facebook','instagram','pinterest') PRIMARY KEY,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  disabled_reason VARCHAR(500) NULL,
  updated_by BIGINT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT social_integrations_admin_fk FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO social_platform_integrations(platform,is_enabled) VALUES
 ('facebook',1),('instagram',1),('pinterest',1);

CREATE TABLE seller_social_connections (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  designer_id BIGINT NOT NULL,
  platform ENUM('facebook','instagram','pinterest') NOT NULL,
  external_account_id VARCHAR(190) NOT NULL,
  external_account_name VARCHAR(190) NULL,
  encrypted_credentials MEDIUMTEXT NOT NULL,
  credential_expires_at DATETIME NULL,
  connection_status ENUM('connected','reconnect_required','permission_error','revoked') NOT NULL DEFAULT 'connected',
  auto_post_enabled TINYINT(1) NOT NULL DEFAULT 0,
  pinterest_board_id VARCHAR(190) NULL,
  pinterest_board_name VARCHAR(190) NULL,
  last_error VARCHAR(500) NULL,
  connected_at DATETIME NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY seller_social_platform_unique(designer_id,platform),
  CONSTRAINT seller_social_designer_fk FOREIGN KEY(designer_id) REFERENCES designers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE social_post_logs (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  designer_id BIGINT NOT NULL,
  product_id BIGINT NOT NULL,
  connection_id BIGINT NULL,
  platform ENUM('facebook','instagram','pinterest') NOT NULL,
  trigger_type ENUM('manual','automatic','retry') NOT NULL,
  image_id BIGINT NOT NULL,
  caption TEXT NOT NULL,
  status ENUM('attempting','succeeded','failed') NOT NULL DEFAULT 'attempting',
  platform_post_id VARCHAR(190) NULL,
  error_code VARCHAR(100) NULL,
  error_message VARCHAR(500) NULL,
  attempted_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  automatic_key VARCHAR(190) NULL,
  retry_of_id BIGINT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY social_post_automatic_unique(automatic_key),
  KEY social_post_seller_idx(designer_id,attempted_at),
  KEY social_post_product_idx(product_id,platform),
  CONSTRAINT social_post_designer_fk FOREIGN KEY(designer_id) REFERENCES designers(id) ON DELETE RESTRICT,
  CONSTRAINT social_post_connection_fk FOREIGN KEY(connection_id) REFERENCES seller_social_connections(id) ON DELETE SET NULL,
  CONSTRAINT social_post_retry_fk FOREIGN KEY(retry_of_id) REFERENCES social_post_logs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
