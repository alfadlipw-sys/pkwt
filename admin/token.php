<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$db = db(); $msg = $err = $newLink = '';
$newLinks = [];
$preKaryawan = intval($_GET['karyawan'] ?? 0);

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['aksi']??'')==='buat') {
    $kids=array_values(array_unique(array_filter(array_map('intval',$_POST['karyawan_ids']??[]))));
    $periode=trim($_POST['periode']??'');
    $days=intval($_POST['days']??TOKEN_DAYS); $shift=trim($_POST['shift']??'');
    $cuti=trim($_POST['cuti']??''); $ijin=trim($_POST['ijin']??'');
    $periode_ke=trim($_POST['periode_ke']??''); $catatan=trim($_POST['catatan_hrd']??'');
    if ($kids && $periode) {
        $aid=$_SESSION['admin_id']; $exp=date('Y-m-d H:i:s',strtotime("+{$days} days"));
        $created=[]; $skipped=[];
        foreach ($kids as $kid) {
            $kRow=$db->query("SELECT nama FROM karyawan WHERE id=$kid")->fetch_assoc();
            $nama=$kRow['nama']??"ID:$kid";
            $cek=$db->prepare("SELECT id FROM form_tokens WHERE karyawan_id=? AND periode=? AND is_submitted=0 AND expires_at>NOW()");
            $cek->bind_param('is',$kid,$periode); $cek->execute();
            $ex=$cek->get_result()->fetch_assoc(); $cek->close();
            if ($ex) { $skipped[]=$nama; continue; }
            $tok=generateToken();
            $s=$db->prepare("INSERT INTO form_tokens (karyawan_id,token,periode,shift,cuti,ijin,periode_ke,catatan_hrd,created_by,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $s->bind_param('isssssssss',$kid,$tok,$periode,$shift,$cuti,$ijin,$periode_ke,$catatan,$aid,$exp);
            if ($s->execute()) {
                $created[]=['nama'=>$nama,'link'=>APP_URL.'/form.php?token='.$tok];
                auditLog($aid,'BUAT_TOKEN',"Karyawan: $nama | Periode: $periode");
            } else { $skipped[]="$nama (gagal)"; }
            $s->close();
        }
        if (count($created)===1 && !$skipped) {
            $newLink=$created[0]['link'];
            $msg="Link penilaian berhasil dibuat!";
        } elseif ($created) {
            $newLinks=$created;
            $msg=count($created)." link penilaian berhasil dibuat.".($skipped?" (".count($skipped)." dilewati karena sudah ada link aktif: ".e(implode(', ',$skipped)).")":"");
        } else {
            $err="Tidak ada link yang dibuat.".($skipped?" Semua karyawan sudah punya link aktif untuk periode ini: ".e(implode(', ',$skipped)):"");
        }
    } else { $err="Pilih minimal satu karyawan dan isi periode."; }
}
if (isset($_GET['hapus'])&&is_numeric($_GET['hapus'])) {
    $id=intval($_GET['hapus']); $s=$db->prepare("DELETE FROM form_tokens WHERE id=? AND is_submitted=0");
    $s->bind_param('i',$id); $s->execute(); $s->close();
    $msg="Token berhasil dihapus."; auditLog($_SESSION['admin_id'],'HAPUS_TOKEN',"ID:$id");
}
$filter=intval($_GET['filter_k']??0); $where=$filter?"WHERE ft.karyawan_id=$filter":"";
$tokens=$db->query("SELECT ft.*,k.nama,k.jabatan,k.nik,k.atasan_langsung FROM form_tokens ft JOIN karyawan k ON k.id=ft.karyawan_id $where ORDER BY ft.created_at DESC LIMIT 60");
$listKaryawan=$db->query("SELECT id,nik,nama,jabatan FROM karyawan WHERE is_active=1 ORDER BY nama");
$pageTitle='Buat Link Penilaian'; $activePage='token';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<?php if($msg): ?><div class="alert alert-success"><?= icon('check-circle') ?> <?= $msg ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-error"><?= icon('alert-triangle') ?> <?= $err ?></div><?php endif; ?>

<?php if($newLink):
  $waTextSingle="Yth. Bapak/Ibu,\n\nBerikut link Form Penilaian Kinerja PKWT.\n\nLink:\n{$newLink}\n\nSilakan isi sebelum kadaluarsa.\n\nTerima kasih,\nHRD Indopelita";
  $wa='https://wa.me/?text='.rawurlencode($waTextSingle);
?>
<div style="background:var(--blue-pale);border:1px solid #bfdbfe;border-radius:var(--radius-lg);padding:14px 18px;margin-bottom:16px;">
  <div style="font-size:13.5px;font-weight:700;color:var(--blue);margin-bottom:4px;display:flex;align-items:center;gap:6px;">
    <?= icon('link') ?> Link Penilaian Berhasil Dibuat!
  </div>
  <div style="font-size:12px;color:var(--gray-500);margin-bottom:10px;">Salin dan kirim ke penilai (atasan). Link hanya bisa digunakan <strong>satu kali</strong> dan akan kadaluarsa sesuai jadwal.</div>
  <div class="link-box"><?= e($newLink) ?></div>
  <div style="display:flex;gap:6px;margin-top:10px;">
    <button onclick="copyLink('<?= e($newLink) ?>')" class="btn btn-navy btn-sm"><?= icon('copy') ?> Salin Link</button>
    <a href="<?= e($wa) ?>" target="_blank" class="btn btn-success btn-sm"><?= icon('message') ?> Kirim WhatsApp</a>
  </div>
</div>
<?php endif; ?>

<?php if(!empty($newLinks)): ?>
<div style="background:var(--blue-pale);border:1px solid #bfdbfe;border-radius:var(--radius-lg);padding:14px 18px;margin-bottom:16px;">
  <div style="font-size:13.5px;font-weight:700;color:var(--blue);margin-bottom:4px;display:flex;align-items:center;gap:6px;">
    <?= icon('link') ?> <?= count($newLinks) ?> Link Penilaian Berhasil Dibuat!
  </div>
  <div style="font-size:12px;color:var(--gray-500);margin-bottom:10px;">Salin dan kirim ke masing-masing penilai (atasan). Setiap link hanya bisa digunakan satu kali.</div>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Karyawan</th><th>Link</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php foreach($newLinks as $ni=>$nl):
          $waText3="Yth. Bapak/Ibu,\n\nBerikut link Form Penilaian Kinerja PKWT untuk {$nl['nama']}.\n\nLink:\n{$nl['link']}\n\nSilakan isi sebelum kadaluarsa.\n\nTerima kasih,\nHRD Indopelita";
          $wa3='https://wa.me/?text='.rawurlencode($waText3);
        ?>
        <tr>
          <td style="font-weight:600"><?= e($nl['nama']) ?></td>
          <td><div class="link-box" style="font-size:11px"><?= e($nl['link']) ?></div></td>
          <td>
            <div style="display:flex;gap:4px;">
              <button onclick="copyLink('<?= e($nl['link']) ?>')" class="btn btn-navy btn-xs" title="Salin"><?= icon('copy') ?></button>
              <a href="<?= e($wa3) ?>" target="_blank" class="btn btn-success btn-xs" title="WhatsApp"><?= icon('message') ?></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:300px 1fr;gap:16px;align-items:flex-start;">
  <div class="form-card">
    <div class="form-card-header"><?= icon('link') ?> Buat Link Penilaian Baru</div>
    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:var(--radius-sm);padding:8px 12px;font-size:11.5px;color:#92400e;margin-bottom:14px;display:flex;align-items:flex-start;gap:6px;">
      <?= icon('info') ?>
      <div>Link bersifat <strong>unik & rahasia</strong> — hanya bisa diisi satu kali.</div>
    </div>
    <form method="POST">
      <input type="hidden" name="aksi" value="buat">
      <div class="form-grid" style="gap:12px;">
        <div class="form-group">
          <label class="form-label">Karyawan <span class="req">*</span> <span style="font-weight:400;color:var(--gray-400);">(bisa pilih lebih dari satu)</span></label>
          <input type="text" id="karyawanFilter" class="form-control" placeholder="Cari nama/NIK..." style="margin-bottom:6px;">
          <div style="display:flex;gap:6px;margin-bottom:6px;">
            <button type="button" class="btn btn-light btn-xs" onclick="toggleAllKaryawan(true)">Pilih Semua</button>
            <button type="button" class="btn btn-light btn-xs" onclick="toggleAllKaryawan(false)">Batal Semua</button>
          </div>
          <div id="karyawanList" style="max-height:220px;overflow-y:auto;border:1px solid var(--gray-200);border-radius:var(--radius);padding:6px;">
            <?php while($k=$listKaryawan->fetch_assoc()): ?>
            <label class="karyawan-item" data-search="<?= strtolower(e($k['nik'].' '.$k['nama'])) ?>" style="display:flex;align-items:center;gap:8px;padding:5px 4px;font-size:13px;cursor:pointer;">
              <input type="checkbox" name="karyawan_ids[]" value="<?= $k['id'] ?>" <?= $preKaryawan===$k['id']?'checked':'' ?>>
              <span><?= e($k['nik']) ?> – <?= e($k['nama']) ?></span>
            </label>
            <?php endwhile; ?>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Periode <span class="req">*</span></label>
          <input type="text" name="periode" class="form-control" placeholder="2025-Q2" required>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <div class="form-group"><label class="form-label">Shift</label><input type="text" name="shift" class="form-control" placeholder="I/II/III"></div>
          <div class="form-group"><label class="form-label">Cuti</label><input type="text" name="cuti" class="form-control" placeholder="hari"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <div class="form-group"><label class="form-label">Ijin (%)</label><input type="text" name="ijin" class="form-control" placeholder="0%"></div>
          <div class="form-group"><label class="form-label">Periode Ke-</label><input type="text" name="periode_ke" class="form-control" placeholder="I/II/III"></div>
        </div>
        <div class="form-group"><label class="form-label">Catatan HRD</label><input type="text" name="catatan_hrd" class="form-control" placeholder="Opsional"></div>
        <div class="form-group">
          <label class="form-label">Aktif Selama</label>
          <select name="days" class="form-control">
            <option value="3">3 hari</option><option value="7" selected>7 hari</option>
            <option value="14">14 hari</option><option value="30">30 hari</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block"><?= icon('link') ?> Generate Link</button>
      </div>
    </form>
  </div>
  <style>.karyawan-item:hover{background:var(--gray-100);}</style>
  <script>
    document.getElementById('karyawanFilter').addEventListener('input', function(){
      const q = this.value.trim().toLowerCase();
      document.querySelectorAll('.karyawan-item').forEach(el => {
        el.style.display = el.dataset.search.includes(q) ? 'flex' : 'none';
      });
    });
    function toggleAllKaryawan(check) {
      document.querySelectorAll('.karyawan-item').forEach(el => {
        if (el.style.display !== 'none') el.querySelector('input[type=checkbox]').checked = check;
      });
    }
  </script>

  <div class="card">
    <div class="card-header">
      <div class="card-title"><?= icon('clipboard') ?> Semua Link Penilaian</div>
      <?php if($filter): ?><a href="token.php" class="btn btn-light btn-sm">Lihat Semua</a><?php endif; ?>
    </div>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Karyawan</th><th>Periode</th><th>Dibuat</th><th>Atasan</th><th>Kadaluarsa</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php while($r=$tokens->fetch_assoc()):
            $link=APP_URL.'/form.php?token='.$r['token'];
            $isExp=!$r['is_submitted']&&strtotime($r['expires_at'])<time();
            $waText2="Yth. Bapak/Ibu,\n\nMohon isi Form Penilaian Kinerja karyawan *{$r['nama']}* periode *{$r['periode']}*.\n\nLink:\n{$link}\n\nAktif hingga: ".date('d/m/Y H:i',strtotime($r['expires_at']))."\n\nTerima kasih,\nHRD Indopelita";
            $wa2='https://wa.me/?text='.rawurlencode($waText2);
          ?>
          <tr>
            <td><div style="font-weight:600"><?= e($r['nama']) ?></div><div style="font-size:11px;color:var(--gray-400)"><?= e($r['nik']) ?> · <?= e($r['jabatan']) ?></div></td>
            <td style="font-size:13px"><?= e($r['periode']) ?></td>
            <td style="font-size:12px;color:var(--gray-500)"><?= date('d/m/Y H:i',strtotime($r['created_at'])) ?></td>
            <td style="font-size:12px;color:var(--gray-600)"><?= e($r['atasan_langsung'] ?: '-') ?></td>
            <td style="font-size:12px;color:<?= $isExp?'var(--red)':'var(--gray-500)' ?>">
              <?= date('d/m/Y',strtotime($r['expires_at'])) ?>
              <?php if($isExp): ?><br><span class="badge badge-red" style="font-size:10px"><span class="badge-dot"></span> Kadaluarsa</span><?php endif; ?>
            </td>
            <td>
              <?php if($r['is_submitted']): ?>
                <span class="badge badge-green"><span class="badge-dot"></span> Sudah Diisi</span>
                <?php if($r['submitted_at']): ?><div style="font-size:10px;color:var(--gray-400);margin-top:2px"><?= date('d/m/Y H:i',strtotime($r['submitted_at'])) ?></div><?php endif; ?>
              <?php elseif($isExp): ?><span class="badge badge-red"><span class="badge-dot"></span> Kadaluarsa</span>
              <?php else: ?><span class="badge badge-amber"><span class="badge-dot"></span> Menunggu</span><?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:4px;flex-wrap:wrap;">
                <?php if(!$r['is_submitted']&&!$isExp): ?>
                  <button onclick="copyLink('<?= e($link) ?>')" class="btn btn-light btn-xs" title="Salin Link"><?= icon('copy') ?></button>
                  <a href="<?= e($wa2) ?>" target="_blank" class="btn btn-success btn-xs" title="Kirim WhatsApp"><?= icon('message') ?></a>
                <?php endif; ?>
                <?php if($r['is_submitted']): ?><a href="detail_penilaian.php?id=<?= $r['id'] ?>" class="btn btn-primary btn-xs" title="Lihat Detail"><?= icon('eye') ?></a><?php endif; ?>
                <?php if(!$r['is_submitted']): ?><a href="?hapus=<?= $r['id'] ?>" class="btn btn-danger btn-xs" title="Hapus" onclick="return confirm('Hapus link ini?')"><?= icon('trash') ?></a><?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
