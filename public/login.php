<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../src/helpers.php';
use App\Auth;

$auth = new Auth();
if ($auth->isLoggedIn()) {
    redirect('/dashboard.php');
}

$error  = '';
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email  = trim($_POST['email']    ?? '');
        $pass   = $_POST['password']      ?? '';
        $result = $auth->login($email, $pass);
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $redirect = $_GET['redirect'] ?? '/dashboard.php';
            // Prevent open redirect: must be a relative path starting with / but not // or /\
            if (!preg_match('/^\/[^\/\\\\]/', $redirect)) {
                $redirect = '/dashboard.php';
            }
            redirect($redirect);
        }
    }
}

$pageTitle = 'Sign In';
require '../templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-7 col-lg-5">
            <div class="card shadow border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-patch-check-fill text-primary" style="font-size:2.5rem"></i>
                        <h2 class="fw-bold mt-2">Welcome back</h2>
                        <p class="text-muted small">Sign in to access your document vault</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2">
                            <i class="bi bi-exclamation-circle me-2"></i><?= h($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php $successMsg = flash('success'); if ($successMsg): ?>
                        <div class="alert alert-success py-2">
                            <i class="bi bi-check-circle me-2"></i><?= h($successMsg) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" novalidate>
                        <?= csrf_field() ?>
                        <?php if (!empty($_GET['redirect'])): ?>
                            <input type="hidden" name="redirect" value="<?= h($_GET['redirect']) ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-lg"
                                   value="<?= h($email) ?>" required autofocus
                                   placeholder="jane@example.com">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control form-control-lg"
                                   required placeholder="Your password">
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </button>
                    </form>

                    <p class="text-center text-muted mt-4 mb-0">
                        No account? <a href="/register.php">Create one free</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../templates/footer.php'; ?>
