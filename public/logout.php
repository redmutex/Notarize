<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once '../src/helpers.php';
use App\Auth;

$auth = new Auth();
$auth->logout();
redirect('/login.php');
