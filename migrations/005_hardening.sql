-- Change documents.status default to 'pending' so any bare INSERT goes to review
ALTER TABLE documents
    MODIFY COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending';

-- Indexes for admin queries that filter/sort by status and submitted_at
ALTER TABLE documents
    ADD INDEX IF NOT EXISTS idx_doc_status       (status),
    ADD INDEX IF NOT EXISTS idx_doc_user_status  (user_id, status),
    ADD INDEX IF NOT EXISTS idx_doc_submitted    (submitted_at);

-- Webhook event log for replay-attack prevention
CREATE TABLE IF NOT EXISTS webhook_events (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    event_id      VARCHAR(100)    NOT NULL,
    event_type    VARCHAR(100)    NOT NULL,
    received_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_event_id (event_id),
    INDEX  idx_we_received (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prune old webhook events (keep 90 days — PayPal replay window is 5 minutes)
DELETE FROM webhook_events WHERE received_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
