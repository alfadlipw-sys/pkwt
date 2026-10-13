<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$db = db(); $msg = $err = '';

if ($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['aksi']??'')==='tambah') {
    $username=trim($_POST['username']??''); $nama=trim($_POST['nama']??'');
    $email=trim($_POST['email']??''); $pass=$_POST['password']??''; $conf=$_POST['confirm']??'';
    if (!$username||!$nama||!$pass){$err="Username, nama, dan password wajib diisi.";}
    elseif(strlen($pass)<8){$err="Password minimal 8 karakter.";}
    elseif($pass!==$conf){$err="Konfirmasi password tidak cocok.";}
    else {
        $hash=password_hash($pass,PASSWORD_BCRYPT,['cost'=>10]);
        $s=$db->prepare("INSERT INTO hr_admin (username,password_hash,nama_lengkap,email) VALUES (?,?,?,?)");
        $s->bind_param('ssss',$username,$hash,$nama,$email);
        if($s->execute()){auditLog($_SESSION['admin_id'],'TAMBAH_ADMIN',"Username: $username");$msg="Admin <strong>$nama</strong> berhasil ditambahkan.";}
        else{$err="Username sudah digunakan.";}
        $s->close();
    }
}
if(isset($_GET['toggle'])&&is_numeric($_GET['toggle'])){
    $tid=intval($_GET['toggle']);
    if($tid===$_SESSION['admin_id']){$err="Tidak bisa menonaktifkan akun sendiri.";}
    else{$db->query("UPDATE hr_admin SET is_active=NOT is_active WHERE id=$tid");$msg="Status admin berhasil diubah.";auditLog($_SESSION['admin_id'],'TOGGLE_ADMIN',"ID:$tid");}
}
if(isset($_GET['hapus'])&&is_numeric($_GET['hapus'])){
    $hid=intval($_GET['hapus']);
    if($hid===$_SESSION['admin_id']){$err="Tidak bisa menghapus akun sendiri.";}
    else{$s=$db->prepare("DELETE FROM hr_admin WHERE id=?");$s->bind_param('i',$hid);$s->execute();$s->close();auditLog($_SESSION['admin_id'],'HAPUS_ADMIN',"ID:$hid");$msg="Admin berhasil dihapus.";}
}
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['aksi']??'')==='reset'){
    $rid=intval($_POST['admin_id']??0); $pass=$_POST['new_pass']??'';
    if($rid&&strlen($pass)>=8){
        $hash=password_hash($pass,PASSWORD_BCRYPT,['cost'=>10]);
        $s=$db->prepare("UPDATE hr_admin SET password_hash=? WHERE id=?");$s->bind_param('si',$hash,$rid);$s->execute();$s->close();
        auditLog($_SESSION['admin_id'],'RESET_PASSWORD_ADMIN',"ID:$rid");$msg="Password admin berhasil direset.";
    }else{$err="Password minimal 8 karakter.";}
}
$admins=$db->query("SELECT * FROM hr_admin ORDER BY created_at ASC");
$pageTitle='Manajemen Admin'; $activePage='admin_mgmt';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<?php if($msg): ?><div class="alert alert-success"><?= icon('check-circle') ?> <?= $msg ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-error"><?= icon('alert-triangle') ?> <?= e($err) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:300px 1fr;gap:22px;align-items:flex-start;">

  <div class="form-card">
    <div class="form-card-header"><?= icon('user-cog') ?> Tambah Admin Baru</div>
    <form method="POST">
      <input type="hidden" name="aksi" value="tambah">
      <div class="form-grid" style="gap:12px;">
        <div class="form-group"><label class="form-label">Username <span class="req">*</span></label><input type="text" name="username" class="form-control" required autocomplete="off"></div>
        <div class="form-group"><label class="form-label">Nama Lengkap <span class="req">*</span></label><input type="text" name="nama" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
        <div class="form-group"><label class="form-label">Password <span class="req">*</span></label><input type="password" name="password" class="form-control" required minlength="8"></div>
        <div class="form-group"><label class="form-label">Konfirmasi Password <span class="req">*</span></label><input type="password" name="confirm" class="form-control" required></div>
        <button type="submit" class="btn btn-primary btn-block"><?= icon('plus') ?> Tambah Admin</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title"><?= icon('users') ?> Daftar Admin</div></div>
    <table class="tbl">
      <thead><tr><th>Username</th><th>Nama</th><th>Email</th><th>Login Terakhir</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php while($a=$admins->fetch_assoc()): $isSelf=$a['id']===$_SESSION['admin_id']; ?>
        <tr>
          <td>
            <code style="background:var(--blue-pale);padding:2px 8px;border-radius:5px;font-size:12px;color:var(--blue)"><?= e($a['username']) ?></code>
            <?php if($isSelf): ?><span class="badge badge-blue" style="font-size:10px;margin-left:4px">Anda</span><?php endif; ?>
          </td>
          <td style="font-weight:600"><?= e($a['nama_lengkap']) ?></td>
          <td style="font-size:12px;color:var(--gray-500)"><?= e($a['email']??'-') ?></td>
          <td style="font-size:12px;color:var(--gray-500)"><?= $a['last_login']?date('d/m/Y H:i',strtotime($a['last_login'])):'Belum pernah' ?></td>
          <td><?= $a['is_active']?'<span class="badge badge-green"><span class="badge-dot"></span> Aktif</span>':'<span class="badge badge-red"><span class="badge-dot"></span> Nonaktif</span>' ?></td>
          <td>
            <?php if(!$isSelf): ?>
            <div style="display:flex;gap:4px;">
              <a href="?toggle=<?= $a['id'] ?>" class="btn btn-warning btn-xs" title="Ubah Status" onclick="return confirm('Ubah status admin ini?')"><?= icon($a['is_active'] ? 'lock' : 'unlock') ?></a>
              <button class="btn btn-light btn-xs" title="Reset Password" onclick="showReset(<?= $a['id'] ?>,'<?= e($a['nama_lengkap']) ?>')"><?= icon('key') ?></button>
              <a href="?hapus=<?= $a['id'] ?>" class="btn btn-danger btn-xs" title="Hapus Admin" onclick="return confirm('Hapus admin ini?')"><?= icon('trash') ?></a>
            </div>
            <?php else: ?>
            <a href="password.php" class="btn btn-light btn-xs"><?= icon('key') ?> Ganti PW</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Reset -->
<div class="modal-overlay" id="resetModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title" style="display:flex;align-items:center;gap:6px;"><?= icon('key') ?> Reset Password Admin</div>
      <button class="modal-close" onclick="document.getElementById('resetModal').classList.remove('show')">×</button>
    </div>
    <div class="modal-body">
      <p style="font-size:13px;color:var(--gray-500);margin-bottom:16px">Reset password untuk: <strong id="resetNama"></strong></p>
      <form method="POST">
        <input type="hidden" name="aksi" value="reset">
        <input type="hidden" name="admin_id" id="resetId">
        <div class="form-group" style="margin-bottom:16px">
          <label class="form-label">Password Baru (min. 8 karakter)</label>
          <input type="password" name="new_pass" id="resetPass" class="form-control" required minlength="8">
        </div>
        <div style="display:flex;gap:10px;">
          <button type="submit" class="btn btn-danger" style="flex:1">Reset Password</button>
          <button type="button" class="btn btn-light" onclick="document.getElementById('resetModal').classList.remove('show')">Batal</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function showReset(id,nama){
  document.getElementById('resetId').value=id;
  document.getElementById('resetNama').textContent=nama;
  document.getElementById('resetPass').value='';
  document.getElementById('resetModal').classList.add('show');
}
</script>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
