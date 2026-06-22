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

$verifyUrl = APP_URL . '/verify.php?uuid=' . urlencode($doc['certificate_uuid']);
$qrSvg     = $notarize->generateQrSvg($verifyUrl);
$isNew     = !empty($_GET['new']);

$pageTitle = 'Certificate — ' . $doc['original_filename'];
require '../templates/header.php';
?>

<div class="container py-4">

    <?php if ($isNew): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Document notarized!</strong> Your certificate is ready below.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
        <a href="/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
        <div>
            <a href="/verify.php?uuid=<?= h($doc['certificate_uuid']) ?>"
               class="btn btn-outline-primary btn-sm me-2" target="_blank">
                <i class="bi bi-qr-code me-1"></i>Public Verify Link
            </a>
            <button onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="bi bi-printer me-1"></i>Print / Save PDF
            </button>
        </div>
    </div>

    <!-- Certificate -->
    <div class="certificate mx-auto" style="max-width:780px">

        <div class="certificate-header">
            <div class="d-flex align-items-center justify-content-center gap-3 mb-2">
                <div class="cert-seal">
                    <i class="bi bi-patch-check-fill"></i>
                </div>
                <div>
                    <div class="certificate-title">Certificate of Notarization</div>
                    <div class="certificate-subtitle">NOTARIZE &bull; notarize.onrite.cloud</div>
                </div>
            </div>
            <p class="cert-intro mb-0">
                This certifies that the document described below was received and digitally notarized
                on the date shown. The cryptographic signature confirms the document's content
                at the time of notarization.
            </p>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-md-8">
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
                        <th>Date &amp; Time</th>
                        <td><?= h(date('F j, Y  H:i:s', strtotime($doc['notarized_at']))) ?> UTC</td>
                    </tr>
                    <tr>
                        <th>Algorithm</th>
                        <td>RSA-4096 / SHA-256</td>
                    </tr>
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
                <div class="cert-qr">
                    <?= $qrSvg ?>
                </div>
                <p class="small text-muted mt-2 mb-0" style="word-break:break-all;font-size:.72rem">
                    <?= h($verifyUrl) ?>
                </p>
            </div>
        </div>

        <div class="certificate-footer mt-4">
            <span>Issued by Notarize &bull; notarize.onrite.cloud</span>
            <span>Verification: <?= h($verifyUrl) ?></span>
        </div>

    </div><!-- /certificate -->

</div>

<?php require '../templates/footer.php'; ?>
