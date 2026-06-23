<?php
declare(strict_types=1);
require_once '../../config/config.php';
use App\Billing;

$body = (string)file_get_contents('php://input');

if (empty($body)) {
    http_response_code(400);
    exit;
}

$headers = [];
foreach (getallheaders() as $k => $v) {
    $headers[strtoupper(str_replace('-', '_', $k))] = $v;
}

$billing = new Billing();

if (!$billing->verifyWebhookSignature($body, $headers)) {
    http_response_code(400);
    exit;
}

$billing->handleWebhook($body, $headers);

http_response_code(200);
echo 'OK';
