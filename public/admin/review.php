<?php
declare(strict_types=1);
require_once '../../config/config.php';
require_once '../../src/helpers.php';
use App\Auth;
use App\Notarize;
use App\Database;

$auth     = new Auth();
$auth->requireAuth();
$authUser = $auth->user();

$db   = Database::getInstance();
$stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$authUser['id']]);
if (!(int)$stmt->fetchColumn()) {
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    http_response_code(400);
    exit('Bad request.');
}

$action = $_POST['action'] ?? '';
$docId  = (int)($_POST['doc_id'] ?? 0);

if (!in_array($action, ['approve', 'reject'], true) || !$docId) {
    redirect('/admin/pending.php');
}

// Validate and sanitise the redirect destination (must be an internal path)
$rawRedirect  = $_POST['redirect'] ?? '';
$backRedirect = (preg_match('/^\/admin\/[a-zA-Z0-9_\-\.]+\.php(\?[^"\'<>]*)?$/', $rawRedirect))
    ? $rawRedirect
    : '/admin/pending.php';

$notarize = new Notarize();

if ($action === 'approve') {
    $result = $notarize->approve($docId, $authUser['id']);
    if (isset($result['error'])) {
        redirect($backRedirect . (str_contains($backRedirect, '?') ? '&' : '?') . 'error=' . urlencode($result['error']));
    }
    redirect($backRedirect . (str_contains($backRedirect, '?') ? '&' : '?') . 'approved=1');
} else {
    $notes = trim($_POST['notes'] ?? '');
    if (!$notes) {
        redirect($backRedirect . (str_contains($backRedirect, '?') ? '&' : '?') . 'error=' . urlencode('Rejection reason is required.'));
    }
    if (mb_strlen($notes) > 5000) {
        redirect($backRedirect . (str_contains($backRedirect, '?') ? '&' : '?') . 'error=' . urlencode('Remarks must be under 5000 characters.'));
    }
    $notarize->reject($docId, $authUser['id'], $notes);
    redirect($backRedirect . (str_contains($backRedirect, '?') ? '&' : '?') . 'rejected=1');
}
