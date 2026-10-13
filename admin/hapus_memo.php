<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$db = db();

$id = intval($_GET['id'] ?? 0);
if (!$id) { redirect(APP_URL . '/admin/memo.php'); }

// Verifikasi memo ada
$chk = $db->prepare("SELECT id, karyawan_id FROM memo_perpanjangan WHERE id=?");
$chk->bind_param('i', $id);
$chk->execute();
$memo = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$memo) { redirect(APP_URL . '/admin/memo.php'); }

// Hapus memo
$del = $db->prepare("DELETE FROM memo_perpanjangan WHERE id=?");
$del->bind_param('i', $id);
$del->execute();
$del->close();

// Audit log
auditLog($_SESSION['admin_id'] ?? 0, 'hapus_memo', "Memo ID: $id");

// Redirect kembali
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if (str_contains($ref, 'detail_memo')) {
    redirect(APP_URL . '/admin/memo.php');
} else {
    redirect(APP_URL . '/admin/memo.php?deleted=1');
}
