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

$page   = max(1, (int)($_GET['page'] ?? 1));
$result = (new Billing())->getAllDocuments($page, 25);

$pageTitle = 'Admin — Documents';
require '../../templates/header.php';
?>

<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="fw-bold mb-0"><i class="bi bi-files me-2 text-primary"></i>Documents</h2>
            <p class="text-muted mb-0 small"><?= number_format($result['total']) ?> notarized documents</p>
        </div>
        <a href="/admin/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>File</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Date</th>
                        <th>Certificate</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($result['rows'] as $d): ?>
                <tr>
                    <td class="text-muted small"><?= (int)$d['id'] ?></td>
                    <td class="small fw-semibold"><?= h($d['original_filename']) ?></td>
                    <td>
                        <div class="small"><?= h($d['user_name']) ?></div>
                        <div class="text-muted" style="font-size:.72rem"><?= h($d['user_email']) ?></div>
                    </td>
                    <td class="small text-muted"><?= h($d['mime_type']) ?></td>
                    <td class="small text-muted"><?= format_bytes((int)$d['file_size']) ?></td>
                    <td class="small text-muted"><?= h(date('M j, Y H:i', strtotime($d['notarized_at']))) ?></td>
                    <td>
                        <a href="/verify.php?uuid=<?= h($d['certificate_uuid']) ?>"
                           class="btn btn-outline-secondary btn-sm" target="_blank">
                            <i class="bi bi-shield-check"></i>
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
