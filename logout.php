<?php
require_once 'vendor/autoload.php';

use CCSD\Search\Auth;

$auth = new Auth();
$auth->logout();

header('Location: login.php');
exit;