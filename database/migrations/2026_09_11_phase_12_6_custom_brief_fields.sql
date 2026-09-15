ALTER TABLE custom_design_services
    ADD COLUMN IF NOT EXISTS brief_fields JSON NULL AFTER buyer_instructions;

UPDATE custom_design_services
SET brief_fields='[
  {"key":"design_request","required":true},
  {"key":"required_text","required":true},
  {"key":"design_direction","required":true},
  {"key":"color_preferences","required":false},
  {"key":"dimensions","required":false},
  {"key":"file_format","required":false},
  {"key":"intended_use","required":false},
  {"key":"additional_notes","required":false}
]'
WHERE brief_fields IS NULL;
