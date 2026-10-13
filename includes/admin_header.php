<?php
$pageTitle  = $pageTitle  ?? 'Admin';
$activePage = $activePage ?? '';
$pageSubtitle = $pageSubtitle ?? '';

// SVG Icons helper
function icon(string $name, string $class = ''): string {
    $icons = [
        'dashboard' => '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'users'     => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>',
        'upload'    => '<polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/>',
        'link'      => '<path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/>',
        'clipboard' => '<path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>',
        'file-text' => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
        'bar-chart' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'search'    => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'user-cog'  => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/><circle cx="20" cy="6" r="2"/><path d="M22 6h-4"/>',
        'key'       => '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>',
        'activity'  => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'chevron'   => '<polyline points="9 18 15 12 9 6"/>',
        'bell'      => '<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>',
        'edit'      => '<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'trash'     => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>',
    ];
    $d = $icons[$name] ?? '<circle cx="12" cy="12" r="10"/>';
    $cls = $class ?: 'nav-icon';
    return "<svg class=\"{$cls}\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\">{$d}</svg>";
}

$initials = strtoupper(substr(str_word_count($_SESSION['admin_nama'] ?? 'A', 1)[0] ?? 'A', 0, 1)
    . (str_word_count($_SESSION['admin_nama'] ?? '', 1)[1][0] ?? ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — PKWT Admin · Indopelita</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
/* Page-level overrides & extras */
.topbar-date { display: none; }
@media(min-width:1100px){ .topbar-date { display:block; } }
</style>
</head>
<body>

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <img src="<?= APP_URL ?>/assets/img/logo.svg" alt="Indopelita">
    <div class="brand-sub">Sistem Penilaian PKWT · HRD</div>
  </div>

  <div class="sidebar-section">Menu Utama</div>
  <nav class="sidebar-nav">
    <a href="<?= APP_URL ?>/admin/dashboard.php" class="<?= $activePage==='dashboard'?'active':'' ?>">
      <?= icon('dashboard') ?> Dashboard
    </a>
    <a href="<?= APP_URL ?>/admin/karyawan.php" class="<?= $activePage==='karyawan'?'active':'' ?>">
      <?= icon('users') ?> Data Karyawan
    </a>
    <a href="<?= APP_URL ?>/admin/import_karyawan.php" class="sub-link <?= $activePage==='import'?'active':'' ?>">
      <?= icon('upload') ?> Import CSV
    </a>
    <a href="<?= APP_URL ?>/admin/token.php" class="<?= $activePage==='token'?'active':'' ?>">
      <?= icon('link') ?> Buat Link Penilaian
    </a>
    <a href="<?= APP_URL ?>/admin/penilaian.php" class="<?= $activePage==='penilaian'?'active':'' ?>">
      <?= icon('clipboard') ?> Hasil Penilaian
    </a>
    <?php
    // Hitung memo belum dicetak untuk badge
    $__memoBadge = 0;
    try {
        $__r = $db->query("SELECT COUNT(*) c FROM memo_perpanjangan WHERE is_printed=0");
        if ($__r) $__memoBadge = intval($__r->fetch_assoc()['c']);
    } catch (Throwable $e) {}
    ?>
    <a href="<?= APP_URL ?>/admin/memo.php" class="<?= $activePage==='memo'?'active':'' ?>" style="display:flex;align-items:center;justify-content:space-between;">
      <span style="display:flex;align-items:center;gap:8px;"><?= icon('file-text') ?> Memo Perpanjangan</span>
      <?php if($__memoBadge > 0): ?>
      <span style="background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:99px;min-width:20px;text-align:center;"><?= $__memoBadge ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= APP_URL ?>/admin/laporan.php" class="<?= $activePage==='laporan'?'active':'' ?>">
      <?= icon('bar-chart') ?> Laporan & Ekspor
    </a>
  </nav>

  <div class="sidebar-section">Pengaturan</div>
  <nav class="sidebar-nav">
    <a href="<?= APP_URL ?>/admin/audit.php" class="<?= $activePage==='audit'?'active':'' ?>">
      <?= icon('activity') ?> Log Aktivitas
    </a>
    <a href="<?= APP_URL ?>/admin/admin_mgmt.php" class="<?= $activePage==='admin_mgmt'?'active':'' ?>">
      <?= icon('user-cog') ?> Manajemen Admin
    </a>
    <a href="<?= APP_URL ?>/admin/password.php" class="<?= $activePage==='password'?'active':'' ?>">
      <?= icon('key') ?> Ganti Password
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="admin-info">
      <div class="avatar"><?= e($initials) ?></div>
      <div>
        <div class="admin-name"><?= e($_SESSION['admin_nama'] ?? 'Admin') ?></div>
        <div class="admin-role">HRD Administrator</div>
      </div>
    </div>
    <a href="<?= APP_URL ?>/admin/logout.php" class="logout-btn">
      <?= icon('logout', 'nav-icon') ?> Keluar dari Sistem
    </a>
  </div>
</aside>

<!-- ══ MAIN ══ -->
<div class="main">

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="topbar-left">
      <?php if($pageSubtitle): ?>
      <div>
        <div class="page-title"><?= e($pageTitle) ?></div>
        <div class="breadcrumb">
          <a href="<?= APP_URL ?>/admin/dashboard.php">Dashboard</a>
          <span class="sep">›</span>
          <span><?= e($pageTitle) ?></span>
        </div>
      </div>
      <?php else: ?>
      <div class="page-title"><?= e($pageTitle) ?></div>
      <?php endif; ?>
    </div>
    <div class="topbar-right">
      <div class="topbar-date" id="topbar-clock"></div>
      <div class="topbar-badge">
        <div class="av"><?= e($initials) ?></div>
        <?= e($_SESSION['admin_nama'] ?? 'Admin') ?>
      </div>
    </div>
  </header>

  <!-- CONTENT -->
  <main class="content">
<script>
(function(){
  const el = document.getElementById('topbar-clock');
  if(!el) return;
  const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
  const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
  function tick(){
    const d = new Date();
    el.textContent = days[d.getDay()] + ', ' + d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear()
      + '  ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
  }
  tick(); setInterval(tick, 1000);
})();
</script>
