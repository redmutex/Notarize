<?php
declare(strict_types=1);
require_once '../../config/config.php';
require_once '../../src/helpers.php';
use App\Auth;
use App\Database;

$auth = new Auth();
$auth->requireAuth();
$authUser = $auth->user();

// CSRF via query param (GET link from plans.php)
if (($_GET['csrf'] ?? '') !== csrf_token()) {
    http_response_code(403);
    redirect('/plans.php');
}

$db = Database::getInstance();
$db->prepare(
    "UPDATE users
     SET plan_type = 'payg', paypal_subscription_id = NULL, subscription_active = 1,
         plan_docs_used = 0, plan_period_start = CURDATE()
     WHERE id = ?"
)->execute([$authUser['id']]);

flash('success', 'Switched to Pay As You Go. You will be charged $3 per document.');
redirect('/plans.php');
