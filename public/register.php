<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../src/helpers.php';
use App\Auth;

$auth = new Auth();
if ($auth->isLoggedIn()) {
    redirect('/dashboard.php');
}

$errors = [];
$values = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $name     = trim($_POST['name']     ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';
        $values   = ['name' => $name, 'email' => $email];

        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        } else {
            $result = $auth->register($name, $email, $password);
            if (isset($result['error'])) {
                $errors[] = $result['error'];
            } else {
                $auth->login($email, $password);
                flash('success', 'Welcome! Your account has been created.');
                redirect('/dashboard.php');
            }
        }
    }
}

$pageTitle = 'Create Account';
require '../templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-7 col-lg-5">
            <div class="card shadow border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-patch-check-fill text-primary" style="font-size:2.5rem"></i>
                        <h2 class="fw-bold mt-2">Create Account</h2>
                        <p class="text-muted small">Start notarizing your documents</p>
                    </div>

                    <?php foreach ($errors as $e): ?>
                        <div class="alert alert-danger py-2">
                            <i class="bi bi-exclamation-circle me-2"></i><?= h($e) ?>
                        </div>
                    <?php endforeach; ?>

                    <form method="post" novalidate>
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Full Name</label>
                            <input type="text" name="name" class="form-control form-control-lg"
                                   value="<?= h($values['name']) ?>" required autofocus
                                   placeholder="Jane Doe">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-lg"
                                   value="<?= h($values['email']) ?>" required
                                   placeholder="jane@example.com">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control form-control-lg"
                                   required minlength="8" placeholder="Minimum 8 characters">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Confirm Password</label>
                            <input type="password" name="password_confirm" class="form-control form-control-lg"
                                   required placeholder="Repeat your password">
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-person-plus me-2"></i>Create Account
                        </button>
                    </form>

                    <p class="text-center text-muted mt-4 mb-0">
                        Already have an account? <a href="/login.php">Sign in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../templates/footer.php'; ?>
