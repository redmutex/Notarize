<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../src/helpers.php';
use App\Auth;
use App\Billing;
use App\Notarize;

$auth = new Auth();
$auth->requireAuth();
$authUser = $auth->user();

$billing       = new Billing();
$billingStatus = $billing->canNotarize($authUser['id']);
$userBilling   = $billing->getUserBilling($authUser['id']);
$isPAYG        = ($userBilling['plan_type'] ?? '') === 'payg';
$emailVerified = $authUser['email_verified'];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid request. Please try again.';
    } elseif (!$emailVerified) {
        $error = 'Please verify your email address before notarizing documents.';
    } elseif (!$billingStatus['allowed']) {
        $error = $billingStatus['reason'];
    } elseif ($isPAYG && empty($_SESSION['payg_captured'])) {
        $error = 'Payment required. Please complete the PayPal payment before submitting.';
    } else {
        // All three files are required
        $docFile     = $_FILES['document']  ?? null;
        $photoIdFile = $_FILES['photo_id']  ?? null;
        $selfieFile  = $_FILES['selfie']    ?? null;

        if (!$docFile || !$photoIdFile || !$selfieFile) {
            $error = 'All three files are required: document, government photo ID, and selfie.';
        } else {
            $paygTxnId = null;
            if ($isPAYG) {
                $paygTxnId = $_SESSION['payg_captured'];
                unset($_SESSION['payg_captured']);
            }

            $notarize = new Notarize();
            $result   = $notarize->submit($authUser['id'], $docFile, $photoIdFile, $selfieFile);

            if (isset($result['error'])) {
                $error = $result['error'];
                // Restore PAYG token so user can retry
                if ($paygTxnId) {
                    $_SESSION['payg_captured'] = $paygTxnId;
                }
            } else {
                $billing->recordNotarization($authUser['id'], $paygTxnId);
                redirect('/document.php?id=' . $result['id'] . '&new=1');
            }
        }
    }
}

$pageTitle = 'Submit Document for Notarization';
require '../templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <h2 class="fw-bold mb-1">
                <i class="bi bi-shield-plus me-2 text-primary"></i>Submit for Notarization
            </h2>
            <p class="text-muted mb-4">
                Upload your document along with a government-issued photo ID and a selfie holding the ID.
                Our team will review and issue a cryptographic certificate within 24 hours.
            </p>

            <?php if (!$emailVerified): ?>
                <div class="alert alert-warning d-flex align-items-start gap-3">
                    <i class="bi bi-envelope-exclamation-fill fs-4 mt-1 text-warning"></i>
                    <div>
                        <strong>Email not verified.</strong>
                        <p class="mb-2 mt-1">Please verify your email address before submitting documents.
                           Check your inbox for the verification link.</p>
                        <a href="/resend-verification.php" class="btn btn-sm btn-warning">
                            <i class="bi bi-envelope-arrow-up me-1"></i>Resend Verification Email
                        </a>
                    </div>
                </div>
            <?php elseif (!$billingStatus['allowed']): ?>
                <div class="alert alert-warning d-flex align-items-start gap-3">
                    <i class="bi bi-exclamation-triangle-fill fs-4 text-warning mt-1"></i>
                    <div>
                        <strong>Cannot submit right now.</strong>
                        <p class="mb-2 mt-1"><?= h($billingStatus['reason']) ?></p>
                        <a href="/plans.php" class="btn btn-gold btn-sm">
                            <i class="bi bi-arrow-up-circle me-1"></i>Upgrade Plan
                        </a>
                    </div>
                </div>
            <?php else: ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i><?= h($error) ?>
                    </div>
                <?php endif; ?>

                <!-- Quota bar (non-PAYG) -->
                <?php if (!$isPAYG): ?>
                    <?php
                    $planName = Billing::PLANS[$userBilling['plan_type']]['name'];
                    $limit    = Billing::PLANS[$userBilling['plan_type']]['docs'];
                    $used     = (int)$userBilling['plan_docs_used'];
                    $pct      = $limit > 0 ? min(100, round($used / $limit * 100)) : 0;
                    ?>
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span><?= h($planName) ?> plan</span>
                        <span><?= $used ?> / <?= $limit ?> documents used this month</span>
                    </div>
                    <div class="progress mb-4" style="height:6px">
                        <div class="progress-bar <?= $pct >= 100 ? 'bg-danger' : 'bg-primary' ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info small py-2 mb-4">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Pay As You Go</strong> — you will be charged <strong>$3</strong> via PayPal.
                        Payment is collected before submission.
                    </div>
                <?php endif; ?>

                <!-- Workflow steps banner -->
                <div class="alert alert-light border d-flex gap-3 align-items-start mb-4 small">
                    <i class="bi bi-info-circle text-primary fs-5 mt-1 flex-shrink-0"></i>
                    <div>
                        <strong>How it works:</strong>
                        Upload your document + ID → Our team verifies your identity →
                        Cryptographic certificate issued within <strong>24 hours</strong> →
                        You receive an email with your notarized document.
                    </div>
                </div>

                <form method="post" enctype="multipart/form-data" id="uploadForm">
                    <?= csrf_field() ?>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">

                            <!-- Document -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-file-earmark-text me-1 text-primary"></i>
                                    Document to Notarize <span class="text-danger">*</span>
                                </label>
                                <input type="file" name="document" id="docInput" class="form-control"
                                       accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.txt" required>
                                <div class="form-text">PDF, JPEG, PNG, GIF, WebP, Word, TXT — max 10 MB</div>
                            </div>

                            <!-- Photo ID -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-credit-card-2-front me-1 text-primary"></i>
                                    Government-Issued Photo ID <span class="text-danger">*</span>
                                </label>
                                <input type="file" name="photo_id" id="photoIdInput" class="form-control"
                                       accept=".jpg,.jpeg,.png,.webp" required>
                                <div class="form-text">
                                    Passport, national ID, or driver's licence — JPEG/PNG/WebP, clearly legible, max 10 MB.
                                    Your ID is stored securely and only visible to our verification team.
                                </div>
                            </div>

                            <!-- Selfie -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-person-bounding-box me-1 text-primary"></i>
                                    Selfie Holding Your ID <span class="text-danger">*</span>
                                </label>
                                <input type="file" name="selfie" id="selfieInput" class="form-control"
                                       accept=".jpg,.jpeg,.png,.webp" required>
                                <div class="form-text">
                                    A clear photo of you holding your government ID so both your face and the ID are visible.
                                    Max 10 MB.
                                </div>
                            </div>

                            <div class="alert alert-secondary small py-2 mb-4">
                                <i class="bi bi-lock-fill me-2"></i>
                                All files are stored on encrypted servers and are never shared with third parties.
                                Photo ID and selfie are used solely for identity verification and are not included in the certificate.
                            </div>

                            <?php if ($isPAYG): ?>
                                <!-- PAYG: pay first, then submit -->
                                <div id="paygBtns">
                                    <div id="paypal-payg-btn" class="mb-2"></div>
                                    <p class="text-muted small text-center mb-0">
                                        Complete PayPal payment ($3) to unlock the Submit button.
                                    </p>
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg w-100 d-none" id="submitBtn">
                                    <i class="bi bi-send-check me-2"></i>Submit for Notarization
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-primary btn-lg w-100" id="submitBtn">
                                    <i class="bi bi-send-check me-2"></i>Submit for Notarization
                                </button>
                            <?php endif; ?>

                        </div>
                    </div>
                </form>

            <?php endif; ?>

        </div>
    </div>
</div>

<?php if (PAYPAL_CLIENT_ID && $isPAYG && $emailVerified && $billingStatus['allowed']): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= h(PAYPAL_CLIENT_ID) ?>&currency=USD"></script>
<?php endif; ?>

<script>
(function () {
    const isPAYG   = <?= $isPAYG ? 'true' : 'false' ?>;
    const submitBtn= document.getElementById('submitBtn');
    const form     = document.getElementById('uploadForm');

    if (form && submitBtn) {
        form.addEventListener('submit', () => {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';
        });
    }

    <?php if ($isPAYG && PAYPAL_CLIENT_ID && $emailVerified && $billingStatus['allowed']): ?>
    if (window.paypal) {
        paypal.Buttons({
            style: { label: 'pay', color: 'gold' },
            createOrder: () => fetch('/billing/payg_create.php', { method: 'POST' })
                .then(r => r.json())
                .then(d => { if (!d.id) throw new Error('Order creation failed'); return d.id; }),
            onApprove: (data) => fetch('/billing/payg_capture.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: data.orderID })
            })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    document.getElementById('paygBtns').innerHTML =
                        '<div class="alert alert-success py-2"><i class="bi bi-check-circle me-2"></i>Payment confirmed — click Submit to continue.</div>';
                    submitBtn.classList.remove('d-none');
                } else {
                    alert('Payment could not be confirmed. Please try again.');
                }
            }),
            onError: () => alert('PayPal encountered an error. Please try again.')
        }).render('#paypal-payg-btn');
    }
    <?php endif; ?>
})();
</script>

<?php require '../templates/footer.php'; ?>
