<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../src/helpers.php';
use App\Auth;
use App\Notarize;

$auth     = new Auth();
$authUser = $auth->user();
$notarize = new Notarize();

// type: 'notarized' | 'original' | 'photo_id' | 'selfie'
$fileType = trim($_GET['type'] ?? 'notarized');
$isAdmin  = $authUser && !empty($_SESSION['user_is_admin']);

$doc = null;

if (isset($_GET['uuid']) && $fileType === 'notarized') {
    // Public path: verify.php embeds the notarized PDF by UUID — no login required.
    // getDocumentByUuid() only returns status='approved' documents.
    $doc = $notarize->getDocumentByUuid(trim($_GET['uuid']));
} elseif (isset($_GET['id']) && $authUser) {
    $docId = (int)$_GET['id'];
    if ($isAdmin) {
        $doc = $notarize->getDocumentForAdmin($docId);
    } else {
        $doc = $notarize->getDocument($docId, $authUser['id']);
    }
}

if (!$doc) {
    http_response_code(404);
    exit('Not found.');
}

// Access control
if (in_array($fileType, ['original', 'photo_id', 'selfie'], true)) {
    // Only owner or admin may access these
    if (!$authUser || ((int)$authUser['id'] !== (int)$doc['user_id'] && !$isAdmin)) {
        http_response_code(403);
        exit('Forbidden.');
    }
    // photo_id and selfie require admin
    if (in_array($fileType, ['photo_id', 'selfie'], true) && !$isAdmin) {
        http_response_code(403);
        exit('Forbidden.');
    }
}

$uploadDir = UPLOAD_DIR;
$userId    = (int)$doc['user_id'];
$base      = $uploadDir . '/' . $userId . '/';

switch ($fileType) {
    case 'original':
        $filePath    = $base . $doc['stored_filename'];
        $contentType = $doc['mime_type'];
        $dlName      = $doc['original_filename'];
        break;

    case 'photo_id':
        $filePath    = $base . ($doc['photo_id_filename'] ?? '');
        $contentType = mime_content_type($filePath) ?: 'image/jpeg';
        $dlName      = 'photo_id_' . $doc['id'] . '.' . pathinfo((string)$doc['photo_id_filename'], PATHINFO_EXTENSION);
        break;

    case 'selfie':
        $filePath    = $base . ($doc['selfie_filename'] ?? '');
        $contentType = mime_content_type($filePath) ?: 'image/jpeg';
        $dlName      = 'selfie_' . $doc['id'] . '.' . pathinfo((string)$doc['selfie_filename'], PATHINFO_EXTENSION);
        break;

    default: // 'notarized'
        $uuid        = $doc['certificate_uuid'] ?? '';
        $filePath    = $base . basename($uuid) . '_notarized.pdf';
        $contentType = 'application/pdf';
        $dlName      = 'notarized_' . pathinfo($doc['original_filename'], PATHINFO_FILENAME) . '.pdf';
        break;
}

// Path traversal guard
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
