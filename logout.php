<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/User.php';
require_once 'includes/functions.php';

$user = new User();
$user->logout();

redirect(SITE_URL . '/login.php');
