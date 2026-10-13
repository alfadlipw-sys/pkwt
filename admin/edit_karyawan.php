<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$db = db(); $id = intval($_GET['id'] ?? 0); $msg = $err = '';
if (!$id) redirect(APP_URL . '/admin/karyawan.php');

$stmt=$db->prepare("SELECT * FROM karyawan WHERE id=?"); $stmt->bind_param('i',$id); $stmt->execute();
$kar=$stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$kar) redirect(APP_URL . '/admin/karyawan.php');

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $nik=trim($_POST['nik']??''); $nama=trim($_POST['nama']??''); $jabatan=trim($_POST['jabatan']??'');
    $atasan=trim($_POST['atasan']??''); $mulai=$_POST['mulai_bekerja']??'';
    if ($nik&&$nama&&$jabatan) {
        $cek=$db->prepare("SELECT id FROM karyawan WHERE nik=? AND id!=?"); $cek->bind_param('si',$nik,$id); $cek->execute();
        $dup=$cek->get_result()->fetch_assoc(); $cek->close();
        if ($dup) { $err="NIK <strong>$nik</strong> sudah digunakan karyawan lain."; }
        else {
            $upd=$db->prepare("UPDATE karyawan SET nik=?,nama=?,jabatan=?,atasan_langsung=?,mulai_bekerja=? WHERE id=?");
            $upd->bind_param('sssssi',$nik,$nama,$jabatan,$atasan,$mulai,$id);
            if ($upd->execute()) {
                auditLog($_SESSION['admin_id'],'EDIT_KARYAWAN',"ID:$id NIK:$nik Nama:$nama");
                $msg="Data karyawan berhasil diperbarui.";
                $kar=array_merge($kar,['nik'=>$nik,'nama'=>$nama,'jabatan'=>$jabatan,'atasan_langsung'=>$atasan,'mulai_bekerja'=>$mulai]);
            } else { $err="Gagal memperbarui data."; }
            $upd->close();
        }
    } else { $err="NIK, Nama, dan Jabatan wajib diisi."; }
}

$riwayat=$db->query("SELECT p.*,ft.periode FROM penilaian p JOIN form_tokens ft ON ft.id=p.token_id WHERE p.karyawan_id=$id ORDER BY p.created_at DESC");
$tokenAktif=$db->query("SELECT * FROM form_tokens WHERE karyawan_id=$id AND is_submitted=0 AND expires_at>NOW() ORDER BY created_at DESC");
$statRow=$db->query("SELECT SUM(status_perpanjangan='Diperpanjang') lanjut,SUM(status_perpanjangan='Tidak Diperpanjang') tidak,AVG(jumlah_nilai) avg FROM penilaian WHERE karyawan_id=$id")->fetch_assoc();

$pageTitle='Edit Karyawan: '.e($kar['nama']); $activePage='karyawan';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div style="margin-bottom:16px;">
  <div class="breadcrumb"><a href="karyawan.php">Data Karyawan</a><span class="sep">›</span><span><?= e($kar['nama']) ?></span></div>
</div>
<?php if($msg): ?><div class="alert alert-success"><span class="alert-icon">✅</span> <?= $msg ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-error"><span class="alert-icon">⚠️</span> <?= $err ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:320px 1fr;gap:22px;align-items:flex-start;">

  <div style="display:flex;flex-direction:column;gap:16px;">
    <div class="form-card">
      <div class="form-card-header">✏️ Edit Data Karyawan</div>
      <form method="POST">
        <div class="form-grid" style="gap:13px;">
          <?php foreach([['nik','NIK / No. Pegawai','text',true],['nama','Nama Lengkap','text',true],['jabatan','Jabatan','text',true],['atasan','Atasan Langsung','text',false]] as [$n,$l,$t,$req]): ?>
          <div class="form-group">
            <label class="form-label"><?= $l ?><?= $req?' <span class="req">*</span>':'' ?></label>
            <input type="<?= $t ?>" name="<?= $n ?>" class="form-control" value="<?= e($kar[$n==='atasan'?'atasan_langsung':$n]??'') ?>" <?= $req?'required':'' ?>>
          </div>
          <?php endforeach; ?>
          <div class="form-group">
            <label class="form-label">Mulai Bekerja</label>
            <input type="date" name="mulai_bekerja" class="form-control" value="<?= e($kar['mulai_bekerja']??'') ?>">
          </div>
          <div style="display:flex;gap:8px;">
            <button type="submit" class="btn btn-primary" style="flex:1">💾 Simpan</button>
            <a href="karyawan.php" class="btn btn-light">Batal</a>
          </div>
        </div>
      </form>
      <div class="divider"></div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <span style="font-size:13px;font-weight:600">Status</span>
        <?= $kar['is_active']?'<span class="badge badge-green">● Aktif</span>':'<span class="badge badge-red">● Nonaktif</span>' ?>
      </div>
      <?php if($kar['is_active']): ?>
        <a href="karyawan.php?hapus=<?= $id ?>" class="btn btn-danger btn-sm btn-block" onclick="return confirm('Nonaktifkan karyawan ini?')">Nonaktifkan</a>
      <?php else: ?>
        <a href="karyawan.php?aktif=<?= $id ?>" class="btn btn-success btn-sm btn-block">Aktifkan</a>
      <?php endif; ?>
    </div>

    <a href="token.php?karyawan=<?= $id ?>" class="btn btn-primary btn-block">🔗 Buat Link Penilaian</a>
  </div>

  <div style="display:flex;flex-direction:column;gap:18px;">
    <!-- Stat cards -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
      <?php foreach([
        [$riwayat->num_rows,'Total Penilaian','var(--blue)'],
        [$statRow['lanjut']??0,'Diperpanjang','var(--green)'],
        [number_format($statRow['avg']??0,1),'Rata-rata Nilai','var(--amber)'],
      ] as [$v,$l,$c]): ?>
      <div style="background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-lg);padding:16px;text-align:center;">
        <div style="font-size:28px;font-weight:900;color:<?= $c ?>"><?= $v ?></div>
        <div style="font-size:12px;color:var(--gray-500);margin-top:3px"><?= $l ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Token aktif -->
    <?php if($tokenAktif->num_rows>0): ?>
    <div class="card">
      <div class="card-header"><div class="card-title"><div class="card-title-icon">⏳</div>Link Aktif Belum Diisi</div></div>
      <table class="tbl">
        <thead><tr><th>Periode</th><th>Kadaluarsa</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php while($t=$tokenAktif->fetch_assoc()): $lnk=APP_URL.'/form.php?token='.$t['token']; ?>
          <tr>
            <td><?= e($t['periode']) ?></td>
            <td style="font-size:12px;color:var(--gray-500)"><?= date('d/m/Y H:i',strtotime($t['expires_at'])) ?></td>
            <td><button onclick="copyLink('<?= e($lnk) ?>')" class="btn btn-light btn-xs">🔗 Salin</button></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Riwayat -->
    <div class="card">
      <div class="card-header"><div class="card-title"><div class="card-title-icon">📋</div>Riwayat Penilaian</div></div>
      <?php $riwayat->data_seek(0); ?>
      <table class="tbl">
        <thead><tr><th>Periode</th><th style="text-align:center">Nilai</th><th>Status</th><th>Penilai</th><th>Tanggal</th><th></th></tr></thead>
        <tbody>
          <?php while($p=$riwayat->fetch_assoc()): ?>
          <tr>
            <td><?= e($p['periode']) ?></td>
            <td style="text-align:center"><span style="font-size:20px;font-weight:800;color:<?= $p['jumlah_nilai']>=28?'var(--green)':'var(--red)' ?>"><?= $p['jumlah_nilai'] ?></span></td>
            <td><?= $p['status_perpanjangan']==='Diperpanjang'?'<span class="badge badge-green">✅</span>':'<span class="badge badge-red">❌</span>' ?></td>
            <td style="font-size:12px"><?= e($p['penilai_nama']) ?></td>
            <td style="font-size:12px;color:var(--gray-500)"><?= formatTanggal($p['tanggal_penilaian']) ?></td>
            <td><a href="detail_penilaian.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-xs">👁</a></td>
          </tr>
          <?php endwhile; ?>
          <?php if($riwayat->num_rows===0): ?><tr><td colspan="6" style="text-align:center;padding:28px;color:var(--gray-400)">Belum ada riwayat penilaian</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
