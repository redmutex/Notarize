<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../src/helpers.php';
use App\Auth;
use App\Notarize;

$auth = new Auth();
$auth->requireAuth();
$authUser = $auth->user();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect('/dashboard.php');
}

$notarize = new Notarize();
$doc      = $notarize->getDocument($id, $authUser['id']);
if (!$doc) {
    http_response_code(404);
    redirect('/dashboard.php');
}

$status = $doc['status'] ?? 'approved';
$isNew  = !empty($_GET['new']);

$pageTitle = $doc['original_filename'] . ' — ' . match($status) {
    'pending'  => 'Under Review',
    'rejected' => 'Rejected',
    default    => 'Notarized',
};
require '../templates/header.php';
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
        <a href="/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>My Documents
        </a>
        <?php if ($status === 'approved'): ?>
        <div>
            <a href="/verify.php?uuid=<?= h($doc['certificate_uuid']) ?>"
               class="btn btn-outline-primary btn-sm me-2" target="_blank">
                <i class="bi bi-shield-check me-1"></i>Public Verification
            </a>
            <a href="/serve.php?id=<?= (int)$doc['id'] ?>&type=notarized"
               class="btn btn-gold btn-sm me-2" download>
                <i class="bi bi-download me-1"></i>Download Notarized Document
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($isNew && $status === 'pending'): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center">
            <i class="bi bi-send-check-fill me-2 fs-5"></i>
            <div>
                <strong>Submitted successfully!</strong>
                Your document is now under review. You will receive an email once it has been notarized (typically within 24 hours).
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($isNew && $status === 'approved'): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center">
            <i class="bi bi-patch-check-fill me-2 fs-5"></i>
            <div><strong>Notarized successfully.</strong> Your document has been signed and the certificate is ready below.</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ── PENDING state ── -->
    <?php if ($status === 'pending'): ?>
        <div class="card border-0 shadow-sm text-center py-5">
            <div class="card-body">
                <i class="bi bi-hourglass-split text-primary" style="font-size:4rem;opacity:.7"></i>
                <h3 class="fw-bold mt-3 mb-2">Under Review</h3>
                <p class="text-muted mx-auto mb-4" style="max-width:460px">
                    Our team is verifying your identity and reviewing your submission.
                    You will receive an email notification once notarization is complete —
                    typically within <strong>24 hours</strong>.
                </p>
                <table class="table table-sm text-start mx-auto" style="max-width:480px">
                    <tr><th style="width:140px" class="text-muted fw-normal">Document</th>
                        <td class="fw-semibold"><?= h($doc['original_filename']) ?></td></tr>
                    <tr><th class="text-muted fw-normal">Submitted</th>
                        <td><?= h(date('M j, Y H:i', strtotime($doc['submitted_at']))) ?> UTC</td></tr>
                    <tr><th class="text-muted fw-normal">Status</th>
                        <td><span class="badge bg-warning text-dark">Pending Review</span></td></tr>
                </table>
                <a href="/dashboard.php" class="btn btn-outline-secondary mt-3">
                    <i class="bi bi-folder2-open me-1"></i>My Documents
                </a>
            </div>
        </div>

    <!-- ── REJECTED state ── -->
    <?php elseif ($status === 'rejected'): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5 text-center">
                <i class="bi bi-x-circle-fill text-danger" style="font-size:4rem"></i>
                <h3 class="fw-bold mt-3 mb-2">Document Not Approved</h3>
                <p class="text-muted mb-3">
                    Unfortunately, we were unable to notarize <strong><?= h($doc['original_filename']) ?></strong>.
                </p>
                <?php if ($doc['review_notes']): ?>
                    <div class="alert alert-warning text-start mx-auto" style="max-width:560px">
                        <strong><i class="bi bi-info-circle me-2"></i>Reason:</strong><br>
                        <?= h($doc['review_notes']) ?>
                    </div>
                <?php endif; ?>
                <p class="text-muted small mb-4">
                    Reviewed on <?= h(date('M j, Y', strtotime($doc['reviewed_at']))) ?> UTC.
                </p>
                <a href="/upload.php" class="btn btn-primary">
                    <i class="bi bi-arrow-repeat me-1"></i>Resubmit a Document
                </a>
                <a href="/help.php" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-question-circle me-1"></i>Get Help
                </a>
            </div>
        </div>

    <!-- ── APPROVED state ── -->
    <?php else: ?>

        <?php
        $verifyUrl = APP_URL . '/verify.php?uuid=' . urlencode($doc['certificate_uuid']);
        $qrHtml    = $notarize->generateQrHtml($verifyUrl);
        ?>

        <!-- Web certificate -->
        <div class="certificate mx-auto d-print-block" style="max-width:780px">
            <div class="certificate-header">
                <div class="d-flex align-items-center justify-content-center gap-3 mb-2">
                    <div class="cert-seal"><i class="bi bi-patch-check-fill"></i></div>
                    <div>
                        <div class="certificate-title">Certificate of Notarization</div>
                        <div class="certificate-subtitle">NOTARIZE &bull; notarize.onrite.cloud</div>
                    </div>
                </div>
                <p class="cert-intro mb-0">
                    This certifies that the document described below was received, identity-verified,
                    and digitally notarized on the date shown.
                </p>
            </div>

            <div class="row g-4 mt-1">
                <div class="col-md-8">
                    <table class="cert-table w-100">
                        <tr><th>Certificate ID</th><td class="font-monospace small"><?= h($doc['certificate_uuid']) ?></td></tr>
                        <tr><th>Document Name</th><td><?= h($doc['original_filename']) ?></td></tr>
                        <tr><th>File Type</th><td><?= h($doc['mime_type']) ?></td></tr>
                        <tr><th>File Size</th><td><?= h(format_bytes((int)$doc['file_size'])) ?></td></tr>
                        <tr><th>SHA-256 Hash</th><td class="font-monospace small text-break"><?= h($doc['file_hash']) ?></td></tr>
                        <tr><th>Notarized By</th><td><?= h($doc['user_name']) ?></td></tr>
                        <tr><th>Date &amp; Time</th><td><?= h(date('F j, Y  H:i:s', strtotime($doc['notarized_at']))) ?> UTC</td></tr>
                        <tr><th>Algorithm</th><td>RSA-4096 / SHA-256</td></tr>
                    </table>
                    <div class="cert-signature-box mt-3">
                        <div class="cert-section-label">Digital Signature</div>
                        <div class="font-monospace small text-break" style="word-break:break-all;font-size:.72rem">
                            <?= h($doc['signature']) ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="cert-section-label mb-2">Scan to Verify</div>
                    <div class="cert-qr"><?= $qrHtml ?></div>
                    <p class="small text-muted mt-2 mb-0" style="word-break:break-all;font-size:.72rem">
                        <?= h($verifyUrl) ?>
                    </p>
                </div>
            </div>

            <div class="certificate-footer mt-4">
                <span>Issued by Notarize &bull; notarize.onrite.cloud</span>
                <span>Verification: <?= h($verifyUrl) ?></span>
            </div>
        </div>

        <!-- Notarized PDF viewer -->
        <div class="mx-auto mt-5 d-print-none" style="max-width:780px">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-file-earmark-check me-2 text-primary"></i>Notarized Document
                    <span class="text-muted fw-normal small ms-2">Original + certificate, signature on every page</span>
                </h5>
                <a href="/serve.php?id=<?= (int)$doc['id'] ?>&type=notarized"
                   class="btn btn-sm btn-gold" download>
                    <i class="bi bi-download me-1"></i>Download PDF
                </a>
            </div>
            <div class="doc-viewer-wrap">
                <div class="notary-stamp-overlay">
                    <span class="stamp-title">NOTARIZED</span>
                    <?= h(date('M j, Y', strtotime($doc['notarized_at']))) ?><br>
                    <span style="font-size:.68rem;letter-spacing:.03em">RSA-4096 &bull; SHA-256</span>
                </div>
                <iframe src="/serve.php?id=<?= (int)$doc['id'] ?>&type=notarized"
                        class="doc-viewer-iframe" title="Notarized Document"></iframe>
            </div>
        </div>

    <?php endif; ?>

</div>

<?php require '../templates/footer.php'; ?>
