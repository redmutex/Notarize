<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../src/helpers.php';
use App\Auth;
use App\Notarize;

$auth = new Auth();
$auth->requireAuth();
$authUser = $auth->user();

$notarize = new Notarize();
$docs     = $notarize->getUserDocuments($authUser['id']);

$successMsg = flash('success');
$pageTitle  = 'Dashboard';
require '../templates/header.php';
?>

<div class="container py-5">

    <?php if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?= h($successMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="bi bi-grid me-2 text-primary"></i>My Documents
            </h2>
            <p class="text-muted mb-0">Welcome back, <?= h($authUser['name']) ?></p>
        </div>
        <a href="/upload.php" class="btn btn-primary">
            <i class="bi bi-cloud-upload me-2"></i>Notarize New Document
        </a>
    </div>

    <?php if (empty($docs)): ?>
        <div class="card border-0 shadow-sm text-center py-5">
            <div class="card-body">
                <i class="bi bi-file-earmark-plus text-muted" style="font-size:3.5rem"></i>
                <h4 class="mt-3 fw-bold">No documents yet</h4>
                <p class="text-muted">Upload your first document to get a tamper-proof digital certificate.</p>
                <a href="/upload.php" class="btn btn-primary btn-lg mt-2">
                    <i class="bi bi-cloud-upload me-2"></i>Notarize Your First Document
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Document</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Notarized</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($docs as $doc): ?>
                        <tr>
                            <td>
                                <i class="bi bi-file-earmark-text me-2 text-primary"></i>
                                <span title="<?= h($doc['original_filename']) ?>">
                                    <?= h(mb_strimwidth($doc['original_filename'], 0, 40, '…')) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary">
                                    <?= h(explode('/', $doc['mime_type'])[1] ?? $doc['mime_type']) ?>
                                </span>
                            </td>
                            <td><?= h(format_bytes((int)$doc['file_size'])) ?></td>
                            <td>
                                <span class="text-muted small">
                                    <?= h(date('M j, Y  H:i', strtotime($doc['notarized_at']))) ?> UTC
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="/document.php?id=<?= (int)$doc['id'] ?>"
                                   class="btn btn-sm btn-outline-primary me-1">
                                    <i class="bi bi-patch-check me-1"></i>Certificate
                                </a>
                                <a href="/verify.php?uuid=<?= h($doc['certificate_uuid']) ?>"
                                   class="btn btn-sm btn-outline-secondary" target="_blank">
                                    <i class="bi bi-qr-code"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-muted small mt-2">
            <?= count($docs) ?> document<?= count($docs) !== 1 ? 's' : '' ?> notarized
        </p>
    <?php endif; ?>
</div>

<?php require '../templates/footer.php'; ?>
