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
        <div class="col-lg-8">

            <div class="text-center mb-5">
                <h2 class="fw-bold">
                    <i class="bi bi-shield-check me-2 text-primary"></i>Document Verification
                </h2>
                <p class="text-muted">
                    Enter a certificate UUID or scan the QR code from a notarization certificate to verify its authenticity.
                </p>
            </div>

            <!-- Search form -->
            <form method="get" class="mb-4">
                <div class="input-group input-group-lg shadow-sm">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-qr-code text-muted"></i>
                    </span>
                    <input type="text" name="uuid" class="form-control border-start-0 ps-0"
                           placeholder="Paste certificate UUID here…"
                           value="<?= h($uuid) ?>" required>
                    <button class="btn btn-primary px-4" type="submit">Verify</button>
                </div>
            </form>

            <?php if ($uuid !== '' && !$doc): ?>
                <div class="card border-0 shadow-sm text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-x-circle-fill text-danger" style="font-size:3rem"></i>
                        <h4 class="mt-3 fw-bold">Certificate Not Found</h4>
                        <p class="text-muted">No notarization record exists for UUID: <code><?= h($uuid) ?></code></p>
                    </div>
                </div>

            <?php elseif ($doc): ?>
                <?php $sigValid = $doc['sig_valid']; ?>

                <div class="card border-0 shadow-sm overflow-hidden">
                    <!-- Status banner -->
                    <div class="<?= $sigValid === true ? 'bg-success' : ($sigValid === false ? 'bg-danger' : 'bg-warning') ?> text-white p-4 text-center">
                        <?php if ($sigValid === true): ?>
                            <i class="bi bi-patch-check-fill" style="font-size:2.5rem"></i>
                            <h3 class="fw-bold mt-2 mb-1">Signature Valid</h3>
                            <p class="mb-0 opacity-75">This document's digital signature is cryptographically verified.</p>
                        <?php elseif ($sigValid === false): ?>
                            <i class="bi bi-patch-exclamation-fill" style="font-size:2.5rem"></i>
                            <h3 class="fw-bold mt-2 mb-1">Signature Invalid</h3>
                            <p class="mb-0 opacity-75">The digital signature could not be verified. The document may have been tampered with.</p>
                        <?php else: ?>
                            <i class="bi bi-patch-question-fill" style="font-size:2.5rem"></i>
                            <h3 class="fw-bold mt-2 mb-1">Certificate Found</h3>
                            <p class="mb-0 opacity-75">Signature verification key unavailable at this time.</p>
                        <?php endif; ?>
                    </div>

                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Certificate Details</h5>
                        <table class="cert-table w-100">
                            <tr>
                                <th>Certificate ID</th>
                                <td class="font-monospace small"><?= h($doc['certificate_uuid']) ?></td>
                            </tr>
                            <tr>
                                <th>Document Name</th>
                                <td><?= h($doc['original_filename']) ?></td>
                            </tr>
                            <tr>
                                <th>File Type</th>
                                <td><?= h($doc['mime_type']) ?></td>
                            </tr>
                            <tr>
                                <th>File Size</th>
                                <td><?= h(format_bytes((int)$doc['file_size'])) ?></td>
                            </tr>
                            <tr>
                                <th>SHA-256 Hash</th>
                                <td class="font-monospace small text-break"><?= h($doc['file_hash']) ?></td>
                            </tr>
                            <tr>
                                <th>Notarized By</th>
                                <td><?= h($doc['user_name']) ?></td>
                            </tr>
                            <tr>
                                <th>Notarized At</th>
                                <td><?= h(date('F j, Y  H:i:s', strtotime($doc['notarized_at']))) ?> UTC</td>
                            </tr>
                            <tr>
                                <th>Algorithm</th>
                                <td>RSA-4096 / SHA-256</td>
                            </tr>
                        </table>

                        <div class="alert alert-info small mt-3 mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>How to verify independently:</strong>
                            Compute the SHA-256 hash of your original file and compare it to the hash above.
                            If they match, the file is unchanged since notarization.
                        </div>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<?php require '../templates/footer.php'; ?>
