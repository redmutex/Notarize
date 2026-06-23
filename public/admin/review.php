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

$notarize = new Notarize();

if ($action === 'approve') {
    $result = $notarize->approve($docId, $authUser['id']);
    if (isset($result['error'])) {
        // Redirect back with error
        redirect('/admin/pending.php?error=' . urlencode($result['error']));
    }
    redirect('/admin/pending.php?approved=1');
} else {
    $notes = trim($_POST['notes'] ?? '');
    if (!$notes) {
        redirect('/admin/pending.php?error=' . urlencode('Rejection reason is required.'));
    }
    $notarize->reject($docId, $authUser['id'], $notes);
    redirect('/admin/pending.php?rejected=1');
}
