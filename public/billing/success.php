<?php
declare(strict_types=1);
require_once '../../config/config.php';
require_once '../../src/helpers.php';
use App\Auth;
use App\Billing;

$auth = new Auth();
$auth->requireAuth();
$authUser = $auth->user();

$subscriptionId = trim($_GET['subscription_id'] ?? '');
$plan           = trim($_GET['plan'] ?? '');

$ok    = false;
$error = '';

if ($subscriptionId && in_array($plan, ['pro', 'elite'], true)) {
    $billing = new Billing();
    $ok      = $billing->activateSubscription($authUser['id'], $subscriptionId, $plan);
    if (!$ok) {
        $error = 'Could not verify your subscription with PayPal. If payment was taken, please contact support.';
    }
} else {
    $error = 'Invalid request.';
}

$pageTitle = $ok ? 'Subscription Activated' : 'Activation Error';
require '../../templates/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <?php if ($ok): ?>
                <div class="mb-4"><i class="bi bi-patch-check-fill text-success" style="font-size:5rem"></i></div>
                <h2 class="fw-bold mb-2">You're on <?= h(ucfirst($plan)) ?>!</h2>
                <p class="text-muted mb-4">Your subscription is active. Your document quota has been reset.</p>
                <a href="/upload.php" class="btn btn-gold btn-lg me-2">
                    <i class="bi bi-plus-circle me-1"></i>Notarize a Document
                </a>
                <a href="/plans.php" class="btn btn-outline-secondary btn-lg">Billing &amp; Plans</a>
            <?php else: ?>
                <div class="mb-4"><i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:5rem"></i></div>
                <h2 class="fw-bold mb-2">Activation Issue</h2>
                <p class="text-muted mb-4"><?= h($error) ?></p>
                <a href="/plans.php" class="btn btn-primary btn-lg">Back to Plans</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require '../../templates/footer.php'; ?>
