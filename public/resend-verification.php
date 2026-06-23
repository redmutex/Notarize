<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../src/helpers.php';
use App\Auth;

$auth = new Auth();
$auth->requireAuth();
$authUser = $auth->user();

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $sent = $auth->resendVerification($authUser['id']);
}

$pageTitle = 'Resend Verification Email';
require '../templates/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <?php if ($authUser['email_verified']): ?>
                <div class="mb-3"><i class="bi bi-patch-check-fill text-success" style="font-size:4rem"></i></div>
                <h3 class="fw-bold">Already Verified</h3>
                <p class="text-muted">Your email address is already confirmed.</p>
                <a href="/dashboard.php" class="btn btn-primary">My Documents</a>
            <?php elseif ($sent): ?>
                <div class="mb-3"><i class="bi bi-envelope-check-fill text-primary" style="font-size:4rem"></i></div>
                <h3 class="fw-bold">Email Sent</h3>
                <p class="text-muted">A new verification link has been sent to <strong><?= h($authUser['email']) ?></strong>. Check your inbox.</p>
                <a href="/dashboard.php" class="btn btn-outline-secondary">Go to Dashboard</a>
            <?php else: ?>
                <div class="mb-3"><i class="bi bi-envelope-exclamation-fill text-warning" style="font-size:4rem"></i></div>
                <h3 class="fw-bold mb-2">Verify Your Email</h3>
                <p class="text-muted mb-4">
                    We'll send a verification link to <strong><?= h($authUser['email']) ?></strong>.
                </p>
                <form method="post">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-envelope-arrow-up me-2"></i>Send Verification Email
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require '../templates/footer.php'; ?>
