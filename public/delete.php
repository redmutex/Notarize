<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../src/helpers.php';
use App\Auth;
use App\Notarize;

$auth = new Auth();
$auth->requireAuth();
$authUser = $auth->user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    http_response_code(400);
    redirect('/dashboard.php');
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    redirect('/dashboard.php');
}

$notarize = new Notarize();
$ok       = $notarize->delete($id, $authUser['id']);

if ($ok) {
    flash('success', 'Document removed from the system.');
} else {
    flash('error', 'Document not found or you do not have permission to delete it.');
}

redirect('/dashboard.php');
