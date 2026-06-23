<?php
declare(strict_types=1);
require_once '../../config/config.php';
require_once '../../src/helpers.php';
use App\Auth;
use App\Billing;
use App\Database;
use App\Notarize;

$auth     = new Auth();
$auth->requireAuth();
$authUser = $auth->user();

$db   = Database::getInstance();
$stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$authUser['id']]);
if (!(int)$stmt->fetchColumn()) {
    redirect('/dashboard.php');
}

$userId = (int)($_GET['id'] ?? 0);
if (!$userId) {
    redirect('/admin/users.php');
}

$billing  = new Billing();
$user     = $billing->getUserDetail($userId);
if (!$user) {
    redirect('/admin/users.php');
}

$docs         = $billing->getUserDocumentsAdmin($userId);
$transactions = $billing->getUserTransactions($userId);
$notarize     = new Notarize();

// Flash messages from review actions
$flashOk  = $_GET['approved'] ?? $_GET['rejected'] ?? null;
$flashErr = $_GET['error'] ?? null;

$pageTitle = 'User — ' . $user['name'];
require '../../templates/header.php';

// Plan badge helper
$planBadge = match($user['plan_type']) {
    'pro'   => 'primary',
    'elite' => 'warning text-dark',
    'payg'  => 'info text-dark',
    default => 'secondary',
};

// Status label helper
function statusBadge(string $status): string {
    return match($status) {
        'pending'  => '<span class="badge bg-warning text-dark">Pending</span>',
        'rejected' => '<span class="badge bg-danger">Rejected</span>',
        default    => '<span class="badge bg-success">Approved</span>',
    };
}

function txnLabel(string $status): string {
    return match($status) {
        'subscription_activated' => '<span class="badge bg-primary">Subscription</span>',
        'completed'              => '<span class="badge bg-success">Paid</span>',
        default                  => '<span class="badge bg-secondary">' . h($status) . '</span>',
    };
}
?>

<div class="container-fluid py-4 px-4">

    <!-- Breadcrumb + back -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="/admin/">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="/admin/users.php">Users</a></li>
            <li class="breadcrumb-item active"><?= h($user['name']) ?></li>
        </ol>
    </nav>

    <?php if ($flashOk !== null): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php if (isset($_GET['approved'])): ?>
            <i class="bi bi-check-circle me-2"></i>Document approved and notarized successfully.
        <?php else: ?>
            <i class="bi bi-x-circle me-2"></i>Document rejected and user notified.
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($flashErr): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-2"></i><?= h($flashErr) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- User profile card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row g-3 align-items-start">
                <div class="col-md-7">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-bold"
                             style="width:52px;height:52px;font-size:1.3rem;flex-shrink:0">
                            <?= mb_strtoupper(mb_substr($user['name'], 0, 1)) ?>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-0"><?= h($user['name']) ?></h4>
                            <div class="text-muted small"><?= h($user['email']) ?></div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-<?= $planBadge ?> px-3 py-2">
                            <i class="bi bi-credit-card me-1"></i><?= strtoupper($user['plan_type']) ?>
                        </span>
                        <?php if ($user['email_verified']): ?>
                            <span class="badge bg-success-subtle text-success border border-success px-3 py-2">
                                <i class="bi bi-patch-check me-1"></i>Email Verified
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2">
                                <i class="bi bi-envelope-exclamation me-1"></i>Unverified Email
                            </span>
                        <?php endif; ?>
                        <?php if (in_array($user['plan_type'], ['pro','elite'])): ?>
                            <?php if ($user['subscription_active']): ?>
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2">
                                    <i class="bi bi-check-circle me-1"></i>Subscription Active
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2">
                                    <i class="bi bi-x-circle me-1"></i>Subscription Failed
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="fs-3 fw-bold text-primary"><?= (int)$user['total_docs'] ?></div>
                            <div class="small text-muted">Total Docs</div>
                        </div>
                        <div class="col-4">
                            <div class="fs-3 fw-bold text-success"><?= (int)$user['approved_docs'] ?></div>
                            <div class="small text-muted">Notarized</div>
                        </div>
                        <div class="col-4">
                            <div class="fs-3 fw-bold <?= (int)$user['pending_docs'] > 0 ? 'text-warning' : 'text-muted' ?>">
                                <?= (int)$user['pending_docs'] ?>
                            </div>
                            <div class="small text-muted">Pending</div>
                        </div>
                    </div>
                    <div class="text-muted small mt-3">
                        <i class="bi bi-calendar3 me-1"></i>Joined <?= h(date('M j, Y', strtotime($user['created_at']))) ?>
                        <?php if ($user['paypal_subscription_id']): ?>
                            <br><i class="bi bi-paypal me-1 mt-1"></i>Sub ID:
                            <code class="small"><?= h($user['paypal_subscription_id']) ?></code>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-0" id="userTabs">
        <li class="nav-item">
            <button class="nav-link active fw-semibold" data-bs-toggle="tab" data-bs-target="#tabDocs">
                <i class="bi bi-files me-1"></i>Documents
                <?php if ((int)$user['pending_docs'] > 0): ?>
                    <span class="badge bg-warning text-dark ms-1"><?= (int)$user['pending_docs'] ?></span>
                <?php endif; ?>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#tabTxns">
                <i class="bi bi-receipt me-1"></i>Transactions
                <span class="badge bg-secondary ms-1"><?= count($transactions) ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- ── Documents tab ── -->
        <div class="tab-pane fade show active" id="tabDocs">
            <div class="card border-0 border-top-0 shadow-sm rounded-top-0">
                <?php if (empty($docs)): ?>
                    <div class="card-body text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 opacity-25"></i>
                        <div class="mt-3">No documents submitted yet.</div>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>File</th>
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
                                <td>
                                    <div class="fw-semibold small"><?= h($doc['original_filename']) ?></div>
                                    <?php if ($doc['status'] === 'rejected' && $doc['review_notes']): ?>
                                        <div class="text-danger" style="font-size:.7rem">
                                            <i class="bi bi-chat-left-text me-1"></i><?= h(mb_strimwidth($doc['review_notes'], 0, 80, '…')) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($doc['status'] === 'approved'): ?>
                                        <div class="text-muted" style="font-size:.7rem;font-family:monospace"><?= h($doc['certificate_uuid']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= h(explode('/', $doc['mime_type'])[1] ?? $doc['mime_type']) ?></td>
                                <td class="small text-muted"><?= format_bytes((int)$doc['file_size']) ?></td>
                                <td><?= statusBadge($doc['status']) ?></td>
                                <td class="small text-muted text-nowrap">
                                    <?= $doc['submitted_at'] ? h(date('M j, Y H:i', strtotime($doc['submitted_at']))) : '—' ?>
                                    <?php if ($doc['status'] === 'approved' && $doc['notarized_at']): ?>
                                        <br><span style="font-size:.7rem" class="text-success">
                                            <i class="bi bi-check2-circle me-1"></i><?= h(date('M j, Y H:i', strtotime($doc['notarized_at']))) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <!-- View document -->
                                        <a href="/serve.php?id=<?= (int)$doc['id'] ?>&type=original"
                                           target="_blank" class="btn btn-outline-primary btn-sm" title="View document">
                                            <i class="bi bi-file-earmark-arrow-down"></i>
                                        </a>
                                        <!-- Photo ID -->
                                        <?php if ($doc['photo_id_filename']): ?>
                                        <a href="/serve.php?id=<?= (int)$doc['id'] ?>&type=photo_id"
                                           target="_blank" class="btn btn-outline-secondary btn-sm" title="Photo ID">
                                            <i class="bi bi-credit-card-2-front"></i>
                                        </a>
                                        <?php endif; ?>
                                        <!-- Selfie -->
                                        <?php if ($doc['selfie_filename']): ?>
                                        <a href="/serve.php?id=<?= (int)$doc['id'] ?>&type=selfie"
                                           target="_blank" class="btn btn-outline-secondary btn-sm" title="Selfie">
                                            <i class="bi bi-person-bounding-box"></i>
                                        </a>
                                        <?php endif; ?>
                                        <!-- Verify link for approved -->
                                        <?php if ($doc['status'] === 'approved' && $doc['certificate_uuid']): ?>
                                        <a href="/verify.php?uuid=<?= h($doc['certificate_uuid']) ?>"
                                           target="_blank" class="btn btn-outline-success btn-sm" title="Verify certificate">
                                            <i class="bi bi-shield-check"></i>
                                        </a>
                                        <?php endif; ?>
                                        <!-- Approve (pending only) -->
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
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Transactions tab ── -->
        <div class="tab-pane fade" id="tabTxns">
            <div class="card border-0 border-top-0 shadow-sm rounded-top-0">
                <?php if (empty($transactions)): ?>
                    <div class="card-body text-center py-5 text-muted">
                        <i class="bi bi-receipt fs-1 opacity-25"></i>
                        <div class="mt-3">No transactions yet.</div>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>PayPal Ref</th>
                                <th>Status</th>
                                <th class="text-end">Invoice</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($transactions as $txn): ?>
                            <tr>
                                <td class="font-monospace small text-muted">INV-<?= str_pad((string)$txn['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                <td class="small text-nowrap"><?= h(date('M j, Y H:i', strtotime($txn['created_at']))) ?> UTC</td>
                                <td class="small">
                                    <?php
                                    $planInfo = Billing::PLANS[$txn['plan_type']] ?? ['name' => strtoupper($txn['plan_type'])];
                                    if ($txn['status'] === 'subscription_activated'):
                                    ?>
                                        <i class="bi bi-arrow-repeat me-1 text-primary"></i>
                                        <?= h($planInfo['name']) ?> subscription activated
                                    <?php elseif ($txn['status'] === 'completed' && $txn['plan_type'] === 'payg'): ?>
                                        <i class="bi bi-lightning me-1 text-info"></i>
                                        Pay As You Go — 1 document
                                    <?php else: ?>
                                        <i class="bi bi-credit-card me-1 text-secondary"></i>
                                        <?= h($planInfo['name']) ?> plan — <?= h($txn['status']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold">$<?= number_format((float)$txn['amount'], 2) ?> <?= h($txn['currency']) ?></td>
                                <td class="font-monospace" style="font-size:.7rem">
                                    <?php $ref = $txn['paypal_txn_id'] ?? $txn['paypal_subscription'] ?? ''; ?>
                                    <?= $ref ? h(mb_strimwidth($ref, 0, 22, '…')) : '<span class="text-muted">—</span>' ?>
                                </td>
                                <td><?= txnLabel($txn['status']) ?></td>
                                <td class="text-end">
                                    <a href="/billing/invoice.php?id=<?= (int)$txn['id'] ?>"
                                       class="btn btn-sm btn-outline-secondary" target="_blank"
                                       title="Download invoice PDF">
                                        <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                    <span class="small text-muted"><?= count($transactions) ?> transaction<?= count($transactions) !== 1 ? 's' : '' ?></span>
                    <span class="fw-semibold">
                        Total paid:
                        $<?= number_format(array_sum(array_column($transactions, 'amount')), 2) ?> USD
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /tab-content -->

</div>

<!-- Approve modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="/admin/review.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="doc_id" id="approveDocId">
                <input type="hidden" name="redirect" value="/admin/user.php?id=<?= $userId ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-check-circle text-success me-2"></i>Approve &amp; Notarize</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Sign and notarize <strong id="approveDocName"></strong>?</p>
                    <p class="text-muted small mb-0">
                        This will RSA-4096 sign the document, issue a certificate, generate the notarized PDF,
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
                <input type="hidden" name="redirect" value="/admin/user.php?id=<?= $userId ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-x-circle text-danger me-2"></i>Reject Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Reject <strong id="rejectDocName"></strong>? The user will receive an email with your remarks.</p>
                    <label class="form-label fw-semibold" for="rejectNotes">
                        Remarks <span class="text-danger">*</span>
                    </label>
                    <textarea name="notes" id="rejectNotes" class="form-control" rows="4"
                              placeholder="e.g., Photo ID is not clearly legible. Please resubmit with better lighting."
                              required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle me-1"></i>Reject &amp; Notify User
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
