<?php
require_once __DIR__ . '/../config.php';
if (isAdminLoggedIn()) redirect(APP_URL . '/admin/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        $stmt = db()->prepare("SELECT id, password_hash, nama_lengkap, is_active FROM hr_admin WHERE username=?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && $row['is_active'] && password_verify($password, $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $row['id'];
            $_SESSION['admin_nama'] = $row['nama_lengkap'];
            $_SESSION['login_time'] = time();
            $u = db()->prepare("UPDATE hr_admin SET last_login=NOW() WHERE id=?");
            $u->bind_param('i', $row['id']); $u->execute(); $u->close();
            auditLog($row['id'], 'LOGIN', 'Berhasil');
            redirect(APP_URL . '/admin/dashboard.php');
        } else { $error = 'Username atau password tidak sesuai.'; }
    } else { $error = 'Username dan password wajib diisi.'; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk Admin — Sistem Penilaian PKWT Indopelita</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
<style>
body {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  margin: 0;
  background-color: #070d1a;
  background-image: 
    radial-gradient(circle at 50% 0%, rgba(29, 78, 216, 0.15) 0%, transparent 65%),
    radial-gradient(circle at 100% 100%, rgba(15, 23, 42, 0.4) 0%, transparent 50%),
    linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
  background-size: 100% 100%, 100% 100%, 36px 36px, 36px 36px;
  font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
  padding: 24px;
}

.login-shell {
  width: 100%;
  max-width: 920px;
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(255, 255, 255, 0.1);
  overflow: hidden;
  display: flex;
  position: relative;
  z-index: 10;
}

/* Left Hero */
.login-hero {
  flex: 1.1;
  background: #090e1a;
  padding: 48px 42px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  border-right: 1px solid rgba(255, 255, 255, 0.06);
  position: relative;
}

.hero-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 40px;
}
.hero-brand img {
  height: 32px;
  filter: brightness(0) invert(1);
}
.hero-badge {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #93c5fd;
  background: rgba(29, 78, 216, 0.25);
  border: 1px solid rgba(147, 197, 253, 0.2);
  padding: 3px 8px;
  border-radius: 6px;
}

.hero-title {
  font-size: 26px;
  font-weight: 800;
  color: #f8fafc;
  line-height: 1.25;
  letter-spacing: -0.025em;
  margin-bottom: 12px;
}
.hero-desc {
  font-size: 13.5px;
  color: #94a3b8;
  line-height: 1.65;
  margin-bottom: 32px;
}

.hero-feature-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.hero-feature-item {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 13px;
  color: #cbd5e1;
}
.hero-icon-check {
  width: 20px;
  height: 20px;
  border-radius: 6px;
  background: rgba(5, 150, 105, 0.2);
  border: 1px solid rgba(5, 150, 105, 0.35);
  color: #34d399;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.hero-icon-check svg {
  width: 12px;
  height: 12px;
  stroke-width: 2.5;
}

.hero-footer {
  font-size: 11px;
  color: #475569;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  padding-top: 20px;
  margin-top: 36px;
}

/* Right Form */
.login-form-pane {
  flex: 1;
  padding: 50px 46px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  background: #ffffff;
}

.pane-header {
  margin-bottom: 28px;
}
.pane-title {
  font-size: 22px;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.02em;
  margin-bottom: 6px;
}
.pane-sub {
  font-size: 13.5px;
  color: #64748b;
}

.form-field {
  margin-bottom: 18px;
}
.field-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}
.field-label-text {
  font-size: 12px;
  font-weight: 600;
  color: #334155;
  letter-spacing: 0.02em;
}

.input-container {
  position: relative;
  display: flex;
  align-items: center;
}
.input-icon-lead {
  position: absolute;
  left: 12px;
  width: 18px;
  height: 18px;
  color: #94a3b8;
  pointer-events: none;
}
.custom-input {
  width: 100%;
  padding: 10px 14px 10px 40px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 13.5px;
  font-family: inherit;
  color: #0f172a;
  background: #fff;
  transition: all 0.15s;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}
.custom-input:focus {
  border-color: #1d4ed8;
  box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12);
  outline: none;
}
.custom-input::placeholder {
  color: #94a3b8;
}

.btn-toggle-view {
  position: absolute;
  right: 10px;
  background: none;
  border: none;
  padding: 6px;
  color: #94a3b8;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
}
.btn-toggle-view:hover {
  color: #475569;
}
.btn-toggle-view svg {
  width: 17px;
  height: 17px;
}

.btn-submit-action {
  width: 100%;
  padding: 11px 18px;
  background: #1d4ed8;
  color: #ffffff;
  border: 1px solid #1e40af;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.15s;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.15);
  margin-top: 8px;
}
.btn-submit-action:hover {
  background: #1e40af;
  box-shadow: 0 3px 8px rgba(29, 78, 216, 0.3);
}
.btn-submit-action:active {
  transform: scale(0.99);
}

.alert-callout-error {
  background: #fff1f2;
  border: 1px solid #fecdd3;
  color: #9f1239;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.alert-callout-error svg {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  stroke: #e11d48;
}

.pane-footer-meta {
  text-align: center;
  font-size: 11.5px;
  color: #64748b;
  margin-top: 22px;
  padding-top: 18px;
  border-top: 1px solid #f1f5f9;
}
.pane-footer-meta code {
  background: #f1f5f9;
  padding: 2px 6px;
  border-radius: 4px;
  font-family: ui-monospace, SFMono-Regular, monospace;
  color: #334155;
  font-size: 11px;
}

@media (max-width: 768px) {
  .login-shell {
    flex-direction: column;
  }
  .login-hero {
    padding: 32px 28px;
  }
  .login-form-pane {
    padding: 32px 28px;
  }
}
</style>
</head>
<body>

<div class="login-shell">

  <!-- Left: Corporate Branding -->
  <div class="login-hero">
    <div>
      <div class="hero-brand">
        <img src="<?= APP_URL ?>/assets/img/logo.svg" alt="Indopelita" onerror="this.src='<?= APP_URL ?>/assets/img/logo.png'">
        <span class="hero-badge">Enterprise HRD</span>
      </div>

      <h1 class="hero-title">Sistem Evaluasi Kinerja Personel PKWT</h1>
      <p class="hero-desc">
        Portal terpadu manajemen evaluasi kinerja kontrak kerja (PKWT) PT Indopelita Aircraft Industries dengan integrasi token penilaian, approval atasan, dan penerbitan memorandum resmi.
      </p>

      <ul class="hero-feature-list">
        <li class="hero-feature-item">
          <div class="hero-icon-check">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <span>Akses aman berbasis One-Time Token per penilai</span>
        </li>
        <li class="hero-feature-item">
          <div class="hero-icon-check">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <span>Kalkulasi objektif 10 indikator kompetensi teknis & non-teknis</span>
        </li>
        <li class="hero-feature-item">
          <div class="hero-icon-check">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <span>Penerbitan otomatis Memorandum Perpanjangan Kontrak</span>
        </li>
        <li class="hero-feature-item">
          <div class="hero-icon-check">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <span>Audit trail lengkap & ekspor laporan PDF/Excel</span>
        </li>
      </ul>
    </div>

    <div class="hero-footer">
      © <?= date('Y') ?> PT Indopelita Aircraft Industries · Human Capital Management
    </div>
  </div>

  <!-- Right: Authentication Form -->
  <div class="login-form-pane">
    <div class="pane-header">
      <h2 class="pane-title">Masuk ke Sistem</h2>
      <div class="pane-sub">Silakan masukkan kredensial akun administrator Anda</div>
    </div>

    <?php if($error): ?>
    <div class="alert-callout-error">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <div><?= e($error) ?></div>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="form-field">
        <div class="field-header">
          <label class="field-label-text">Username</label>
        </div>
        <div class="input-container">
          <svg class="input-icon-lead" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <input type="text" name="username" class="custom-input"
                 value="<?= e($_POST['username'] ?? '') ?>"
                 placeholder="Nama pengguna admin" autocomplete="username" required autofocus>
        </div>
      </div>

      <div class="form-field">
        <div class="field-header">
          <label class="field-label-text">Kata Sandi</label>
        </div>
        <div class="input-container">
          <svg class="input-icon-lead" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
          <input type="password" name="password" id="pwInput" class="custom-input"
                 placeholder="Masukkan kata sandi" autocomplete="current-password" required>
          <button type="button" class="btn-toggle-view" onclick="togglePasswordVisibility()" title="Tampilkan sandi" id="toggleBtn">
            <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-submit-action">Masuk Administrator</button>
    </form>

    <div class="pane-footer-meta">
      Kredensial standar: <code>admin</code> / <code>admin123</code><br>
      Akses terenkripsi dan diawasi oleh sistem audit log
    </div>
  </div>

</div>

<script>
function togglePasswordVisibility() {
  const input = document.getElementById('pwInput');
  const icon = document.getElementById('eyeIcon');
  if (input.type === 'password') {
    input.type = 'text';
    icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
  } else {
    input.type = 'password';
    icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  }
}
</script>

</body>
</html>
