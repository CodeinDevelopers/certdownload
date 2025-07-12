<?php
require_once 'admin_auth.php';
if (isAdminAuthenticated()) {
    logAdminActivity('Admin Logout');
}
adminLogout();
header("Location: admin_login.php");
exit();
?>