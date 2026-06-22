<?php
declare(strict_types=1);
require_once '../../config/config.php';
require_once '../../src/helpers.php';
use App\Auth;

$auth = new Auth();
$auth->requireAuth();

$pageTitle = 'Payment Cancelled';
require '../../templates/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <div class="mb-4"><i class="bi bi-x-circle-fill text-secondary" style="font-size:5rem"></i></div>
            <h2 class="fw-bold mb-2">Payment Cancelled</h2>
            <p class="text-muted mb-4">No charges were made. You can try again whenever you're ready.</p>
            <a href="/plans.php" class="btn btn-primary btn-lg me-2">View Plans</a>
            <a href="/dashboard.php" class="btn btn-outline-secondary btn-lg">My Documents</a>
        </div>
    </div>
</div>
<?php require '../../templates/footer.php'; ?>
