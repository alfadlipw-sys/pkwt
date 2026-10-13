<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$db = db();

$search   = trim($_GET['q']      ?? '');
$fStatus  = $_GET['status']      ?? '';
$fPeriode = trim($_GET['periode'] ?? '');
$fCetak   = $_GET['cetak']       ?? '';

$where = "WHERE 1=1"; $params = []; $types = '';
if ($search)  { $where .= " AND (k.nama LIKE ? OR k.nik LIKE ? OR m.nomor_memo LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $types .= 'sss'; }
if ($fStatus) { $where .= " AND p.status_perpanjangan=?"; $params[] = $fStatus; $types .= 's'; }
if ($fPeriode){ $where .= " AND p.periode LIKE ?"; $params[] = "%$fPeriode%"; $types .= 's'; }
if ($fCetak === '1') { $where .= " AND m.is_printed=1"; }
elseif ($fCetak === '0') { $where .= " AND m.is_printed=0"; }

$limit  = 20;
$page   = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Count total
$cntSql = "SELECT COUNT(*) c FROM memo_perpanjangan m JOIN karyawan k ON k.id=m.karyawan_id JOIN penilaian p ON p.id=m.penilaian_id $where";
if ($types) {
    $cs = $db->prepare($cntSql); $cs->bind_param($types, ...$params); $cs->execute();
    $total = $cs->get_result()->fetch_assoc()['c']; $cs->close();
} else {
    $total = $db->query($cntSql)->fetch_assoc()['c'];
}

// Data list
$sql = "SELECT m.id, m.nomor_memo, m.tanggal_memo, m.kota_memo, m.dari_jabatan, m.dari_nama,
               m.ttd_memo_nama, m.ttd_memo_jabatan,
               m.kualifikasi_memo, m.tanggal_mulai_kontrak, m.tanggal_akhir_kontrak, m.is_printed,
               m.created_at,
               k.nama, k.nik, k.jabatan,
               p.jumlah_nilai, p.status_perpanjangan, p.periode
        FROM memo_perpanjangan m
        JOIN karyawan k ON k.id=m.karyawan_id
        JOIN penilaian p ON p.id=m.penilaian_id
        $where
        ORDER BY m.created_at DESC LIMIT ? OFFSET ?";
$allP = array_merge($params, [$limit, $offset]); $allT = $types . 'ii';
$stmt = $db->prepare($sql); $stmt->bind_param($allT, ...$allP); $stmt->execute();
$list = $stmt->get_result(); $stmt->close();
$totalPage = max(1, ceil($total / $limit));

// Stats
$statTotal   = $db->query("SELECT COUNT(*) c FROM memo_perpanjangan")->fetch_assoc()['c'];
$statDicetak = $db->query("SELECT COUNT(*) c FROM memo_perpanjangan WHERE is_printed=1")->fetch_assoc()['c'];
$statBelumCetak = $statTotal - $statDicetak;
$noBelumMemo = $db->query("SELECT COUNT(*) c FROM penilaian WHERE id NOT IN (SELECT penilaian_id FROM memo_perpanjangan) AND status_perpanjangan='Diperpanjang'")->fetch_assoc()['c'];

$pageTitle  = 'Memo Perpanjangan';
$activePage = 'memo';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-success" style="margin-bottom:18px">
  <span class="alert-icon">✅</span>
  <div>Memo berhasil dihapus dari sistem.</div>
</div>
<?php endif; ?>

<!-- ── STATS ── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:22px;">

  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:14px;">
    <div style="width:44px;height:44px;background:#eff6ff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;">📄</div>
    <div>
      <div style="font-size:26px;font-weight:900;color:var(--blue);line-height:1"><?= $statTotal ?></div>
      <div style="font-size:12px;color:var(--gray-500);margin-top:2px">Total Memo</div>
    </div>
  </div>

  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:14px;">
    <div style="width:44px;height:44px;background:#fff7ed;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;">⏳</div>
    <div>
      <div style="font-size:26px;font-weight:900;color:var(--amber);line-height:1"><?= $noBelumMemo ?></div>
      <div style="font-size:12px;color:var(--gray-500);margin-top:2px">Perlu Dibuat Memo</div>
    </div>
  </div>

  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:14px;">
    <div style="width:44px;height:44px;background:#fef2f2;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;">🗂️</div>
    <div>
      <div style="font-size:26px;font-weight:900;color:#ef4444;line-height:1"><?= $statBelumCetak ?></div>
      <div style="font-size:12px;color:var(--gray-500);margin-top:2px">Belum Dicetak</div>
    </div>
  </div>

  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:14px;">
    <div style="width:44px;height:44px;background:#f0fdf4;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;">🖨️</div>
    <div>
      <div style="font-size:26px;font-weight:900;color:var(--green);line-height:1"><?= $statDicetak ?></div>
      <div style="font-size:12px;color:var(--gray-500);margin-top:2px">Sudah Dicetak</div>
    </div>
  </div>

</div>

<?php if ($noBelumMemo > 0): ?>
<div class="alert alert-warning" style="background:#fffbeb;border-color:#fde68a;color:#92400e;margin-bottom:18px;">
  <span class="alert-icon">⚠️</span>
  <div>
    <strong><?= $noBelumMemo ?> karyawan DIPERPANJANG belum memiliki memo.</strong>
    <a href="<?= APP_URL ?>/admin/penilaian.php?status=Diperpanjang" style="color:#b45309;margin-left:8px;text-decoration:underline">Lihat daftarnya →</a>
  </div>
</div>
<?php endif; ?>

<!-- ── FILTER ── -->
<div class="form-card" style="padding:14px 20px;margin-bottom:18px;">
  <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
    <div>
      <div style="font-size:11px;font-weight:600;color:var(--gray-600);margin-bottom:5px">Cari Nama / NIK / Nomor</div>
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Ahmad / EMP001 / VP/TA/..." class="form-control" style="width:200px">
    </div>
    <div>
      <div style="font-size:11px;font-weight:600;color:var(--gray-600);margin-bottom:5px">Periode</div>
      <input type="text" name="periode" value="<?= e($fPeriode) ?>" placeholder="2025-Q1..." class="form-control" style="width:120px">
    </div>
    <div>
      <div style="font-size:11px;font-weight:600;color:var(--gray-600);margin-bottom:5px">Status Penilaian</div>
      <select name="status" class="form-control" style="width:170px">
        <option value="">Semua Status</option>
        <option value="Diperpanjang"     <?= $fStatus==='Diperpanjang'?'selected':'' ?>>✅ Diperpanjang</option>
        <option value="Tidak Diperpanjang" <?= $fStatus==='Tidak Diperpanjang'?'selected':'' ?>>❌ Tidak Diperpanjang</option>
      </select>
    </div>
    <div>
      <div style="font-size:11px;font-weight:600;color:var(--gray-600);margin-bottom:5px">Status Cetak</div>
      <select name="cetak" class="form-control" style="width:150px">
        <option value="">Semua</option>
        <option value="0" <?= $fCetak==='0'?'selected':'' ?>>🗂️ Belum Dicetak</option>
        <option value="1" <?= $fCetak==='1'?'selected':'' ?>>🖨️ Sudah Dicetak</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">🔍 Filter</button>
    <a href="memo.php" class="btn btn-light btn-sm" style="align-self:flex-end">↩ Reset</a>
  </form>
</div>

<!-- ── TABEL MEMO ── -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><div class="card-title-icon">📄</div>Daftar Memo Perpanjangan (<?= $total ?>)</div>
    <div style="font-size:12px;color:var(--gray-400)">
      <?php if($search||$fStatus||$fPeriode||$fCetak !== ''): ?>
        Hasil filter · <a href="memo.php" style="color:var(--blue)">Reset</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th style="width:36px">#</th>
          <th>Nomor Memo</th>
          <th>Karyawan</th>
          <th>Periode</th>
          <th>Kontrak</th>
          <th style="text-align:center">Status</th>
          <th>Penandatangan</th>
          <th>Dibuat</th>
          <th style="text-align:center;width:80px">Cetak</th>
          <th style="width:100px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php $no = $offset + 1; while ($r = $list->fetch_assoc()): ?>
        <tr>
          <td style="color:var(--gray-400);font-size:12px"><?= $no++ ?></td>

          <!-- Nomor memo -->
          <td>
            <div style="font-family:monospace;font-size:11.5px;background:var(--blue-pale);color:var(--blue);padding:2px 8px;border-radius:4px;display:inline-block;white-space:nowrap">
              <?= e($r['nomor_memo']) ?>
            </div>
            <div style="font-size:10.5px;color:var(--gray-400);margin-top:3px"><?= e($r['kota_memo']) ?>, <?= date('d/m/Y', strtotime($r['tanggal_memo'])) ?></div>
          </td>

          <!-- Karyawan -->
          <td>
            <div style="font-weight:700;font-size:13px"><?= e($r['nama']) ?></div>
            <div style="font-size:11px;color:var(--gray-400)"><?= e($r['nik']) ?> · <?= e($r['kualifikasi_memo']) ?></div>
          </td>

          <!-- Periode -->
          <td style="font-size:12px;white-space:nowrap"><?= e($r['periode']) ?></td>

          <!-- Kontrak -->
          <td style="font-size:12px">
            <?php if ($r['tanggal_mulai_kontrak'] && $r['tanggal_mulai_kontrak'] !== '0000-00-00'): ?>
            <div style="white-space:nowrap"><?= date('d/m/Y', strtotime($r['tanggal_mulai_kontrak'])) ?></div>
            <div style="color:var(--gray-400)">s/d <?= date('d/m/Y', strtotime($r['tanggal_akhir_kontrak'])) ?></div>
            <?php else: ?><span style="color:var(--gray-300)">—</span><?php endif; ?>
          </td>

          <!-- Status -->
          <td style="text-align:center">
            <?= $r['status_perpanjangan'] === 'Diperpanjang'
              ? '<span class="badge badge-green">✅ Diperpanjang</span>'
              : '<span class="badge badge-red">❌ Tidak Diperpanjang</span>' ?>
            <div style="font-size:11px;color:var(--gray-400);margin-top:2px">Nilai: <strong><?= $r['jumlah_nilai'] ?></strong>/50</div>
          </td>

          <!-- Penandatangan -->
          <td style="font-size:12px">
            <?= e($r['ttd_memo_nama']) ?>
            <div style="font-size:10.5px;color:var(--gray-400)"><?= e($r['ttd_memo_jabatan'] ?? $r['dari_jabatan']) ?></div>
          </td>

          <!-- Dibuat -->
          <td style="font-size:11.5px;color:var(--gray-500);white-space:nowrap"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>

          <!-- Status cetak -->
          <td style="text-align:center">
            <?= $r['is_printed']
              ? '<span class="badge badge-green" style="font-size:10px">✓ Dicetak</span>'
              : '<span class="badge badge-gray" style="font-size:10px">Belum</span>' ?>
          </td>

          <!-- Aksi -->
          <td>
            <div style="display:flex;gap:4px;flex-wrap:nowrap;">
              <a href="<?= APP_URL ?>/cetak_memo.php?id=<?= $r['id'] ?>" target="_blank"
                 class="btn btn-navy btn-xs" title="Cetak Memo">🖨️</a>
              <a href="detail_memo.php?id=<?= $r['id'] ?>"
                 class="btn btn-primary btn-xs" title="Detail">👁</a>
              <a href="hapus_memo.php?id=<?= $r['id'] ?>"
                 class="btn btn-danger btn-xs" title="Hapus"
                 onclick="return confirm('Hapus memo ini? Tindakan ini tidak dapat dibatalkan.')">🗑</a>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
        <?php if ($total === 0): ?>
        <tr>
          <td colspan="10" style="text-align:center;padding:48px;color:var(--gray-400)">
            <div style="font-size:36px;margin-bottom:12px">📭</div>
            <div style="font-size:14px;font-weight:600">Belum ada memo perpanjangan</div>
            <div style="font-size:12px;margin-top:4px">Memo akan muncul setelah penilai mengisi form penilaian dan memo.</div>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPage > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $totalPage; $i++):
      $q = http_build_query(['q' => $search, 'status' => $fStatus, 'periode' => $fPeriode, 'cetak' => $fCetak, 'page' => $i]); ?>
      <?php if ($i === $page): ?><span class="active"><?= $i ?></span>
      <?php else: ?><a href="?<?= $q ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
