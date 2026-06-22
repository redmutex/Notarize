<?php
declare(strict_types=1);
require_once '../../config/config.php';
require_once '../../src/helpers.php';
use App\Auth;
use App\Billing;

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$orderId = trim($input['order_id'] ?? '');

if (!$orderId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing order_id']);
    exit;
}

$billing = new Billing();
$result  = $billing->capturePaygOrder($orderId);

if (($result['status'] ?? '') === 'COMPLETED') {
    $txnId = $result['purchase_units'][0]['payments']['captures'][0]['id'] ?? $orderId;
    $_SESSION['payg_captured'] = $txnId;
    echo json_encode(['success' => true]);
} else {
    http_response_code(402);
    echo json_encode(['success' => false, 'error' => 'Payment not completed']);
}
