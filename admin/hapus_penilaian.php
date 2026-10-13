<?php
require_once __DIR__ . '/../config.php';
requireAdmin();

$id = intval($_GET['id'] ?? 0);
if (!$id) redirect(APP_URL . '/admin/penilaian.php');

$db = db();

// Ambil info dulu untuk log
$stmt = $db->prepare("SELECT p.id, p.karyawan_id, p.token_id, p.periode, k.nama FROM penilaian p JOIN karyawan k ON k.id=p.karyawan_id WHERE p.id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) redirect(APP_URL . '/admin/penilaian.php');

// Hapus penilaian & kembalikan token agar bisa diisi ulang
$db->query("DELETE FROM penilaian WHERE id=$id");
$db->query("UPDATE form_tokens SET is_submitted=0, submitted_at=NULL, submitted_ip=NULL WHERE id={$row['token_id']}");

auditLog($_SESSION['admin_id'], 'HAPUS_PENILAIAN', "ID:{$id} Karyawan:{$row['nama']} Periode:{$row['periode']}");

$_SESSION['flash'] = "Penilaian <strong>{$row['nama']}</strong> periode <strong>{$row['periode']}</strong> berhasil dihapus. Token dikembalikan (bisa diisi ulang).";
redirect(APP_URL . '/admin/penilaian.php');
