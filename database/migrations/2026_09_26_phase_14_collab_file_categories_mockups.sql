ALTER TABLE collab_files
  MODIFY file_kind ENUM('contribution','terms','mockup') NOT NULL,
  ADD COLUMN category_id BIGINT NULL AFTER file_kind,
  ADD KEY collab_file_category_idx(category_id),
  ADD CONSTRAINT collab_file_category_fk
    FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE RESTRICT;
