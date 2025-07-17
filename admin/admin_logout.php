<?php
require_once './../auth00/admin_auth.php';
if (isAdminAuthenticated()) {
    logAdminActivity('Admin Logout');
}
adminLogout();
header("Location: admin_login");
exit();
?>