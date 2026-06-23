<?php
declare(strict_types=1);
require_once '../../config/config.php';
require_once '../../src/helpers.php';
use App\Auth;
use App\Billing;
use App\Database;

$auth     = new Auth();
$auth->requireAuth();
$authUser = $auth->user();

$db   = Database::getInstance();
$stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$authUser['id']]);
if (!(int)$stmt->fetchColumn()) {
    redirect('/dashboard.php');
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$billing = new Billing();
$result  = $billing->getAllTransactions($page, 30);

$totalRevenue = (float)$db->query(
    "SELECT COALESCE(SUM(amount),0) FROM billing_history WHERE status IN ('completed','subscription_activated')"
)->fetchColumn();

$pageTitle = 'Admin — Transactions';
require '../../templates/header.php';
?>

<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="fw-bold mb-0"><i class="bi bi-receipt me-2 text-primary"></i>Transactions</h2>
            <p class="text-muted mb-0 small">
                <?= number_format($result['total']) ?> transaction<?= $result['total'] !== 1 ? 's' : '' ?> &nbsp;·&nbsp;
                <strong>Total revenue: $<?= number_format($totalRevenue, 2) ?> USD</strong>
            </p>
        </div>
        <a href="/admin/" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>User</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>PayPal Ref</th>
                        <th>Status</th>
                        <th class="text-end">Invoice</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($result['rows'] as $txn): ?>
                    <?php
                    $planInfo = Billing::PLANS[$txn['plan_type']] ?? ['name' => strtoupper($txn['plan_type'])];
                    if ($txn['status'] === 'subscription_activated') {
                        $desc = $planInfo['name'] . ' subscription activated';
                    } elseif ($txn['status'] === 'completed' && $txn['plan_type'] === 'payg') {
                        $desc = 'Pay As You Go — 1 doc';
                    } else {
                        $desc = $planInfo['name'] . ' — ' . $txn['status'];
                    }
                    $statusBadge = match($txn['status']) {
                        'completed'              => '<span class="badge bg-success">Paid</span>',
                        'subscription_activated' => '<span class="badge bg-primary">Subscription</span>',
                        default                  => '<span class="badge bg-secondary">' . h($txn['status']) . '</span>',
                    };
                    ?>
                <tr>
                    <td class="font-monospace small text-muted">INV-<?= str_pad((string)$txn['id'], 6, '0', STR_PAD_LEFT) ?></td>
                    <td class="small text-nowrap"><?= h(date('M j, Y H:i', strtotime($txn['created_at']))) ?></td>
                    <td>
                        <a href="/admin/user.php?id=<?= (int)$txn['user_id'] ?>" class="text-decoration-none">
                            <div class="small fw-semibold"><?= h($txn['user_name']) ?></div>
                            <div class="text-muted" style="font-size:.72rem"><?= h($txn['user_email']) ?></div>
                        </a>
                    </td>
                    <td class="small"><?= h($desc) ?></td>
                    <td class="fw-semibold">$<?= number_format((float)$txn['amount'], 2) ?></td>
                    <td class="font-monospace" style="font-size:.7rem">
                        <?php $ref = $txn['paypal_txn_id'] ?? $txn['paypal_subscription'] ?? ''; ?>
                        <?= $ref ? h(mb_strimwidth($ref, 0, 22, '…')) : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td><?= $statusBadge ?></td>
                    <td class="text-end">
                        <a href="/billing/invoice.php?id=<?= (int)$txn['id'] ?>"
                           class="btn btn-sm btn-outline-secondary" target="_blank">
                            <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($result['pages'] > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="small text-muted">Page <?= $page ?> of <?= $result['pages'] ?></span>
            <div class="d-flex gap-1 flex-wrap">
                <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
                    <a href="?page=<?= $p ?>"
                       class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require '../../templates/footer.php'; ?>
