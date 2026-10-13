<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$db = db();
$id = intval($_GET['id'] ?? 0);
if (!$id) redirect(APP_URL . '/admin/memo.php');

$stmt = $db->prepare("
    SELECT m.*, k.nik, k.nama, k.jabatan, k.atasan_langsung, k.mulai_bekerja,
           p.jumlah_nilai, p.status_perpanjangan, p.periode, p.tanggal_penilaian, p.penilai_nama
    FROM memo_perpanjangan m
    JOIN karyawan k ON k.id=m.karyawan_id
    JOIN penilaian p ON p.id=m.penilaian_id
    WHERE m.id=?
");
$stmt->bind_param('i', $id); $stmt->execute();
$m = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$m) redirect(APP_URL . '/admin/memo.php');

$pageTitle  = 'Detail Memo: ' . $m['nama'];
$activePage = 'memo';
require_once __DIR__ . '/../includes/admin_header.php';

function fTgl($d) {
    if (!$d || $d === '0000-00-00') return '—';
    $b = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei','06'=>'Jun',
          '07'=>'Jul','08'=>'Ags','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des'];
    [$y, $mn, $day] = explode('-', $d);
    return intval($day) . ' ' . ($b[$mn] ?? $mn) . ' ' . $y;
}
$diperpanjang = $m['status_perpanjangan'] === 'Diperpanjang';
?>

<!-- Breadcrumb + Header -->
<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:22px;flex-wrap:wrap;gap:12px;">
  <div>
    <div class="breadcrumb" style="margin-bottom:6px">
      <a href="memo.php">Memo Perpanjangan</a>
      <span class="sep">›</span>
      <span><?= e($m['nama']) ?></span>
    </div>
    <h1 style="font-size:20px;font-weight:800;color:var(--gray-900);margin-bottom:4px"><?= e($m['nama']) ?></h1>
    <div style="font-size:13px;color:var(--gray-500)">
      No. Memo: <strong><?= e($m['nomor_memo']) ?></strong>
      &nbsp;·&nbsp; <?= fTgl($m['tanggal_memo']) ?>
      &nbsp;·&nbsp; <?= e($m['kota_memo']) ?>
    </div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <a href="memo.php" class="btn btn-light">← Kembali</a>
    <a href="<?= APP_URL ?>/cetak_memo.php?id=<?= $id ?>" target="_blank" class="btn btn-navy">🖨️ Cetak Memo</a>
    <a href="hapus_memo.php?id=<?= $id ?>" class="btn btn-danger"
       onclick="return confirm('Yakin hapus memo ini? Tindakan tidak dapat dibatalkan.')">🗑 Hapus</a>
  </div>
</div>

<!-- Status Banner -->
<div style="background:<?= $diperpanjang ? '#f0fdf4' : '#fef2f2' ?>;border:1px solid <?= $diperpanjang ? '#86efac' : '#fca5a5' ?>;border-radius:12px;padding:14px 20px;margin-bottom:22px;display:flex;align-items:center;gap:16px;">
  <div style="font-size:28px"><?= $diperpanjang ? '✅' : '❌' ?></div>
  <div>
    <div style="font-size:15px;font-weight:700;color:<?= $diperpanjang ? '#15803d' : '#b91c1c' ?>">
      <?= $diperpanjang ? 'Kontrak Diperpanjang' : 'Kontrak Tidak Diperpanjang' ?>
    </div>
    <div style="font-size:13px;color:var(--gray-500)">
      Nilai penilaian: <strong><?= $m['jumlah_nilai'] ?>/50</strong>
      &nbsp;·&nbsp; Periode: <strong><?= e($m['periode']) ?></strong>
      &nbsp;·&nbsp; Dinilai oleh: <?= e($m['penilai_nama']) ?>
    </div>
  </div>
  <div style="margin-left:auto">
    <?= $m['is_printed']
      ? '<span class="badge badge-green" style="font-size:12px;padding:5px 14px">🖨️ Sudah Dicetak</span>'
      : '<span class="badge badge-gray" style="font-size:12px;padding:5px 14px">📋 Belum Dicetak</span>' ?>
  </div>
</div>

<!-- Grid 2 kolom -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

  <!-- Data Memo -->
  <div class="form-card">
    <div class="form-card-header">📄 Data Memo</div>
    <?php
    $rowsMemo = [
        ['Nomor Memo',       e($m['nomor_memo'])],
        ['Tanggal Memo',     fTgl($m['tanggal_memo'])],
        ['Kota',             e($m['kota_memo'])],
        ['Kepada',           e($m['kepada_jabatan'])],
        ['Dari',             e($m['dari_jabatan']) . ($m['dari_nama'] ? ' — <strong>' . e($m['dari_nama']) . '</strong>' : '')],
        ['Perihal',          e($m['perihal'])],
        ['Ref. Kontrak',     $m['ref_kontrak'] ? e($m['ref_kontrak']) : '<span style="color:var(--gray-300)">—</span>'],
    ];
    foreach ($rowsMemo as [$l, $v]): ?>
    <div style="display:flex;font-size:13px;padding:7px 0;border-bottom:1px solid var(--gray-50);">
      <span style="color:var(--gray-400);width:130px;flex-shrink:0"><?= $l ?></span>
      <span><?= $v ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Data Karyawan & Kontrak -->
  <div class="form-card">
    <div class="form-card-header">👤 Karyawan & Kontrak</div>
    <?php
    $rowsKar = [
        ['Nama',               '<strong>' . e($m['nama']) . '</strong>'],
        ['NIK',                e($m['nik'])],
        ['Jabatan Asli',       e($m['jabatan'])],
        ['Kualifikasi Memo',   e($m['kualifikasi_memo'])],
        ['No. Pegawai',        $m['no_pegawai'] ? e($m['no_pegawai']) : '<span style="color:var(--gray-300)">—</span>'],
        ['Periode Penilaian',  e($m['periode'])],
        ['Mulai Kontrak',      fTgl($m['tanggal_mulai_kontrak'])],
        ['Akhir Kontrak',      fTgl($m['tanggal_akhir_kontrak'])],
    ];
    foreach ($rowsKar as [$l, $v]): ?>
    <div style="display:flex;font-size:13px;padding:7px 0;border-bottom:1px solid var(--gray-50);">
      <span style="color:var(--gray-400);width:130px;flex-shrink:0"><?= $l ?></span>
      <span><?= $v ?></span>
    </div>
    <?php endforeach; ?>
  </div>

</div>

<!-- Bawah: Penandatangan + Tembusan + Meta -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">

  <!-- Penandatangan -->
  <div class="form-card">
    <div class="form-card-header">✍️ Penandatangan</div>
    <div style="font-size:16px;font-weight:800;color:var(--gray-800);margin-bottom:4px"><?= e($m['ttd_memo_nama']) ?></div>
    <div style="font-size:13px;color:var(--gray-500);margin-bottom:14px"><?= e($m['ttd_memo_jabatan']) ?></div>
    <div class="divider"></div>
    <div style="font-size:12px;color:var(--gray-400)">Dibuat: <?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></div>
  </div>

  <!-- Tembusan -->
  <div class="form-card">
    <div class="form-card-header">📬 Tembusan</div>
    <?php
    $tembArr = $m['tembusan'] ? array_filter(array_map('trim', explode("\n", $m['tembusan']))) : [];
    if ($tembArr):
        foreach ($tembArr as $t): ?>
        <div style="font-size:13px;padding:5px 0;border-bottom:1px solid var(--gray-50);display:flex;align-items:center;gap:6px">
          <span style="color:var(--gray-300)">—</span> <?= e($t) ?>
        </div>
        <?php endforeach;
    else: ?>
    <div style="font-size:13px;color:var(--gray-300);padding:8px 0">Tidak ada tembusan.</div>
    <?php endif; ?>
  </div>

  <!-- Info Penilaian -->
  <div class="form-card">
    <div class="form-card-header">📊 Info Penilaian</div>
    <div style="display:flex;flex-direction:column;gap:8px;">
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:12px;color:var(--gray-400)">Total Nilai</span>
        <span style="font-size:20px;font-weight:900;color:<?= $diperpanjang ? 'var(--green)' : 'var(--red)' ?>"><?= $m['jumlah_nilai'] ?><span style="font-size:12px;font-weight:400;color:var(--gray-400)">/50</span></span>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:12px;color:var(--gray-400)">Status</span>
        <?= $diperpanjang
          ? '<span class="badge badge-green">✅ Diperpanjang</span>'
          : '<span class="badge badge-red">❌ Tidak Diperpanjang</span>' ?>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:12px;color:var(--gray-400)">Penilai</span>
        <span style="font-size:12px;font-weight:600"><?= e($m['penilai_nama']) ?></span>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:12px;color:var(--gray-400)">Tgl Penilaian</span>
        <span style="font-size:12px"><?= fTgl($m['tanggal_penilaian']) ?></span>
      </div>
      <div class="divider"></div>
      <a href="<?= APP_URL ?>/admin/detail_penilaian.php?id=<?= $m['penilaian_id'] ?>" class="btn btn-light btn-sm" style="width:100%;text-align:center;justify-content:center;">
        📋 Lihat Data Penilaian Lengkap
      </a>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
