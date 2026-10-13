<?php
// form_memo_skip.php — Halaman jika penilai memilih lewati memo
require_once __DIR__ . '/config.php';
// Clear session memo
unset($_SESSION['memo_penilaian_id'], $_SESSION['memo_token']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Penilaian Terarsip — PT Indopelita</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
<style>
body {
  background: var(--bg);
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  padding: 24px;
  font-family: 'Plus Jakarta Sans', var(--font-body);
}
.box {
  background: #fff;
  border-radius: 16px;
  padding: 48px 40px;
  text-align: center;
  max-width: 460px;
  width: 100%;
  box-shadow: var(--shadow-md);
  border: 1px solid var(--border-light);
}
.icon {
  width: 64px;
  height: 64px;
  background: #ecfdf5;
  color: #059669;
  border: 1px solid #a7f3d0;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
}
h2 {
  color: var(--navy-900);
  font-size: 21px;
  font-weight: 700;
  margin-bottom: 8px;
}
p {
  color: var(--text-muted);
  font-size: 14px;
  line-height: 1.6;
}
</style>
</head>
<body>
<div class="box">
  <div class="icon"><span style="display:inline-flex;transform:scale(1.3)"><?= icon('check') ?></span></div>
  <h2>Penilaian Berhasil Disimpan</h2>
  <p>Data lembar penilaian kinerja telah tercatat secara resmi ke dalam pangkalan data HRD PT Indopelita.<br>Penerbitan memo perpanjangan dapat dilanjutkan sewaktu-waktu oleh tim HRD.</p>
  <div style="margin-top:24px;padding-top:18px;border-top:1px solid var(--border-light);font-size:12px;color:var(--text-muted);">
    Departemen Human Capital &bull; PT Indopelita Aircraft Industries
  </div>
</div>
</body>
</html>
