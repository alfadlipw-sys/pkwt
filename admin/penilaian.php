<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$db = db();

$flash='';
if(isset($_SESSION['flash'])){$flash=$_SESSION['flash'];unset($_SESSION['flash']);}

$fKaryawan=intval($_GET['karyawan']??0); $fStatus=$_GET['status']??''; $fPeriode=trim($_GET['periode']??''); $fBulan=trim($_GET['bulan']??''); $search=trim($_GET['q']??'');
$where="WHERE 1=1"; $params=[]; $types='';
if($fKaryawan){$where.=" AND p.karyawan_id=?";$params[]=$fKaryawan;$types.='i';}
if($fStatus){$where.=" AND p.status_perpanjangan=?";$params[]=$fStatus;$types.='s';}
if($fPeriode){$where.=" AND p.periode LIKE ?";$params[]="%$fPeriode%";$types.='s';}
if($fBulan){$where.=" AND (p.tanggal_penilaian LIKE ? OR (p.tanggal_penilaian IS NULL AND p.created_at LIKE ?))";$params[]="$fBulan%";$params[]="$fBulan%";$types.='ss';}
if($search){$where.=" AND (k.nama LIKE ? OR k.nik LIKE ?)";$params[]="%$search%";$params[]="%$search%";$types.='ss';}

$limit=20; $page=max(1,intval($_GET['page']??1)); $offset=($page-1)*$limit;
$cntSql="SELECT COUNT(*) c FROM penilaian p JOIN karyawan k ON k.id=p.karyawan_id $where";
if($types){$cs=$db->prepare($cntSql);$cs->bind_param($types,...$params);$cs->execute();$total=$cs->get_result()->fetch_assoc()['c'];$cs->close();}
else{$total=$db->query($cntSql)->fetch_assoc()['c'];}

$allP=array_merge($params,[$limit,$offset]); $allT=$types.'ii';
$stmt=$db->prepare("SELECT p.*,k.nama,k.jabatan,k.nik,m.id AS memo_id,m.nomor_memo FROM penilaian p JOIN karyawan k ON k.id=p.karyawan_id LEFT JOIN memo_perpanjangan m ON m.penilaian_id=p.id $where ORDER BY p.created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param($allT,...$allP); $stmt->execute(); $list=$stmt->get_result(); $stmt->close();
$totalPage=ceil($total/$limit);

$stats=$db->query("SELECT COUNT(*) total,SUM(status_perpanjangan='Diperpanjang') lanjut,SUM(status_perpanjangan='Tidak Diperpanjang') tidak,AVG(jumlah_nilai) avg_nilai,MAX(jumlah_nilai) max_nilai,MIN(jumlah_nilai) min_nilai FROM penilaian")->fetch_assoc();

$pageTitle='Hasil Penilaian'; $activePage='penilaian';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if($flash): ?><div class="alert alert-success"><span class="alert-icon">✅</span><?= $flash ?></div><?php endif; ?>

<!-- Mini Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:22px;">
  <?php foreach([
    ['Total Penilaian',$stats['total'],'📋','var(--blue)'],
    ['Diperpanjang',$stats['lanjut'],'✅','var(--green)'],
    ['Tidak Diperpanjang',$stats['tidak'],'❌','var(--red)'],
    ['Rata-rata Nilai',number_format($stats['avg_nilai']??0,1),'📊','var(--blue)'],
    ['Nilai Tertinggi',$stats['max_nilai']??0,'🏆','var(--green)'],
    ['Nilai Terendah',$stats['min_nilai']??0,'📉','var(--amber)'],
  ] as [$lbl,$val,$ico,$col]): ?>
  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;display:flex;align-items:center;gap:12px;">
    <div style="font-size:22px"><?= $ico ?></div>
    <div>
      <div style="font-size:22px;font-weight:800;color:<?= $col ?>"><?= $val ?></div>
      <div style="font-size:11.5px;color:var(--gray-500);margin-top:2px"><?= $lbl ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filter -->
<div class="form-card" style="padding:16px 20px;margin-bottom:18px;">
  <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
    <div>
      <div style="font-size:11px;font-weight:600;color:var(--gray-600);margin-bottom:5px">Cari Nama/NIK</div>
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Nama atau NIK..." class="form-control" style="width:170px">
    </div>
    <div>
      <div style="font-size:11px;font-weight:600;color:var(--gray-600);margin-bottom:5px">Bulan</div>
      <input type="month" name="bulan" value="<?= e($fBulan) ?>" class="form-control" style="width:150px">
    </div>
    <div>
      <div style="font-size:11px;font-weight:600;color:var(--gray-600);margin-bottom:5px">Periode</div>
      <input type="text" name="periode" value="<?= e($fPeriode) ?>" placeholder="2025-Q1..." class="form-control" style="width:120px">
    </div>
    <div>
      <div style="font-size:11px;font-weight:600;color:var(--gray-600);margin-bottom:5px">Status</div>
      <select name="status" class="form-control" style="width:170px">
        <option value="">Semua Status</option>
        <option value="Diperpanjang" <?= $fStatus==='Diperpanjang'?'selected':'' ?>>✅ Diperpanjang</option>
        <option value="Tidak Diperpanjang" <?= $fStatus==='Tidak Diperpanjang'?'selected':'' ?>>❌ Tidak Diperpanjang</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">🔍 Filter</button>
    <a href="penilaian.php" class="btn btn-light btn-sm" style="align-self:flex-end">Reset</a>
    <?php $expQuery = http_build_query(array_filter(['bulan'=>$fBulan, 'periode'=>$fPeriode, 'status'=>$fStatus])); ?>
    <a href="laporan.php<?= $expQuery ? '?'.$expQuery : '' ?>" class="btn btn-success btn-sm" style="align-self:flex-end;margin-left:auto">📊 Ekspor</a>
  </form>
</div>

<!-- Batch Action Bar -->
<div id="batchBar" style="display:none;background:#e0f2fe;border:1.5px solid #7dd3fc;border-radius:10px;padding:12px 18px;margin-bottom:18px;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
  <div style="font-size:13px;font-weight:700;color:#0369a1;">
    📌 <span id="selectedCount">0</span> data hasil penilaian dipilih
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <button type="button" onclick="batchPrintPDFCombined()" class="btn btn-navy btn-sm">🖨️ Cetak / Save PDF Terpilih (Gabungan 1 File)</button>
    <button type="button" onclick="batchPrintPDFSeparate()" class="btn btn-success btn-sm">📄 Cetak / Save PDF Masing-Masing (Terpisah)</button>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><div class="card-title-icon">📋</div>Hasil Penilaian (<?= $total ?> data)</div>
  </div>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th style="width:36px;text-align:center" id="thSelectAll" onclick="event.stopPropagation()">
            <input type="checkbox" id="selectAll" onclick="event.stopPropagation(); toggleSelectAll(this);" onchange="toggleSelectAll(this);" title="Pilih Semua">
          </th>
          <th>#</th><th>Karyawan</th><th>Periode</th><th style="text-align:center">Nilai</th><th style="text-align:center">Status</th><th>Penilai</th><th>Tgl Penilaian</th><th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php $no=$offset+1; while($r=$list->fetch_assoc()): ?>
        <tr class="clickable" onclick="location.href='detail_penilaian.php?id=<?= $r['id'] ?>'">
          <td style="text-align:center" onclick="event.stopPropagation()">
            <input type="checkbox" class="item-select" value="<?= $r['id'] ?>" onclick="event.stopPropagation(); updateBatchState();" onchange="updateBatchState()">
          </td>
          <td style="color:var(--gray-400);font-size:12px"><?= $no++ ?></td>
          <td>
            <div style="font-weight:700"><?= e($r['nama']) ?></div>
            <div style="font-size:11px;color:var(--gray-400)"><?= e($r['nik']) ?> · <?= e($r['jabatan']) ?></div>
          </td>
          <td style="font-size:12px;color:var(--gray-500)"><?= e($r['periode']) ?></td>
          <td style="text-align:center">
            <span style="font-size:24px;font-weight:900;color:<?= $r['jumlah_nilai']>=28?'var(--green)':'var(--red)' ?>"><?= $r['jumlah_nilai'] ?></span>
            <div style="font-size:10px;color:var(--gray-400)">/ 50</div>
          </td>
          <td style="text-align:center">
            <?= $r['status_perpanjangan']==='Diperpanjang'
              ? '<span class="badge badge-green">✅ Diperpanjang</span>'
              : '<span class="badge badge-red">❌ Ditolak</span>' ?>
          </td>
          <td style="font-size:12px"><?= e($r['penilai_nama']) ?></td>
          <td style="font-size:12px;color:var(--gray-500)"><?= formatTanggal($r['tanggal_penilaian']) ?></td>
          <td onclick="event.stopPropagation()">
            <div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center;">
              <a href="detail_penilaian.php?id=<?= $r['id'] ?>" class="btn btn-primary btn-xs" title="Lihat Detail">👁 Detail</a>
              <?php if(!empty($r['memo_id'])): ?>
                <a href="detail_memo.php?id=<?= $r['memo_id'] ?>" class="btn btn-warning btn-xs" title="Memo sudah dibuat (<?= e($r['nomor_memo']) ?>)">📄 Memo</a>
              <?php else: ?>
                <a href="<?= APP_URL ?>/form_memo.php?penilaian_id=<?= $r['id'] ?>" class="btn btn-success btn-xs" title="Buat Memo Perpanjangan Kontrak" style="font-weight:700;">📝 Buat Memo</a>
              <?php endif; ?>
              <a href="cetak_penilaian.php?id=<?= $r['id'] ?>&auto=1" target="_blank" class="btn btn-navy btn-xs" title="Cetak / Download PDF Rapi">🖨️ Cetak PDF</a>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
        <?php if($total===0): ?>
        <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--gray-400)">Belum ada penilaian yang masuk</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if($totalPage>1): ?>
  <div class="pagination">
    <?php for($i=1;$i<=$totalPage;$i++): ?>
      <?php $q=http_build_query(array_filter(['q'=>$search,'bulan'=>$fBulan,'status'=>$fStatus,'periode'=>$fPeriode]) + ['page'=>$i]); ?>
      <?php if($i===$page): ?><span class="active"><?=$i?></span>
      <?php else: ?><a href="?<?=$q?>"><?=$i?></a><?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<script>
function updateBatchState() {
  const allBoxes = document.querySelectorAll('.item-select');
  const checkedBoxes = document.querySelectorAll('.item-select:checked');
  const batchBar = document.getElementById('batchBar');
  const selectedCount = document.getElementById('selectedCount');
  const master = document.getElementById('selectAll');

  const count = checkedBoxes.length;

  if (batchBar) {
    batchBar.style.display = count > 0 ? 'flex' : 'none';
  }
  if (selectedCount) {
    selectedCount.textContent = count;
  }
  if (master) {
    master.checked = allBoxes.length > 0 && count === allBoxes.length;
    master.indeterminate = count > 0 && count < allBoxes.length;
  }
}

function toggleSelectAll(master) {
  const isChecked = master.checked;
  const checkboxes = document.querySelectorAll('.item-select');
  checkboxes.forEach(cb => {
    cb.checked = isChecked;
  });
  updateBatchState();
}

document.addEventListener('DOMContentLoaded', function() {
  const master = document.getElementById('selectAll');
  if (master) {
    master.addEventListener('change', function() {
      toggleSelectAll(this);
    });
  }

  document.querySelectorAll('.item-select').forEach(cb => {
    cb.addEventListener('change', function() {
      updateBatchState();
    });
  });

  updateBatchState();
});

function getSelectedIdsArray() {
  return Array.from(document.querySelectorAll('.item-select:checked')).map(cb => cb.value);
}

function batchPrintPDFCombined() {
  const ids = getSelectedIdsArray();
  if (ids.length === 0) return alert('Pilih minimal satu data penilaian.');
  window.open('cetak_penilaian.php?ids=' + ids.join(',') + '&auto=1', '_blank');
}

function batchPrintPDFSeparate() {
  const ids = getSelectedIdsArray();
  if (ids.length === 0) return alert('Pilih minimal satu data penilaian.');

  if (ids.length > 10) {
    if (!confirm(`Anda memilih ${ids.length} karyawan. Browser akan membuka ${ids.length} tab terpisah. Lanjutkan?`)) return;
  }

  ids.forEach((id, index) => {
    setTimeout(() => {
      window.open(`cetak_penilaian.php?id=${id}&auto=1`, '_blank');
    }, index * 350);
  });
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
