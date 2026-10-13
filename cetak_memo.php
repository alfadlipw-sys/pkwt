<?php
// ============================================================
// cetak_memo.php — Cetak Memo Perpanjangan Kontrak (Print/PDF)
// Bisa diakses dari form_memo (publik via id) atau admin panel
// ============================================================
require_once __DIR__ . '/config.php';

$db = db();
$id = intval($_GET['id'] ?? 0);
if (!$id) die('<p>ID tidak valid.</p>');

$stmt = $db->prepare("
    SELECT m.*, k.nik, k.nama AS karyawan_nama, k.jabatan AS karyawan_jabatan,
           k.atasan_langsung, k.mulai_bekerja,
           p.jumlah_nilai, p.status_perpanjangan, p.periode,
           p.penilai_nama
    FROM memo_perpanjangan m
    JOIN karyawan k ON k.id = m.karyawan_id
    JOIN penilaian p ON p.id = m.penilaian_id
    WHERE m.id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$m = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$m) die('<p>Memo tidak ditemukan.</p>');

// Tandai sudah dicetak
$db->query("UPDATE memo_perpanjangan SET is_printed=1 WHERE id=$id");

function tglIndo(string $d): string {
    if (!$d || $d === '0000-00-00') return '-';
    $bl=['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei',
         '06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September',
         '10'=>'Oktober','11'=>'November','12'=>'Desember'];
    [$y,$mn,$day] = explode('-', $d);
    return intval($day).' '.($bl[$mn]??$mn).' '.$y;
}

$tembusan = array_filter(array_map('trim', explode("\n", $m['tembusan'] ?? '')));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Memo Perpanjangan — <?= e($m['karyawan_nama']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Times+New+Roman&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
*{ margin:0; padding:0; box-sizing:border-box; }

body {
  font-family: 'Times New Roman', Times, serif;
  font-size: 11pt;
  color: #000;
  background: #0b1329;
  padding: 24px 16px;
}

.print-bar {
  background: #090e1a;
  color: #fff;
  padding: 12px 20px;
  border-radius: 12px;
  margin-bottom: 20px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 13px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  max-width: 210mm;
  margin-left: auto;
  margin-right: auto;
  border: 1px solid rgba(255,255,255,0.08);
  box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.print-bar .bar-title {
  font-weight: 600;
  font-size: 13px;
  color: #e2e8f0;
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 8px;
}
.bar-actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
}
.bar-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  font-family: 'Inter', sans-serif;
  transition: all 0.15s ease;
  white-space: nowrap;
}
.bar-btn-download {
  background: #059669;
  color: #fff;
  box-shadow: 0 2px 8px rgba(5,150,105,0.3);
}
.bar-btn-download:hover { background: #047857; }
.bar-btn-download:active { transform: scale(0.97); }
.bar-btn-print {
  background: #1e293b;
  color: #f1f5f9;
  border: 1px solid rgba(255,255,255,0.12);
}
.bar-btn-print:hover { background: #334155; }
.bar-btn-back {
  background: transparent;
  color: #94a3b8;
  border: 1px solid rgba(255,255,255,0.1);
}
.bar-btn-back:hover { color: #fff; border-color: rgba(255,255,255,0.25); }
/* Spinner saat generating */
.spinner {
  display: inline-block;
  width: 14px; height: 14px;
  border: 2px solid rgba(255,255,255,0.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.page {
  background: #fff;
  width: 210mm;
  min-height: 297mm;
  margin: 0 auto;
  padding: 20mm 20mm 20mm 25mm;
  box-shadow: 0 2px 16px rgba(0,0,0,0.25);
  position: relative;
}

/* Header */
.memo-header-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
}

.memo-title {
  font-size: 14pt;
  font-weight: bold;
  letter-spacing: 0.5px;
}

.logo-area img {
  height: 70px;
  display: block;
  margin-left: auto;
  margin-top: -10px;
}

/* Meta table */
.meta-tbl {
  border-collapse: collapse;
  margin-bottom: 20px;
  width: auto;
}

.meta-tbl td {
  padding: 2px 0;
  vertical-align: top;
  font-size: 11pt;
}

.meta-tbl td.lbl { width: 90px; white-space: nowrap; }
.meta-tbl td.sep { width: 16px; }
.meta-tbl td.kota { white-space: nowrap; }
.meta-tbl .hr-line td { border-top: 1px solid #999; padding-top: 8px; }

/* Body text */
.memo-body p {
  line-height: 1.75;
  margin-bottom: 10px;
  text-align: justify;
  font-size: 11pt;
}

/* Main table */
.data-tbl {
  width: 100%;
  border-collapse: collapse;
  margin: 14px 0 18px;
  font-size: 10.5pt;
}

.data-tbl th {
  border: 1px solid #000;
  padding: 5px 8px;
  background: #f0f0f0;
  text-align: left;
  font-weight: bold;
}

.data-tbl td {
  border: 1px solid #000;
  padding: 5px 8px;
  vertical-align: top;
}

.data-tbl .col-no  { width: 40px; text-align: center; }
.data-tbl .col-nama{ width: 26%; }
.data-tbl .col-kual{ width: 26%; }
.data-tbl .col-tgl { width: 26%; }
.data-tbl .col-dip { width: 11%; text-align: center; }
.data-tbl .col-tdk { width: 13%; text-align: center; }
.data-tbl td.centang { font-size: 14pt; font-weight: bold; text-align: center; vertical-align: middle; }

/* Signature */
.sig-section {
  margin-top: 24px;
  display: flex;
  justify-content: flex-start;
}

.sig-block {
  min-width: 220px;
}

.sig-jabatan {
  font-size: 11pt;
  margin-bottom: 52px;
}

.sig-nama {
  font-size: 11pt;
  font-weight: bold;
  text-decoration: underline;
}

/* Tembusan */
.tembusan-section {
  margin-top: 28px;
  font-size: 10.5pt;
}

.tembusan-section p {
  margin-bottom: 3px;
}

/* Ref block */
.ref-block {
  margin-bottom: 14px;
  font-size: 11pt;
  line-height: 1.6;
}

/* Status badge */
.status-badge {
  display: inline-block;
  padding: 3px 12px;
  border-radius: 4px;
  font-size: 9pt;
  font-weight: bold;
  font-family: 'Segoe UI', sans-serif;
  margin-left: 8px;
}
.badge-green { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.badge-red   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

/* Watermark for non-diperpanjang */
<?php if($m['status_perpanjangan'] !== 'Diperpanjang'): ?>
.page::before {
  content: 'TIDAK DIPERPANJANG';
  position: absolute;
  top: 50%; left: 50%;
  transform: translate(-50%,-50%) rotate(-35deg);
  font-size: 52px;
  font-weight: 900;
  color: rgba(220,38,38,0.08);
  white-space: nowrap;
  pointer-events: none;
  font-family: Arial, sans-serif;
}
<?php endif; ?>

@media print {
  body { background: #fff; padding: 0; }
  .print-bar { display: none !important; }
  .page { box-shadow: none; margin: 0; width: 100%; padding: 15mm; }
}

/* Mobile responsiveness */
@media (max-width: 600px) {
  body { padding: 0; background: #fff; }
  .print-bar { border-radius: 0; margin-bottom: 0; position: sticky; top: 0; z-index: 100; }
  .bar-title { display: none; }
  .page {
    width: 100%;
    min-height: auto;
    padding: 16px;
    box-shadow: none;
    border-radius: 0;
  }
  .memo-header-row { flex-direction: column; gap: 10px; }
  .logo-area img { height: 32px; }
  .memo-title { font-size: 13pt; }
  .meta-tbl { font-size: 10pt; }
  .data-tbl { font-size: 9pt; }
  .memo-body p { font-size: 10pt; }
}
</style>
</head>
<body>

<div class="print-bar no-print">
  <span class="bar-title"><?= icon('file-text') ?> Memorandum — <?= e($m['karyawan_nama']) ?> &nbsp;&bull;&nbsp; <span style="font-family:monospace;font-size:12px;color:#94a3b8;"><?= e($m['nomor_memo']) ?></span></span>
  <div class="bar-actions">
    <button class="bar-btn bar-btn-download" onclick="downloadPDF()" id="btnDl">
      <?= icon('download') ?> Unduh PDF
    </button>
    <button class="bar-btn bar-btn-print" onclick="window.print()">
      <?= icon('printer') ?> Cetak Dokumen
    </button>
    <?php if(isAdminLoggedIn()): ?>
    <button class="bar-btn bar-btn-back" onclick="history.back()"><?= icon('arrow-left') ?> Kembali</button>
    <?php endif; ?>
  </div>
</div>

<div class="page">

  <!-- HEADER -->
  <div class="memo-header-row">
    <div class="memo-title">MEMORANDUM</div>
    <div class="logo-area">
      <img src="<?= APP_URL ?>/assets/img/logo_indopelita.jpg" alt="Indopelita" style="height:70px;display:block;margin-left:auto;margin-top:-10px;">
    </div>
  </div>

  <!-- META -->
  <table class="meta-tbl">
    <tr>
      <td class="kota" colspan="3" style="white-space:nowrap"><?= e($m['kota_memo']) ?>, <?= tglIndo($m['tanggal_memo']) ?></td>
    </tr>
    <tr>
      <td class="lbl">Nomor</td>
      <td class="sep">:</td>
      <td><?= e($m['nomor_memo']) ?></td>
    </tr>
    <tr><td colspan="3" style="height:10px"></td></tr>
    <tr>
      <td class="lbl">Kepada</td>
      <td class="sep">:</td>
      <td><?= e($m['kepada_jabatan']) ?></td>
    </tr>
    <tr>
      <td class="lbl">Dari</td>
      <td class="sep">:</td>
      <td><?= e($m['dari_jabatan']) ?></td>
    </tr>
    <tr><td colspan="3" style="height:10px"></td></tr>
    <tr>
      <td class="lbl">Lampiran</td>
      <td class="sep">:</td>
      <td>-</td>
    </tr>
    <tr>
      <td class="lbl">Perihal</td>
      <td class="sep">:</td>
      <td><strong><?= e($m['perihal']) ?></strong></td>
    </tr>
  </table>

  <?php if($m['ref_kontrak']): ?>
  <div class="ref-block">
    Reff: <?= e($m['ref_kontrak']) ?>
  </div>
  <?php endif; ?>

  <!-- BODY -->
  <?php
    $statusCetakBody = !empty($m['status_memo']) ? $m['status_memo'] : $m['status_perpanjangan'];
    $par1 = !empty($m['isi_paragraf1']) ? $m['isi_paragraf1']
          : 'Bersama ini kami sampaikan daftar nama para Personel yang masih dibutuhkan, dikarenakan terkait dengan Capability Perusahaan serta kebutuhan perusahaan.';
    $par2 = !empty($m['isi_paragraf2']) ? $m['isi_paragraf2']
          : ($statusCetakBody === 'Diperpanjang'
              ? 'Maka sehubungan dengan hal tersebut diatas, mohon agar kiranya dapat dilakukan perpanjangan masa kerja, dengan pengantar sebagai berikut :'
              : 'Maka sehubungan dengan hal tersebut diatas, dengan berat hati kami sampaikan bahwa masa kerja personel berikut tidak dapat diperpanjang, dengan keterangan sebagai berikut :');
  ?>
  <div class="memo-body">
    <p><?= nl2br(e($par1)) ?></p>
    <p><?= nl2br(e($par2)) ?></p>
  </div>

  <!-- TABEL KARYAWAN -->
  <?php
    // Tentukan status untuk centang: gunakan status_memo jika ada, fallback ke status_perpanjangan
    $statusCetak = !empty($m['status_memo']) ? $m['status_memo'] : $m['status_perpanjangan'];
    $isDiperpanjang = ($statusCetak === 'Diperpanjang');
  ?>
  <table class="data-tbl">
    <thead>
      <tr>
        <th class="col-no">No.</th>
        <th class="col-nama">Personil</th>
        <?php if($m['no_pegawai']): ?><th style="width:12%">No. Pegawai</th><?php endif; ?>
        <th class="col-kual">Kualifikasi</th>
        <th class="col-tgl">Tanggal Dibutuhkan</th>
        <th class="col-dip">Diperpanjang</th>
        <th class="col-tdk">Tidak Diperpanjang</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td style="text-align:center">01.</td>
        <td><?= e($m['karyawan_nama']) ?></td>
        <?php if($m['no_pegawai']): ?><td><?= e($m['no_pegawai']) ?></td><?php endif; ?>
        <td><?= e($m['kualifikasi_memo']) ?></td>
        <td><?= tglIndo($m['tanggal_mulai_kontrak']) ?> – <?= tglIndo($m['tanggal_akhir_kontrak']) ?></td>
        <td class="centang"><?= $isDiperpanjang ? '&#10003;' : '' ?></td>
        <td class="centang"><?= !$isDiperpanjang ? '&#10003;' : '' ?></td>
      </tr>
    </tbody>
  </table>

  <div class="memo-body">
    <p>Demikian disampaikan, atas perhatian dan kerjasamanya diucapkan terima kasih.</p>
  </div>

  <!-- TANDA TANGAN -->
  <div class="sig-section">
    <div class="sig-block">
      <div class="sig-jabatan"><?= e($m['ttd_memo_jabatan']) ?></div>
      <div class="sig-nama"><?= e($m['ttd_memo_nama']) ?></div>
    </div>
  </div>

  <!-- TEMBUSAN -->
  <?php if($tembusan): ?>
  <div class="tembusan-section">
    <p>Tembusan :</p>
    <?php foreach($tembusan as $t): ?>
    <p style="padding-left:16px">- <?= e($t) ?></p>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Footer info kecil (hanya di layar, hidden saat print) -->
  <div style="position:absolute;bottom:12mm;right:20mm;font-size:8pt;color:#94a3b8;font-family:'Inter',sans-serif;" class="no-print">
    Nilai Penilaian: <strong style="color:#0f172a;"><?= $m['jumlah_nilai'] ?>/50</strong> &nbsp;|&nbsp;
    <?= $m['status_perpanjangan'] === 'Diperpanjang'
      ? '<span class="badge badge-green" style="font-size:10.5px;padding:3px 10px;"><span class="badge-dot"></span> DIPERPANJANG</span>'
      : '<span class="badge badge-red" style="font-size:10.5px;padding:3px 10px;"><span class="badge-dot"></span> TIDAK DIPERPANJANG</span>' ?>
  </div>

</div>

</body>
<script>
function downloadPDF() {
  const btn = document.getElementById('btnDl');
  const origText = btn.innerHTML;
  btn.innerHTML = '<span class="spinner"></span> Generating...';
  btn.disabled = true;

  const el = document.querySelector('.page');
  const nama = <?= json_encode($m['karyawan_nama']) ?>;
  const nomor = <?= json_encode(str_replace('/', '-', $m['nomor_memo'])) ?>;
  const filename = 'Memo-' + nama.replace(/\s+/g, '_') + '_' + nomor + '.pdf';

  const opt = {
    margin:       [10, 10, 10, 10],
    filename:     filename,
    image:        { type: 'jpeg', quality: 0.98 },
    html2canvas:  { scale: 2, useCORS: true, letterRendering: true },
    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
  };

  html2pdf().set(opt).from(el).save().then(function() {
    btn.innerHTML = 'Berhasil Diunduh';
    setTimeout(function() {
      btn.innerHTML = origText;
      btn.disabled = false;
    }, 2500);
  }).catch(function() {
    btn.innerHTML = origText;
    btn.disabled = false;
    alert('Gagal generate PDF. Coba gunakan tombol Cetak dan pilih Save as PDF.');
  });
}
</script>
</html>
