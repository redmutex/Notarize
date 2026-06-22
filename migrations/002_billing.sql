ALTER TABLE users
    ADD COLUMN plan_type              ENUM('lite','pro','elite','payg') NOT NULL DEFAULT 'lite',
    ADD COLUMN plan_docs_used         INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN plan_period_start      DATE NOT NULL DEFAULT '2000-01-01',
    ADD COLUMN paypal_subscription_id VARCHAR(100) DEFAULT NULL,
    ADD COLUMN subscription_active    TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN is_admin               TINYINT(1) NOT NULL DEFAULT 0;

UPDATE users SET plan_period_start = CURDATE();

CREATE TABLE IF NOT EXISTS billing_history (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id              INT UNSIGNED  NOT NULL,
    amount               DECIMAL(8,2)  NOT NULL,
    currency             CHAR(3)       NOT NULL DEFAULT 'USD',
    plan_type            VARCHAR(20)   NOT NULL,
    paypal_txn_id        VARCHAR(100)  DEFAULT NULL,
    paypal_subscription  VARCHAR(100)  DEFAULT NULL,
    status               VARCHAR(50)   NOT NULL,
    created_at           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bh_user    (user_id),
    INDEX idx_bh_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE users SET is_admin = 1 WHERE email = 'danial@redmutex.com';
