<?php
declare(strict_types=1);

namespace App;

use PDO;

class Billing
{
    private PDO $db;

    const PLANS = [
        'lite'  => ['name' => 'Lite',          'price' => 0,   'docs' => 1,   'label' => '1 document / month'],
        'pro'   => ['name' => 'Pro',           'price' => 10,  'docs' => 10,  'label' => '10 documents / month'],
        'elite' => ['name' => 'Elite',         'price' => 50,  'docs' => 100, 'label' => '100 documents / month'],
        'payg'  => ['name' => 'Pay As You Go', 'price' => 3,   'docs' => 0,   'label' => '$3 per document'],
    ];

    const PAYG_PRICE = '3.00';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Quota ────────────────────────────────────────────────────────

    public function canNotarize(int $userId): array
    {
        $user = $this->getUserBilling($userId);
        if (!$user) {
            return ['allowed' => false, 'reason' => 'User not found.'];
        }

        $plan = $user['plan_type'];

        if ($plan === 'payg') {
            return ['allowed' => true, 'payg' => true];
        }

        if (in_array($plan, ['pro', 'elite'], true) && !(bool)$user['subscription_active']) {
            return [
                'allowed' => false,
                'reason'  => 'Your subscription payment failed or was cancelled. Please update your billing to continue.',
                'upgrade' => true,
            ];
        }

        $limit = self::PLANS[$plan]['docs'];
        $used  = (int)$user['plan_docs_used'];

        if ($used >= $limit) {
            $planName = self::PLANS[$plan]['name'];
            return [
                'allowed' => false,
                'reason'  => "You've used all {$limit} document" . ($limit !== 1 ? 's' : '') . " on your {$planName} plan this month.",
                'upgrade' => true,
            ];
        }

        return ['allowed' => true, 'payg' => false, 'used' => $used, 'limit' => $limit];
    }

    public function recordNotarization(int $userId, ?string $paygTxnId = null): void
    {
        $stmt = $this->db->prepare("SELECT plan_type FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $plan = $stmt->fetchColumn();

        if ($plan === 'payg' && $paygTxnId) {
            $this->db->prepare(
                "INSERT INTO billing_history (user_id, amount, plan_type, paypal_txn_id, status)
                 VALUES (?, ?, 'payg', ?, 'completed')"
            )->execute([$userId, self::PAYG_PRICE, $paygTxnId]);
        } else {
            $this->db->prepare(
                "UPDATE users SET plan_docs_used = plan_docs_used + 1 WHERE id = ?"
            )->execute([$userId]);
        }
    }

    // ── User billing data ────────────────────────────────────────────

    public function getUserBilling(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, email, name, plan_type, plan_docs_used, plan_period_start,
                    paypal_subscription_id, subscription_active
             FROM users WHERE id = ?"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            return null;
        }

        $this->maybeResetMonthlyQuota($user);

        // Re-fetch if reset happened
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    private function maybeResetMonthlyQuota(array $user): void
    {
        if ($user['plan_type'] !== 'lite') {
            return;
        }
        $periodStart = new \DateTime($user['plan_period_start']);
        $now         = new \DateTime();
        if ($now->format('Y-m') !== $periodStart->format('Y-m')) {
            $this->db->prepare(
                "UPDATE users SET plan_docs_used = 0, plan_period_start = CURDATE() WHERE id = ?"
            )->execute([$user['id']]);
        }
    }

    public function getBillingHistory(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM billing_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 24"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    // ── PayPal REST API ──────────────────────────────────────────────

    private function paypalBase(): string
    {
        return (($_ENV['PAYPAL_MODE'] ?? 'sandbox') === 'live')
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function paypalToken(): string
    {
        $ch = curl_init($this->paypalBase() . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERPWD        => ($_ENV['PAYPAL_CLIENT_ID'] ?? '') . ':' . ($_ENV['PAYPAL_CLIENT_SECRET'] ?? ''),
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $resp = json_decode((string)curl_exec($ch), true);
        curl_close($ch);
        return $resp['access_token'] ?? '';
    }

    private function paypalRequest(string $method, string $path, array $body = []): array
    {
        $ch = curl_init($this->paypalBase() . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->paypalToken(),
                'Content-Type: application/json',
            ],
        ]);
        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $resp = json_decode((string)curl_exec($ch), true);
        curl_close($ch);
        return $resp ?? [];
    }

    public function verifyWebhookSignature(string $body, array $headers): bool
    {
        $webhookId = $_ENV['PAYPAL_WEBHOOK_ID'] ?? '';
        if (!$webhookId) {
            return false; // Reject if webhook ID not configured
        }

        $event = json_decode($body, true);
        if (!is_array($event)) {
            return false;
        }

        $resp = $this->paypalRequest('POST', '/v1/notifications/verify-webhook-signature', [
            'auth_algo'         => $headers['PAYPAL_AUTH_ALGO']         ?? '',
            'cert_url'          => $headers['PAYPAL_CERT_URL']          ?? '',
            'transmission_id'   => $headers['PAYPAL_TRANSMISSION_ID']   ?? '',
            'transmission_sig'  => $headers['PAYPAL_TRANSMISSION_SIG']  ?? '',
            'transmission_time' => $headers['PAYPAL_TRANSMISSION_TIME'] ?? '',
            'webhook_id'        => $webhookId,
            'webhook_event'     => $event,
        ]);

        return ($resp['verification_status'] ?? '') === 'SUCCESS';
    }

    // ── Subscriptions ────────────────────────────────────────────────

    public function activateSubscription(int $userId, string $subscriptionId, string $planType): bool
    {
        if (!in_array($planType, ['pro', 'elite'], true)) {
            return false;
        }

        $resp = $this->paypalRequest('GET', '/v1/billing/subscriptions/' . $subscriptionId);
        $status = $resp['status'] ?? '';

        if (!in_array($status, ['ACTIVE', 'APPROVED'], true)) {
            return false;
        }

        $this->db->prepare(
            "UPDATE users
             SET plan_type = ?, paypal_subscription_id = ?, subscription_active = 1,
                 plan_docs_used = 0, plan_period_start = CURDATE()
             WHERE id = ?"
        )->execute([$planType, $subscriptionId, $userId]);

        $this->db->prepare(
            "INSERT INTO billing_history (user_id, amount, plan_type, paypal_subscription, status)
             VALUES (?, ?, ?, ?, 'subscription_activated')"
        )->execute([$userId, self::PLANS[$planType]['price'], $planType, $subscriptionId]);

        return true;
    }

    public function cancelSubscription(int $userId): void
    {
        $stmt = $this->db->prepare(
            "SELECT paypal_subscription_id FROM users WHERE id = ?"
        );
        $stmt->execute([$userId]);
        $subId = $stmt->fetchColumn();

        if ($subId) {
            $this->paypalRequest(
                'POST',
                '/v1/billing/subscriptions/' . $subId . '/cancel',
                ['reason' => 'User requested cancellation']
            );
        }

        $this->db->prepare(
            "UPDATE users
             SET plan_type = 'lite', paypal_subscription_id = NULL, subscription_active = 1,
                 plan_docs_used = 0, plan_period_start = CURDATE()
             WHERE id = ?"
        )->execute([$userId]);
    }

    // ── PAYG Orders ──────────────────────────────────────────────────

    public function createPaygOrder(): array
    {
        return $this->paypalRequest('POST', '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount'      => ['currency_code' => 'USD', 'value' => self::PAYG_PRICE],
                'description' => 'Notarize — 1 document notarization',
            ]],
            'application_context' => [
                'brand_name'  => 'Notarize',
                'user_action' => 'PAY_NOW',
            ],
        ]);
    }

    public function capturePaygOrder(string $orderId): array
    {
        return $this->paypalRequest(
            'POST',
            '/v2/checkout/orders/' . $orderId . '/capture'
        );
    }

    // ── Webhook ──────────────────────────────────────────────────────

    public function handleWebhook(string $body, array $headers): void
    {
        $event = json_decode($body, true) ?? [];
        $type  = $event['event_type'] ?? '';

        if (str_starts_with($type, 'PAYMENT.SALE.COMPLETED') ||
            $type === 'BILLING.SUBSCRIPTION.ACTIVATED') {
            $this->onPaymentSuccess($event);
        } elseif (in_array($type, [
            'BILLING.SUBSCRIPTION.PAYMENT.FAILED',
            'BILLING.SUBSCRIPTION.SUSPENDED',
            'BILLING.SUBSCRIPTION.CANCELLED',
            'BILLING.SUBSCRIPTION.EXPIRED',
        ], true)) {
            $this->onSubscriptionFailed($event);
        }
    }

    private function onPaymentSuccess(array $event): void
    {
        $resource = $event['resource'] ?? [];
        $subId    = $resource['billing_agreement_id'] ?? ($resource['id'] ?? null);
        if (!$subId) {
            return;
        }

        $stmt = $this->db->prepare(
            "SELECT id, plan_type FROM users WHERE paypal_subscription_id = ?"
        );
        $stmt->execute([$subId]);
        $user = $stmt->fetch();
        if (!$user) {
            return;
        }

        $this->db->prepare(
            "UPDATE users
             SET subscription_active = 1, plan_docs_used = 0, plan_period_start = CURDATE()
             WHERE id = ?"
        )->execute([$user['id']]);

        $amount = $resource['amount']['total'] ?? (self::PLANS[$user['plan_type']]['price'] ?? 0);
        $txnId  = $resource['id'] ?? null;

        $this->db->prepare(
            "INSERT INTO billing_history (user_id, amount, plan_type, paypal_txn_id, paypal_subscription, status)
             VALUES (?, ?, ?, ?, ?, 'completed')"
        )->execute([$user['id'], $amount, $user['plan_type'], $txnId, $subId]);
    }

    private function onSubscriptionFailed(array $event): void
    {
        $resource = $event['resource'] ?? [];
        $subId    = $resource['id'] ?? null;
        if (!$subId) {
            return;
        }

        $this->db->prepare(
            "UPDATE users SET subscription_active = 0 WHERE paypal_subscription_id = ?"
        )->execute([$subId]);
    }

    // ── Admin stats ──────────────────────────────────────────────────

    public function getAdminStats(): array
    {
        return [
            'total_users'    => (int)$this->db->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn(),
            'total_docs'     => (int)$this->db->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
            'new_users_week' => (int)$this->db->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND is_admin = 0")->fetchColumn(),
            'new_docs_week'  => (int)$this->db->query("SELECT COUNT(*) FROM documents WHERE notarized_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
            'total_revenue'  => (float)$this->db->query("SELECT COALESCE(SUM(amount),0) FROM billing_history WHERE status IN ('completed','subscription_activated')")->fetchColumn(),
            'plan_dist'      => $this->db->query("SELECT plan_type, COUNT(*) as cnt FROM users WHERE is_admin = 0 GROUP BY plan_type")->fetchAll(PDO::FETCH_KEY_PAIR),
            'recent_users'   => $this->db->query("SELECT name, email, plan_type, subscription_active, created_at FROM users WHERE is_admin = 0 ORDER BY created_at DESC LIMIT 8")->fetchAll(),
            'recent_docs'    => $this->db->query("SELECT d.original_filename, d.file_size, d.notarized_at, u.name AS user_name FROM documents d JOIN users u ON d.user_id = u.id ORDER BY d.notarized_at DESC LIMIT 8")->fetchAll(),
            'docs_per_day'   => $this->db->query("SELECT DATE(notarized_at) AS day, COUNT(*) AS cnt FROM documents WHERE notarized_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(notarized_at) ORDER BY day ASC")->fetchAll(),
        ];
    }

    public function getAllUsers(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $total  = (int)$this->db->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn();
        $stmt   = $this->db->prepare(
            "SELECT u.id, u.name, u.email, u.plan_type, u.plan_docs_used,
                    u.subscription_active, u.created_at,
                    COUNT(d.id) AS total_docs
             FROM users u
             LEFT JOIN documents d ON d.user_id = u.id
             WHERE u.is_admin = 0
             GROUP BY u.id
             ORDER BY u.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$perPage, $offset]);
        return [
            'rows'  => $stmt->fetchAll(),
            'total' => $total,
            'pages' => max(1, (int)ceil($total / $perPage)),
            'page'  => $page,
        ];
    }

    public function getAllDocuments(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $total  = (int)$this->db->query("SELECT COUNT(*) FROM documents")->fetchColumn();
        $stmt   = $this->db->prepare(
            "SELECT d.id, d.original_filename, d.mime_type, d.file_size,
                    d.notarized_at, d.certificate_uuid,
                    u.name AS user_name, u.email AS user_email
             FROM documents d
             JOIN users u ON d.user_id = u.id
             ORDER BY d.notarized_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$perPage, $offset]);
        return [
            'rows'  => $stmt->fetchAll(),
            'total' => $total,
            'pages' => max(1, (int)ceil($total / $perPage)),
            'page'  => $page,
        ];
    }
}
