<?php
require_once __DIR__ . '/../config.php';
requireAdmin();

$db  = db();
$msg = '';
$err = '';
$preview  = [];
$imported = 0;
$skipped  = 0;
$errors   = [];

// DOWNLOAD TEMPLATE
if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template_import_karyawan.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['nik','nama','jabatan','atasan_langsung','mulai_bekerja']);
    fputcsv($out, ['EMP001','Ahmad Fauzi','Operator Produksi','Budi Santoso','2024-01-15']);
    fputcsv($out, ['EMP002','Siti Rahayu','Teknisi Mesin','Budi Santoso','2024-02-01']);
    fclose($out);
    exit;
}

// PROSES UPLOAD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvfile'])) {
    $file    = $_FILES['csvfile'];
    $mode    = $_POST['mode'] ?? 'skip'; // skip | update

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $err = "Gagal upload file.";
    } elseif (!in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), ['csv','txt'])) {
        $err = "File harus berformat CSV.";
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        // Hapus BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $header = fgetcsv($handle);
        if (!$header) { $err = "File CSV kosong atau tidak valid."; } else {
            // Normalisasi header
            $header = array_map(fn($h) => strtolower(trim($h)), $header);
            $required = ['nik','nama','jabatan'];
            foreach ($required as $r2) {
                if (!in_array($r2, $header)) { $err = "Kolom '$r2' tidak ditemukan di CSV."; break; }
            }

            if (!$err) {
                $rowNum = 1;
                while (($row = fgetcsv($handle)) !== false) {
                    $rowNum++;
                    if (count($row) < 3) continue;
                    $data = array_combine($header, array_pad($row, count($header), ''));

                    $nik     = trim($data['nik'] ?? '');
                    $nama    = trim($data['nama'] ?? '');
                    $jabatan = trim($data['jabatan'] ?? '');
                    $atasan  = trim($data['atasan_langsung'] ?? '');
                    $mulai   = trim($data['mulai_bekerja'] ?? '');

                    if (!$nik || !$nama || !$jabatan) {
                        $errors[] = "Baris $rowNum: NIK/Nama/Jabatan kosong – dilewati.";
                        $skipped++;
                        continue;
                    }

                    // Validasi tanggal
                    if ($mulai && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $mulai)) $mulai = '';

                    // Cek existing
                    $cek = $db->prepare("SELECT id FROM karyawan WHERE nik=?");
                    $cek->bind_param('s', $nik);
                    $cek->execute();
                    $existing = $cek->get_result()->fetch_assoc();
                    $cek->close();

                    if ($existing) {
                        if ($mode === 'update') {
                            $upd = $db->prepare("UPDATE karyawan SET nama=?,jabatan=?,atasan_langsung=?,mulai_bekerja=? WHERE nik=?");
                            $upd->bind_param('sssss', $nama, $jabatan, $atasan, $mulai, $nik);
                            $upd->execute();
                            $upd->close();
                            $imported++;
                        } else {
                            $errors[] = "Baris $rowNum: NIK '$nik' sudah ada – dilewati.";
                            $skipped++;
                        }
                    } else {
                        $ins = $db->prepare("INSERT INTO karyawan (nik,nama,jabatan,atasan_langsung,mulai_bekerja) VALUES (?,?,?,?,?)");
                        $ins->bind_param('sssss', $nik, $nama, $jabatan, $atasan, $mulai);
                        $ins->execute();
                        $ins->close();
                        $imported++;
                    }
                }
                fclose($handle);

                auditLog($_SESSION['admin_id'], 'IMPORT_KARYAWAN', "Import: $imported, Lewati: $skipped");
                $msg = "Import selesai: <strong>$imported</strong> berhasil, <strong>$skipped</strong> dilewati.";
            }
        }
        if ($handle && is_resource($handle)) fclose($handle);
    }
}

$pageTitle  = 'Import Karyawan CSV';
$activePage = 'karyawan';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="margin-bottom:16px;">
  <div class="breadcrumb"><a href="karyawan.php">Data Karyawan</a><span class="sep">›</span><span>Import CSV</span></div>
</div>

<?php if($msg): ?><div class="alert alert-success"><?= icon('check-circle') ?> <?= $msg ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-error"><?= icon('alert-triangle') ?> <?= e($err) ?></div><?php endif; ?>
<?php if($errors): ?>
<div class="alert alert-info">
  <div>
    <div style="font-weight:700;margin-bottom:4px"><?= icon('info') ?> Baris yang dilewati:</div>
    <ul style="margin:4px 0 0 18px;font-size:12px;">
      <?php foreach(array_slice($errors,0,10) as $e2): ?><li><?= e($e2) ?></li><?php endforeach; ?>
      <?php if(count($errors)>10): ?><li>... dan <?= count($errors)-10 ?> baris lainnya</li><?php endif; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:330px 1fr;gap:16px;">

  <!-- FORM UPLOAD -->
  <div class="form-card">
    <div class="form-card-header"><?= icon('upload') ?> Import dari CSV</div>
    <p style="font-size:12.5px;color:var(--gray-500);margin-bottom:14px;line-height:1.55">
      Upload file CSV berisi daftar karyawan. Unduh template terlebih dahulu untuk memastikan format yang benar.
    </p>

    <a href="?template=1" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;margin-bottom:14px;">
      <?= icon('download') ?> Download Template CSV
    </a>

    <form method="POST" enctype="multipart/form-data">
      <div class="form-group" style="margin-bottom:12px;">
        <label class="form-label">File CSV <span class="req">*</span></label>
        <input type="file" name="csvfile" accept=".csv,.txt" required
               style="width:100%;padding:8px 10px;border:1.5px dashed var(--gray-300);border-radius:var(--radius-sm);font-size:12px;cursor:pointer;background:var(--gray-50);">
      </div>
      <div class="form-group" style="margin-bottom:16px;">
        <label class="form-label">Jika NIK sudah ada:</label>
        <div style="display:flex;gap:14px;margin-top:4px;">
          <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;">
            <input type="radio" name="mode" value="skip" checked> Lewati (tidak ubah)
          </label>
          <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;">
            <input type="radio" name="mode" value="update"> Update data
          </label>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-sm" style="width:100%"><?= icon('upload') ?> Import Sekarang</button>
    </form>
  </div>

  <!-- PETUNJUK -->
  <div class="form-card">
    <div class="form-card-header"><?= icon('file-text') ?> Petunjuk Format CSV</div>

    <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:var(--radius);padding:16px;margin-bottom:18px;font-size:13px;">
      <div style="font-weight:600;margin-bottom:8px;color:var(--gray-800);">Kolom yang dibutuhkan:</div>
      <table style="width:100%;border-collapse:collapse;">
        <thead><tr style="background:var(--gray-200);"><th style="padding:6px 10px;text-align:left;font-size:12px;border-radius:4px 0 0 4px">Kolom</th><th style="padding:6px 10px;text-align:left;font-size:12px">Wajib</th><th style="padding:6px 10px;text-align:left;font-size:12px;border-radius:0 4px 4px 0">Keterangan</th></tr></thead>
        <tbody>
          <?php foreach([
            ['nik','<span class="badge badge-green"><span class="badge-dot"></span> Wajib</span>','Nomor unik karyawan (contoh: EMP001)'],
            ['nama','<span class="badge badge-green"><span class="badge-dot"></span> Wajib</span>','Nama lengkap karyawan'],
            ['jabatan','<span class="badge badge-green"><span class="badge-dot"></span> Wajib</span>','Jabatan/posisi karyawan'],
            ['atasan_langsung','<span class="badge badge-gray">Opsional</span>','Nama atasan langsung'],
            ['mulai_bekerja','<span class="badge badge-gray">Opsional</span>','Format: YYYY-MM-DD (contoh: 2024-01-15)'],
          ] as [$col,$req,$ket]): ?>
          <tr style="border-bottom:1px solid var(--gray-100)">
            <td style="padding:7px 10px"><code style="background:var(--blue-pale);padding:2px 7px;border-radius:4px;font-size:12px;color:var(--blue)"><?= $col ?></code></td>
            <td style="padding:7px 10px;font-size:12px"><?= $req ?></td>
            <td style="padding:7px 10px;font-size:12px;color:var(--gray-600)"><?= $ket ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="font-weight:600;font-size:13px;margin-bottom:8px;color:var(--gray-800);">Contoh isi file CSV:</div>
    <pre style="background:#090e1a;color:#93c5fd;padding:14px;border-radius:8px;font-size:12px;overflow-x:auto;line-height:1.7;border:1px solid rgba(255,255,255,0.1);">nik,nama,jabatan,atasan_langsung,mulai_bekerja
EMP001,Ahmad Fauzi,Operator Produksi,Budi Santoso,2024-01-15
EMP002,Siti Rahayu,Teknisi Mesin,Budi Santoso,2024-02-01
EMP003,Rizky Pratama,Staff Administrasi,Dewi Lestari,2024-03-10</pre>

    <div class="alert alert-info" style="margin-top:16px;font-size:12px;">
      <?= icon('info') ?> <strong>Tips:</strong> Buat file CSV di Excel → Save As → CSV UTF-8. Pastikan tidak ada spasi ekstra di awal/akhir nilai.
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
