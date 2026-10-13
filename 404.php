<?php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Halaman Tidak Ditemukan – PKWT Indopelita</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
<style>
body {
  font-family: 'Plus Jakarta Sans', var(--font-body);
  background: var(--bg);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
.box {
  background: #fff;
  border-radius: 16px;
  padding: 48px 40px;
  text-align: center;
  max-width: 440px;
  width: 100%;
  box-shadow: var(--shadow-md);
  border: 1px solid var(--border-light);
}
.num {
  font-size: 72px;
  font-weight: 800;
  color: #0284c7;
  line-height: 1;
  letter-spacing: -2px;
  margin-bottom: 12px;
}
h2 {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 8px;
  color: var(--navy-900);
}
p {
  color: var(--text-muted);
  font-size: 14px;
  line-height: 1.6;
  margin-bottom: 24px;
}
</style>
</head>
<body>
<div class="box">
  <div class="num">404</div>
  <h2>Halaman Tidak Ditemukan</h2>
  <p>Halaman atau dokumen yang Anda cari tidak tersedia dalam direktori sistem.</p>
  <a href="javascript:history.back()" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:8px;">
    <?= icon('arrow-left') ?> Kembali ke Halaman Sebelumnya
  </a>
</div>
</body>
</html>
