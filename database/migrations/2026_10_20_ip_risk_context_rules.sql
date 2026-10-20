CREATE TABLE IF NOT EXISTS ip_risk_context_rules (
    id BIGINT NOT NULL AUTO_INCREMENT,
    ip_risk_term_id BIGINT NOT NULL,
    trigger_text VARCHAR(190) NOT NULL,
    normalized_trigger VARCHAR(190) NOT NULL,
    context_text VARCHAR(190) NOT NULL,
    normalized_context VARCHAR(190) NOT NULL,
    is_enabled TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ip_risk_context_rule (
        ip_risk_term_id,
        normalized_trigger,
        normalized_context
    ),
    KEY idx_ip_risk_context_term (ip_risk_term_id),
    KEY idx_ip_risk_context_enabled (is_enabled),
    CONSTRAINT fk_ip_risk_context_term
        FOREIGN KEY (ip_risk_term_id)
        REFERENCES ip_risk_terms(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO ip_risk_context_rules
(ip_risk_term_id,trigger_text,normalized_trigger,context_text,normalized_context,is_enabled)
SELECT id,'monster','monster','energy drink','energy drink',1
FROM ip_risk_terms
WHERE normalized_term='monster energy';
