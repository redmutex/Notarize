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
$errorMsg   = flash('error');
$pageTitle  = 'My Documents';
require '../templates/header.php';
?>

<div class="container py-5">

    <?php if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?= h($successMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle me-2"></i><?= h($errorMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="bi bi-folder2-open me-2 text-primary"></i>Document Vault
            </h2>
            <p class="text-muted mb-0">
                <?= h($authUser['name']) ?> &mdash;
                <?php
                $approvedCount = count(array_filter($docs, fn($d) => ($d['status'] ?? 'approved') === 'approved'));
                $pendingCount  = count(array_filter($docs, fn($d) => ($d['status'] ?? '') === 'pending'));
                if (empty($docs)): ?>
                    no documents yet
                <?php else: ?>
                    <?= count($docs) ?> document<?= count($docs) !== 1 ? 's' : '' ?>
                    <?php if ($pendingCount > 0): ?>
                        · <span class="text-warning"><?= $pendingCount ?> pending</span>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </div>
        <a href="/upload.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i>New Notarization
        </a>
    </div>

    <?php if (empty($docs)): ?>
        <div class="card border-0 shadow-sm text-center py-5">
            <div class="card-body">
                <i class="bi bi-shield-plus text-muted" style="font-size:3.5rem;opacity:.5"></i>
                <h4 class="mt-3 fw-bold">Your vault is empty</h4>
                <p class="text-muted mx-auto" style="max-width:380px">
                    Notarize a document to create a tamper-proof, cryptographically signed record
                    with a certificate anyone can verify.
                </p>
                <a href="/upload.php" class="btn btn-primary btn-lg mt-2">
                    <i class="bi bi-plus-circle me-2"></i>Notarize Your First Document
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
                            <th>Notarized On</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($docs as $doc): ?>
                        <?php $status = $doc['status'] ?? 'approved'; ?>
                        <tr>
                            <td>
                                <?php
                                $icon = match($status) {
                                    'pending'  => 'bi-hourglass-split text-warning',
                                    'rejected' => 'bi-x-circle text-danger',
                                    default    => 'bi-file-earmark-lock2 text-primary',
                                };
                                ?>
                                <i class="bi <?= $icon ?> me-2"></i>
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
                                <?php if ($status === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">Pending Review</span>
                                <?php elseif ($status === 'rejected'): ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php else: ?>
                                    <span class="text-muted small">
                                        <?= h(date('M j, Y  H:i', strtotime($doc['notarized_at']))) ?> UTC
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="/document.php?id=<?= (int)$doc['id'] ?>"
                                   class="btn btn-sm btn-gold me-1">
                                    <i class="bi bi-patch-check me-1"></i>View
                                </a>
                                <?php if ($status === 'approved'): ?>
                                <a href="/verify.php?uuid=<?= h($doc['certificate_uuid']) ?>"
                                   class="btn btn-sm btn-outline-secondary me-1" target="_blank"
                                   title="Public verification page">
                                    <i class="bi bi-shield-check"></i>
                                </a>
                                <?php endif; ?>
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        title="Permanently remove this document"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteModal"
                                        data-doc-id="<?= (int)$doc['id'] ?>"
                                        data-doc-name="<?= h($doc['original_filename']) ?>">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="deleteModalLabel">
                    <i class="bi bi-trash3 text-danger me-2"></i>Delete Document
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="mb-1">Permanently delete this document?</p>
                <p class="fw-semibold text-break" id="deleteDocName"></p>
                <div class="alert alert-warning small py-2 mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    The stored file and all notarization records will be removed.
                    Any existing certificates for this document will no longer be verifiable.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="/delete.php" id="deleteForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" id="deleteDocId">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash3 me-1"></i>Delete Document
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('deleteModal').addEventListener('show.bs.modal', function (e) {
    const btn  = e.relatedTarget;
    document.getElementById('deleteDocId').value   = btn.dataset.docId;
    document.getElementById('deleteDocName').textContent = btn.dataset.docName;
});
</script>

<?php require '../templates/footer.php'; ?>
