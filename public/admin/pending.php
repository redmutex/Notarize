<?php
declare(strict_types=1);
require_once '../../config/config.php';
require_once '../../src/helpers.php';
use App\Auth;
use App\Notarize;
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

$notarize = new Notarize();
$pending  = $notarize->getPendingDocuments();

$pageTitle = 'Admin — Pending Reviews';
require '../../templates/header.php';
?>

<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="fw-bold mb-0">
                <i class="bi bi-hourglass-split me-2 text-warning"></i>Pending Reviews
            </h2>
            <p class="text-muted small mb-0"><?= count($pending) ?> submission<?= count($pending) !== 1 ? 's' : '' ?> awaiting review</p>
        </div>
        <a href="/admin/" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Dashboard
        </a>
    </div>

    <?php if (empty($pending)): ?>
        <div class="card border-0 shadow-sm text-center py-5">
            <div class="card-body">
                <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem;opacity:.7"></i>
                <h4 class="mt-3 fw-bold">All caught up!</h4>
                <p class="text-muted">No submissions are awaiting review.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
        <?php foreach ($pending as $doc): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="row g-3 align-items-start">

                            <!-- Info -->
                            <div class="col-md-5">
                                <div class="d-flex align-items-start gap-3">
                                    <i class="bi bi-file-earmark-text text-primary fs-2 mt-1"></i>
                                    <div>
                                        <div class="fw-semibold"><?= h($doc['original_filename']) ?></div>
                                        <div class="text-muted small"><?= h($doc['mime_type']) ?> · <?= format_bytes((int)$doc['file_size']) ?></div>
                                        <div class="mt-2">
                                            <span class="fw-semibold small"><?= h($doc['user_name']) ?></span>
                                            <span class="text-muted small ms-2"><?= h($doc['user_email']) ?></span>
                                        </div>
                                        <div class="text-muted small mt-1">
                                            Submitted <?= h(date('M j, Y H:i', strtotime($doc['submitted_at']))) ?> UTC
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Document preview links -->
                            <div class="col-md-4">
                                <div class="d-flex flex-column gap-2">
                                    <a href="/serve.php?id=<?= (int)$doc['id'] ?>&type=original"
                                       target="_blank" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-file-earmark-arrow-down me-1"></i>View Document
                                    </a>
                                    <?php if ($doc['photo_id_filename']): ?>
                                    <a href="/serve.php?id=<?= (int)$doc['id'] ?>&type=photo_id"
                                       target="_blank" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-credit-card-2-front me-1"></i>View Photo ID
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($doc['selfie_filename']): ?>
                                    <a href="/serve.php?id=<?= (int)$doc['id'] ?>&type=selfie"
                                       target="_blank" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-person-bounding-box me-1"></i>View Selfie
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="col-md-3 d-flex flex-column gap-2">
                                <button type="button"
                                        class="btn btn-success"
                                        data-bs-toggle="modal"
                                        data-bs-target="#approveModal"
                                        data-doc-id="<?= (int)$doc['id'] ?>"
                                        data-doc-name="<?= h($doc['original_filename']) ?>">
                                    <i class="bi bi-check-circle me-1"></i>Approve &amp; Notarize
                                </button>
                                <button type="button"
                                        class="btn btn-outline-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#rejectModal"
                                        data-doc-id="<?= (int)$doc['id'] ?>"
                                        data-doc-name="<?= h($doc['original_filename']) ?>">
                                    <i class="bi bi-x-circle me-1"></i>Reject
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<!-- Approve modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="/admin/review.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="doc_id" id="approveDocId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-check-circle text-success me-2"></i>Approve &amp; Notarize</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>
                        Confirm: approve and cryptographically notarize
                        <strong id="approveDocName"></strong>?
                    </p>
                    <p class="text-muted small mb-0">
                        This will sign the document with the RSA-4096 key, issue a certificate, generate the notarized PDF,
                        and email the user.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i>Approve &amp; Sign
                    </button>
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
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-x-circle text-danger me-2"></i>Reject Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        Reject <strong id="rejectDocName"></strong>?
                        The user will receive an email with the reason.
                    </p>
                    <label class="form-label fw-semibold" for="rejectNotes">
                        Reason <span class="text-danger">*</span>
                    </label>
                    <textarea name="notes" id="rejectNotes" class="form-control" rows="4"
                              placeholder="e.g., Photo ID is not legible. Please resubmit with a clearer image."
                              required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle me-1"></i>Reject Submission
                    </button>
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
