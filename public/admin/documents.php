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

// Filter by status
$filterStatus = $_GET['status'] ?? '';
if (!in_array($filterStatus, ['pending', 'approved', 'rejected'], true)) {
    $filterStatus = '';
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$billing = new Billing();

// Parameterized status filter — no string interpolation
$perPage    = 25;
$offset     = ($page - 1) * $perPage;
$bindParams = $filterStatus ? [$filterStatus] : [];

$countSql = $filterStatus
    ? "SELECT COUNT(*) FROM documents d WHERE d.status = ?"
    : "SELECT COUNT(*) FROM documents d";
$countStmt = $db->prepare($countSql);
$countStmt->execute($bindParams);
$total = (int)$countStmt->fetchColumn();

$dataSql = $filterStatus
    ? "SELECT d.id, d.user_id, d.original_filename, d.mime_type, d.file_size,
              d.status, d.submitted_at, d.notarized_at, d.certificate_uuid,
              d.review_notes, d.photo_id_filename, d.selfie_filename,
              u.name AS user_name, u.email AS user_email
       FROM documents d
       JOIN users u ON d.user_id = u.id
       WHERE d.status = ?
       ORDER BY d.submitted_at DESC
       LIMIT ? OFFSET ?"
    : "SELECT d.id, d.user_id, d.original_filename, d.mime_type, d.file_size,
              d.status, d.submitted_at, d.notarized_at, d.certificate_uuid,
              d.review_notes, d.photo_id_filename, d.selfie_filename,
              u.name AS user_name, u.email AS user_email
       FROM documents d
       JOIN users u ON d.user_id = u.id
       ORDER BY d.submitted_at DESC
       LIMIT ? OFFSET ?";

$stmt = $db->prepare($dataSql);
$stmt->execute($filterStatus ? [$filterStatus, $perPage, $offset] : [$perPage, $offset]);
$docs  = $stmt->fetchAll();
$pages = max(1, (int)ceil($total / $perPage));

$flashOk  = $_GET['approved'] ?? $_GET['rejected'] ?? null;
$flashErr = $_GET['error'] ?? null;

$pageTitle = 'Admin — All Documents';
require '../../templates/header.php';
?>

<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="fw-bold mb-0"><i class="bi bi-files me-2 text-primary"></i>All Documents</h2>
            <p class="text-muted mb-0 small"><?= number_format($total) ?> document<?= $total !== 1 ? 's' : '' ?></p>
        </div>
        <a href="/admin/" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
    </div>

    <?php if ($flashOk !== null): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= isset($_GET['approved']) ? '<i class="bi bi-check-circle me-2"></i>Document approved.' : '<i class="bi bi-x-circle me-2"></i>Document rejected.' ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($flashErr): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-2"></i><?= h($flashErr) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Status filter tabs -->
    <ul class="nav nav-pills mb-3 gap-1">
        <?php foreach (['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $val => $label): ?>
            <li class="nav-item">
                <a class="nav-link <?= $filterStatus === $val ? 'active' : '' ?> py-1 px-3"
                   href="?status=<?= h($val) ?>">
                    <?= $label ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

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
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($docs as $doc): ?>
                <tr>
                    <td class="text-muted small"><?= (int)$doc['id'] ?></td>
                    <td class="small">
                        <div class="fw-semibold"><?= h($doc['original_filename']) ?></div>
                        <?php if ($doc['status'] === 'rejected' && $doc['review_notes']): ?>
                            <div class="text-danger" style="font-size:.7rem">
                                <i class="bi bi-chat-left-text me-1"></i><?= h(mb_strimwidth($doc['review_notes'], 0, 60, '…')) ?>
                            </div>
                        <?php elseif ($doc['status'] === 'approved' && $doc['certificate_uuid']): ?>
                            <div class="font-monospace text-muted" style="font-size:.65rem"><?= h($doc['certificate_uuid']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/admin/user.php?id=<?= (int)$doc['user_id'] ?>" class="text-decoration-none">
                            <div class="small fw-semibold"><?= h($doc['user_name']) ?></div>
                            <div class="text-muted" style="font-size:.72rem"><?= h($doc['user_email']) ?></div>
                        </a>
                    </td>
                    <td class="small text-muted"><?= h(explode('/', $doc['mime_type'])[1] ?? $doc['mime_type']) ?></td>
                    <td class="small text-muted"><?= format_bytes((int)$doc['file_size']) ?></td>
                    <td>
                        <?php
                        echo match($doc['status']) {
                            'pending'  => '<span class="badge bg-warning text-dark">Pending</span>',
                            'rejected' => '<span class="badge bg-danger">Rejected</span>',
                            default    => '<span class="badge bg-success">Approved</span>',
                        };
                        ?>
                    </td>
                    <td class="small text-muted text-nowrap">
                        <?= $doc['submitted_at'] ? h(date('M j, Y', strtotime($doc['submitted_at']))) : '—' ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <a href="/serve.php?id=<?= (int)$doc['id'] ?>&type=original"
                               target="_blank" class="btn btn-outline-secondary btn-sm" title="Document">
                                <i class="bi bi-file-earmark-arrow-down"></i>
                            </a>
                            <?php if ($doc['photo_id_filename']): ?>
                            <a href="/serve.php?id=<?= (int)$doc['id'] ?>&type=photo_id"
                               target="_blank" class="btn btn-outline-secondary btn-sm" title="Photo ID">
                                <i class="bi bi-credit-card-2-front"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($doc['selfie_filename']): ?>
                            <a href="/serve.php?id=<?= (int)$doc['id'] ?>&type=selfie"
                               target="_blank" class="btn btn-outline-secondary btn-sm" title="Selfie">
                                <i class="bi bi-person-bounding-box"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($doc['status'] === 'approved' && $doc['certificate_uuid']): ?>
                            <a href="/verify.php?uuid=<?= h($doc['certificate_uuid']) ?>"
                               target="_blank" class="btn btn-outline-success btn-sm" title="Verify">
                                <i class="bi bi-shield-check"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($doc['status'] === 'pending'): ?>
                            <button class="btn btn-success btn-sm"
                                    data-bs-toggle="modal" data-bs-target="#approveModal"
                                    data-doc-id="<?= (int)$doc['id'] ?>"
                                    data-doc-name="<?= h($doc['original_filename']) ?>"
                                    title="Approve">
                                <i class="bi bi-check-circle"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-sm"
                                    data-bs-toggle="modal" data-bs-target="#rejectModal"
                                    data-doc-id="<?= (int)$doc['id'] ?>"
                                    data-doc-name="<?= h($doc['original_filename']) ?>"
                                    title="Reject">
                                <i class="bi bi-x-circle"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="small text-muted">Page <?= $page ?> of <?= $pages ?></span>
            <div class="d-flex gap-1 flex-wrap">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <a href="?status=<?= h($filterStatus) ?>&page=<?= $p ?>"
                       class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- Approve modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="/admin/review.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="doc_id" id="approveDocId">
                <input type="hidden" name="redirect" value="/admin/documents.php?status=<?= h($filterStatus) ?>&page=<?= $page ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-check-circle text-success me-2"></i>Approve &amp; Notarize</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Sign and notarize <strong id="approveDocName"></strong>?</p>
                    <p class="text-muted small mb-0">RSA-4096 signed, PDF generated, user emailed.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="/admin/review.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="doc_id" id="rejectDocId">
                <input type="hidden" name="redirect" value="/admin/documents.php?status=<?= h($filterStatus) ?>&page=<?= $page ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-x-circle text-danger me-2"></i>Reject Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Reject <strong id="rejectDocName"></strong>? The user will receive an email with your remarks.</p>
                    <label class="form-label fw-semibold">Remarks <span class="text-danger">*</span></label>
                    <textarea name="notes" id="rejectNotes" class="form-control" rows="4"
                              placeholder="e.g., Photo ID is not clearly legible. Please resubmit with better lighting."
                              required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i>Reject &amp; Notify</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('[data-bs-target="#approveModal"]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('approveDocId').value   = btn.dataset.docId;
        document.getElementById('approveDocName').textContent = btn.dataset.docName;
    });
});
document.querySelectorAll('[data-bs-target="#rejectModal"]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('rejectDocId').value   = btn.dataset.docId;
        document.getElementById('rejectDocName').textContent = btn.dataset.docName;
        document.getElementById('rejectNotes').value  = '';
    });
});
</script>

<?php require '../../templates/footer.php'; ?>
