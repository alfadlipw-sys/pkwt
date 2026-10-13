<?php
require_once __DIR__ . '/config.php';

if (isAdminLoggedIn()) {
    redirect(APP_URL . '/admin/dashboard.php');
} else {
    redirect(APP_URL . '/admin/login.php');
}
