<?php
declare(strict_types=1);
require_once '../../config/config.php';
require_once '../../src/helpers.php';
use App\Auth;
use App\Billing;

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$billing = new Billing();
$order   = $billing->createPaygOrder();

if (isset($order['id'])) {
    echo json_encode(['id' => $order['id']]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create PayPal order']);
}
