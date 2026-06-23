<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../src/helpers.php';
use App\Auth;

$auth  = new Auth();
$token = trim($_GET['token'] ?? '');
$ok    = $token !== '' && $auth->verifyEmail($token);

$pageTitle = $ok ? 'Email Verified' : 'Invalid Verification Link';
require '../templates/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <?php if ($ok): ?>
                <div class="mb-4"><i class="bi bi-patch-check-fill text-success" style="font-size:5rem"></i></div>
                <h2 class="fw-bold mb-2">Email Verified!</h2>
                <p class="text-muted mb-4">Your email address has been confirmed. You can now submit documents for notarization.</p>
                <a href="/upload.php" class="btn btn-gold btn-lg me-2">
                    <i class="bi bi-plus-circle me-1"></i>Submit a Document
                </a>
                <a href="/dashboard.php" class="btn btn-outline-secondary btn-lg">My Documents</a>
            <?php else: ?>
                <div class="mb-4"><i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:5rem"></i></div>
                <h2 class="fw-bold mb-2">Link Invalid or Expired</h2>
                <p class="text-muted mb-4">This verification link is invalid or has already been used.</p>
                <?php if ($auth->isLoggedIn()): ?>
                    <a href="/resend-verification.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-envelope-arrow-up me-1"></i>Send a New Link
                    </a>
                <?php else: ?>
                    <a href="/login.php" class="btn btn-primary btn-lg">Sign In</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require '../templates/footer.php'; ?>
