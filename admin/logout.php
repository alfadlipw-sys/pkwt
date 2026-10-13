<?php
require_once __DIR__ . '/../config.php';
if (isAdminLoggedIn()) {
    auditLog($_SESSION['admin_id'], 'LOGOUT', 'Keluar dari sistem');
}
session_destroy();
redirect(APP_URL . '/admin/login.php');
