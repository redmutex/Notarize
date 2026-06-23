<?php
declare(strict_types=1);
require_once '../../config/config.php';
require_once '../../src/helpers.php';
use App\Auth;
use App\Billing;

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
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

// Verify the order ID was created by this session — prevents using a self-created cheap order
$pendingOrder = $_SESSION['payg_pending_order'] ?? '';
if (!$pendingOrder || !hash_equals($pendingOrder, $orderId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Order ID mismatch']);
    exit;
}
unset($_SESSION['payg_pending_order']);

$billing = new Billing();
$result  = $billing->capturePaygOrder($orderId);

if (($result['status'] ?? '') !== 'COMPLETED') {
    http_response_code(402);
    echo json_encode(['success' => false, 'error' => 'Payment not completed']);
    exit;
}

// Verify the captured amount matches the expected PAYG price
$capturedAmount = (float)($result['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0);
if ($capturedAmount < (float)Billing::PAYG_PRICE - 0.01) {
    error_log('[Notarize] PAYG amount mismatch: captured ' . $capturedAmount . ' for order ' . $orderId);
    http_response_code(402);
    echo json_encode(['success' => false, 'error' => 'Payment amount incorrect']);
    exit;
}

$txnId = $result['purchase_units'][0]['payments']['captures'][0]['id'] ?? $orderId;
$_SESSION['payg_captured'] = $txnId;

echo json_encode(['success' => true]);
