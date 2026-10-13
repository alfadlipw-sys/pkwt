<?php
require_once __DIR__ . '/config.php';

$db    = db();
$token = trim($_GET['token'] ?? '');

// Validasi token
if (!$token) { die('<div style="font-family:sans-serif;text-align:center;padding:80px;"><h2>❌ Link tidak valid.</h2></div>'); }

$stmt = $db->prepare("SELECT ft.*, k.nik, k.nama, k.jabatan, k.atasan_langsung, k.mulai_bekerja FROM form_tokens ft JOIN karyawan k ON k.id=ft.karyawan_id WHERE ft.token=?");
$stmt->bind_param('s', $token);
$stmt->execute();
$ft = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ft) { die('<div style="font-family:sans-serif;text-align:center;padding:80px;"><h2>❌ Link tidak ditemukan atau sudah tidak berlaku.</h2></div>'); }
if ($ft['is_submitted']) { ?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Sudah Diisi</title>
<style>body{font-family:'Segoe UI',sans-serif;background:#f0f2f5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}
.box{background:#fff;border-radius:12px;padding:50px 40px;text-align:center;max-width:420px;box-shadow:0 4px 20px rgba(0,0,0,0.1);}
.icon{font-size:60px;margin-bottom:16px;} h2{color:#16a34a;} p{color:#555;font-size:14px;}</style>
</head><body><div class="box"><div class="icon">✅</div>
<h2>Form Sudah Diisi</h2>
<p>Penilaian untuk karyawan <strong><?= e($ft['nama']) ?></strong> periode <strong><?= e($ft['periode']) ?></strong> telah berhasil disimpan.</p>
<p style="margin-top:20px;font-size:12px;color:#aaa">Terima kasih telah mengisi penilaian.</p>
</div></body></html>
<?php exit; }

if (strtotime($ft['expires_at']) < time()) { ?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Link Kadaluarsa</title>
<style>body{font-family:'Segoe UI',sans-serif;background:#f0f2f5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}
.box{background:#fff;border-radius:12px;padding:50px 40px;text-align:center;max-width:420px;box-shadow:0 4px 20px rgba(0,0,0,0.1);}
.icon{font-size:60px;} h2{color:#dc2626;} p{color:#555;font-size:14px;}</style>
</head><body><div class="box"><div class="icon">⏰</div>
<h2>Link Sudah Kadaluarsa</h2>
<p>Link penilaian ini sudah tidak aktif sejak <strong><?= date('d/m/Y H:i', strtotime($ft['expires_at'])) ?></strong>.</p>
<p>Silakan hubungi HRD untuk mendapatkan link baru.</p>
</div></body></html>
<?php exit; }

// PROSES SUBMIT
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['aksi']??'')==='submit') {
    // Validasi nilai 1–5
    $kriteria = ['sikap','kerjasama','inisiatif','ketelitian','ketepatan','pengetahuan','kualitas','kuantitas','belajar','tjawab'];
    $vals = [];
    foreach ($kriteria as $k) {
        $v = intval($_POST[$k.'_nilai'] ?? 0);
        if ($v < 1 || $v > 5) { $errors[] = "Semua kriteria harus diisi dengan nilai 1–5."; break; }
        $vals[$k] = $v;
    }

    $penilaian_keseluruhan = trim($_POST['penilaian_keseluruhan'] ?? '');
    $penilai_nama          = trim($_POST['penilai_nama'] ?? '');
    $tanggal               = trim($_POST['tanggal_penilaian'] ?? '');

    if (!$penilaian_keseluruhan) $errors[] = "Penilaian keseluruhan wajib dipilih.";
    if (!$penilai_nama)          $errors[] = "Nama penilai wajib diisi.";
    if (!$tanggal)               $errors[] = "Tanggal penilaian wajib diisi.";

    // Validasi & sanitasi tanda tangan (base64 PNG)
    $tanda_tangan_raw = trim($_POST['tanda_tangan'] ?? '');
    $tanda_tangan     = '';
    if ($tanda_tangan_raw) {
        // Pastikan format data URL PNG yang valid
        if (preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $tanda_tangan_raw)) {
            $tanda_tangan = $tanda_tangan_raw;
        }
    }
    if (!$tanda_tangan) $errors[] = "Tanda tangan wajib diisi.";

    if (empty($errors)) {
        $jumlah = array_sum($vals);
        $status = $jumlah >= 28 ? 'Diperpanjang' : 'Tidak Diperpanjang';

        $bidang_unggul    = trim($_POST['bidang_unggul'] ?? '');
        $bidang_tingkat   = trim($_POST['bidang_tingkatkan'] ?? '');
        // Gabungkan rencana pelatihan 1-4
        $rencana_parts = array_filter(array_map('trim', [
            $_POST['rencana_pelatihan']  ?? '',
            $_POST['rencana_pelatihan2'] ?? '',
            $_POST['rencana_pelatihan3'] ?? '',
            $_POST['rencana_pelatihan4'] ?? '',
        ]));
        $rencana = implode("\n", $rencana_parts);
        // Gabungkan jobdesk a-j menjadi satu teks
        $jobdesk_parts = [];
        foreach(['a','b','c','d','e','f','g','h','i','j'] as $k) {
            $v = trim($_POST['jobdesk_'.$k] ?? '');
            if ($v) $jobdesk_parts[] = strtoupper($k).'. '.$v;
        }
        $jobdesk = implode("\n", $jobdesk_parts);
        $pesan            = trim($_POST['pesan_tambahan'] ?? '');
        $permohonan       = trim($_POST['permohonan_perpanjangan'] ?? '');
        $kualifikasi_jab  = trim($_POST['kualifikasi_jabatan'] ?? '');
        $ip               = $_SERVER['REMOTE_ADDR'] ?? '';
        $token_id         = $ft['id'];
        $kar_id           = $ft['karyawan_id'];
        $periode          = $ft['periode'];

        $ins = $db->prepare("INSERT INTO penilaian
            (token_id,karyawan_id,periode,
             sikap_nilai,kerjasama_nilai,inisiatif_nilai,ketelitian_nilai,ketepatan_nilai,pengetahuan_nilai,
             kualitas_nilai,kuantitas_nilai,belajar_nilai,tjawab_nilai,
             jumlah_nilai,status_perpanjangan,penilaian_keseluruhan,
             bidang_unggul,bidang_tingkatkan,rencana_pelatihan,jobdesk_harian,pesan_tambahan,
             permohonan_perpanjangan,kualifikasi_jabatan,
             penilai_nama,tanggal_penilaian,tanda_tangan)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

        $ins->bind_param('iisiiiiiiiiiiissssssssssss',
            $token_id, $kar_id, $periode,
            $vals['sikap'], $vals['kerjasama'], $vals['inisiatif'], $vals['ketelitian'],
            $vals['ketepatan'], $vals['pengetahuan'],
            $vals['kualitas'], $vals['kuantitas'], $vals['belajar'], $vals['tjawab'],
            $jumlah, $status, $penilaian_keseluruhan,
            $bidang_unggul, $bidang_tingkat, $rencana, $jobdesk, $pesan,
            $permohonan, $kualifikasi_jab,
            $penilai_nama, $tanggal, $tanda_tangan
        );

        if ($ins->execute()) {
            // Tandai token sudah disubmit
            $penilaian_id_baru = $db->insert_id;
            $upd = $db->prepare("UPDATE form_tokens SET is_submitted=1, submitted_at=NOW(), submitted_ip=? WHERE id=?");
            $upd->bind_param('si', $ip, $token_id);
            $upd->execute();
            $upd->close();
            // Set session untuk form memo dan redirect ke halaman memo
            $_SESSION['memo_penilaian_id'] = $penilaian_id_baru;
            $_SESSION['memo_token']        = $token;
            $success = true;
            // Redirect ke form memo
            redirect(APP_URL . '/form_memo.php?token=' . urlencode($token));
        } else {
            $errors[] = "Gagal menyimpan data. Silakan coba lagi.";
        }
        $ins->close();
    }
}

if ($success): ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Terima Kasih — PKWT Indopelita</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
<style>
body{display:flex;align-items:center;justify-content:center;min-height:100vh;background:linear-gradient(135deg,#0f2d5c 0%,#091e40 60%,#0a2252 100%);margin:0;padding:20px;}
.success-box{background:#fff;border-radius:20px;padding:50px 44px;text-align:center;max-width:500px;width:100%;box-shadow:0 30px 60px rgba(0,0,0,0.35);}
.check-circle{width:84px;height:84px;background:linear-gradient(135deg,#22c55e,#16a34a);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 22px;font-size:38px;box-shadow:0 8px 24px rgba(22,163,74,0.35);}
.success-box h2{color:#0f2d5c;font-size:23px;font-weight:800;margin-bottom:10px;}
.success-box p{color:#64748b;font-size:14px;line-height:1.7;margin-bottom:0;}
.info-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:18px 20px;margin:22px 0;text-align:left;}
.info-row{display:flex;padding:5px 0;border-bottom:1px solid #f1f5f9;font-size:13px;}
.info-row:last-child{border-bottom:none;}
.info-row .lk{color:#94a3b8;width:130px;flex-shrink:0;}
.info-row .vk{font-weight:600;color:#1e293b;}
.nilai-big{font-size:26px;font-weight:900;color:<?= isset($vals)&&array_sum($vals??[])>=28?'#16a34a':'#dc2626' ?>;}
.status-pill{display:inline-block;padding:5px 16px;border-radius:99px;font-size:12.5px;font-weight:700;
  background:<?= isset($vals)&&array_sum($vals??[])>=28?'#dcfce7':'#fee2e2' ?>;
  color:<?= isset($vals)&&array_sum($vals??[])>=28?'#15803d':'#b91c1c' ?>;
  border:1.5px solid <?= isset($vals)&&array_sum($vals??[])>=28?'#86efac':'#fca5a5' ?>;}
.footer-note{font-size:11.5px;color:#cbd5e1;margin-top:16px;}
</style>
</head>
<body>
<div class="success-box">
  <div class="check-circle">✅</div>
  <h2>Penilaian Berhasil Dikirim!</h2>
  <p>Data penilaian kinerja telah berhasil disimpan ke sistem HRD Indopelita.</p>
  <div class="info-card">
    <div class="info-row"><span class="lk">Karyawan</span><span class="vk"><?= e($ft['nama']) ?></span></div>
    <div class="info-row"><span class="lk">Jabatan</span><span class="vk"><?= e($ft['jabatan']) ?></span></div>
    <div class="info-row"><span class="lk">Periode</span><span class="vk"><?= e($ft['periode']) ?></span></div>
    <div class="info-row">
      <span class="lk">Total Nilai</span>
      <span class="vk"><span class="nilai-big"><?= array_sum($vals ?? []) ?></span><span style="color:#94a3b8;font-size:13px;font-weight:400"> / 50</span></span>
    </div>
    <div class="info-row">
      <span class="lk">Status</span>
      <span class="vk">
        <span class="status-pill">
          <?= (isset($vals)&&array_sum($vals??[])>=28) ? '✅ Diperpanjang' : '❌ Tidak Diperpanjang' ?>
        </span>
      </span>
    </div>
  </div>
  <p class="footer-note">Form ini sudah tidak bisa diisi ulang.<br>Hubungi HRD jika ada pertanyaan.</p>
</div>
</body>
</html>
<?php exit; endif; ?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Form Penilaian Kinerja PKWT – <?= e($ft['nama']) ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Source+Serif+4:ital,wght@0,300;0,400;0,600;0,700&display=swap');
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Times New Roman',Times,serif;background:#c8c8c8;padding:20px;}

/* TOP BAR */
.top-notice{background:#1a3a6b;color:#fff;text-align:center;padding:10px;font-family:'Segoe UI',sans-serif;font-size:13px;border-radius:6px;margin-bottom:16px;}

/* PAGE */
.page{background:#fff;width:210mm;margin:0 auto 20px;padding:14mm 14mm 14mm 18mm;box-shadow:0 2px 16px rgba(0,0,0,0.3);position:relative;}

/* HEADER */
.header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:5px;}






.form-title{font-size:12.5pt;font-weight:bold;text-transform:uppercase;}
.form-subtitle{font-size:11pt;font-weight:bold;text-transform:uppercase;}

/* META */
.meta-input{width:100%;border:none;border-bottom:1px solid #000;background:transparent;font-family:'Times New Roman',serif;font-size:9.5pt;outline:none;padding:1px 0;}

/* TABLE */
.main-table{width:100%;border-collapse:collapse;font-size:8.8pt;margin-top:5px;}
.main-table th{background:#000;color:#fff;padding:4px 5px;font-size:9.5pt;font-weight:bold;}
.main-table td{border:1px solid #000;padding:3px 5px;vertical-align:top;}
.section-row td{background:#e0e0e0;font-weight:bold;font-size:9pt;}
.col-f{width:22%}.col-v{width:54%}.col-n{width:12%;text-align:center}.col-p{width:12%;text-align:center}
.crit-list p{margin:1.5px 0;line-height:1.4;font-size:8.3pt;}
.score-sel{width:55px;font-family:'Times New Roman',serif;font-size:9pt;border:1.5px solid #555;padding:2px 4px;text-align:center;cursor:pointer;border-radius:3px;}
.predikat{font-size:8pt;font-style:italic;color:#555;}

/* Page 3 */
.cb-group{display:flex;gap:14px;flex-wrap:wrap;margin:5px 0 10px;}
.cb-item{display:flex;align-items:center;gap:5px;font-size:9.5pt;cursor:pointer;}
.cb-item input{width:12px;height:12px;cursor:pointer;}
.open-q{margin-bottom:10px;}
.open-q .ql{font-size:9pt;margin-bottom:3px;}
.line-input{width:100%;border:none;border-bottom:1px solid #000;background:transparent;font-family:'Times New Roman',serif;font-size:9pt;outline:none;padding:2px 0;display:block;margin-bottom:3px;}
.pelatihan-t{width:100%;border-collapse:collapse;font-size:9pt;margin-bottom:8px;}
.pelatihan-t th{background:#e0e0e0;border:1px solid #000;padding:3px 6px;font-size:8.5pt;}
.pelatihan-t td{border:1px solid #000;padding:2px 5px;}
.jobdesk-t{width:100%;border-collapse:collapse;font-size:9pt;margin-bottom:6px;}
.jobdesk-t td{border:1px solid #000;padding:3px 5px;vertical-align:top;}
.jd-label{background:#f0f0f0;font-weight:bold;font-size:8pt;width:30%;}

/* Errors */
.error-box{background:#fee2e2;border:1px solid #fca5a5;color:#dc2626;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-family:'Segoe UI',sans-serif;font-size:13px;}
.error-box ul{margin:6px 0 0 18px;}

/* Submit btn */
.submit-wrap{text-align:center;margin:20px 0;font-family:'Segoe UI',sans-serif;}
.btn-submit{background:#1a3a6b;color:#fff;border:none;padding:14px 50px;font-size:16px;font-weight:700;border-radius:8px;cursor:pointer;transition:background .2s;letter-spacing:0.3px;}
.btn-submit:hover{background:#0f2449;}

/* total display */
.total-display{font-size:22pt;font-weight:900;text-align:center;}
.total-status{text-align:center;font-size:10pt;}

@media print{body{background:#fff;padding:0;}.top-notice,.submit-wrap{display:none!important;}.page{box-shadow:none;margin:0;page-break-after:always;}.sig-toolbar{display:none!important;}}
.sig-wrap{position:relative;border:1.5px solid #aaa;border-radius:4px;background:#fff;margin:4px 0;touch-action:none;}
.sig-canvas{display:block;width:100%;cursor:crosshair;border-radius:4px;}
.sig-toolbar{display:flex;gap:6px;margin-top:4px;align-items:center;}
.sig-btn{font-family:'Segoe UI',sans-serif;font-size:11px;padding:4px 10px;border-radius:4px;cursor:pointer;border:1px solid #ccc;background:#f8f8f8;}
.sig-btn:hover{background:#e8e8e8;}
.sig-btn.clear{color:#c00;border-color:#fca5a5;}
.sig-hint{font-size:9px;color:#aaa;font-family:'Segoe UI',sans-serif;flex:1;text-align:right;}
.sig-required{color:#dc2626;font-size:9px;font-family:'Segoe UI',sans-serif;display:none;margin-top:2px;}
</style>
</head>
<body>

<div class="top-notice">
  🔒 Form ini bersifat RAHASIA. Link ini unik untuk satu kali pengisian saja.
</div>

<?php if($errors): ?>
<div class="error-box" style="width:210mm;margin:0 auto 12px;font-family:'Segoe UI',sans-serif;">
  <strong>⚠️ Harap perbaiki kesalahan berikut:</strong>
  <ul><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" id="mainForm">
<input type="hidden" name="aksi" value="submit">

<!-- ═══════════════ PAGE 1 ═══════════════ -->
<div class="page">
  <div class="header">
    <div>
      <div class="form-title">FORM PENILAIAN KINERJA KARYAWAN PKWT</div>
      <div class="form-subtitle">(SANGAT RAHASIA)</div>
    </div>
    <div style="text-align:right;">
      <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Indopelita" style="height:40px;display:block;margin-left:auto;">
    </div>
  </div>

  <!-- META INFO -->
  <table style="width:100%;border-collapse:collapse;font-size:9.2pt;margin-bottom:5px;">
    <tr>
      <td style="width:50%;vertical-align:top;">
        <table style="width:100%;border-collapse:collapse;">
          <tr><td style="width:95px;">NIK / No.Peg</td><td style="width:8px;">:</td><td><span style="border-bottom:1px solid #000;display:block;min-height:16px;font-weight:600"><?= e($ft['nik']) ?></span></td></tr>
          <tr><td>Nama</td><td>:</td><td><span style="border-bottom:1px solid #000;display:block;min-height:16px;font-weight:600"><?= e($ft['nama']) ?></span></td></tr>
          <tr><td>Jabatan</td><td>:</td><td><span style="border-bottom:1px solid #000;display:block;min-height:16px"><?= e($ft['jabatan']) ?></span></td></tr>
          <tr><td>Mulai bekerja</td><td>:</td><td><span style="border-bottom:1px solid #000;display:block;min-height:16px"><?= $ft['mulai_bekerja'] ? formatTanggal($ft['mulai_bekerja']) : '' ?></span></td></tr>
        </table>
      </td>
      <td style="width:50%;vertical-align:top;padding-left:14px;">
        <table style="width:100%;border-collapse:collapse;">
          <tr><td style="width:80px;">Periode</td><td style="width:8px;">:</td><td><span style="border-bottom:1px solid #000;display:block;min-height:16px;font-weight:600"><?= e($ft['periode']) ?></span></td></tr>
          <tr><td>Atasan Langsung</td><td>:</td><td><span style="border-bottom:1px solid #000;display:block;min-height:16px"><?= e($ft['atasan_langsung']??'') ?></span></td></tr>
          <tr><td>Catatan Hrd</td><td>:</td><td><span style="border-bottom:1px solid #000;display:block;min-height:16px"><?= e($ft['catatan_hrd']??'') ?></span></td></tr>
          <tr><td>Shift</td><td>:</td><td><span style="border-bottom:1px solid #000;display:block;min-height:16px"><?= e($ft['shift']??'') ?></span></td></tr>
          <tr><td>Cuti</td><td>:</td><td><span style="border-bottom:1px solid #000;display:block;min-height:16px"><?= e($ft['cuti']??'') ?></span></td></tr>
          <tr><td>Ijin % hari</td><td>:</td><td><span style="border-bottom:1px solid #000;display:block;min-height:16px"><?= e($ft['ijin']??'') ?></span></td></tr>
          <tr><td>II, II, III, III</td><td>:</td><td><span style="border-bottom:1px solid #000;display:block;min-height:16px"><?= e($ft['periode_ke']??'') ?></span></td></tr>
        </table>
      </td>
    </tr>
  </table>

  <!-- TABEL PENILAIAN HALAMAN 1 -->
  <table class="main-table">
    <thead>
      <tr>
        <th class="col-f">Faktor-Faktor Kinerja</th>
        <th class="col-v">Nilai</th>
        <th class="col-n">Nilai</th>
        <th class="col-p">Predikat</th>
      </tr>
    </thead>
    <tbody>
      <tr class="section-row"><td colspan="4">General</td></tr>

      <?php
      $kriteria_h1 = [
        ['key'=>'sikap','label'=>'Tingkat Sikap / Attitude','items'=>[
          '5 – SANGAT BAIK, sangat menyenangkan, sangat sopan, sangat terbuka dan sangat positif dalam menerima kritik.',
          '4 – BAIK, menyenangkan, sopan, terbuka dan menerima kritik dengan baik.',
          '3 – CUKUP BAIK, cukup menyenangkan, cukup sopan, cukup terbuka, cukup bersifat positif dalam menerima kritik.',
          '2 – KURANG BAIK, Kurang menyenangkan, kurang sopan, agak tertutup, agak negatif dalam menerima kritik.',
          '1 – TIDAK BAIK, tidak menyenangkan, tidak sopan, tertutup, tidak bisa menerima kritik.'
        ]],
        ['key'=>'kerjasama','label'=>'Kerjasama / Teamwork','items'=>[
          '5 – SANGAT BAIK, sangat kooperatif dan sangat antusias.',
          '4 – BAIK, kooperatif dan cukup antusias.',
          '3 – CUKUP BAIK, cukup kooperatif dan cukup antusias.',
          '2 – KURANG BAIK, kurang kooperatif dan kurang antusias.',
          '1 – TIDAK BAIK, tidak kooperatif dan tidak antusias sama sekali.'
        ]],
        ['key'=>'inisiatif','label'=>'Inisiatif & Kreativitas','items'=>[
          '5 – SANGAT BAIK, sangat tanggap dan sangat bersemangat dalam melaksanakan pekerjaan demi kemajuan diri dan departemennya.',
          '4 – BAIK, tanggap dan bersemangat dalam melaksanakan pekerjaan demi kemajuan diri dan departemennya.',
          '3 – CUKUP BAIK, cukup tanggap dan kurang bersemangat dalam melaksanakan pekerjaan demi kemajuan diri dan departemennya.',
          '2 – KURANG BAIK, kurang tanggap dan kurang bersemangat dalam melaksanakan pekerjaan demi kemajuan diri dan departemennya.',
          '1 – TIDAK BAIK, tidak tanggap dalam melaksanakan pekerjaan dan tidak bersemangat dalam kemajuan diri dan departemennya.'
        ]],
        ['key'=>'ketelitian','label'=>'Ketelitian','items'=>[
          '5 – SANGAT BAIK, hampir tidak pernah membuat kesalahan dalam melaksanakan pekerjaan.',
          '4 – BAIK, teliti, jarang melaksanakan kesalahan dalam pekerjaan.',
          '3 – CUKUP BAIK, cukup teliti, cukup sering melaksanakan kesalahan, bisa mentoleransi kesalahan dalam pekerjaan.',
          '2 – KURANG BAIK, kurang teliti, sering melaksanakan kesalahan dan sering tidak mentoleransi kesalahan dalam pekerjaannya.',
          '1 – TIDAK BAIK, tidak teliti, sering sekali melaksanakan kesalahan dalam pekerjaan.'
        ]],
        ['key'=>'ketepatan','label'=>'Ketepatan waktu menyelesaikan pekerjaan','items'=>[
          '5 – SANGAT BAIK, selalu tepat waktu (hampir tidak pernah) terlambat dalam pekerjaan.',
          '4 – BAIK, tepat waktu, jarang terlambat dan jarang tidak menyelesaikan pekerjaan.',
          '3 – CUKUP BAIK, cukup tepat waktu, kadang terlambat tapi masih bisa memenuhi target pekerjaan.',
          '2 – KURANG BAIK, kurang tepat waktu, sering terlambat tapi selalu berusaha menyelesaikan pekerjaan.',
          '1 – TIDAK BAIK, tidak tepat waktu dan tidak pernah menyelesaikan pekerjaan.'
        ]],
        ['key'=>'pengetahuan','label'=>'Pengetahuan tentang pekerjaan','items'=>[
          '5 – SANGAT BAIK, pengetahuan tentang semua aspek jabatan yang menjadi tanggung jawabnya sangat tinggi.',
          '4 – BAIK, pengetahuan tentang sebagian besar aspek jabatan sudah tinggi.',
          '3 – CUKUP BAIK, secara umum pengetahuan pada kebanyakan aspek jabatan cukup.',
          '2 – KURANG BAIK, pengetahuan hanya pada aspek yang disukai atau tugas yang biasa dilakukan saja.',
          '1 – TIDAK BAIK, pengetahuan terbatas pada poin-poin kecil yang relevan saja dalam rangka tugas-tugasnya.'
        ]],
      ];
      foreach($kriteria_h1 as $kr): ?>
      <tr>
        <td style="font-weight:bold;font-size:8.8pt;vertical-align:top;padding-top:4px;"><?= e($kr['label']) ?></td>
        <td>
          <div class="crit-list">
            <?php foreach($kr['items'] as $item): ?><p><?= e($item) ?></p><?php endforeach; ?>
          </div>
        </td>
        <td style="text-align:center;vertical-align:middle;">
          <select class="score-sel" name="<?= $kr['key'] ?>_nilai" required onchange="updateP(this,'p_<?= $kr['key'] ?>')">
            <option value="">–</option>
            <?php for($v=5;$v>=1;$v--): ?>
            <option value="<?=$v?>" <?= (intval($_POST[$kr['key'].'_nilai']??0)===$v)?'selected':'' ?>><?=$v?></option>
            <?php endfor; ?>
          </select>
        </td>
        <td style="text-align:center;vertical-align:middle;"><div class="predikat" id="p_<?= $kr['key'] ?>">–</div></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- ═══════════════ PAGE 2 ═══════════════ -->
<div class="page">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:5px;">
    <table style="font-size:9pt;border-collapse:collapse;">
      <tr><td style="width:100px;">Faktor-Faktor</td><td>:</td><td style="border-bottom:1px solid #000;width:130px;"></td>
      <td style="padding-left:20px;width:40px;">Nilai</td><td>:</td><td style="border-bottom:1px solid #000;width:80px;"></td>
      <td style="padding-left:20px;">Predikat</td></tr>
    </table>
    <div style="text-align:right;">
      <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Indopelita" style="height:40px;display:block;margin-left:auto;">
    </div>
  </div>

  <table class="main-table">
    <thead>
      <tr>
        <th class="col-f">Faktor-Faktor Kinerja</th>
        <th class="col-v">Nilai</th>
        <th class="col-n">Nilai</th>
        <th class="col-p">Predikat</th>
      </tr>
    </thead>
    <tbody>
      <tr class="section-row"><td colspan="4">General</td></tr>
      <?php
      $kriteria_h2 = [
        ['key'=>'kualitas','label'=>'Kualitas Pekerjaan','items'=>[
          '5 – SANGAT BAIK, har biasa berhasil bekerja mencapai kesempurnaan dan kualitas yang tinggi.',
          '4 – BAIK, rasa keingintahuan dalam menyampaikan kualitas yang superior.',
          '3 – CUKUP BAIK, mencapai tingkat kualitas rata-rata pada sebagian besar situasi.',
          '2 – KURANG BAIK, mutu di bawah standar tingkat kualitas rata-rata.',
          '1 – TIDAK BAIK, gagal menyampaikan tingkat kualitas yang layak.'
        ]],
        ['key'=>'kuantitas','label'=>'Kuantitas Pekerjaan','items'=>[
          '5 – SANGAT BAIK, selalu dalam melaksanakannya.',
          '4 – BAIK, sering dalam melaksanakan.',
          '3 – CUKUP BAIK, kadang dalam melaksanakan pekerjaan.',
          '2 – KURANG BAIK, jarang dalam melaksanakan pekerjaan.',
          '1 – TIDAK BAIK, terbatas dalam melaksanakan.'
        ]],
        ['key'=>'belajar','label'=>'Kebiasaan dan kemampuan untuk belajar','items'=>[
          '5 – SANGAT BAIK, antusias untuk belajar dan memastikan pengembangan kerja.',
          '4 – BAIK, antusias untuk belajar dan berusaha memastikan pengembangan kerja.',
          '3 – CUKUP BAIK, cukup antusias untuk belajar dan berusaha memastikan pengembangan kerja.',
          '2 – KURANG BAIK, kurang antusias dan kurang berusaha untuk memastikan pengembangan kerja.',
          '1 – TIDAK BAIK, tidak antusias untuk belajar dan tidak berusaha untuk meningkatkan pengetahuan.'
        ]],
        ['key'=>'tjawab','label'=>'Tanggung Jawab','items'=>[
          '5 – SANGAT BAIK, sangat bertanggung jawab, tidak melempar tanggung jawab kepada orang lain.',
          '4 – BAIK, menyukai adanya tanggung jawab, cukup menyukai adanya tanggung jawab namun terkadang masih ada bantuan.',
          '3 – CUKUP BAIK, cukup menyukai tanggung jawabnya, kadang melimpahkan tanggung jawab kepada orang lain.',
          '2 – KURANG BAIK, kurang menyukai tanggung jawabnya, sering melimpahkan tanggung jawab kepada orang lain.',
          '1 – TIDAK BAIK, tidak mau menyukai tanggung jawabnya, selalu melimpahkan tanggung jawabnya kepada orang lain.'
        ]],
      ];
      foreach($kriteria_h2 as $kr): ?>
      <tr>
        <td style="font-weight:bold;font-size:8.8pt;vertical-align:top;padding-top:4px;"><?= e($kr['label']) ?></td>
        <td><div class="crit-list"><?php foreach($kr['items'] as $item): ?><p><?= e($item) ?></p><?php endforeach; ?></div></td>
        <td style="text-align:center;vertical-align:middle;">
          <select class="score-sel" name="<?= $kr['key'] ?>_nilai" required onchange="updateP(this,'p_<?= $kr['key'] ?>')">
            <option value="">–</option>
            <?php for($v=5;$v>=1;$v--): ?>
            <option value="<?=$v?>" <?= (intval($_POST[$kr['key'].'_nilai']??0)===$v)?'selected':'' ?>><?=$v?></option>
            <?php endfor; ?>
          </select>
        </td>
        <td style="text-align:center;vertical-align:middle;"><div class="predikat" id="p_<?= $kr['key'] ?>">–</div></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- JUMLAH NILAI -->
  <table style="width:100%;border-collapse:collapse;border:1px solid #000;margin-top:0;">
    <tr>
      <td style="width:20%;background:#e0e0e0;font-weight:bold;text-align:center;padding:5px;border:1px solid #000;font-size:9pt;">Jumlah Nilai</td>
      <td style="width:35%;text-align:center;border:1px solid #000;padding:5px;">
        <span class="total-display" id="totalNilai">–</span>
      </td>
      <td style="width:45%;border:1px solid #000;padding:6px;font-size:8.5pt;">
        <strong>Catatan.</strong><br>
        Bagi pekerja yang nilainya dibawah 28 hari di <u><strong>Rejected / Tidak diperpanjang.</strong></u>
      </td>
    </tr>
  </table>
</div>

<!-- ═══════════════ PAGE 3 ═══════════════ -->
<div class="page">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
    <div style="font-size:10pt;font-weight:bold;">Ringkasan Penilaian Kerja</div>
    <div style="text-align:right;">
      <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Indopelita" style="height:40px;display:block;margin-left:auto;">
    </div>
  </div>

  <div style="font-size:9pt;margin-bottom:4px;">Menurut anda, bagaimanakah anda menilai kinerja karyawan ini secara keseluruhan?</div>
  <div class="cb-group">
    <?php foreach(['Sangat Baik','Baik','Cukup Baik','Kurang Baik','Tidak Baik'] as $opt): ?>
    <label class="cb-item">
      <input type="radio" name="penilaian_keseluruhan" value="<?= e($opt) ?>" <?= ($_POST['penilaian_keseluruhan']??'')===$opt?'checked':'' ?> required>
      <?= e($opt) ?>
    </label>
    <?php endforeach; ?>
  </div>

  <div class="open-q">
    <div class="ql">Dibidang-bidang tugas kerja dan diri-diri apa karyawan ini unggul atau menonjol?</div>
    <textarea name="bidang_unggul" class="line-input" rows="2" style="border:none;border-bottom:1px solid #000;resize:none;width:100%;font-family:'Times New Roman',serif;font-size:9pt;outline:none;background:transparent;"><?= e($_POST['bidang_unggul']??'') ?></textarea>
    <div class="line-input" style="min-height:16px;"></div>
  </div>

  <div class="open-q">
    <div class="ql">Bidang-bidang tugas kerja apa yang perlu diperhatikan dan ditingkatkan?</div>
    <textarea name="bidang_tingkatkan" class="line-input" rows="2" style="border:none;border-bottom:1px solid #000;resize:none;width:100%;font-family:'Times New Roman',serif;font-size:9pt;outline:none;background:transparent;"><?= e($_POST['bidang_tingkatkan']??'') ?></textarea>
    <div class="line-input" style="min-height:16px;"></div>
  </div>

  <table class="pelatihan-t">
    <thead><tr><th colspan="2" style="text-align:left;">Rencana pelatihan atau pengembangan yang dibutuhkan</th></tr></thead>
    <tbody>
      <tr><td style="height:20px;"><input type="text" name="rencana_pelatihan" class="meta-input" placeholder="1."></td><td style="height:20px;"><input type="text" name="rencana_pelatihan2" class="meta-input" placeholder="2."></td></tr>
      <tr><td style="height:20px;"><input type="text" name="rencana_pelatihan3" class="meta-input" placeholder="3."></td><td style="height:20px;"><input type="text" name="rencana_pelatihan4" class="meta-input" placeholder="4."></td></tr>
    </tbody>
  </table>

  <table class="jobdesk-t">
    <tr>
      <td class="jd-label" rowspan="10" style="padding:5px;font-size:8pt;text-align:center;">*JOBDESK POKOK<br>HARIAN/MINGGUAN</td>
      <?php $jdKeys = ['a','b','c','d','e','f','g','h','i','j'];
      $first = true;
      foreach($jdKeys as $k): if(!$first) echo '</tr><tr>'; $first=false; ?>
      <td style="height:18px;"><?= strtoupper($k) ?>. <input type="text" name="jobdesk_<?= $k ?>" class="meta-input" style="width:90%;"></td>
      <?php endforeach; ?>
    </tr>
  </table>

  <table class="jobdesk-t">
    <tr>
      <td class="jd-label" style="padding:5px;text-align:center;">*PESAN/KOMENTAR<br>TAMBAHAN / JUSTIFIKASI</td>
      <td><textarea name="pesan_tambahan" style="width:100%;min-height:45px;border:none;font-family:'Times New Roman',serif;font-size:9pt;outline:none;resize:none;background:transparent;"><?= e($_POST['pesan_tambahan']??'') ?></textarea></td>
    </tr>
  </table>

  <!-- PERMOHONAN PERPANJANGAN & KUALIFIKASI JABATAN -->
  <table style="width:100%;border-collapse:collapse;border:1px solid #000;margin-top:5px;">
    <tr>
      <!-- KOLOM KIRI: Permohonan Perpanjangan -->
      <td style="width:50%;border-right:1px solid #000;padding:8px 10px;vertical-align:top;">
        <div style="font-size:8.5pt;font-weight:bold;text-transform:uppercase;margin-bottom:8px;text-align:center;letter-spacing:0.3px;">
          Permohonan untuk Diperpanjang /<br>Tidak Diperpanjang
        </div>
        <?php $pPerpanjangan = $_POST['permohonan_perpanjangan'] ?? ''; ?>
        <div style="display:flex;flex-direction:column;gap:8px;align-items:center;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px 18px;border:1.5px solid <?= $pPerpanjangan==='Diperpanjang'?'#16a34a':'#ccc' ?>;border-radius:8px;width:80%;justify-content:center;background:<?= $pPerpanjangan==='Diperpanjang'?'#dcfce7':'#fff' ?>;font-size:9pt;font-weight:600;font-family:'Times New Roman',serif;transition:all 0.2s;" id="lbl-diperpanjang">
            <input type="radio" name="permohonan_perpanjangan" value="Diperpanjang" <?= $pPerpanjangan==='Diperpanjang'?'checked':'' ?> onchange="highlightPermohonan()" style="accent-color:#16a34a;width:14px;height:14px;">
            ✅ Diperpanjang
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px 18px;border:1.5px solid <?= $pPerpanjangan==='Tidak Diperpanjang'?'#dc2626':'#ccc' ?>;border-radius:8px;width:80%;justify-content:center;background:<?= $pPerpanjangan==='Tidak Diperpanjang'?'#fee2e2':'#fff' ?>;font-size:9pt;font-weight:600;font-family:'Times New Roman',serif;transition:all 0.2s;" id="lbl-tidak">
            <input type="radio" name="permohonan_perpanjangan" value="Tidak Diperpanjang" <?= $pPerpanjangan==='Tidak Diperpanjang'?'checked':'' ?> onchange="highlightPermohonan()" style="accent-color:#dc2626;width:14px;height:14px;">
            ❌ Tidak Diperpanjang
          </label>
        </div>
      </td>
      <!-- KOLOM KANAN: Kualifikasi Jabatan -->
      <td style="width:50%;padding:8px 10px;vertical-align:top;">
        <div style="font-size:8.5pt;font-weight:bold;text-transform:uppercase;margin-bottom:6px;text-align:center;letter-spacing:0.3px;">
          Kualifikasi / Jabatan<br>yang Bersangkutan Saat Ini
        </div>
        <textarea name="kualifikasi_jabatan" placeholder="Tuliskan kualifikasi / jabatan karyawan yang bersangkutan saat ini..." style="width:100%;min-height:72px;border:1px solid #ccc;border-radius:4px;font-family:'Times New Roman',serif;font-size:9pt;padding:5px 7px;resize:vertical;box-sizing:border-box;background:#fff;"><?= e($_POST['kualifikasi_jabatan']??'') ?></textarea>
      </td>
    </tr>
  </table>
  <script>
  function highlightPermohonan() {
    var radios = document.querySelectorAll('input[name="permohonan_perpanjangan"]');
    var lblD = document.getElementById('lbl-diperpanjang');
    var lblT = document.getElementById('lbl-tidak');
    radios.forEach(function(r) {
      if (r.value === 'Diperpanjang') {
        lblD.style.borderColor = r.checked ? '#16a34a' : '#ccc';
        lblD.style.background = r.checked ? '#dcfce7' : '#fff';
      } else {
        lblT.style.borderColor = r.checked ? '#dc2626' : '#ccc';
        lblT.style.background = r.checked ? '#fee2e2' : '#fff';
      }
    });
  }
  </script>

  <!-- SIGNATURE -->
  <table style="width:100%;border-collapse:collapse;border:1px solid #000;margin-top:5px;">
    <tr>
      <td style="padding:6px 10px;vertical-align:top;border-right:1px solid #000;width:60%;">
        <div style="font-weight:bold;font-size:9pt;margin-bottom:3px;">Dibuat oleh,</div>
        <div style="font-size:8.5pt;color:#555;margin-bottom:4px;">Tanda tangan penilai (Atasan)</div>
        <div class="sig-wrap">
          <canvas id="sigCanvas" class="sig-canvas" height="100"
                  style="height:100px;"></canvas>
        </div>
        <div class="sig-toolbar">
          <button type="button" class="sig-btn clear" onclick="clearSig()">✕ Hapus</button>
          <button type="button" class="sig-btn" onclick="undoSig()">↩ Undo</button>
          <span class="sig-hint">✍️ Tanda tangan dengan jari / mouse / stylus</span>
        </div>
        <div class="sig-required" id="sigRequired">⚠️ Tanda tangan wajib diisi</div>
        <input type="hidden" name="tanda_tangan" id="sigData">
        <div style="display:flex;gap:12px;margin-top:5px;">
          <div style="flex:1;font-size:8.5pt;">
            Nama :<br>
            <input type="text" name="penilai_nama" required class="meta-input" value="<?= e($_POST['penilai_nama']??$ft['atasan_langsung']??'') ?>" style="margin-top:2px;">
          </div>
          <div style="flex:1;font-size:8.5pt;">
            Tanggal :<br>
            <input type="date" name="tanggal_penilaian" required class="meta-input" value="<?= e($_POST['tanggal_penilaian']??date('Y-m-d')) ?>" style="margin-top:2px;">
          </div>
        </div>
      </td>
      <td style="padding:6px 10px;vertical-align:top;width:40%;">
        <div style="font-size:8.5pt;color:#888;">(Diisi oleh HRD)</div>
        <div style="font-size:8pt;margin-top:8px;">Catatan HRD:</div>
        <div style="border:1px solid #ddd;min-height:60px;margin-top:4px;padding:4px;font-size:8.5pt;background:#fffbeb;"><?= e($ft['catatan_hrd']??'-') ?></div>
      </td>
    </tr>
  </table>
</div>

<!-- SUBMIT BUTTON -->
<div class="submit-wrap">
  <button type="submit" class="btn-submit" onclick="return confirmSubmit()">
    ✅ Kirim Penilaian
  </button>
  <div style="font-size:12px;color:#888;margin-top:10px;">Form hanya bisa disubmit sekali. Pastikan semua nilai sudah terisi dengan benar.</div>
</div>

</form>

<script>
const PREDIKAT = {5:'Sangat Baik',4:'Baik',3:'Cukup Baik',2:'Kurang Baik',1:'Tidak Baik'};
const KEYS = ['sikap','kerjasama','inisiatif','ketelitian','ketepatan','pengetahuan','kualitas','kuantitas','belajar','tjawab'];

function updateP(sel, targetId) {
  const v = parseInt(sel.value);
  const el = document.getElementById(targetId);
  if (el) el.textContent = v ? PREDIKAT[v] : '–';
  updateTotal();
}

function updateTotal() {
  let total = 0, filled = 0;
  KEYS.forEach(k => {
    const sel = document.querySelector(`select[name="${k}_nilai"]`);
    const v = sel ? parseInt(sel.value) : 0;
    if (v) { total += v; filled++; }
  });
  const el = document.getElementById('totalNilai');
  if (el) {
    el.textContent = filled > 0 ? total : '–';
    el.style.color = (filled === KEYS.length && total < 28) ? '#dc2626' : '#1a1a1a';
  }
}

function confirmSubmit() {
  const sels = document.querySelectorAll('select.score-sel');
  let ok = true;
  sels.forEach(s => { if(!s.value) ok = false; });
  if (!ok) { alert('⚠️ Semua nilai kriteria harus diisi terlebih dahulu!'); return false; }
  const total = [...sels].reduce((s,el)=>s+parseInt(el.value||0),0);
  const status = total >= 28 ? 'DIPERPANJANG ✅' : 'TIDAK DIPERPANJANG ❌';
  return confirm(`Total Nilai: ${total}/50\nStatus: ${status}\n\nApakah Anda yakin ingin mengirim penilaian ini?\nData tidak dapat diubah setelah dikirim.`);
}

// Init predikat dari nilai yang sudah ada (error recovery)
KEYS.forEach(k => {
  const sel = document.querySelector(`select[name="${k}_nilai"]`);
  if (sel && sel.value) updateP(sel, 'p_' + k);
});
updateTotal();

// ── SIGNATURE PAD ──
(function() {
  const canvas  = document.getElementById('sigCanvas');
  if (!canvas) return;
  const ctx     = canvas.getContext('2d');
  let drawing   = false;
  let strokes   = [];   // [{x,y,type}] untuk undo per stroke
  let curStroke = [];

  function resize() {
    const rect = canvas.getBoundingClientRect();
    const dpr  = window.devicePixelRatio || 1;
    // Simpan data dulu sebelum resize
    const imgData = canvas.width > 0 ? canvas.toDataURL() : null;
    canvas.width  = rect.width  * dpr;
    canvas.height = rect.height * dpr;
    ctx.scale(dpr, dpr);
    ctx.strokeStyle = '#1a1a1a';
    ctx.lineWidth   = 1.8;
    ctx.lineCap     = 'round';
    ctx.lineJoin    = 'round';
    // Restore gambar
    if (imgData) {
      const img = new Image();
      img.onload = () => ctx.drawImage(img, 0, 0, rect.width, rect.height);
      img.src = imgData;
    }
  }
  resize();
  window.addEventListener('resize', resize);

  function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    const src  = e.touches ? e.touches[0] : e;
    return { x: src.clientX - rect.left, y: src.clientY - rect.top };
  }

  function startDraw(e) {
    e.preventDefault();
    drawing = true;
    curStroke = [];
    const p = getPos(e);
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
    curStroke.push(p);
  }

  function draw(e) {
    if (!drawing) return;
    e.preventDefault();
    const p = getPos(e);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    curStroke.push(p);
  }

  function endDraw(e) {
    if (!drawing) return;
    drawing = false;
    ctx.closePath();
    if (curStroke.length > 1) strokes.push([...curStroke]);
    saveData();
  }

  function saveData() {
    const input = document.getElementById('sigData');
    if (input) {
      // Hanya simpan jika ada gambar (strokes > 0)
      input.value = strokes.length > 0 ? canvas.toDataURL('image/png') : '';
    }
    const req = document.getElementById('sigRequired');
    if (req) req.style.display = 'none';
  }

  // Mouse events
  canvas.addEventListener('mousedown',  startDraw);
  canvas.addEventListener('mousemove',  draw);
  canvas.addEventListener('mouseup',    endDraw);
  canvas.addEventListener('mouseleave', endDraw);

  // Touch events
  canvas.addEventListener('touchstart', startDraw, { passive: false });
  canvas.addEventListener('touchmove',  draw,      { passive: false });
  canvas.addEventListener('touchend',   endDraw);

  window.clearSig = function() {
    const rect = canvas.getBoundingClientRect();
    ctx.clearRect(0, 0, rect.width, rect.height);
    strokes = [];
    saveData();
  };

  window.undoSig = function() {
    if (strokes.length === 0) return;
    strokes.pop();
    const rect = canvas.getBoundingClientRect();
    ctx.clearRect(0, 0, rect.width, rect.height);
    // Redraw semua strokes tersisa
    strokes.forEach(stroke => {
      if (stroke.length < 2) return;
      ctx.beginPath();
      ctx.moveTo(stroke[0].x, stroke[0].y);
      for (let i = 1; i < stroke.length; i++) ctx.lineTo(stroke[i].x, stroke[i].y);
      ctx.stroke();
      ctx.closePath();
    });
    saveData();
  };
})();

// Patch confirmSubmit untuk cek tanda tangan
const _origConfirm = confirmSubmit;
confirmSubmit = function() {
  const sigData = document.getElementById('sigData');
  if (!sigData || !sigData.value) {
    const req = document.getElementById('sigRequired');
    if (req) { req.style.display = 'block'; req.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    alert('⚠️ Tanda tangan wajib diisi sebelum mengirim!');
    return false;
  }
  return _origConfirm();
};
</script>
</body>
</html>
