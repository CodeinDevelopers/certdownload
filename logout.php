<?php
// logout.php - Logout handler
require_once 'auth.php';

logout();
header('Location: login.php');
exit();
?>