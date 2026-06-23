-- Admin password for danial@redmutex.com (bcrypt cost=12 of "Gravity89!")
UPDATE users
SET password_hash = '$2y$12$Rweby56yzemWhQl7HrvbjeIiiqyBoXzMFs0Ip0bzE4XMlnXnJx0sO'
WHERE email = 'danial@redmutex.com';

-- Email verification
ALTER TABLE users
    ADD COLUMN email_verified     TINYINT(1)  NOT NULL DEFAULT 0     AFTER is_admin,
    ADD COLUMN email_verify_token VARCHAR(64) DEFAULT NULL            AFTER email_verified;

-- Admin is pre-verified
UPDATE users SET email_verified = 1 WHERE is_admin = 1;

-- Make signing columns nullable: populated at approval, not at submission
ALTER TABLE documents
    MODIFY COLUMN file_hash        CHAR(64)  NULL    DEFAULT NULL    COMMENT 'SHA-256 of original — set on approval',
    MODIFY COLUMN signature        TEXT      NULL    DEFAULT NULL    COMMENT 'RSA-SHA256 base64 — set on approval',
    MODIFY COLUMN certificate_uuid CHAR(36)  NULL    DEFAULT NULL,
    MODIFY COLUMN notarized_at     TIMESTAMP NULL    DEFAULT NULL;

-- Review workflow columns
ALTER TABLE documents
    ADD COLUMN status            ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER user_id,
    ADD COLUMN photo_id_filename VARCHAR(500) NULL DEFAULT NULL        AFTER stored_filename,
    ADD COLUMN selfie_filename   VARCHAR(500) NULL DEFAULT NULL        AFTER photo_id_filename,
    ADD COLUMN review_notes      TEXT         NULL DEFAULT NULL,
    ADD COLUMN reviewed_by       INT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN reviewed_at       TIMESTAMP    NULL DEFAULT NULL,
    ADD COLUMN submitted_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Existing documents are already approved/signed
UPDATE documents SET status = 'approved' WHERE file_hash IS NOT NULL;
