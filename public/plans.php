<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../src/helpers.php';
use App\Auth;
use App\Billing;

$auth = new Auth();
$auth->requireAuth();
$authUser = $auth->user();

$billing = new Billing();
$user    = $billing->getUserBilling($authUser['id']);
$history = $billing->getBillingHistory($authUser['id']);

$error   = '';
$success = '';

// Cancel subscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    if (($_POST['action'] ?? '') === 'cancel') {
        $billing->cancelSubscription($authUser['id']);
        $success = 'Subscription cancelled. You have been moved to the Lite plan.';
        $user    = $billing->getUserBilling($authUser['id']);
    }
}

$plan       = $user['plan_type'] ?? 'lite';
$planInfo   = Billing::PLANS[$plan];
$ppClientId = $_ENV['PAYPAL_CLIENT_ID'] ?? '';
$ppProPlan  = $_ENV['PAYPAL_PRO_PLAN_ID'] ?? '';
$ppElitePlan = $_ENV['PAYPAL_ELITE_PLAN_ID'] ?? '';

$pageTitle = 'Billing & Plans';
require '../templates/header.php';
?>

<div class="container py-5">
    <h2 class="fw-bold mb-1"><i class="bi bi-credit-card me-2 text-primary"></i>Billing &amp; Plans</h2>
    <p class="text-muted mb-5">Choose the plan that fits your needs. Upgrade or downgrade at any time.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= h($success) ?></div>
    <?php endif; ?>

    <!-- Current plan banner -->
    <div class="alert alert-primary d-flex align-items-center gap-3 mb-5">
        <i class="bi bi-shield-check fs-4"></i>
        <div>
            <strong>Current plan: <?= h($planInfo['name']) ?></strong>
            &mdash; <?= h($planInfo['label']) ?>
            <?php if (in_array($plan, ['pro','elite']) && !$user['subscription_active']): ?>
                <span class="badge bg-danger ms-2">Payment failed</span>
            <?php elseif (in_array($plan, ['pro','elite'])): ?>
                <span class="badge bg-success ms-2">Active</span>
            <?php endif; ?>
        </div>
        <?php if (in_array($plan, ['pro','elite'])): ?>
            <form method="post" class="ms-auto" onsubmit="return confirm('Cancel your subscription and return to Lite?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel">
                <button class="btn btn-sm btn-outline-danger">Cancel Subscription</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Plan cards -->
    <div class="row g-4 mb-5">

        <!-- Lite -->
        <div class="col-md-3">
            <div class="card h-100 border-2 <?= $plan === 'lite' ? 'border-primary' : 'border-light' ?>">
                <div class="card-body text-center p-4">
                    <?php if ($plan === 'lite'): ?>
                        <span class="badge bg-primary mb-2">Current plan</span>
                    <?php endif; ?>
                    <h4 class="fw-bold">Lite</h4>
                    <div class="display-6 fw-bold my-3">Free</div>
                    <ul class="list-unstyled text-muted small mb-4">
                        <li class="py-1 border-bottom"><i class="bi bi-check text-success me-1"></i>1 document / month</li>
                        <li class="py-1 border-bottom"><i class="bi bi-check text-success me-1"></i>Full cryptographic certificate</li>
                        <li class="py-1"><i class="bi bi-check text-success me-1"></i>QR verification</li>
                    </ul>
                    <?php if ($plan === 'lite'): ?>
                        <span class="btn btn-secondary w-100 disabled">Current plan</span>
                    <?php else: ?>
                        <span class="btn btn-outline-secondary w-100 disabled">Free tier</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pro -->
        <div class="col-md-3">
            <div class="card h-100 border-2 <?= $plan === 'pro' ? 'border-primary' : 'border-gold' ?>" style="<?= $plan !== 'pro' ? 'border-color:var(--clr-gold)!important' : '' ?>">
                <div class="card-body text-center p-4">
                    <?php if ($plan === 'pro'): ?>
                        <span class="badge bg-primary mb-2">Current plan</span>
                    <?php else: ?>
                        <span class="badge bg-gold text-dark mb-2">Popular</span>
                    <?php endif; ?>
                    <h4 class="fw-bold">Pro</h4>
                    <div class="display-6 fw-bold my-3">$10<small class="fs-6 fw-normal text-muted">/mo</small></div>
                    <ul class="list-unstyled text-muted small mb-4">
                        <li class="py-1 border-bottom"><i class="bi bi-check text-success me-1"></i>10 documents / month</li>
                        <li class="py-1 border-bottom"><i class="bi bi-check text-success me-1"></i>Full cryptographic certificate</li>
                        <li class="py-1"><i class="bi bi-check text-success me-1"></i>Priority support</li>
                    </ul>
                    <?php if ($plan === 'pro'): ?>
                        <span class="btn btn-primary w-100 disabled">Current plan</span>
                    <?php elseif (!empty($ppClientId) && !empty($ppProPlan)): ?>
                        <div id="paypal-pro-btn"></div>
                    <?php else: ?>
                        <span class="btn btn-outline-secondary w-100 disabled">PayPal not configured</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Elite -->
        <div class="col-md-3">
            <div class="card h-100 border-2 <?= $plan === 'elite' ? 'border-primary' : 'border-light' ?>">
                <div class="card-body text-center p-4">
                    <?php if ($plan === 'elite'): ?>
                        <span class="badge bg-primary mb-2">Current plan</span>
                    <?php endif; ?>
                    <h4 class="fw-bold">Elite</h4>
                    <div class="display-6 fw-bold my-3">$50<small class="fs-6 fw-normal text-muted">/mo</small></div>
                    <ul class="list-unstyled text-muted small mb-4">
                        <li class="py-1 border-bottom"><i class="bi bi-check text-success me-1"></i>100 documents / month</li>
                        <li class="py-1 border-bottom"><i class="bi bi-check text-success me-1"></i>Full cryptographic certificate</li>
                        <li class="py-1"><i class="bi bi-check text-success me-1"></i>Priority support</li>
                    </ul>
                    <?php if ($plan === 'elite'): ?>
                        <span class="btn btn-primary w-100 disabled">Current plan</span>
                    <?php elseif (!empty($ppClientId) && !empty($ppElitePlan)): ?>
                        <div id="paypal-elite-btn"></div>
                    <?php else: ?>
                        <span class="btn btn-outline-secondary w-100 disabled">PayPal not configured</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PAYG -->
        <div class="col-md-3">
            <div class="card h-100 border-2 <?= $plan === 'payg' ? 'border-primary' : 'border-light' ?>">
                <div class="card-body text-center p-4">
                    <?php if ($plan === 'payg'): ?>
                        <span class="badge bg-primary mb-2">Current plan</span>
                    <?php endif; ?>
                    <h4 class="fw-bold">Pay As You Go</h4>
                    <div class="display-6 fw-bold my-3">$3<small class="fs-6 fw-normal text-muted">/doc</small></div>
                    <ul class="list-unstyled text-muted small mb-4">
                        <li class="py-1 border-bottom"><i class="bi bi-check text-success me-1"></i>No monthly commitment</li>
                        <li class="py-1 border-bottom"><i class="bi bi-check text-success me-1"></i>Pay per notarization</li>
                        <li class="py-1"><i class="bi bi-check text-success me-1"></i>PayPal at upload time</li>
                    </ul>
                    <?php if ($plan === 'payg'): ?>
                        <span class="btn btn-secondary w-100 disabled">Current plan</span>
                    <?php else: ?>
                        <form method="post" action="/billing/switch_payg.php">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-primary w-100">Switch to Pay As You Go</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /row -->

    <!-- Billing history -->
    <?php if (!empty($history)): ?>
    <h5 class="fw-bold mb-3"><i class="bi bi-receipt me-2"></i>Payment History</h5>
    <div class="card border-0 shadow-sm mb-5">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th class="text-end">Invoice</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $row): ?>
                    <?php
                    $pInfo = Billing::PLANS[$row['plan_type']] ?? ['name' => strtoupper($row['plan_type'])];
                    if ($row['status'] === 'subscription_activated') {
                        $rowDesc = $pInfo['name'] . ' subscription activated';
                    } elseif ($row['status'] === 'completed' && $row['plan_type'] === 'payg') {
                        $rowDesc = 'Pay As You Go — 1 document';
                    } else {
                        $rowDesc = $pInfo['name'] . ' plan payment';
                    }
                    ?>
                    <tr>
                        <td class="font-monospace small text-muted">
                            INV-<?= str_pad((string)$row['id'], 6, '0', STR_PAD_LEFT) ?>
                        </td>
                        <td class="text-muted small text-nowrap">
                            <?= h(date('M j, Y', strtotime($row['created_at']))) ?>
                        </td>
                        <td class="small"><?= h($rowDesc) ?></td>
                        <td class="fw-semibold">$<?= h(number_format((float)$row['amount'], 2)) ?></td>
                        <td>
                            <?php if ($row['status'] === 'completed'): ?>
                                <span class="badge bg-success">Paid</span>
                            <?php elseif ($row['status'] === 'subscription_activated'): ?>
                                <span class="badge bg-primary">Subscription</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?= h($row['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="/billing/invoice.php?id=<?= (int)$row['id'] ?>"
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
    </div>
    <?php endif; ?>

</div><!-- /container -->

<?php if (!empty($ppClientId)): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= h($ppClientId) ?>&vault=true&intent=subscription&currency=USD"></script>
<script>
(function () {
    function subButton(containerId, planId, planKey) {
        if (!document.getElementById(containerId)) return;
        paypal.Buttons({
            style: { label: 'subscribe', color: 'gold' },
            createSubscription: (data, actions) => actions.subscription.create({ plan_id: planId }),
            onApprove: (data) => {
                window.location.href = '/billing/success.php?subscription_id=' + encodeURIComponent(data.subscriptionID) + '&plan=' + planKey;
            },
            onError: (err) => { alert('Payment failed. Please try again.'); }
        }).render('#' + containerId);
    }

    <?php if ($plan !== 'pro' && !empty($ppProPlan)): ?>
    subButton('paypal-pro-btn', '<?= h($ppProPlan) ?>', 'pro');
    <?php endif; ?>

    <?php if ($plan !== 'elite' && !empty($ppElitePlan)): ?>
    subButton('paypal-elite-btn', '<?= h($ppElitePlan) ?>', 'elite');
    <?php endif; ?>

})();
</script>
<?php endif; ?>

<?php require '../templates/footer.php'; ?>
