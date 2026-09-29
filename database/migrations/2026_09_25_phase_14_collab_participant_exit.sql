ALTER TABLE collab_participants
MODIFY membership_status
ENUM('host','invited','requested','accepted','denied','left','removed')
NOT NULL;
