<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$db = db();

if (isset($_GET['export'])&&$_GET['export']==='csv') {
    $periode=trim($_GET['periode']??''); $status=$_GET['status']??''; $bulan=trim($_GET['bulan']??'');
    $id=intval($_GET['id']??0); $idsStr=trim($_GET['ids']??'');
    $where="WHERE 1=1"; $params=[]; $types='';
    if($id){
        $where.=" AND p.id=?"; $params[]=$id; $types.='i';
    } elseif($idsStr){
        $idArr=array_values(array_unique(array_filter(array_map('intval',explode(',',$idsStr)))));
        if($idArr){
            $in=implode(',',$idArr);
            $where.=" AND p.id IN ($in)";
        }
    }
    if($periode){$where.=" AND p.periode LIKE ?";$params[]="%$periode%";$types.='s';}
    if($status){$where.=" AND p.status_perpanjangan=?";$params[]=$status;$types.='s';}
    if($bulan){$where.=" AND (p.tanggal_penilaian LIKE ? OR (p.tanggal_penilaian IS NULL AND p.created_at LIKE ?))";$params[]="$bulan%";$params[]="$bulan%";$types.='ss';}
    $sql="SELECT k.nik,k.nama,k.jabatan,k.atasan_langsung,p.periode,p.sikap_nilai,p.kerjasama_nilai,p.inisiatif_nilai,p.ketelitian_nilai,p.ketepatan_nilai,p.pengetahuan_nilai,p.kualitas_nilai,p.kuantitas_nilai,p.belajar_nilai,p.tjawab_nilai,p.jumlah_nilai,p.status_perpanjangan,p.penilaian_keseluruhan,p.penilai_nama,p.tanggal_penilaian,p.created_at FROM penilaian p JOIN karyawan k ON k.id=p.karyawan_id $where ORDER BY p.created_at DESC";
    if($types){$s=$db->prepare($sql);$s->bind_param($types,...$params);$s->execute();$rows=$s->get_result();$s->close();}
    else{$rows=$db->query($sql);}
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="penilaian_pkwt_'.date('Ymd').'.csv"');
    $out=fopen('php://output','w'); fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['NIK','Nama','Jabatan','Atasan','Periode','Sikap','Kerjasama','Inisiatif','Ketelitian','Ketepatan','Pengetahuan','Kualitas','Kuantitas','Belajar','Tg.Jawab','TOTAL','Status','Penilaian Keseluruhan','Penilai','Tgl Penilaian','Disubmit']);
    while($r=$rows->fetch_assoc()) fputcsv($out,[$r['nik'],$r['nama'],$r['jabatan'],$r['atasan_langsung'],$r['periode'],$r['sikap_nilai'],$r['kerjasama_nilai'],$r['inisiatif_nilai'],$r['ketelitian_nilai'],$r['ketepatan_nilai'],$r['pengetahuan_nilai'],$r['kualitas_nilai'],$r['kuantitas_nilai'],$r['belajar_nilai'],$r['tjawab_nilai'],$r['jumlah_nilai'],$r['status_perpanjangan'],$r['penilaian_keseluruhan'],$r['penilai_nama'],$r['tanggal_penilaian'],$r['created_at']]);
    fclose($out); exit;
}

$bulanList=$db->query("SELECT DATE_FORMAT(COALESCE(p.tanggal_penilaian, p.created_at), '%Y-%m') bln, COUNT(*) jml, SUM(p.status_perpanjangan='Diperpanjang') lanjut, SUM(p.status_perpanjangan='Tidak Diperpanjang') tidak, AVG(p.jumlah_nilai) avg_nilai FROM penilaian p GROUP BY bln ORDER BY bln DESC");
$periodeList=$db->query("SELECT periode,COUNT(*) jml,SUM(status_perpanjangan='Diperpanjang') lanjut,SUM(status_perpanjangan='Tidak Diperpanjang') tidak,AVG(jumlah_nilai) avg_nilai FROM penilaian GROUP BY periode ORDER BY periode DESC");
$jabatanList=$db->query("SELECT k.jabatan,COUNT(*) jml,AVG(p.jumlah_nilai) avg,SUM(p.status_perpanjangan='Diperpanjang') lanjut FROM penilaian p JOIN karyawan k ON k.id=p.karyawan_id GROUP BY k.jabatan ORDER BY jml DESC LIMIT 10");
$totalAll=$db->query("SELECT COUNT(*) c FROM penilaian")->fetch_assoc()['c'];

$pageTitle='Laporan & Ekspor'; $activePage='laporan';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:22px;">

  <!-- Export Card -->
  <div class="form-card">
    <div class="form-card-header">
      <?= icon('download') ?> Ekspor Data ke CSV / Excel
    </div>
    <p style="font-size:13px;color:var(--gray-500);margin-bottom:20px;line-height:1.6">Unduh seluruh data penilaian dalam format CSV yang kompatibel dengan Microsoft Excel & Google Sheets.</p>
    <form method="GET" style="display:flex;flex-direction:column;gap:13px;">
      <input type="hidden" name="export" value="csv">
      <div class="form-group">
        <label class="form-label">Filter Bulan (opsional)</label>
        <input type="month" name="bulan" class="form-control" value="<?= e($_GET['bulan']??'') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Filter Periode (opsional)</label>
        <input type="text" name="periode" class="form-control" placeholder="Contoh: 2025-Q1" value="<?= e($_GET['periode']??'') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Filter Status (opsional)</label>
        <select name="status" class="form-control">
          <option value="">Semua Status</option>
          <option value="Diperpanjang" <?= ($_GET['status']??'')==='Diperpanjang'?'selected':'' ?>>Diperpanjang</option>
          <option value="Tidak Diperpanjang" <?= ($_GET['status']??'')==='Tidak Diperpanjang'?'selected':'' ?>>Tidak Diperpanjang</option>
        </select>
      </div>
      <button type="submit" class="btn btn-success btn-block"><?= icon('download') ?> Download CSV (<?= $totalAll ?> data)</button>
    </form>
  </div>

  <!-- Rekap Jabatan -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><?= icon('bar-chart') ?> Rekap per Jabatan</div>
    </div>
    <table class="tbl">
      <thead><tr><th>Jabatan</th><th style="text-align:center">Total</th><th style="text-align:center">Rata-rata</th><th style="text-align:center">Lulus</th></tr></thead>
      <tbody>
        <?php while($r=$jabatanList->fetch_assoc()): ?>
        <tr>
          <td style="font-weight:600;font-size:13px"><?= e($r['jabatan']) ?></td>
          <td style="text-align:center"><span class="badge badge-blue"><?= $r['jml'] ?></span></td>
          <td style="text-align:center;font-weight:700;color:<?= $r['avg']>=28?'var(--green)':'var(--red)' ?>"><?= number_format($r['avg'],1) ?></td>
          <td style="text-align:center"><span class="badge badge-green"><?= $r['lanjut'] ?></span></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

</div>

<!-- Rekap per Bulan -->
<div class="card" style="margin-bottom:22px;">
  <div class="card-header">
    <div class="card-title"><?= icon('calendar') ?> Rekap per Bulan Penilaian</div>
  </div>
  <table class="tbl">
    <thead>
      <tr>
        <th>Bulan</th>
        <th style="text-align:center">Total Dinilai</th>
        <th style="text-align:center">Diperpanjang</th>
        <th style="text-align:center">Tidak Diperpanjang</th>
        <th style="text-align:center">Rata-rata Nilai</th>
        <th style="text-align:center">Tingkat Lulus</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php while($r=$bulanList->fetch_assoc()): $pct=$r['jml']?round($r['lanjut']/$r['jml']*100):0; ?>
      <tr>
        <td><strong><?= e(formatBulan($r['bln'])) ?></strong></td>
        <td style="text-align:center"><span class="badge badge-blue"><?= $r['jml'] ?></span></td>
        <td style="text-align:center"><span class="badge badge-green"><?= $r['lanjut'] ?></span></td>
        <td style="text-align:center"><span class="badge badge-red"><?= $r['tidak'] ?></span></td>
        <td style="text-align:center;font-weight:800;font-size:15px;color:<?= $r['avg_nilai']>=28?'var(--green)':'var(--red)' ?>"><?= number_format($r['avg_nilai'],1) ?></td>
        <td style="text-align:center">
          <div style="display:flex;align-items:center;gap:8px;justify-content:center;">
            <div style="width:60px;background:var(--gray-100);border-radius:99px;height:6px;">
              <div style="height:6px;border-radius:99px;width:<?= $pct ?>%;background:<?= $pct>=80?'var(--green)':($pct>=50?'var(--amber)':'var(--red)') ?>"></div>
            </div>
            <span style="font-size:12px;font-weight:700;color:<?= $pct>=80?'var(--green)':($pct>=50?'var(--amber)':'var(--red)') ?>"><?= $pct ?>%</span>
          </div>
        </td>
        <td>
          <div style="display:flex;gap:5px;">
            <a href="penilaian.php?bulan=<?= urlencode($r['bln']) ?>" class="btn btn-primary btn-xs"><?= icon('eye') ?> Lihat</a>
            <a href="?export=csv&bulan=<?= urlencode($r['bln']) ?>" class="btn btn-outline btn-xs"><?= icon('download') ?> CSV</a>
          </div>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<!-- Rekap per Periode -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><?= icon('clock') ?> Rekap per Periode Penilaian</div>
  </div>
  <table class="tbl">
    <thead>
      <tr>
        <th>Periode</th>
        <th style="text-align:center">Total Dinilai</th>
        <th style="text-align:center">Diperpanjang</th>
        <th style="text-align:center">Tidak Diperpanjang</th>
        <th style="text-align:center">Rata-rata Nilai</th>
        <th style="text-align:center">Tingkat Lulus</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php while($r=$periodeList->fetch_assoc()): $pct=$r['jml']?round($r['lanjut']/$r['jml']*100):0; ?>
      <tr>
        <td><strong><?= e($r['periode']) ?></strong></td>
        <td style="text-align:center"><span class="badge badge-blue"><?= $r['jml'] ?></span></td>
        <td style="text-align:center"><span class="badge badge-green"><?= $r['lanjut'] ?></span></td>
        <td style="text-align:center"><span class="badge badge-red"><?= $r['tidak'] ?></span></td>
        <td style="text-align:center;font-weight:800;font-size:15px;color:<?= $r['avg_nilai']>=28?'var(--green)':'var(--red)' ?>"><?= number_format($r['avg_nilai'],1) ?></td>
        <td style="text-align:center">
          <div style="display:flex;align-items:center;gap:8px;justify-content:center;">
            <div style="width:60px;background:var(--gray-100);border-radius:99px;height:6px;">
              <div style="height:6px;border-radius:99px;width:<?= $pct ?>%;background:<?= $pct>=80?'var(--green)':($pct>=50?'var(--amber)':'var(--red)') ?>"></div>
            </div>
            <span style="font-size:12px;font-weight:700;color:<?= $pct>=80?'var(--green)':($pct>=50?'var(--amber)':'var(--red)') ?>"><?= $pct ?>%</span>
          </div>
        </td>
        <td>
          <div style="display:flex;gap:5px;">
            <a href="penilaian.php?periode=<?= urlencode($r['periode']) ?>" class="btn btn-primary btn-xs"><?= icon('eye') ?> Lihat</a>
            <a href="?export=csv&periode=<?= urlencode($r['periode']) ?>" class="btn btn-outline btn-xs"><?= icon('download') ?> CSV</a>
          </div>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
