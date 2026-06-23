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

$billing = new Billing();
$stats   = $billing->getAdminStats();

$pageTitle = 'Admin — Dashboard';
require '../../templates/header.php';
?>

<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="fw-bold mb-0"><i class="bi bi-speedometer2 me-2 text-primary"></i>Admin Dashboard</h2>
            <p class="text-muted mb-0 small">Platform overview</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/admin/users.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-people me-1"></i>Users</a>
            <a href="/admin/documents.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-files me-1"></i>Documents</a>
            <a href="/admin/transactions.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-receipt me-1"></i>Transactions</a>
            <a href="/admin/pending.php" class="btn btn-warning btn-sm"><i class="bi bi-hourglass-split me-1"></i>Pending<?= $__pendingCount > 0 ? ' (' . $__pendingCount . ')' : '' ?></a>
        </div>
    </div>

    <!-- Stats cards -->
    <?php
    $__notarize    = new App\Notarize();
    $__pendingCount = $__notarize->getPendingCount();
    ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">Total Users</div>
                    <div class="fs-2 fw-bold"><?= number_format($stats['total_users']) ?></div>
                    <div class="text-success small"><i class="bi bi-arrow-up-short"></i><?= $stats['new_users_week'] ?> this week</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">Total Documents</div>
                    <div class="fs-2 fw-bold"><?= number_format($stats['total_docs']) ?></div>
                    <div class="text-success small"><i class="bi bi-arrow-up-short"></i><?= $stats['new_docs_week'] ?> this week</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <a href="/admin/pending.php" class="card border-0 shadow-sm h-100 text-decoration-none <?= $__pendingCount > 0 ? 'border-warning border' : '' ?>">
                <div class="card-body">
                    <div class="text-muted small mb-1">Pending Review</div>
                    <div class="fs-2 fw-bold <?= $__pendingCount > 0 ? 'text-warning' : 'text-muted' ?>"><?= $__pendingCount ?></div>
                    <div class="small <?= $__pendingCount > 0 ? 'text-warning' : 'text-muted' ?>">
                        <?= $__pendingCount > 0 ? '<i class="bi bi-exclamation-triangle me-1"></i>Requires action' : 'All clear' ?>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">Plan Distribution</div>
                    <?php
                    $planDist = $stats['plan_dist'];
                    $total    = max(1, array_sum($planDist));
                    $colors   = ['lite' => 'secondary', 'pro' => 'primary', 'elite' => 'warning', 'payg' => 'info'];
                    foreach (['lite','pro','elite','payg'] as $p):
                        $cnt = (int)($planDist[$p] ?? 0);
                        $pct = round($cnt / $total * 100);
                    ?>
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?= ucfirst($p) ?></span><span><?= $cnt ?></span>
                    </div>
                    <div class="progress mb-1" style="height:6px">
                        <div class="progress-bar bg-<?= $colors[$p] ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart + recent tables -->
    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold border-0 pt-3">Documents — last 30 days</div>
                <div class="card-body">
                    <canvas id="docsChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold border-0 pt-3">Recent Sign-ups</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <tbody>
                        <?php foreach ($stats['recent_users'] as $u): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold small"><?= h($u['name']) ?></div>
                                <div class="text-muted" style="font-size:.72rem"><?= h($u['email']) ?></div>
                            </td>
                            <td class="align-middle"><span class="badge bg-secondary"><?= h(strtoupper($u['plan_type'])) ?></span></td>
                            <td class="align-middle text-muted small"><?= h(date('M j', strtotime($u['created_at']))) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="/admin/users.php" class="small text-primary">View all users →</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent documents -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold border-0 pt-3 d-flex justify-content-between">
            <span>Recent Documents</span>
            <a href="/admin/documents.php" class="small text-primary">View all →</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>File</th><th>User</th><th>Size</th><th>Date</th></tr>
                </thead>
                <tbody>
                <?php foreach ($stats['recent_docs'] as $d): ?>
                <tr>
                    <td class="small"><?= h($d['original_filename']) ?></td>
                    <td class="small text-muted"><?= h($d['user_name']) ?></td>
                    <td class="small text-muted"><?= format_bytes((int)$d['file_size']) ?></td>
                    <td class="small text-muted"><?= $d['notarized_at'] ? h(date('M j, Y H:i', strtotime($d['notarized_at']))) : '<span class="badge bg-warning text-dark">Pending</span>' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const raw  = <?= json_encode($stats['docs_per_day']) ?>;
    const labels = raw.map(r => r.day);
    const data   = raw.map(r => parseInt(r.cnt));
    new Chart(document.getElementById('docsChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{ label: 'Documents', data, backgroundColor: 'rgba(46,95,158,.65)', borderRadius: 4 }]
        },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
})();
</script>

<?php require '../../templates/footer.php'; ?>
