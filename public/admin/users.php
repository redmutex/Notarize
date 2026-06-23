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
$result  = $billing->getAllUsers($page, 25);

$pageTitle = 'Admin — Users';
require '../../templates/header.php';
?>

<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Users</h2>
            <p class="text-muted mb-0 small"><?= number_format($result['total']) ?> registered users</p>
        </div>
        <a href="/admin/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Name / Email</th>
                        <th>Plan</th>
                        <th>Docs Used</th>
                        <th>Sub. Active</th>
                        <th>Total Docs</th>
                        <th>Joined</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($result['rows'] as $u): ?>
                <?php
                    $planInfo = Billing::PLANS[$u['plan_type']] ?? ['docs' => 0, 'name' => $u['plan_type']];
                    $limit    = $planInfo['docs'];
                ?>
                <tr>
                    <td class="text-muted small"><?= (int)$u['id'] ?></td>
                    <td>
                        <div class="fw-semibold"><?= h($u['name']) ?></div>
                        <div class="text-muted small"><?= h($u['email']) ?></div>
                    </td>
                    <td>
                        <?php
                        $badge = match($u['plan_type']) {
                            'lite'  => 'secondary',
                            'pro'   => 'primary',
                            'elite' => 'warning text-dark',
                            'payg'  => 'info text-dark',
                            default => 'secondary',
                        };
                        ?>
                        <span class="badge bg-<?= $badge ?>"><?= h(strtoupper($u['plan_type'])) ?></span>
                    </td>
                    <td class="small">
                        <?php if ($u['plan_type'] === 'payg'): ?>
                            <span class="text-muted">—</span>
                        <?php else: ?>
                            <?= (int)$u['plan_docs_used'] ?> / <?= $limit ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (in_array($u['plan_type'], ['pro','elite'])): ?>
                            <?php if ($u['subscription_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Failed</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= (int)$u['total_docs'] ?></td>
                    <td class="text-muted small"><?= h(date('M j, Y', strtotime($u['created_at']))) ?></td>
                    <td>
                        <a href="/admin/user.php?id=<?= (int)$u['id'] ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-person-lines-fill me-1"></i>View
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
            <div class="d-flex gap-1">
                <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
                    <a href="?page=<?= $p ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require '../../templates/footer.php'; ?>
