<?php
require_once __DIR__ . '/../config.php';
requireAdmin();

$id = intval($_GET['id'] ?? 0);
$idsStr = trim($_GET['ids'] ?? '');
if (!$id && !$idsStr) redirect(APP_URL . '/admin/penilaian.php');

$idList = [];
if ($id) {
    $idList = [$id];
} else if ($idsStr) {
    $idList = array_values(array_unique(array_filter(array_map('intval', explode(',', $idsStr)))));
}
if (!$idList) redirect(APP_URL . '/admin/penilaian.php');

$in = implode(',', $idList);
$stmt = db()->query("
    SELECT p.*, p.tanda_tangan, k.nama, k.jabatan, k.nik, k.atasan_langsung, k.mulai_bekerja,
           ft.periode, ft.shift, ft.cuti, ft.ijin, ft.periode_ke, ft.catatan_hrd
    FROM penilaian p
    JOIN karyawan k ON k.id = p.karyawan_id
    JOIN form_tokens ft ON ft.id = p.token_id
    WHERE p.id IN ($in)
    ORDER BY FIELD(p.id, $in)
");
$records = [];
while ($row = $stmt->fetch_assoc()) {
    $records[] = $row;
}

if (!$records) die('Data tidak ditemukan.');

$predikatColor = [5=>'#059669',4=>'#16a34a',3=>'#d97706',2=>'#ea580c',1=>'#dc2626'];
$predikatLabel = [5=>'Sangat Baik',4=>'Baik',3=>'Cukup Baik',2=>'Kurang Baik',1=>'Tidak Baik'];

$kriteriaDefs = [
    'sikap' => ['label'=>'Tingkat Sikap / Attitude','items'=>[
        '5 – SANGAT BAIK, sangat menyenangkan, sangat sopan, sangat terbuka dan sangat positif dalam menerima kritik.',
        '4 – BAIK, menyenangkan, sopan, terbuka dan menerima kritik dengan baik.',
        '3 – CUKUP BAIK, cukup menyenangkan, cukup sopan, cukup terbuka, cukup bersifat positif dalam menerima kritik.',
        '2 – KURANG BAIK, Kurang menyenangkan, kurang sopan, agak tertutup, agak negatif dalam menerima kritik.',
        '1 – TIDAK BAIK, tidak menyenangkan, tidak sopan, tertutup, tidak bisa menerima kritik.'
    ]],
    'kerjasama' => ['label'=>'Kerjasama / Teamwork','items'=>[
        '5 – SANGAT BAIK, sangat kooperatif dan sangat antusias.',
        '4 – BAIK, kooperatif dan cukup antusias.',
        '3 – CUKUP BAIK, cukup kooperatif dan cukup antusias.',
        '2 – KURANG BAIK, kurang kooperatif dan kurang antusias.',
        '1 – TIDAK BAIK, tidak kooperatif dan tidak antusias sama sekali.'
    ]],
    'inisiatif' => ['label'=>'Inisiatif & Kreativitas','items'=>[
        '5 – SANGAT BAIK, sangat tanggap dan sangat bersemangat dalam melaksanakan pekerjaan demi kemajuan diri dan departemennya.',
        '4 – BAIK, tanggap dan bersemangat dalam melaksanakan pekerjaan demi kemajuan diri dan departemennya.',
        '3 – CUKUP BAIK, cukup tanggap dan kurang bersemangat dalam melaksanakan pekerjaan demi kemajuan diri dan departemennya.',
        '2 – KURANG BAIK, kurang tanggap dan kurang bersemangat dalam melaksanakan pekerjaan demi kemajuan diri dan departemennya.',
        '1 – TIDAK BAIK, tidak tanggap dalam melaksanakan pekerjaan dan tidak bersemangat dalam kemajuan diri dan departemennya.'
    ]],
    'ketelitian' => ['label'=>'Ketelitian','items'=>[
        '5 – SANGAT BAIK, hampir tidak pernah membuat kesalahan dalam melaksanakan pekerjaan.',
        '4 – BAIK, teliti, jarang melaksanakan kesalahan dalam pekerjaan.',
        '3 – CUKUP BAIK, cukup teliti, cukup sering melaksanakan kesalahan, bisa mentoleransi kesalahan dalam pekerjaan.',
        '2 – KURANG BAIK, kurang teliti, sering melaksanakan kesalahan dan sering tidak mentoleransi kesalahan dalam pekerjaannya.',
        '1 – TIDAK BAIK, tidak teliti, sering sekali melaksanakan kesalahan dalam pekerjaan.'
    ]],
    'ketepatan' => ['label'=>'Ketepatan waktu menyelesaikan pekerjaan','items'=>[
        '5 – SANGAT BAIK, selalu tepat waktu (hampir tidak pernah) terlambat dalam pekerjaan.',
        '4 – BAIK, tepat waktu, jarang terlambat dan jarang tidak menyelesaikan pekerjaan.',
        '3 – CUKUP BAIK, cukup tepat waktu, kadang terlambat tapi masih bisa memenuhi target pekerjaan.',
        '2 – KURANG BAIK, kurang tepat waktu, sering terlambat tapi selalu berusaha menyelesaikan pekerjaan.',
        '1 – TIDAK BAIK, tidak tepat waktu dan tidak pernah menyelesaikan pekerjaan.'
    ]],
    'pengetahuan' => ['label'=>'Pengetahuan tentang pekerjaan','items'=>[
        '5 – SANGAT BAIK, pengetahuan tentang semua aspek jabatan yang menjadi tanggung jawabnya sangat tinggi.',
        '4 – BAIK, pengetahuan tentang sebagian besar aspek jabatan sudah tinggi.',
        '3 – CUKUP BAIK, secara umum pengetahuan pada kebanyakan aspek jabatan cukup.',
        '2 – KURANG BAIK, pengetahuan hanya pada aspek yang disukai atau tugas yang biasa dilakukan saja.',
        '1 – TIDAK BAIK, pengetahuan terbatas pada poin-poin kecil yang relevan saja dalam rangka tugas-tugasnya.'
    ]],
    'kualitas' => ['label'=>'Kualitas Pekerjaan','items'=>[
        '5 – SANGAT BAIK, luar biasa berhasil bekerja mencapai kesempurnaan dan kualitas yang tinggi.',
        '4 – BAIK, rasa keingintahuan dalam menyampaikan kualitas yang superior.',
        '3 – CUKUP BAIK, mencapai tingkat kualitas rata-rata pada sebagian besar situasi.',
        '2 – KURANG BAIK, mutu di bawah standar tingkat kualitas rata-rata.',
        '1 – TIDAK BAIK, gagal menyampaikan tingkat kualitas yang layak.'
    ]],
    'kuantitas' => ['label'=>'Kuantitas Pekerjaan','items'=>[
        '5 – SANGAT BAIK, selalu dalam melaksanakannya.',
        '4 – BAIK, sering dalam melaksanakan.',
        '3 – CUKUP BAIK, kadang dalam melaksanakan pekerjaan.',
        '2 – KURANG BAIK, jarang dalam melaksanakan pekerjaan.',
        '1 – TIDAK BAIK, terbatas dalam melaksanakan.'
    ]],
    'belajar' => ['label'=>'Kebiasaan dan kemampuan untuk belajar','items'=>[
        '5 – SANGAT BAIK, antusias untuk belajar dan memastikan pengembangan kerja.',
        '4 – BAIK, antusias untuk belajar dan berusaha memastikan pengembangan kerja.',
        '3 – CUKUP BAIK, cukup antusias untuk belajar dan berusaha memastikan pengembangan kerja.',
        '2 – KURANG BAIK, kurang antusias dan kurang berusaha untuk memastikan pengembangan kerja.',
        '1 – TIDAK BAIK, tidak antusias untuk belajar dan tidak berusaha untuk meningkatkan pengetahuan.'
    ]],
    'tjawab' => ['label'=>'Tanggung Jawab','items'=>[
        '5 – SANGAT BAIK, sangat bertanggung jawab, tidak melempar tanggung jawab kepada orang lain.',
        '4 – BAIK, menyukai adanya tanggung jawab, cukup menyukai adanya tanggung jawab namun terkadang masih ada bantuan.',
        '3 – CUKUP BAIK, cukup menyukai tanggung jawabnya, kadang melimpahkan tanggung jawab kepada orang lain.',
        '2 – KURANG BAIK, kurang menyukai tanggung jawabnya, sering melimpahkan tanggung jawab kepada orang lain.',
        '1 – TIDAK BAIK, tidak mau menyukai tanggung jawabnya, selalu melimpahkan tanggung jawabnya kepada orang lain.'
    ]]
];

function logoBlock(): string {
    $url = APP_URL . '/assets/img/logo_indopelita.jpg';
    return '<div style="text-align:right"><img src="' . $url . '" alt="Indopelita" style="height:70px;display:block;margin-left:auto;margin-top:-10px;"></div>';
}

$autoDownload = isset($_GET['download']) || isset($_GET['auto']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cetak Penilaian Kinerja PKWT</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Times New Roman',Times,serif;font-size:10pt;background:#ccc;padding:20px;}
.page{background:#fff;width:210mm;margin:0 auto 20px;padding:14mm 14mm 14mm 18mm;box-shadow:0 2px 12px rgba(0,0,0,.25);page-break-after:always;}
.header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:5px;}
.d{width:8px;height:8px;background:#c8a428;transform:rotate(45deg);display:inline-block;margin:0 2px;}
.form-title{font-size:12.5pt;font-weight:bold;text-transform:uppercase;}
.form-subtitle{font-size:11pt;font-weight:bold;text-transform:uppercase;}
.meta-val{border-bottom:1px solid #000;display:block;min-height:16px;font-weight:600;font-size:9pt;}
.main-table{width:100%;border-collapse:collapse;font-size:8.8pt;margin-top:5px;}
.main-table th{background:#000;color:#fff;padding:4px 5px;font-size:9.5pt;}
.main-table td{border:1px solid #000;padding:3px 5px;vertical-align:top;}
.section-row td{background:#e0e0e0;font-weight:bold;}
.predikat-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:8pt;font-weight:600;}
.print-bar{position:fixed;top:10px;right:10px;display:flex;gap:8px;z-index:100;}
.print-btn{background:#1a3a6b;color:#fff;border:none;padding:10px 18px;border-radius:6px;cursor:pointer;font-size:14px;font-family:sans-serif;font-weight:600;}
.download-btn{background:#059669;color:#fff;border:none;padding:10px 18px;border-radius:6px;cursor:pointer;font-size:14px;font-family:sans-serif;font-weight:600;display:inline-flex;align-items:center;gap:6px;}
.download-btn:hover{background:#047857;}
@media print{.print-bar,.page-wrapper-notice{display:none!important;}body{background:#fff;padding:0;}.page{box-shadow:none;margin:0;}}
</style>
</head>
<body>
<div class="print-bar">
  <button class="print-btn" onclick="window.print()">🖨️ Cetak / Print</button>
  <button class="download-btn" onclick="window.print()">📄 Download PDF</button>
</div>

<?php foreach ($records as $r):
$kriteria = [];
foreach ($kriteriaDefs as $kKey => $kDef) {
    $kriteria[] = [
        'key' => $kKey,
        'label' => $kDef['label'],
        'items' => $kDef['items'],
        'val' => intval($r[$kKey . '_nilai'] ?? 0)
    ];
}
?>

<!-- PAGE 1 -->
<div class="page">
  <div class="header">
    <div><div class="form-title">FORM PENILAIAN KINERJA KARYAWAN PKWT</div><div class="form-subtitle">(SANGAT RAHASIA)</div></div>
    <?= logoBlock() ?>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:9pt;margin-bottom:5px;">
    <tr>
      <td style="width:50%;vertical-align:top;">
        <table style="width:100%;border-collapse:collapse;">
          <?php foreach([['NIK / No.Peg',$r['nik']],['Nama',$r['nama']],['Jabatan',$r['jabatan']],['Mulai bekerja',$r['mulai_bekerja']?formatTanggal($r['mulai_bekerja']):'-']] as [$l,$v]): ?>
          <tr><td style="width:95px;padding:1.5px 0"><?= $l ?></td><td style="width:8px">:</td><td><span class="meta-val"><?= e($v) ?></span></td></tr>
          <?php endforeach; ?>
        </table>
      </td>
      <td style="width:50%;vertical-align:top;padding-left:14px;">
        <table style="width:100%;border-collapse:collapse;">
          <?php foreach([['Periode',$r['periode']],['Atasan Langsung',$r['atasan_langsung']??'-'],['Catatan Hrd',$r['catatan_hrd']??'-'],['Shift',$r['shift']??'-'],['Cuti',$r['cuti']??'-'],['Ijin % hari',$r['ijin']??'-'],['Periode Ke',$r['periode_ke']??'-']] as [$l,$v]): ?>
          <tr><td style="width:85px;padding:1.5px 0"><?= $l ?></td><td style="width:8px">:</td><td><span class="meta-val"><?= e($v) ?></span></td></tr>
          <?php endforeach; ?>
        </table>
      </td>
    </tr>
  </table>

  <table class="main-table">
    <thead><tr><th style="width:22%">Faktor-Faktor Kinerja</th><th style="width:54%">Nilai</th><th style="width:12%;text-align:center">Nilai</th><th style="width:12%;text-align:center">Predikat</th></tr></thead>
    <tbody>
      <tr class="section-row"><td colspan="4">General</td></tr>
      <?php foreach(array_slice($kriteria,0,6) as $kr): ?>
      <tr>
        <td style="font-weight:bold;font-size:8.8pt;vertical-align:top;padding-top:4px"><?= e($kr['label']) ?></td>
        <td style="font-size:7.5pt;line-height:1.25;padding:3px 5px">
          <?php foreach($kr['items'] as $item):
            $isSelected = str_starts_with($item, (string)$kr['val']);
          ?>
            <div style="margin-bottom:1.5px;<?= $isSelected ? 'font-weight:bold;color:#000;' : 'color:#555;' ?>">
              <?= e($item) ?>
            </div>
          <?php endforeach; ?>
        </td>
        <td style="text-align:center;font-size:14pt;font-weight:900;vertical-align:middle"><?= $kr['val'] ?></td>
        <td style="text-align:center;vertical-align:middle">
          <span class="predikat-badge" style="background:<?= $predikatColor[$kr['val']]?>1a;color:<?= $predikatColor[$kr['val']]?>"><?= $predikatLabel[$kr['val']] ?></span>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- PAGE 2 -->
<div class="page">
  <div class="header">
    <div style="font-size:9pt;">Lanjutan Penilaian – <?= e($r['nama']) ?></div>
    <?= logoBlock() ?>
  </div>
  <table class="main-table">
    <thead><tr><th style="width:22%">Faktor-Faktor Kinerja</th><th style="width:54%">Nilai</th><th style="width:12%;text-align:center">Nilai</th><th style="width:12%;text-align:center">Predikat</th></tr></thead>
    <tbody>
      <tr class="section-row"><td colspan="4">General</td></tr>
      <?php foreach(array_slice($kriteria,6) as $kr): ?>
      <tr>
        <td style="font-weight:bold;font-size:8.8pt;vertical-align:top;padding-top:4px"><?= e($kr['label']) ?></td>
        <td style="font-size:7.5pt;line-height:1.25;padding:3px 5px">
          <?php foreach($kr['items'] as $item):
            $isSelected = str_starts_with($item, (string)$kr['val']);
          ?>
            <div style="margin-bottom:1.5px;<?= $isSelected ? 'font-weight:bold;color:#000;' : 'color:#555;' ?>">
              <?= e($item) ?>
            </div>
          <?php endforeach; ?>
        </td>
        <td style="text-align:center;font-size:14pt;font-weight:900;vertical-align:middle"><?= $kr['val'] ?></td>
        <td style="text-align:center;vertical-align:middle">
          <span class="predikat-badge" style="background:<?= $predikatColor[$kr['val']]?>1a;color:<?= $predikatColor[$kr['val']]?>"><?= $predikatLabel[$kr['val']] ?></span>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <table style="width:100%;border-collapse:collapse;border:1px solid #000;margin-top:0;">
    <tr>
      <td style="width:20%;background:#e0e0e0;font-weight:bold;text-align:center;padding:5px;border:1px solid #000;">Jumlah Nilai</td>
      <td style="text-align:center;border:1px solid #000;padding:5px;font-size:22pt;font-weight:900;color:<?= $r['jumlah_nilai']>=28?'#16a34a':'#dc2626' ?>"><?= $r['jumlah_nilai'] ?></td>
      <td style="border:1px solid #000;padding:6px;font-size:8.5pt;">
        <strong>Catatan.</strong><br>Bagi pekerja yang nilainya dibawah 28 hari di <u><strong>Rejected / Tidak diperpanjang.</strong></u>
      </td>
    </tr>
  </table>
  <div style="text-align:center;margin-top:12px;padding:10px;border:2px solid <?= $r['status_perpanjangan']==='Diperpanjang'?'#16a34a':'#dc2626' ?>;border-radius:8px;">
    <span style="font-size:14pt;font-weight:900;color:<?= $r['status_perpanjangan']==='Diperpanjang'?'#16a34a':'#dc2626' ?>">
      <?= $r['status_perpanjangan']==='Diperpanjang' ? '✅ DIPERPANJANG' : '❌ TIDAK DIPERPANJANG' ?>
    </span>
  </div>
</div>

<!-- PAGE 3 -->
<div class="page">
  <div class="header">
    <div style="font-weight:bold;font-size:10pt">Ringkasan Penilaian Kerja</div>
    <?= logoBlock() ?>
  </div>
  <div style="font-size:9pt;margin-bottom:4px;">Menurut anda, bagaimanakah anda menilai kinerja karyawan ini secara keseluruhan?</div>
  <div style="margin-bottom:10px;">
    <strong style="font-size:11pt"><?= e($r['penilaian_keseluruhan']) ?></strong>
  </div>

  <?php foreach([
    ['Bidang-bidang tugas kerja yang unggul / menonjol', $r['bidang_unggul']],
    ['Bidang-bidang tugas kerja yang perlu ditingkatkan', $r['bidang_tingkatkan']],
  ] as [$lbl,$val]): ?>
  <div style="margin-bottom:10px;">
    <div style="font-size:9pt;margin-bottom:3px;"><?= e($lbl) ?></div>
    <div style="border-bottom:1px solid #000;min-height:18px;font-size:9pt;padding-bottom:2px"><?= nl2br(e($val??'-')) ?></div>
    <div style="border-bottom:1px solid #000;min-height:18px;"></div>
  </div>
  <?php endforeach; ?>

  <table style="width:100%;border-collapse:collapse;font-size:9pt;margin-bottom:8px;border:1px solid #000;">
    <tr><td colspan="2" style="background:#e0e0e0;font-weight:bold;padding:3px 6px;font-size:8.5pt;border:1px solid #000;">Rencana pelatihan atau pengembangan yang dibutuhkan</td></tr>
    <tr><td style="border:1px solid #000;padding:4px;min-height:18px;"><?= e($r['rencana_pelatihan']??'-') ?></td><td style="border:1px solid #000;padding:4px;"></td></tr>
  </table>

  <?php if($r['jobdesk_harian']): ?>
  <div style="margin-bottom:8px;border:1px solid #000;">
    <div style="background:#f0f0f0;font-weight:bold;font-size:8pt;padding:4px 6px;">*JOBDESK POKOK HARIAN/MINGGUAN</div>
    <div style="padding:6px;font-size:9pt;line-height:1.7"><?= nl2br(e($r['jobdesk_harian'])) ?></div>
  </div>
  <?php endif; ?>

  <?php if($r['pesan_tambahan']): ?>
  <div style="margin-bottom:8px;border:1px solid #000;">
    <div style="background:#f0f0f0;font-weight:bold;font-size:8pt;padding:4px 6px;">*PESAN/KOMENTAR TAMBAHAN / JUSTIFIKASI</div>
    <div style="padding:6px;font-size:9pt"><?= nl2br(e($r['pesan_tambahan'])) ?></div>
  </div>
  <?php endif; ?>

  <!-- PERMOHONAN PERPANJANGAN & KUALIFIKASI JABATAN -->
  <table style="width:100%;border-collapse:collapse;border:1px solid #000;margin-bottom:8px;">
    <tr>
      <!-- Kiri: Permohonan -->
      <td style="width:50%;border-right:1px solid #000;padding:6px 8px;vertical-align:top;">
        <div style="font-size:8pt;font-weight:bold;text-transform:uppercase;text-align:center;margin-bottom:6px;">Permohonan untuk Diperpanjang / Tidak Diperpanjang</div>
        <?php if(!empty($r['permohonan_perpanjangan'])): ?>
          <div style="text-align:center;margin-top:4px;">
            <?php if($r['permohonan_perpanjangan']==='Diperpanjang'): ?>
              <span style="display:inline-block;border:2px solid #16a34a;border-radius:6px;padding:6px 18px;font-size:10pt;font-weight:900;color:#15803d;background:#f0fff4;">✅ DIPERPANJANG</span>
            <?php else: ?>
              <span style="display:inline-block;border:2px solid #dc2626;border-radius:6px;padding:6px 18px;font-size:10pt;font-weight:900;color:#b91c1c;background:#fff5f5;">❌ TIDAK DIPERPANJANG</span>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div style="font-size:8.5pt;color:#aaa;font-style:italic;text-align:center;margin-top:4px;">—</div>
        <?php endif; ?>
      </td>
      <!-- Kanan: Kualifikasi Jabatan -->
      <td style="width:50%;padding:6px 8px;vertical-align:top;">
        <div style="font-size:8pt;font-weight:bold;text-transform:uppercase;text-align:center;margin-bottom:6px;">Kualifikasi / Jabatan yang Bersangkutan Saat Ini</div>
        <?php if(!empty($r['kualifikasi_jabatan'])): ?>
          <div style="font-size:8.5pt;line-height:1.6"><?= nl2br(e($r['kualifikasi_jabatan'])) ?></div>
        <?php else: ?>
          <div style="font-size:8.5pt;color:#aaa;font-style:italic;text-align:center;margin-top:4px;">—</div>
        <?php endif; ?>
      </td>
    </tr>
  </table>

  <!-- TANDA TANGAN -->
  <table style="width:100%;border-collapse:collapse;border:1px solid #000;margin-top:8px;">
    <tr>
      <td style="padding:6px 10px;width:60%;border-right:1px solid #000;vertical-align:top;">
        <div style="font-weight:bold;font-size:9pt">Dibuat oleh,</div>
        <div style="font-size:8.5pt;color:#666">Tanda tangan penilai (Atasan)</div>
        <?php if(!empty($r['tanda_tangan'])): ?>
        <img src="<?= e($r['tanda_tangan']) ?>" alt="Tanda Tangan"
             style="max-width:220px;max-height:60px;display:block;margin:6px 0;border:1px solid #eee;border-radius:3px;">
        <?php else: ?>
        <div style="height:50px;border:1px dashed #ccc;margin:6px 0;"></div>
        <?php endif; ?>
        <div style="display:flex;gap:20px;font-size:8.5pt;margin-top:4px;">
          <div>Nama : <span style="border-bottom:1px solid #000;display:inline-block;min-width:120px;font-weight:600"><?= e($r['penilai_nama']) ?></span></div>
          <div>Tanggal : <span style="border-bottom:1px solid #000;display:inline-block;min-width:90px"><?= formatTanggal($r['tanggal_penilaian']) ?></span></div>
        </div>
      </td>
      <td style="padding:6px 10px;vertical-align:top;width:40%;">
        <div style="font-size:8.5pt;color:#888">(Diisi oleh HRD)</div>
        <div style="font-size:8pt;margin-top:6px">Catatan HRD:</div>
        <div style="border:1px solid #ddd;min-height:50px;padding:4px;font-size:8.5pt;margin-top:4px;background:#fffbeb"><?= nl2br(e($r['catatan_hrd']??'-')) ?></div>
      </td>
    </tr>
  </table>
</div>
<?php endforeach; ?>

<?php if($autoDownload): ?>
<script>
  window.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() { window.print(); }, 300);
  });
</script>
<?php endif; ?>

</body>
</html>
