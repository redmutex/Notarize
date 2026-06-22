<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../src/helpers.php';
use App\Auth;
use App\Notarize;

$auth     = new Auth();
$authUser = $auth->user();

$uuid = trim($_GET['uuid'] ?? '');
$doc  = null;

if ($uuid !== '') {
    $notarize = new Notarize();
    $doc      = $notarize->getDocumentByUuid($uuid);
}

$pageTitle = $doc ? 'Verified: ' . $doc['original_filename'] : 'Verify Document';
require '../templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">

            <div class="text-center mb-5">
                <h2 class="fw-bold">
                    <i class="bi bi-shield-check me-2 text-primary"></i>Verify Document Authenticity
                </h2>
                <p class="text-muted mx-auto" style="max-width:500px">
                    Enter the certificate ID from a notarized document, or scan its QR code,
                    to confirm the document is authentic and unchanged since notarization.
                </p>
            </div>

            <!-- Search form -->
            <form method="get" class="mb-4">
                <div class="input-group input-group-lg shadow-sm">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-shield-lock text-muted"></i>
                    </span>
                    <input type="text" name="uuid" class="form-control border-start-0 ps-0"
                           placeholder="Enter certificate ID (UUID)…"
                           value="<?= h($uuid) ?>" required>
                    <button class="btn btn-primary px-4" type="submit">
                        <i class="bi bi-shield-check me-1"></i>Verify
                    </button>
                </div>
            </form>

            <?php if ($uuid !== '' && !$doc): ?>
                <div class="card border-0 shadow-sm text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-x-circle-fill text-danger" style="font-size:3rem"></i>
                        <h4 class="mt-3 fw-bold">No Record Found</h4>
                        <p class="text-muted">No notarization record matches ID: <code><?= h($uuid) ?></code></p>
                    </div>
                </div>

            <?php elseif ($doc): ?>
                <?php $sigValid = $doc['sig_valid']; ?>

                <!-- Status banner -->
                <div class="card border-0 shadow-sm overflow-hidden mb-4">
                    <div class="<?= $sigValid === true ? 'bg-success' : ($sigValid === false ? 'bg-danger' : 'bg-warning') ?> text-white p-4 text-center">
                        <?php if ($sigValid === true): ?>
                            <i class="bi bi-patch-check-fill" style="font-size:2.5rem"></i>
                            <h3 class="fw-bold mt-2 mb-1">Authentic &amp; Unmodified</h3>
                            <p class="mb-0 opacity-75">The digital signature is valid. This document has not been altered since notarization.</p>
                        <?php elseif ($sigValid === false): ?>
                            <i class="bi bi-patch-exclamation-fill" style="font-size:2.5rem"></i>
                            <h3 class="fw-bold mt-2 mb-1">Signature Mismatch</h3>
                            <p class="mb-0 opacity-75">The digital signature could not be verified. This document may have been tampered with.</p>
                        <?php else: ?>
                            <i class="bi bi-patch-question-fill" style="font-size:2.5rem"></i>
                            <h3 class="fw-bold mt-2 mb-1">Record Found</h3>
                            <p class="mb-0 opacity-75">The notarization record exists but the signature verification key is temporarily unavailable.</p>
                        <?php endif; ?>
                    </div>

                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Certificate Details</h5>
                        <table class="cert-table w-100">
                            <tr><th>Certificate ID</th><td class="font-monospace small"><?= h($doc['certificate_uuid']) ?></td></tr>
                            <tr><th>Document Name</th><td><?= h($doc['original_filename']) ?></td></tr>
                            <tr><th>File Type</th><td><?= h($doc['mime_type']) ?></td></tr>
                            <tr><th>File Size</th><td><?= h(format_bytes((int)$doc['file_size'])) ?></td></tr>
                            <tr><th>SHA-256 Hash</th><td class="font-monospace small text-break"><?= h($doc['file_hash']) ?></td></tr>
                            <tr><th>Notarized By</th><td><?= h($doc['user_name']) ?></td></tr>
                            <tr><th>Notarized At</th><td><?= h(date('F j, Y  H:i:s', strtotime($doc['notarized_at']))) ?> UTC</td></tr>
                            <tr><th>Algorithm</th><td>RSA-4096 / SHA-256</td></tr>
                        </table>

                        <div class="d-flex gap-2 mt-3">
                            <a href="/serve.php?uuid=<?= h($uuid) ?>&type=notarized"
                               class="btn btn-gold btn-sm" download>
                                <i class="bi bi-download me-1"></i>Download Notarized Document
                            </a>
                        </div>

                        <div class="alert alert-info small mt-3 mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Independent verification:</strong>
                            Compute the SHA-256 hash of the original file and compare it to the hash shown above.
                            A matching hash confirms the file is byte-for-byte identical to what was notarized.
                        </div>
                    </div>
                </div>

                <!-- Single notarized document viewer -->
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-file-earmark-check me-2 text-primary"></i>Notarized Document
                            <span class="text-muted fw-normal small ms-2">
                                Original + certificate, signature on every page
                            </span>
                        </h5>
                        <a href="/serve.php?uuid=<?= h($uuid) ?>&type=notarized"
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
                        <iframe src="/serve.php?uuid=<?= h($uuid) ?>&type=notarized"
                                class="doc-viewer-iframe"
                                title="Notarized Document"></iframe>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<?php require '../templates/footer.php'; ?>
