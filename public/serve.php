<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../src/helpers.php';
use App\Auth;
use App\Notarize;
use App\NotarizePDF;

$auth     = new Auth();
$authUser = $auth->user();
$notarize = new Notarize();

$doc      = null;
$fileType = trim($_GET['type'] ?? 'notarized'); // 'notarized' | 'original'

if (isset($_GET['uuid'])) {
    $uuid = preg_replace('/[^a-f0-9\-]/', '', $_GET['uuid']);
    $doc  = $notarize->getDocumentByUuid($uuid);
} elseif (isset($_GET['id']) && $authUser) {
    $doc = $notarize->getDocument((int)$_GET['id'], $authUser['id']);
}

if (!$doc) {
    http_response_code(404);
    exit('Not found.');
}

// Original file access requires authentication and ownership
if ($fileType === 'original') {
    $ownerAllowed = $authUser && (int)$authUser['id'] === (int)$doc['user_id'];
    $uuidAllowed  = isset($_GET['uuid']); // public verify link may also show original
    if (!$ownerAllowed && !$uuidAllowed) {
        http_response_code(403);
        exit('Forbidden.');
    }
}

$uploadDir = UPLOAD_DIR;
$userId    = (int)$doc['user_id'];
$uuid      = $doc['certificate_uuid'];

if ($fileType === 'original') {
    $filePath    = $uploadDir . '/' . $userId . '/' . $doc['stored_filename'];
    $contentType = $doc['mime_type'];
    $dlName      = $doc['original_filename'];
} else {
    $filePath    = $uploadDir . '/' . $userId . '/' . $uuid . '_notarized.pdf';
    $contentType = 'application/pdf';
    $dlName      = 'notarized_' . pathinfo($doc['original_filename'], PATHINFO_FILENAME) . '.pdf';

    // Lazy-generate the notarized PDF if it doesn't exist yet
    if (!is_file($filePath)) {
        try {
            $verifyUrl = APP_URL . '/verify.php?uuid=' . urlencode($uuid);
            (new NotarizePDF())->generate($doc, $verifyUrl, $uploadDir);
        } catch (\Throwable $e) {
            // generation failed — fall through to 404
        }
    }
}

// Prevent path traversal: both paths must be inside UPLOAD_DIR
$realUpload = realpath($uploadDir);
$realFile   = is_file($filePath) ? realpath($filePath) : false;

if (!$realFile || !$realUpload || !str_starts_with($realFile, $realUpload . '/')) {
    http_response_code(404);
    exit('File not found.');
}

header('Content-Type: ' . $contentType);
header('Content-Disposition: inline; filename="' . rawurlencode($dlName) . '"');
header('Content-Length: ' . filesize($realFile));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($realFile);
