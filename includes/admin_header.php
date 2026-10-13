<?php
$pageTitle  = $pageTitle  ?? 'Admin';
$activePage = $activePage ?? '';
$pageSubtitle = $pageSubtitle ?? '';

// SVG Icons helper
if (!function_exists('icon')) {
    function icon(string $name, string $class = ''): string {
        $icons = [
            'dashboard'     => '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
            'users'         => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>',
            'user'          => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'upload'        => '<polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/>',
            'download'      => '<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
            'link'          => '<path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/>',
            'clipboard'     => '<path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>',
            'file'          => '<path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 20 9"/>',
            'file-text'     => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
            'bar-chart'     => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
            'search'        => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
            'user-cog'      => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/><circle cx="20" cy="6" r="2"/><path d="M22 6h-4"/>',
            'key'           => '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>',
            'activity'      => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
            'logout'        => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
            'chevron'       => '<polyline points="9 18 15 12 9 6"/>',
            'bell'          => '<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>',
            'edit'          => '<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>',
            'trash'         => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>',
            'plus'          => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
            'check'         => '<polyline points="20 6 9 17 4 12"/>',
            'check-circle'  => '<path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
            'x'             => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
            'x-circle'      => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
            'printer'       => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
            'eye'           => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
            'filter'        => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
            'refresh'       => '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/>',
            'copy'          => '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>',
            'external'      => '<path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
            'calendar'      => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
            'clock'         => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'award'         => '<circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>',
            'trending-up'   => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
            'trending-down' => '<polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/>',
            'alert-triangle'=> '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
            'lock'          => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>',
            'unlock'        => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 019.9-1"/>',
            'shield'        => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
            'message'       => '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>',
            'info'          => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
            'save'          => '<path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>',
            'send'          => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
        ];
        $d = $icons[$name] ?? '<circle cx="12" cy="12" r="10"/>';
        $cls = $class ?: 'nav-icon';
        return "<svg class=\"{$cls}\" width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\">{$d}</svg>";
    }
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
        $__dbConn = (isset($db) && $db instanceof mysqli) ? $db : (function_exists('db') ? db() : null);
        if ($__dbConn) {
            $__r = $__dbConn->query("SELECT COUNT(*) c FROM memo_perpanjangan WHERE is_printed=0");
            if ($__r) $__memoBadge = intval($__r->fetch_assoc()['c']);
        }
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
