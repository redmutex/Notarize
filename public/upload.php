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

$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $results[] = ['name' => '', 'error' => 'Invalid request. Please try again.'];
    } elseif (!$billingStatus['allowed']) {
        $results[] = ['name' => '', 'error' => $billingStatus['reason']];
    } elseif ($isPAYG) {
        // PAYG: payment must be captured first via payg_capture.php
        if (empty($_SESSION['payg_captured'])) {
            $results[] = ['name' => '', 'error' => 'Payment required. Please complete the PayPal payment before notarizing.'];
        } elseif (empty($_FILES['documents'])) {
            $results[] = ['name' => '', 'error' => 'No file received.'];
        } else {
            $paygTxnId = $_SESSION['payg_captured'];
            unset($_SESSION['payg_captured']);

            $notarize = new Notarize();
            $file = [
                'name'     => $_FILES['documents']['name'][0],
                'type'     => $_FILES['documents']['type'][0],
                'tmp_name' => $_FILES['documents']['tmp_name'][0],
                'error'    => $_FILES['documents']['error'][0],
                'size'     => $_FILES['documents']['size'][0],
            ];
            $result = $notarize->notarize($authUser['id'], $file);
            if (isset($result['id'])) {
                $billing->recordNotarization($authUser['id'], $paygTxnId);
                redirect('/document.php?id=' . $result['id'] . '&new=1');
            }
            $results[] = array_merge(['name' => $file['name']], $result);
        }
    } elseif (empty($_FILES['documents'])) {
        $results[] = ['name' => '', 'error' => 'No files received.'];
    } else {
        $notarize = new Notarize();
        $files    = $_FILES['documents'];
        $count    = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            $file = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
            $result = $notarize->notarize($authUser['id'], $file);
            if (isset($result['id'])) {
                $billing->recordNotarization($authUser['id']);
            }
            $results[] = array_merge(['name' => $file['name']], $result);
        }

        $successes = array_filter($results, fn($r) => isset($r['id']));
        if (count($successes) === 1 && $count === 1) {
            redirect('/document.php?id=' . $results[0]['id'] . '&new=1');
        }
    }
}

$pageTitle = 'New Notarization';
require '../templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">

            <h2 class="fw-bold mb-1">
                <i class="bi bi-shield-plus me-2 text-primary"></i>New Notarization
            </h2>
            <p class="text-muted mb-4">
                Upload one or more documents. Each file will be hashed with SHA-256,
                signed with an RSA-4096 key, and packaged into a verifiable notarized PDF.
            </p>

            <?php if (!$billingStatus['allowed']): ?>
                <!-- Quota exceeded / subscription failed -->
                <div class="alert alert-warning d-flex align-items-start gap-3">
                    <i class="bi bi-exclamation-triangle-fill fs-4 text-warning mt-1"></i>
                    <div>
                        <strong>Cannot notarize right now.</strong>
                        <p class="mb-2 mt-1"><?= h($billingStatus['reason']) ?></p>
                        <a href="/plans.php" class="btn btn-gold btn-sm">
                            <i class="bi bi-arrow-up-circle me-1"></i>Upgrade Plan
                        </a>
                    </div>
                </div>
            <?php else: ?>

                <?php if (!empty($results)): ?>
                    <?php $successes = array_filter($results, fn($r) => isset($r['id'])); ?>
                    <?php $failures  = array_filter($results, fn($r) => isset($r['error'])); ?>

                    <?php if ($successes): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="bi bi-patch-check-fill me-2"></i>
                            <strong><?= count($successes) ?> document<?= count($successes) !== 1 ? 's' : '' ?> notarized.</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <div class="card border-0 shadow-sm mb-4">
                            <ul class="list-group list-group-flush">
                            <?php foreach ($successes as $r): ?>
                                <li class="list-group-item d-flex align-items-center justify-content-between py-3">
                                    <span><i class="bi bi-file-earmark-check text-success me-2"></i><span class="fw-semibold"><?= h($r['name']) ?></span></span>
                                    <a href="/document.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-gold">
                                        <i class="bi bi-patch-check me-1"></i>Open Certificate
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($failures as $r): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?php if ($r['name']): ?><strong><?= h($r['name']) ?>:</strong> <?php endif; ?>
                            <?= h($r['error']) ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Quota usage bar (non-PAYG) -->
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
                        <strong>Pay As You Go</strong> — you will be charged <strong>$3</strong> via PayPal per document.
                        One file at a time.
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <form method="post" enctype="multipart/form-data" id="uploadForm">
                            <?= csrf_field() ?>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Select Document<?= $isPAYG ? '' : 's' ?></label>
                                <div class="upload-zone" id="dropZone">
                                    <input type="file" name="documents[]" id="fileInput" class="d-none"
                                           accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.txt"
                                           <?= $isPAYG ? '' : 'multiple' ?> required>
                                    <div id="dropZoneContent">
                                        <i class="bi bi-cloud-arrow-up upload-icon"></i>
                                        <p class="mb-1 fw-semibold">Drag &amp; drop or click to select</p>
                                        <p class="text-muted small mb-0">
                                            PDF, JPEG, PNG, GIF, WebP, DOC, DOCX, TXT &mdash; max 10 MB each
                                            <?= $isPAYG ? '' : '&mdash; <strong>multiple files supported</strong>' ?>
                                        </p>
                                    </div>
                                    <div id="fileListWrap" class="d-none">
                                        <i class="bi bi-files upload-icon text-primary"></i>
                                        <ul id="fileList" class="list-unstyled mb-0 mt-2 small text-start" style="max-height:180px;overflow-y:auto"></ul>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info small py-2 mb-4">
                                <i class="bi bi-lock-fill me-2"></i>
                                Files are stored securely and never shared. Your notarized PDF is ready immediately after upload.
                            </div>

                            <?php if ($isPAYG): ?>
                                <!-- PayPal button for PAYG -->
                                <div id="paypal-payg-wrap" class="d-none mb-2">
                                    <div id="paypal-payg-btn"></div>
                                </div>
                                <button type="button" class="btn btn-secondary btn-lg w-100 d-none" id="paygPlaceholder" disabled>
                                    <i class="bi bi-credit-card me-2"></i>Select a file to pay &amp; notarize
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-primary btn-lg w-100" id="submitBtn" disabled>
                                    <i class="bi bi-patch-check me-2"></i><span id="submitLabel">Select files to continue</span>
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<?php if (PAYPAL_CLIENT_ID && $isPAYG): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= h(PAYPAL_CLIENT_ID) ?>&currency=USD"></script>
<?php endif; ?>

<script>
(function () {
    const zone         = document.getElementById('dropZone');
    const input        = document.getElementById('fileInput');
    const dzContent    = document.getElementById('dropZoneContent');
    const fileListWrap = document.getElementById('fileListWrap');
    const fileList     = document.getElementById('fileList');
    const isPAYG       = <?= $isPAYG ? 'true' : 'false' ?>;
    const submitBtn    = document.getElementById('submitBtn');
    const submitLabel  = document.getElementById(submitBtn ? 'submitLabel' : null);
    const paygWrap     = document.getElementById('paypal-payg-wrap');
    const paygPH       = document.getElementById('paygPlaceholder');

    if (!zone) return; // billing blocked, no form

    zone.addEventListener('click', e => { if (!e.target.closest('#paypal-payg-btn')) input.click(); });
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; showFiles(e.dataTransfer.files); }
    });
    input.addEventListener('change', () => { if (input.files.length) showFiles(input.files); });

    let filesReady = false;

    function showFiles(files) {
        fileList.innerHTML = '';
        Array.from(files).forEach(f => {
            const li = document.createElement('li');
            li.className = 'py-1 border-bottom d-flex align-items-center gap-2';
            li.innerHTML = '<i class="bi bi-file-earmark-text text-primary"></i>'
                         + '<span class="flex-grow-1 text-truncate">' + escHtml(f.name) + '</span>'
                         + '<span class="text-muted text-nowrap">' + fmtBytes(f.size) + '</span>';
            fileList.appendChild(li);
        });
        dzContent.classList.add('d-none');
        fileListWrap.classList.remove('d-none');
        filesReady = true;

        if (isPAYG) {
            paygPH && paygPH.classList.add('d-none');
            paygWrap && paygWrap.classList.remove('d-none');
        } else {
            const n = files.length;
            submitLabel.textContent = 'Notarize ' + n + ' document' + (n !== 1 ? 's' : '');
            submitBtn.disabled = false;
        }
    }

    if (!isPAYG) {
        document.getElementById('uploadForm').addEventListener('submit', () => {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Notarizing…';
        });
    }

    <?php if ($isPAYG && PAYPAL_CLIENT_ID): ?>
    if (window.paypal) {
        paypal.Buttons({
            style: { label: 'pay', color: 'gold' },
            createOrder: () => fetch('/billing/payg_create.php', { method: 'POST' })
                .then(r => r.json())
                .then(d => { if (!d.id) throw new Error('Order creation failed'); return d.id; }),
            onApprove: (data) => {
                return fetch('/billing/payg_capture.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: data.orderID })
                })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        paygWrap.innerHTML = '<div class="alert alert-success py-2"><i class="bi bi-check-circle me-2"></i>Payment confirmed. Submitting…</div>';
                        document.getElementById('uploadForm').submit();
                    } else {
                        alert('Payment could not be confirmed. Please try again.');
                    }
                });
            },
            onError: () => alert('PayPal encountered an error. Please try again.')
        }).render('#paypal-payg-btn');
    }
    <?php endif; ?>

    function fmtBytes(b) {
        if (b >= 1048576) return (b/1048576).toFixed(2)+' MB';
        if (b >= 1024)    return (b/1024).toFixed(1)+' KB';
        return b+' B';
    }
    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();
</script>

<?php require '../templates/footer.php'; ?>
