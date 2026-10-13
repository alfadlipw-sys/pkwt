<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$db = db(); $msg = $err = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $old=$_POST['old_password']??''; $new=$_POST['new_password']??''; $conf=$_POST['confirm']??'';
    $stmt=$db->prepare("SELECT password_hash FROM hr_admin WHERE id=?"); $stmt->bind_param('i',$_SESSION['admin_id']); $stmt->execute();
    $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!password_verify($old,$row['password_hash'])) { $err="Password lama tidak sesuai."; }
    elseif (strlen($new)<8) { $err="Password baru minimal 8 karakter."; }
    elseif ($new!==$conf) { $err="Konfirmasi password tidak cocok."; }
    else {
        $hash=password_hash($new,PASSWORD_BCRYPT,['cost'=>10]);
        $u=$db->prepare("UPDATE hr_admin SET password_hash=? WHERE id=?");
        $u->bind_param('si',$hash,$_SESSION['admin_id']); $u->execute(); $u->close();
        auditLog($_SESSION['admin_id'],'GANTI_PASSWORD','Password berhasil diubah');
        $msg="Password berhasil diubah.";
    }
}
$pageTitle='Ganti Password'; $activePage='password';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div style="max-width:440px;">
  <?php if($msg): ?><div class="alert alert-success"><span class="alert-icon">✅</span> <?= e($msg) ?></div><?php endif; ?>
  <?php if($err): ?><div class="alert alert-error"><span class="alert-icon">⚠️</span> <?= e($err) ?></div><?php endif; ?>
  <div class="form-card">
    <div class="form-card-header">🔑 Ganti Password Admin</div>
    <form method="POST">
      <div class="form-grid" style="gap:14px;">
        <div class="form-group">
          <label class="form-label">Password Lama <span class="req">*</span></label>
          <input type="password" name="old_password" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Password Baru <span class="req">*</span> <span style="font-weight:400;color:var(--gray-400)">(min. 8 karakter)</span></label>
          <input type="password" name="new_password" class="form-control" required minlength="8">
        </div>
        <div class="form-group">
          <label class="form-label">Konfirmasi Password Baru <span class="req">*</span></label>
          <input type="password" name="confirm" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Simpan Password Baru</button>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
