<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? 'Notarize') ?> — Notarize</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary-dark">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/">
            <i class="bi bi-shield-lock-fill text-gold"></i>
            <span>Notarize</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
<?php if (!empty($authUser)): ?>
                <li class="nav-item">
                    <a class="nav-link" href="/dashboard.php">
                        <i class="bi bi-folder2-open me-1"></i>My Documents
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/upload.php">
                        <i class="bi bi-plus-circle me-1"></i>New Notarization
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/verify.php">
                        <i class="bi bi-shield-check me-1"></i>Verify
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/plans.php">
                        <i class="bi bi-credit-card me-1"></i>Plans
                    </a>
                </li>
                <?php if (!empty($authUser['is_admin'])): ?>
                <?php
                    $__pendingCount = 0;
                    try {
                        $__pendingCount = (new App\Notarize())->getPendingCount();
                    } catch (\Throwable $__e) {}
                ?>
                <li class="nav-item">
                    <a class="nav-link text-warning" href="/admin/">
                        <i class="bi bi-speedometer2 me-1"></i>Admin
                        <?php if ($__pendingCount > 0): ?>
                            <span class="badge bg-danger ms-1" style="font-size:.65rem;vertical-align:middle"><?= $__pendingCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <span><?= h($authUser['name']) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li class="dropdown-header small text-muted"><?= h($authUser['email']) ?></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item" href="/plans.php">
                            <i class="bi bi-credit-card me-2"></i>Billing &amp; Plans
                        </a></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item text-danger" href="/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                        </a></li>
                    </ul>
                </li>
<?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="/verify.php">
                        <i class="bi bi-shield-check me-1"></i>Verify a Document
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/login.php">Sign In</a>
                </li>
                <li class="nav-item">
                    <a class="btn btn-gold btn-sm px-3 ms-lg-2" href="/register.php">Create Account</a>
                </li>
<?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main>

<?php if (!empty($authUser) && empty($authUser['email_verified'])): ?>
<div class="alert alert-warning alert-dismissible mb-0 py-2 rounded-0 border-0 border-bottom" style="background:#fff3cd">
    <div class="container d-flex align-items-center gap-2">
        <i class="bi bi-envelope-exclamation-fill text-warning"></i>
        <span class="small">
            <strong>Verify your email</strong> to unlock document submissions.
            <a href="/resend-verification.php" class="ms-2 text-decoration-none fw-semibold">Send verification email →</a>
        </span>
        <button type="button" class="btn-close btn-sm ms-auto" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>
