<?php
require_once __DIR__ . '/../config.php';
requireAdmin();

$db = db();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['aksi']??'')==='tambah') {
    $nik=trim($_POST['nik']??''); $nama=trim($_POST['nama']??'');
    $jabatan=trim($_POST['jabatan']??''); $penempatan=trim($_POST['penempatan']??'');
    $atasan=trim($_POST['atasan']??''); $mulai=$_POST['mulai_bekerja']??'';
    if ($nik && $nama && $jabatan && $penempatan) {
        $stmt=$db->prepare("INSERT INTO karyawan (nik,nama,jabatan,penempatan,atasan_langsung,mulai_bekerja) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('ssssss',$nik,$nama,$jabatan,$penempatan,$atasan,$mulai);
        if($stmt->execute()){auditLog($_SESSION['admin_id'],'TAMBAH_KARYAWAN',"$nik – $nama");$msg="Karyawan <strong>$nama</strong> berhasil ditambahkan.";}
        else{$err="NIK sudah terdaftar atau terjadi kesalahan.";}
        $stmt->close();
    } else {$err="NIK, Nama, Jabatan, dan Penempatan wajib diisi.";}
}
if (isset($_GET['hapus'])&&is_numeric($_GET['hapus'])){
    $id=intval($_GET['hapus']); $s=$db->prepare("UPDATE karyawan SET is_active=0 WHERE id=?"); $s->bind_param('i',$id); $s->execute(); $s->close();
    $msg="Karyawan berhasil dinonaktifkan."; auditLog($_SESSION['admin_id'],'NONAKTIF',"ID:$id");
}
if (isset($_GET['aktif'])&&is_numeric($_GET['aktif'])){
    $id=intval($_GET['aktif']); $s=$db->prepare("UPDATE karyawan SET is_active=1 WHERE id=?"); $s->bind_param('i',$id); $s->execute(); $s->close();
    $msg="Karyawan berhasil diaktifkan.";
}

$search=trim($_GET['q']??''); $limit=15; $page=max(1,intval($_GET['page']??1)); $offset=($page-1)*$limit;
if($search){
    $like="%$search%";
    $cntStmt=$db->prepare("SELECT COUNT(*) c FROM karyawan k WHERE (k.nik LIKE ? OR k.nama LIKE ? OR k.jabatan LIKE ? OR k.penempatan LIKE ?)");
    $cntStmt->bind_param('ssss',$like,$like,$like,$like); $cntStmt->execute();
    $total=$cntStmt->get_result()->fetch_assoc()['c']; $cntStmt->close();
    $stmt=$db->prepare("SELECT k.*,(SELECT COUNT(*) FROM penilaian p WHERE p.karyawan_id=k.id) AS jml_penilaian FROM karyawan k WHERE (k.nik LIKE ? OR k.nama LIKE ? OR k.jabatan LIKE ? OR k.penempatan LIKE ?) ORDER BY k.nama LIMIT ? OFFSET ?");
    $stmt->bind_param('ssssii',$like,$like,$like,$like,$limit,$offset);
} else {
    $total=$db->query("SELECT COUNT(*) c FROM karyawan")->fetch_assoc()['c'];
    $stmt=$db->prepare("SELECT k.*,(SELECT COUNT(*) FROM penilaian p WHERE p.karyawan_id=k.id) AS jml_penilaian FROM karyawan k ORDER BY k.nama LIMIT ? OFFSET ?");
    $stmt->bind_param('ii',$limit,$offset);
}
$stmt->execute(); $karyawan=$stmt->get_result(); $stmt->close();
$totalPage=ceil($total/$limit);

// ====== Google Apps Script URL (fetch dilakukan dari browser, bukan server) ======
// Browser pengguna yang langsung fetch ke Google — hosting tidak perlu akses Google.
$SHEET_API_URL = 'https://script.google.com/macros/s/AKfycbzWMequ9kdl2yCbHHj4wHEMvY09n5xg-GyFc0weffHS7zWRvlAeScabtBvdfXsQUFs09w/exec';

$pageTitle='Data Karyawan'; $activePage='karyawan';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if($msg): ?><div class="alert alert-success"><span class="alert-icon">✅</span><?= $msg ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-error"><span class="alert-icon">⚠️</span><?= e($err) ?></div><?php endif; ?>

<div style="display:flex;gap:22px;align-items:flex-start;">

  <!-- Form Tambah -->
  <div class="form-card" style="width:300px;flex-shrink:0;">
    <div class="form-card-header">➕ Tambah Karyawan</div>
    <form method="POST">
      <input type="hidden" name="aksi" value="tambah">
      <div class="form-grid" style="gap:14px;">

        <!-- Cari Spreadsheet: fetch dilakukan JS client-side, tidak lewat server -->
        <style>.sheet-suggestion-item:hover{background:var(--gray-100);}</style>
        <div class="form-group" style="position:relative;">
          <label class="form-label">📋 Cari dari Spreadsheet</label>
          <div id="sheetLoadingInfo" style="font-size:12px;color:var(--gray-400);margin-bottom:4px;">⏳ Memuat data...</div>
          <input type="text" id="sheetSearch" class="form-control" placeholder="Ketik NIK atau nama..." autocomplete="off" disabled>
          <div id="sheetSuggestions" style="display:none;position:absolute;z-index:10;top:100%;left:0;right:0;background:#fff;border:1px solid var(--gray-200);border-radius:6px;max-height:220px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.12);"></div>
          <small style="color:var(--gray-400);">Ketik untuk mencari, lalu klik hasil untuk mengisi otomatis.</small>
        </div>

        <div class="form-group">
          <label class="form-label">NIK / No. Pegawai <span class="req">*</span></label>
          <input type="text" id="f_nik" name="nik" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Nama Lengkap <span class="req">*</span></label>
          <input type="text" id="f_nama" name="nama" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Jabatan <span class="req">*</span></label>
          <input type="text" id="f_jabatan" name="jabatan" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Penempatan <span class="req">*</span></label>
          <input type="text" id="f_penempatan" name="penempatan" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Atasan Langsung</label>
          <input type="text" id="f_atasan" name="atasan" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Mulai Bekerja</label>
          <input type="text" id="f_mulai" name="mulai_bekerja" class="form-control" placeholder="YYYY-MM-DD">
        </div>
        <button type="submit" class="btn btn-primary btn-block">💾 Simpan Karyawan</button>
      </div>
    </form>
  </div>

  <!-- ===== Client-Side Fetch dari Google Apps Script ===== -->
  <script>
  (function() {
    const SHEET_URL = '<?= $SHEET_API_URL ?>';
    const sheetSearch      = document.getElementById('sheetSearch');
    const sheetSuggestions = document.getElementById('sheetSuggestions');
    const sheetLoadingInfo = document.getElementById('sheetLoadingInfo');
    let SHEET_DATA = [];

    function escapeHtml(s) {
      return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    function fillForm(d) {
      document.getElementById('f_nik').value        = d.nik || '';
      document.getElementById('f_nama').value       = d.nama || '';
      document.getElementById('f_jabatan').value    = d.jabatan || '';
      document.getElementById('f_penempatan').value = d.penempatan || '';
      document.getElementById('f_atasan').value     = d.atasan || '';
      document.getElementById('f_mulai').value      = d.mulai_bekerja || '';
    }

    function renderSuggestions(q) {
      if (!q) { sheetSuggestions.style.display = 'none'; sheetSuggestions.innerHTML = ''; return; }
      const matches = SHEET_DATA
        .map((sd, i) => ({ i, sd }))
        .filter(({ sd }) => (sd.nama||'').toLowerCase().includes(q) || (sd.nik||'').toLowerCase().includes(q))
        .slice(0, 20);
      if (!matches.length) {
        sheetSuggestions.innerHTML = '<div style="padding:8px 12px;color:var(--gray-400);font-size:13px;">Tidak ditemukan</div>';
        sheetSuggestions.style.display = 'block';
        return;
      }
      sheetSuggestions.innerHTML = matches.map(({ i, sd }) =>
        `<div class="sheet-suggestion-item" data-idx="${i}" style="padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--gray-100);">
          <strong>${escapeHtml(sd.nik||'')}</strong> — ${escapeHtml(sd.nama||'')}
         </div>`
      ).join('');
      sheetSuggestions.style.display = 'block';
    }

    sheetSearch.addEventListener('input', function() {
      renderSuggestions(this.value.trim().toLowerCase());
    });

    sheetSuggestions.addEventListener('click', function(e) {
      const item = e.target.closest('.sheet-suggestion-item');
      if (!item || item.dataset.idx === undefined) return;
      const d = SHEET_DATA[item.dataset.idx];
      if (!d) return;
      fillForm(d);
      sheetSearch.value = (d.nik || '') + ' — ' + (d.nama || '');
      sheetSuggestions.style.display = 'none';
    });

    document.addEventListener('click', function(e) {
      if (!e.target.closest('#sheetSearch') && !e.target.closest('#sheetSuggestions')) {
        sheetSuggestions.style.display = 'none';
      }
    });

    // ─── Fetch data dari Google Apps Script (browser → Google, bukan server) ───
    fetch(SHEET_URL)
      .then(function(res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function(data) {
        if (data.status === 'ok' && Array.isArray(data.data)) {
          SHEET_DATA = data.data;
          sheetLoadingInfo.textContent = '✅ ' + SHEET_DATA.length + ' data dimuat dari spreadsheet.';
          sheetLoadingInfo.style.color = 'var(--success, #16a34a)';
          sheetSearch.disabled = false;
          sheetSearch.focus();
        } else {
          throw new Error('Format respons tidak valid');
        }
      })
      .catch(function(err) {
        sheetLoadingInfo.innerHTML = '⚠️ Gagal memuat spreadsheet. <small>(' + err.message + ')</small> Form tetap bisa diisi manual.';
        sheetLoadingInfo.style.color = 'var(--warning, #d97706)';
        sheetSearch.placeholder = 'Isi manual di bawah...';
      });
  })();
  </script>

  <!-- Tabel -->
  <div style="flex:1;min-width:0;">
    <div class="card">
      <div class="card-header">
        <div class="card-title">👥 Daftar Karyawan PKWT (<?= $total ?>)</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <form method="GET" style="display:flex;gap:6px;">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari nama/NIK..." class="form-control" style="width:200px;padding:7px 12px;">
            <button type="submit" class="btn btn-primary btn-sm">Cari</button>
            <?php if($search): ?><a href="?" class="btn btn-light btn-sm">Reset</a><?php endif; ?>
          </form>
          <a href="import_karyawan.php" class="btn btn-success btn-sm">⬆️ Import CSV</a>
        </div>
      </div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr><th>#</th><th>NIK</th><th>Nama</th><th>Jabatan</th><th>Penempatan</th><th>Atasan</th><th>Mulai Kerja</th><th>Penilaian</th><th>Status</th><th>Aksi</th></tr>
          </thead>
          <tbody>
            <?php $no=$offset+1; while($r=$karyawan->fetch_assoc()): ?>
            <tr>
              <td style="color:var(--gray-400);font-size:12px"><?= $no++ ?></td>
              <td><code style="background:var(--gray-100);padding:2px 7px;border-radius:4px;font-size:12px"><?= e($r['nik']) ?></code></td>
              <td><div style="font-weight:600"><?= e($r['nama']) ?></div></td>
              <td style="color:var(--gray-600);font-size:13px"><?= e($r['jabatan']) ?></td>
              <td style="color:var(--gray-600);font-size:13px"><?= e($r['penempatan']??'-') ?></td>
              <td style="color:var(--gray-500);font-size:12px"><?= e($r['atasan_langsung']??'-') ?></td>
              <td style="font-size:12px;color:var(--gray-500)"><?= $r['mulai_bekerja']?formatTanggal($r['mulai_bekerja']):'-' ?></td>
              <td style="text-align:center">
                <?php if($r['jml_penilaian']>0): ?>
                  <a href="penilaian.php?karyawan=<?= $r['id'] ?>" class="badge badge-blue"><?= $r['jml_penilaian'] ?> form</a>
                <?php else: ?>
                  <span class="badge badge-gray">0</span>
                <?php endif; ?>
              </td>
              <td><?= $r['is_active'] ? '<span class="badge badge-green">Aktif</span>' : '<span class="badge badge-red">Nonaktif</span>' ?></td>
              <td>
                <div style="display:flex;gap:4px;">
                  <a href="edit_karyawan.php?id=<?= $r['id'] ?>" class="btn btn-light btn-xs">✏️</a>
                  <a href="token.php?karyawan=<?= $r['id'] ?>" class="btn btn-primary btn-xs" title="Buat Link">🔗</a>
                  <?php if($r['is_active']): ?>
                    <a href="?hapus=<?= $r['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Nonaktifkan karyawan ini?')">✕</a>
                  <?php else: ?>
                    <a href="?aktif=<?= $r['id'] ?>" class="btn btn-success btn-xs">✓</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php if($total===0): ?>
            <tr><td colspan="10" style="text-align:center;padding:40px;color:var(--gray-400)">Tidak ada data karyawan</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if($totalPage>1): ?>
      <div class="pagination">
        <?php for($i=1;$i<=$totalPage;$i++): ?>
          <?php if($i===$page): ?><span class="active"><?=$i?></span>
          <?php else: ?><a href="?page=<?=$i?>&q=<?=e($search)?>"><?=$i?></a><?php endif; ?>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>