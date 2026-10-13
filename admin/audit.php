<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$db = db();
$logs = $db->query("SELECT al.*,ha.nama_lengkap FROM audit_log al LEFT JOIN hr_admin ha ON ha.id=al.admin_id ORDER BY al.created_at DESC LIMIT 100");
$pageTitle='Log Aktivitas'; $activePage='audit';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="card">
  <div class="card-header">
    <div class="card-title"><?= icon('activity') ?> Log Aktivitas Admin (100 Terakhir)</div>
    <div style="font-size:12px;color:var(--gray-400)">Audit trail keamanan dan operasional sistem</div>
  </div>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr><th>Waktu</th><th>Admin</th><th>Aksi</th><th>Keterangan</th><th>IP Address</th></tr>
      </thead>
      <tbody>
        <?php while($r=$logs->fetch_assoc()): ?>
        <tr>
          <td style="font-size:12px;white-space:nowrap;color:var(--gray-500)"><?= date('d/m/Y H:i:s',strtotime($r['created_at'])) ?></td>
          <td>
            <div style="font-weight:600;font-size:13px"><?= e($r['nama_lengkap']??'Sistem') ?></div>
          </td>
          <td>
            <code style="background:var(--blue-pale);color:var(--blue);padding:3px 9px;border-radius:5px;font-size:11.5px;font-weight:600"><?= e($r['aksi']) ?></code>
          </td>
          <td style="font-size:12.5px;color:var(--gray-600)"><?= e($r['keterangan']??'-') ?></td>
          <td style="font-size:11px;font-family:monospace;color:var(--gray-400)"><?= e($r['ip_address']??'-') ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
