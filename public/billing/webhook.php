<?php
declare(strict_types=1);
require_once '../../config/config.php';
use App\Billing;

// PayPal sends raw JSON body
$body    = (string)file_get_contents('php://input');
$headers = [];
foreach (getallheaders() as $k => $v) {
    $headers[strtoupper(str_replace('-', '_', $k))] = $v;
}

if (empty($body)) {
    http_response_code(400);
    exit;
}

(new Billing())->handleWebhook($body, $headers);

http_response_code(200);
echo 'OK';
