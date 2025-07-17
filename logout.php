<?php
require_once 'auth00/user_auth.php';
logout();
header('Location: login');
exit();
?>