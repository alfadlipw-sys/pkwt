<?php
// form_memo_skip.php — Halaman jika penilai memilih skip memo
require_once __DIR__ . '/config.php';
// Clear session memo
unset($_SESSION['memo_penilaian_id'], $_SESSION['memo_token']);
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>Selesai</title>
<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Segoe UI',sans-serif;background:#0f1923;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}
.box{background:#fff;border-radius:16px;padding:50px 40px;text-align:center;max-width:440px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.5);}
.icon{width:72px;height:72px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:32px;}
h2{color:#1e6fd9;font-size:20px;margin-bottom:10px;}p{color:#555;font-size:14px;line-height:1.7;}</style>
</head><body>
<div class="box">
  <div class="icon">✅</div>
  <h2>Penilaian Berhasil Disimpan</h2>
  <p>Data penilaian kinerja telah berhasil dikirimkan ke sistem HRD.<br>Memo perpanjangan dapat dibuat oleh HRD melalui panel admin.</p>
  <p style="font-size:12px;color:#aaa;margin-top:20px">Terima kasih atas partisipasi Anda.</p>
</div>
</body></html>
