<?php
// ============================================================
// form_memo.php — Form Pengisian Memo Perpanjangan Kontrak
// Diakses setelah form penilaian berhasil disubmit
// ============================================================
require_once __DIR__ . '/config.php';

$db    = db();
$token = trim($_GET['token'] ?? '');
$penilaian_id = intval($_GET['penilaian_id'] ?? $_GET['id'] ?? ($_SESSION['memo_penilaian_id'] ?? 0));
$sess_token   = $_SESSION['memo_token'] ?? '';

// Jika admin login dan membawa penilaian_id atau token:
if ($penilaian_id && isAdminLoggedIn()) {
    $pStmt = $db->prepare("SELECT p.id, ft.token FROM penilaian p JOIN form_tokens ft ON ft.id = p.token_id WHERE p.id = ? LIMIT 1");
    $pStmt->bind_param('i', $penilaian_id);
    $pStmt->execute();
    $pRow = $pStmt->get_result()->fetch_assoc();
    $pStmt->close();
    if ($pRow) {
        $token = $pRow['token'];
        $_SESSION['memo_penilaian_id'] = $penilaian_id;
        $_SESSION['memo_token']        = $token;
    }
} elseif ((!$penilaian_id || $token !== $sess_token) && $token) {
    // Fallback: jika session hilang setelah redirect, cari lewat token di DB
    $fbStmt = $db->prepare("SELECT p.id FROM penilaian p JOIN form_tokens ft ON ft.id = p.token_id WHERE ft.token = ? AND ft.is_submitted = 1 LIMIT 1");
    $fbStmt->bind_param('s', $token);
    $fbStmt->execute();
    $fbRow = $fbStmt->get_result()->fetch_assoc();
    $fbStmt->close();
    if ($fbRow) {
        $penilaian_id = intval($fbRow['id']);
        $_SESSION['memo_penilaian_id'] = $penilaian_id;
        $_SESSION['memo_token']        = $token;
    }
}

if (!$penilaian_id || !$token) {
    die('
    <div style="font-family:\'Segoe UI\',sans-serif;text-align:center;padding:80px;background:#f0f2f5;min-height:100vh;display:flex;align-items:center;justify-content:center;">
      <div style="background:#fff;border-radius:16px;padding:48px 40px;max-width:440px;box-shadow:0 10px 40px rgba(0,0,0,0.1);">
        <div style="font-size:48px;margin-bottom:16px;">🔒</div>
        <h2 style="color:#dc2626;margin-bottom:10px;">Akses Tidak Valid</h2>
        <p style="color:#666;font-size:14px;line-height:1.7">Halaman ini hanya bisa diakses setelah mengisi form penilaian atau melalui menu Admin.</p>
        <a href="' . APP_URL . '/admin/penilaian.php" style="display:inline-block;margin-top:16px;padding:10px 20px;background:#1e6fd9;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;font-family:\'Inter\',sans-serif;">← Kembali ke Admin</a>
      </div>
    </div>');
}

// ── Ambil data penilaian & karyawan ──
$stmt = $db->prepare("
    SELECT p.*, k.nik, k.nama, k.jabatan, k.atasan_langsung, k.mulai_bekerja,
           ft.periode, ft.shift, ft.cuti, ft.periode_ke, ft.catatan_hrd
    FROM penilaian p
    JOIN karyawan k  ON k.id = p.karyawan_id
    JOIN form_tokens ft ON ft.id = p.token_id
    WHERE p.id = ?
");
$stmt->bind_param('i', $penilaian_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) { die('<p style="font-family:sans-serif;padding:40px">Data tidak ditemukan.</p>'); }

// ── Cek: memo sudah pernah dibuat? ──
$existMemo = $db->prepare("SELECT * FROM memo_perpanjangan WHERE penilaian_id=?");
$existMemo->bind_param('i', $penilaian_id);
$existMemo->execute();
$alreadyMemo = $existMemo->get_result()->fetch_assoc();
$existMemo->close();

$isEditing = isset($_GET['edit']) && $_GET['edit'] == '1';

// ── Status diperpanjang ──
$diperpanjang = ($data['status_perpanjangan'] === 'Diperpanjang');

$errors  = [];
$success = false;
$memoId  = 0;

// ── PROSES SUBMIT ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan_memo') {

    $nomor_memo           = trim($_POST['nomor_memo']           ?? '');
    $tanggal_memo         = trim($_POST['tanggal_memo']         ?? '');
    $kota_memo            = trim($_POST['kota_memo']            ?? 'Tangerang Selatan');
    $dari_jabatan         = trim($_POST['dari_jabatan']         ?? '');
    $dari_nama            = trim($_POST['dari_nama']            ?? '');
    $kepada_jabatan       = trim($_POST['kepada_jabatan']       ?? '');
    $perihal              = trim($_POST['perihal']              ?? 'Permohonan Perpanjangan Kesepakatan Kerja Personil');
    $kualifikasi_memo     = trim($_POST['kualifikasi_memo']     ?? '');
    $tanggal_mulai_kontrak= trim($_POST['tanggal_mulai_kontrak']?? '');
    $tanggal_akhir_kontrak= trim($_POST['tanggal_akhir_kontrak']?? '');
    $no_pegawai           = trim($_POST['no_pegawai']           ?? '');
    $ref_kontrak          = trim($_POST['ref_kontrak']          ?? '');
    $tembusan             = trim($_POST['tembusan']             ?? '');
    $ttd_memo_nama        = trim($_POST['ttd_memo_nama']        ?? '');
    $ttd_memo_jabatan     = trim($_POST['ttd_memo_jabatan']     ?? '');
    $status_memo          = trim($_POST['status_memo']          ?? '');
    $isi_paragraf1        = trim($_POST['isi_paragraf1']        ?? '');
    $isi_paragraf2        = trim($_POST['isi_paragraf2']        ?? '');
    // Validasi status_memo
    if (!in_array($status_memo, ['Diperpanjang','Tidak Diperpanjang'])) {
        $status_memo = $data['status_perpanjangan'];
    }
    // Default paragraf jika kosong
    if (!$isi_paragraf1) $isi_paragraf1 = 'Bersama ini kami sampaikan daftar nama para Personel yang masih dibutuhkan, dikarenakan terkait dengan Capability Perusahaan serta kebutuhan perusahaan.';
    if (!$isi_paragraf2) $isi_paragraf2 = ($status_memo === 'Diperpanjang')
        ? 'Maka sehubungan dengan hal tersebut diatas, mohon agar kiranya dapat dilakukan perpanjangan masa kerja, dengan pengantar sebagai berikut :'
        : 'Maka sehubungan dengan hal tersebut diatas, dengan berat hati kami sampaikan bahwa masa kerja personel berikut tidak dapat diperpanjang, dengan keterangan sebagai berikut :';

    if (!$nomor_memo)           $errors[] = "Nomor memo wajib diisi.";
    if (!$tanggal_memo)         $errors[] = "Tanggal memo wajib diisi.";
    if (!$dari_jabatan)         $errors[] = "Jabatan pengirim wajib diisi.";
    if (!$dari_nama)            $errors[] = "Nama pengirim wajib diisi.";
    if (!$kepada_jabatan)       $errors[] = "Jabatan penerima wajib diisi.";
    if (!$kualifikasi_memo)     $errors[] = "Kualifikasi/jabatan dalam memo wajib diisi.";
    if (!$tanggal_mulai_kontrak)$errors[] = "Tanggal mulai kontrak wajib diisi.";
    if (!$tanggal_akhir_kontrak)$errors[] = "Tanggal akhir kontrak wajib diisi.";
    if (!$ttd_memo_nama)        $errors[] = "Nama penandatangan memo wajib diisi.";
    if (!$ttd_memo_jabatan)     $errors[] = "Jabatan penandatangan memo wajib diisi.";

    if (empty($errors)) {
        $tid = intval($data['token_id']);
        $kid = intval($data['karyawan_id']);

        if ($alreadyMemo) {
            $memoId = intval($alreadyMemo['id']);
            $upd = $db->prepare("UPDATE memo_perpanjangan SET
                nomor_memo=?, tanggal_memo=?, kota_memo=?,
                dari_jabatan=?, dari_nama=?, kepada_jabatan=?, perihal=?,
                kualifikasi_memo=?, tanggal_mulai_kontrak=?, tanggal_akhir_kontrak=?,
                status_memo=?, isi_paragraf1=?, isi_paragraf2=?,
                no_pegawai=?, ref_kontrak=?, tembusan=?,
                ttd_memo_nama=?, ttd_memo_jabatan=?
                WHERE id=?");
            $upd->bind_param('ssssssssssssssssssi',
                $nomor_memo, $tanggal_memo, $kota_memo,
                $dari_jabatan, $dari_nama, $kepada_jabatan, $perihal,
                $kualifikasi_memo, $tanggal_mulai_kontrak, $tanggal_akhir_kontrak,
                $status_memo, $isi_paragraf1, $isi_paragraf2,
                $no_pegawai, $ref_kontrak, $tembusan,
                $ttd_memo_nama, $ttd_memo_jabatan,
                $memoId
            );
            if ($upd->execute()) {
                $success = true;
                if (isAdminLoggedIn()) {
                    auditLog($_SESSION['admin_id'], 'EDIT_MEMO', "Memo ID: $memoId, Penilaian: $penilaian_id, No: $nomor_memo");
                }
                unset($_SESSION['memo_penilaian_id'], $_SESSION['memo_token']);
            } else {
                $errors[] = "Gagal memperbarui memo: " . $db->error;
            }
            $upd->close();
        } else {
            $ins = $db->prepare("INSERT INTO memo_perpanjangan
                (penilaian_id, karyawan_id, token_id,
                 nomor_memo, tanggal_memo, kota_memo,
                 dari_jabatan, dari_nama, kepada_jabatan, perihal,
                 kualifikasi_memo, tanggal_mulai_kontrak, tanggal_akhir_kontrak,
                 status_memo, isi_paragraf1, isi_paragraf2,
                 no_pegawai, ref_kontrak, tembusan,
                 ttd_memo_nama, ttd_memo_jabatan)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $ins->bind_param('iiissssssssssssssssss',
                $penilaian_id, $kid, $tid,
                $nomor_memo, $tanggal_memo, $kota_memo,
                $dari_jabatan, $dari_nama, $kepada_jabatan, $perihal,
                $kualifikasi_memo, $tanggal_mulai_kontrak, $tanggal_akhir_kontrak,
                $status_memo, $isi_paragraf1, $isi_paragraf2,
                $no_pegawai, $ref_kontrak, $tembusan,
                $ttd_memo_nama, $ttd_memo_jabatan
            );
            if ($ins->execute()) {
                $memoId  = $db->insert_id;
                $success = true;
                if (isAdminLoggedIn()) {
                    auditLog($_SESSION['admin_id'], 'BUAT_MEMO', "Memo ID: $memoId, Penilaian: $penilaian_id, No: $nomor_memo");
                }
                // Hapus session memo
                unset($_SESSION['memo_penilaian_id'], $_SESSION['memo_token']);
            } else {
                $errors[] = "Gagal menyimpan memo. Silakan coba lagi.";
            }
            $ins->close();
        }
    }
}

// ── Helper tanggal ──
function tglIndo(string $d): string {
    if (!$d) return '';
    $bulan=['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei',
            '06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September',
            '10'=>'Oktober','11'=>'November','12'=>'Desember'];
    [$y,$m,$day]=explode('-',$d);
    return intval($day).' '.($bulan[$m]??$m).' '.$y;
}

// Auto-isi tanggal kontrak dari periode & siapkan default nilai
$periodeStr  = $data['periode'] ?? '';
$kontrakMulai = date('Y-m-d', strtotime('first day of next month'));
$kontrakAkhir = date('Y-m-d', strtotime('+1 year', strtotime($kontrakMulai)));

$val_kota         = $alreadyMemo['kota_memo'] ?? 'Tangerang Selatan';
$val_tgl          = $alreadyMemo['tanggal_memo'] ?? date('Y-m-d');
$val_nomor        = $alreadyMemo['nomor_memo'] ?? '';
$val_kepada       = $alreadyMemo['kepada_jabatan'] ?? 'Finance & General Affair VP';
$val_perihal      = $alreadyMemo['perihal'] ?? 'Permohonan Perpanjangan Kesepakatan Kerja Personil';
$val_ref          = $alreadyMemo['ref_kontrak'] ?? '';
$val_no_pegawai   = $alreadyMemo['no_pegawai'] ?? $data['nik'];
$val_kualifikasi  = $alreadyMemo['kualifikasi_memo'] ?? (!empty($data['kualifikasi_jabatan']) ? $data['kualifikasi_jabatan'] : $data['jabatan']);
$val_mulai        = $alreadyMemo['tanggal_mulai_kontrak'] ?? $kontrakMulai;
$val_akhir        = $alreadyMemo['tanggal_akhir_kontrak'] ?? $kontrakAkhir;
$val_status       = $alreadyMemo['status_memo'] ?? (!empty($data['permohonan_perpanjangan']) ? $data['permohonan_perpanjangan'] : $data['status_perpanjangan']);
$diperpanjang     = ($val_status === 'Diperpanjang');
$val_dari_jabatan = $alreadyMemo['dari_jabatan'] ?? 'Aviation Services VP';
$val_dari_nama    = $alreadyMemo['dari_nama'] ?? ($data['atasan_langsung'] ?? '');
$val_tembusan     = $alreadyMemo['tembusan'] ?? 'HCGA Manager';
$val_ttd_jabatan  = $alreadyMemo['ttd_memo_jabatan'] ?? 'Aviation Services VP';
$val_ttd_nama     = $alreadyMemo['ttd_memo_nama'] ?? ($data['penilai_nama'] ?: ($data['atasan_langsung'] ?? ''));
$val_par1         = $alreadyMemo['isi_paragraf1'] ?? '';
$val_par2         = $alreadyMemo['isi_paragraf2'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Form Memo Perpanjangan Kontrak — <?= e($data['nama']) ?></title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
<style>
body { background: var(--bg); padding: 20px; display: block; font-family: 'Plus Jakarta Sans', var(--font-body); }

.page-wrap { max-width: 900px; margin: 0 auto; }

/* ── SUKSES PAGE ── */
.sukses-wrap {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #090e1a;
  padding: 24px;
}
.sukses-card {
  background: #fff;
  border-radius: 14px;
  padding: 32px 28px;
  text-align: center;
  width: 100%;
  max-width: 460px;
  box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
  border: 1px solid rgba(255,255,255,0.1);
}
.sukses-icon {
  width: 50px; height: 50px;
  background: #f0fdf4;
  color: #16a34a;
  border: 1px solid #bbf7d0;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 14px;
}
.sukses-title {
  font-size: 18px; font-weight: 800;
  color: #0f172a; margin-bottom: 4px;
  letter-spacing: -0.015em;
}
.sukses-sub {
  font-size: 12.5px; color: #64748b;
  line-height: 1.55;
  margin-bottom: 18px;
}
.info-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 13px 15px;
  margin-bottom: 18px;
  text-align: left;
}
.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12.5px;
  padding: 6px 0;
  border-bottom: 1px solid #f1f5f9;
}
.info-row:last-child { border-bottom: none; }
.info-lbl { color: #64748b; font-size: 12px; }
.info-val { font-weight: 700; color: #0f172a; }
.btn-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.dl-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 9px 16px;
  border-radius: var(--radius);
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.15s;
  cursor: pointer;
  border: 1px solid transparent;
  width: 100%;
}
.dl-btn-primary {
  background: var(--navy);
  color: #fff;
  border-color: #020617;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.dl-btn-primary:hover { background: #1e293b; }
.dl-btn-outline {
  background: #fff;
  color: var(--gray-800);
  border: 1px solid var(--gray-300);
}
.dl-btn-outline:hover { background: var(--gray-50); border-color: var(--gray-400); }
.sukses-note {
  font-size: 11px; color: #94a3b8;
  margin-top: 16px;
  line-height: 1.5;
}
@media (max-width: 480px) {
  .sukses-card { padding: 24px 18px; }
  .sukses-title { font-size: 17px; }
  .dl-btn { font-size: 12.5px; padding: 8px 14px; }
}

.top-notice {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  color: #0f172a;
  border-radius: 10px;
  padding: 12px 16px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: var(--shadow-xs);
}
.top-notice .step-badge {
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #dbeafe;
  border-radius: 5px;
  padding: 4px 10px;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
}
.top-notice .step-text { font-size: 12.5px; color: #475569; }
.top-notice .step-text strong { font-size: 13.5px; display: block; margin-bottom: 1px; color: #0f172a; }

/* Status bar */
.status-bar {
  display: flex;
  gap: 6px;
  align-items: center;
  margin-bottom: 16px;
}
.status-step {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 12px;
  font-weight: 600;
}
.status-dot {
  width: 20px; height: 20px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700;
}
.status-dot.done  { background: #16a34a; color: #fff; }
.status-dot.active{ background: #2563eb; color: #fff; }
.status-dot.idle  { background: #f1f5f9; color: #94a3b8; border: 1px solid #cbd5e1; }
.status-line { flex: 1; height: 2px; background: #e2e8f0; }
.status-line.done { background: #16a34a; }

/* Summary card */
.summary-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 13px 16px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 14px;
  box-shadow: var(--shadow-xs);
}
.summary-score {
  width: 48px; height: 48px;
  border-radius: 8px;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  flex-shrink: 0;
}
.summary-score .s-num { font-size: 18px; font-weight: 900; line-height: 1; }
.summary-score .s-lbl { font-size: 8.5px; font-weight: 600; margin-top: 1px; opacity: 0.8; }
.summary-info { flex: 1; }
.summary-name { font-size: 14.5px; font-weight: 800; color: #0f172a; }
.summary-meta { font-size: 11.5px; color: #64748b; margin-top: 2px; }
.summary-status { flex-shrink: 0; }

/* Form section headers */
.section-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 14px;
  padding-bottom: 10px;
  border-bottom: 1px solid #f1f5f9;
}
.section-num {
  width: 20px; height: 20px;
  background: #0f172a;
  color: #fff;
  border-radius: 4px;
  font-size: 10.5px;
  font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}

/* Memo preview */
.memo-preview {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
  font-family: 'Times New Roman', Times, serif;
  font-size: 10.5pt;
}
.memo-preview-header {
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  padding: 10px 16px;
  font-family: 'Inter','Segoe UI',sans-serif;
  font-size: 12px;
  font-weight: 700;
  color: #1e6fd9;
  display: flex;
  align-items: center;
  gap: 8px;
}
.memo-body {
  padding: 28px 32px;
}
.memo-logo-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 22px;
}
.memo-title-text { font-size: 14pt; font-weight: bold; }
.memo-meta-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 10pt; }
.memo-meta-table td { padding: 2px 0; vertical-align: top; }
.memo-meta-table .lbl { width: 80px; white-space: nowrap; }
.memo-meta-table .sep { width: 14px; }
.memo-body p { line-height: 1.7; margin-bottom: 10px; font-size: 10.5pt; text-align: justify; }
.memo-tbl { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 10pt; }
.memo-tbl th { border: 1px solid #000; padding: 4px 8px; background: #f0f0f0; text-align: left; }
.memo-tbl td { border: 1px solid #000; padding: 4px 8px; }
.memo-sig-area { margin-top: 22px; display: flex; justify-content: flex-start; }
.memo-sig-block { min-width: 200px; }
.memo-sig-name { font-weight: bold; text-decoration: underline; font-size: 10.5pt; }
.memo-tembusan { margin-top: 20px; font-size: 10pt; }

/* Skip memo link */
.skip-link {
  text-align: center;
  font-size: 12.5px;
  color: #94a3b8;
  margin-top: 16px;
  font-family: 'Inter',sans-serif;
}
.skip-link a { color: #94a3b8; text-decoration: underline; }

@media print {
  body { background: #fff; padding: 0; }
  .no-print { display: none !important; }
  .memo-preview { border: none; border-radius: 0; box-shadow: none; }
  .memo-preview-header { display: none; }
}
</style>
</head>
<body>

<?php if($success): ?>
<!-- ═══════════ SUKSES ═══════════ -->
<div class="sukses-wrap">
  <div class="sukses-card">
    <div class="sukses-icon" style="background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;width:64px;height:64px;border-radius:50%;">
      <span style="display:inline-flex;transform:scale(1.3)"><?= icon('check') ?></span>
    </div>
    <h2 class="sukses-title">Penilaian &amp; Memo Tersimpan</h2>
    <p class="sukses-sub">
      Data penilaian dan berkas memo perpanjangan kontrak berhasil diterbitkan ke sistem HRD PT Indopelita.
    </p>

    <!-- Info Ringkasan -->
    <div class="info-card">
      <div class="info-row">
        <span class="info-lbl" style="display:inline-flex;align-items:center;gap:6px;"><?= icon('user') ?> Karyawan</span>
        <span class="info-val"><?= e($data['nama']) ?></span>
      </div>
      <div class="info-row">
        <span class="info-lbl" style="display:inline-flex;align-items:center;gap:6px;"><?= icon('calendar') ?> Periode</span>
        <span class="info-val"><?= e($data['periode']) ?></span>
      </div>
      <div class="info-row">
        <span class="info-lbl" style="display:inline-flex;align-items:center;gap:6px;"><?= icon('award') ?> Total Nilai</span>
        <span class="info-val" style="font-weight:700;color:<?= $diperpanjang?'#059669':'#e11d48' ?>">
          <?= $data['jumlah_nilai'] ?> / 50 Poin
        </span>
      </div>
      <div class="info-row">
        <span class="info-lbl" style="display:inline-flex;align-items:center;gap:6px;"><?= icon('clipboard') ?> Status Rekomendasi</span>
        <span class="info-val">
          <span class="badge <?= $diperpanjang ? 'badge-green' : 'badge-red' ?>">
            <span class="badge-dot"></span> <?= $diperpanjang ? 'Diperpanjang' : 'Tidak Diperpanjang' ?>
          </span>
        </span>
      </div>
    </div>

    <!-- Tombol Aksi -->
    <div class="btn-group">
      <a href="<?= APP_URL ?>/cetak_memo.php?id=<?= $memoId ?>"
         class="dl-btn dl-btn-primary" id="btnDownload" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;">
        <?= icon('printer') ?> Download / Cetak Memo PDF
      </a>
      <button class="dl-btn dl-btn-outline" onclick="shareOrCopy('<?= APP_URL ?>/cetak_memo.php?id=<?= $memoId ?>')" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;">
        <?= icon('share') ?> Bagikan Tautan Dokumen
      </button>
      <?php if(isAdminLoggedIn()): ?>
      <a href="<?= APP_URL ?>/admin/detail_memo.php?id=<?= $memoId ?>" class="dl-btn dl-btn-outline" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;">
        <?= icon('eye') ?> Lihat di Admin Panel
      </a>
      <a href="<?= APP_URL ?>/admin/detail_penilaian.php?id=<?= $penilaian_id ?>" class="dl-btn dl-btn-outline" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;">
        <?= icon('arrow-left') ?> Kembali ke Detail Penilaian
      </a>
      <?php endif; ?>
    </div>

    <p class="sukses-note">
      Dokumen ini telah terarsip secara elektronik dalam pangkalan data PT Indopelita.<br>
      Hubungi Departemen Human Capital untuk koordinasi lebih lanjut.
    </p>
  </div>
</div>
<script>
function shareOrCopy(url) {
  if (navigator.share) {
    navigator.share({ title: 'Memo Perpanjangan Kontrak', url: url });
  } else {
    navigator.clipboard.writeText(url).then(function() {
      alert('Tautan dokumen memo berhasil disalin ke clipboard.');
    }).catch(function() {
      prompt('Salin link ini:', url);
    });
  }
}
</script>
<?php exit; endif; ?>

<?php if($alreadyMemo && !$isEditing): ?>
<!-- ═══════════ SUDAH ADA MEMO ═══════════ -->
<div class="sukses-wrap">
  <div class="sukses-card">
    <div class="sukses-icon" style="background:#f1f5f9;color:#0f172a;border:1px solid #cbd5e1;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;width:64px;height:64px;border-radius:50%;">
      <span style="display:inline-flex;transform:scale(1.3)"><?= icon('file-text') ?></span>
    </div>
    <h2 class="sukses-title">Memorandum Telah Diterbitkan</h2>
    <p class="sukses-sub">
      Memo perpanjangan untuk <strong><?= e($data['nama']) ?></strong> telah teregistrasi dalam sistem
      (Nomor: <code style="font-size:13px;font-weight:600;color:#0f172a;background:#f1f5f9;padding:2px 8px;border-radius:6px;"><?= e($alreadyMemo['nomor_memo']) ?></code>).
    </p>
    <div class="btn-group">
      <a href="<?= APP_URL ?>/cetak_memo.php?id=<?= $alreadyMemo['id'] ?>"
         class="dl-btn dl-btn-primary" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;">
        <?= icon('printer') ?> Download / Cetak Memo PDF
      </a>
      <a href="<?= APP_URL ?>/form_memo.php?penilaian_id=<?= $penilaian_id ?>&edit=1"
         class="dl-btn dl-btn-outline" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;">
        <?= icon('edit') ?> Perbarui / Edit Data Memo
      </a>
      <?php if(isAdminLoggedIn()): ?>
      <a href="<?= APP_URL ?>/admin/detail_memo.php?id=<?= $alreadyMemo['id'] ?>"
         class="dl-btn dl-btn-outline" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;">
        <?= icon('eye') ?> Lihat Detail Memo
      </a>
      <a href="<?= APP_URL ?>/admin/detail_penilaian.php?id=<?= $penilaian_id ?>"
         class="dl-btn dl-btn-outline" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;">
        <?= icon('arrow-left') ?> Detail Penilaian
      </a>
      <a href="<?= APP_URL ?>/admin/penilaian.php"
         class="dl-btn dl-btn-outline" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;">
        <?= icon('clipboard') ?> Daftar Penilaian
      </a>
      <?php else: ?>
      <button class="dl-btn dl-btn-outline" onclick="shareOrCopy('<?= APP_URL ?>/cetak_memo.php?id=<?= $alreadyMemo['id'] ?>')" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;">
        <?= icon('share') ?> Bagikan Tautan Dokumen
      </button>
      <?php endif; ?>
    </div>
    <p class="sukses-note">Hubungi Departemen Human Capital jika terdapat revisi administratif.</p>
  </div>
</div>
<script>
function shareOrCopy(url) {
  if (navigator.share) {
    navigator.share({ title: 'Memo Perpanjangan Kontrak', url: url });
  } else {
    navigator.clipboard.writeText(url).then(function() {
      alert('Tautan memo berhasil disalin ke clipboard.');
    }).catch(function() {
      prompt('Salin link ini:', url);
    });
  }
}
</script>
<?php exit; endif; ?>

<?php if(isAdminLoggedIn()): ?>
<!-- Admin top breadcrumb -->
<div style="max-width:900px;margin:0 auto 16px;display:flex;justify-content:space-between;align-items:center;background:#fff;padding:12px 20px;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,0.05);border:1px solid #e2e8f0;" class="no-print">
  <div style="font-size:13px;font-weight:600;color:#0f172a;display:flex;align-items:center;gap:8px;">
    <span style="color:#0284c7;display:inline-flex;align-items:center;gap:6px;"><?= icon('shield') ?> <strong>Admin Portal:</strong></span>
    <span style="color:#64748b"><?= $alreadyMemo ? 'Edit Memo' : 'Penerbitan Memo' ?> untuk <strong><?= e($data['nama']) ?></strong></span>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="<?= APP_URL ?>/admin/detail_penilaian.php?id=<?= $penilaian_id ?>" class="btn btn-secondary btn-sm" style="display:inline-flex;align-items:center;gap:6px;"><?= icon('arrow-left') ?> Detail Penilaian</a>
    <a href="<?= APP_URL ?>/admin/penilaian.php" class="btn btn-secondary btn-sm" style="display:inline-flex;align-items:center;gap:6px;"><?= icon('clipboard') ?> Hasil Penilaian</a>
  </div>
</div>
<?php endif; ?>

<!-- ═══════════ FORM MEMO ═══════════ -->
<div class="page-wrap">

  <!-- Top Notice -->
  <div class="top-notice no-print" style="display:flex;align-items:center;gap:16px;">
    <div class="step-badge">Tahap 2 dari 2</div>
    <div class="step-text">
      <div style="font-weight:700;color:var(--navy-900);margin-bottom:2px;">Dokumen Memorandum Perpanjangan Kontrak</div>
      Penilaian kinerja telah diverifikasi. Silakan lengkapi formulir memorandum resmi sebelum penerbitan dokumen final.
    </div>
  </div>

  <!-- Progress Steps -->
  <div class="status-bar no-print">
    <div class="status-step">
      <div class="status-dot done" style="display:flex;align-items:center;justify-content:center;"><?= icon('check') ?></div>
      <span style="color:#059669;font-weight:600;">Penilaian Kinerja</span>
    </div>
    <div class="status-line done"></div>
    <div class="status-step">
      <div class="status-dot active">2</div>
      <span style="color:#0284c7;font-weight:600;">Penyusunan Memo</span>
    </div>
    <div class="status-line"></div>
    <div class="status-step">
      <div class="status-dot idle">3</div>
      <span style="color:#94a3b8">Pengesahan &amp; Cetak</span>
    </div>
  </div>

  <!-- Summary Karyawan -->
  <div class="summary-card no-print">
    <div class="summary-score" style="background:<?= $diperpanjang?'#ecfdf5':'#fff1f2' ?>">
      <div class="s-num" style="color:<?= $diperpanjang?'#059669':'#e11d48' ?>"><?= $data['jumlah_nilai'] ?></div>
      <div class="s-lbl" style="color:<?= $diperpanjang?'#059669':'#e11d48' ?>">/ 50 Poin</div>
    </div>
    <div class="summary-info">
      <div class="summary-name"><?= e($data['nama']) ?></div>
      <div class="summary-meta"><?= e($data['jabatan']) ?> &nbsp;·&nbsp; NIK: <?= e($data['nik']) ?> &nbsp;·&nbsp; Periode: <?= e($data['periode']) ?></div>
    </div>
    <div class="summary-status">
      <?php if($diperpanjang): ?>
        <span class="badge badge-green" style="font-size:12px;padding:6px 14px"><span class="badge-dot"></span> DIPERPANJANG</span>
      <?php else: ?>
        <span class="badge badge-red" style="font-size:12px;padding:6px 14px"><span class="badge-dot"></span> TIDAK DIPERPANJANG</span>
      <?php endif; ?>
    </div>
  </div>

  <?php if($errors): ?>
  <div class="alert alert-error no-print">
    <span class="alert-icon"><?= icon('alert-circle') ?></span>
    <div>
      <strong>Harap periksa kolom berikut:</strong>
      <ul style="margin:6px 0 0 16px">
        <?php foreach($errors as $e2): ?><li><?= e($e2) ?></li><?php endforeach; ?>
      </ul>
    </div>
  </div>
  <?php endif; ?>

  <form method="POST" id="memoForm">
  <input type="hidden" name="aksi" value="simpan_memo">

  <!-- ── FORM SECTION ── -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:20px;" class="no-print">

    <!-- Kiri: Header Memo -->
    <div class="form-card">
      <div class="section-header"><div class="section-num">1</div>Header Memorandum</div>
      <div class="form-grid" style="gap:13px;">
        <div class="form-group">
          <label class="form-label">Kota <span class="req">*</span></label>
          <input type="text" name="kota_memo" class="form-control" value="<?= e($val_kota) ?>" oninput="updatePreview()">
        </div>
        <div class="form-group">
          <label class="form-label">Tanggal Memo <span class="req">*</span></label>
          <input type="date" name="tanggal_memo" class="form-control" value="<?= e($val_tgl) ?>" required oninput="updatePreview()">
        </div>
        <div class="form-group">
          <label class="form-label">Nomor Memo <span class="req">*</span>
            <span style="font-weight:400;color:var(--text-muted);font-size:11px"> — contoh: VP/TA/IAS/XI/129/2025</span>
          </label>
          <input type="text" name="nomor_memo" id="nomor_memo" class="form-control" value="<?= e($val_nomor) ?>" placeholder="VP/TA/IAS/XI/129/2025" required oninput="updatePreview()">
        </div>
        <div class="form-group">
          <label class="form-label">Kepada (Jabatan Penerima) <span class="req">*</span></label>
          <input type="text" name="kepada_jabatan" class="form-control"
                 value="<?= e($val_kepada) ?>" required oninput="updatePreview()"
                 list="kepada-list">
          <datalist id="kepada-list">
            <option value="Finance &amp; General Affair VP">
            <option value="Human Capital &amp; General Affair Manager">
            <option value="HCGA Manager">
            <option value="Human Capital &amp; General Affair VP">
          </datalist>
        </div>
        <div class="form-group">
          <label class="form-label">Perihal</label>
          <input type="text" name="perihal" class="form-control" value="<?= e($val_perihal) ?>" oninput="updatePreview()">
        </div>
        <div class="form-group">
          <label class="form-label">Referensi Kontrak (opsional)
            <span style="font-weight:400;color:var(--text-muted);font-size:11px"> — contoh: No. 4710005301</span>
          </label>
          <input type="text" name="ref_kontrak" class="form-control" value="<?= e($val_ref) ?>" placeholder="Nomor kontrak / NUP (jika ada)" oninput="updatePreview()">
        </div>
      </div>
    </div>

    <!-- Kanan: Detail Kontrak -->
    <div class="form-card">
      <div class="section-header"><div class="section-num">2</div>Detail Kontrak Karyawan</div>
      <div class="form-grid" style="gap:13px;">
        <div class="form-group">
          <label class="form-label">No. Pegawai <span style="font-weight:400;color:var(--text-muted);font-size:11px">(opsional)</span></label>
          <input type="text" name="no_pegawai" class="form-control"
                 value="<?= e($val_no_pegawai) ?>" oninput="updatePreview()"
                 placeholder="NIK / No. Pegawai di perusahaan">
        </div>
        <div class="form-group">
          <label class="form-label">Kualifikasi / Jabatan dalam Memo <span class="req">*</span></label>
          <input type="text" name="kualifikasi_memo" id="kualifikasi_memo" class="form-control"
                 value="<?= e($val_kualifikasi) ?>" required oninput="updatePreview()"
                 placeholder="Technician / Engineering / PA Leader...">
        </div>
        <div class="form-group">
          <label class="form-label">Tanggal Mulai Kontrak <span class="req">*</span></label>
          <input type="date" name="tanggal_mulai_kontrak" id="tgl_mulai" class="form-control"
                 value="<?= e($val_mulai) ?>" required oninput="updatePreview()">
        </div>
        <div class="form-group">
          <label class="form-label">Tanggal Akhir Kontrak <span class="req">*</span></label>
          <input type="date" name="tanggal_akhir_kontrak" id="tgl_akhir" class="form-control"
                 value="<?= e($val_akhir) ?>" required oninput="updatePreview()">
        </div>

        <!-- ── Status Perpanjangan ── -->
        <div class="form-group">
          <label class="form-label">Status Perpanjangan <span class="req">*</span>
            <span style="font-weight:400;color:var(--text-muted);font-size:11px"> — akan muncul sebagai centang (✓) di tabel memo</span>
          </label>
          <div style="display:flex;gap:10px;margin-top:6px;">
            <label id="lbl-diperpanjang" style="
              flex:1;display:flex;align-items:center;justify-content:center;gap:8px;
              padding:11px 14px;border-radius:10px;cursor:pointer;font-weight:600;
              font-size:13.5px;border:1.5px solid;
              transition:all 0.18s;
              <?= $diperpanjang ? 'background:#ecfdf5;border-color:#10b981;color:#047857;' : 'background:#f8fafc;border-color:#e2e8f0;color:#64748b;' ?>
            ">
              <input type="radio" name="status_memo" value="Diperpanjang"
                     id="radio_diperpanjang"
                     <?= $diperpanjang ? 'checked' : '' ?>
                     onchange="syncStatusToggle()" style="display:none">
              <span style="display:inline-flex;align-items:center;"><?= icon('check-circle') ?></span> Diperpanjang
            </label>
            <label id="lbl-tidak" style="
              flex:1;display:flex;align-items:center;justify-content:center;gap:8px;
              padding:11px 14px;border-radius:10px;cursor:pointer;font-weight:600;
              font-size:13.5px;border:1.5px solid;
              transition:all 0.18s;
              <?= !$diperpanjang ? 'background:#fff1f2;border-color:#f43f5e;color:#be123c;' : 'background:#f8fafc;border-color:#e2e8f0;color:#64748b;' ?>
            ">
              <input type="radio" name="status_memo" value="Tidak Diperpanjang"
                     id="radio_tidak"
                     <?= !$diperpanjang ? 'checked' : '' ?>
                     onchange="syncStatusToggle()" style="display:none">
              <span style="display:inline-flex;align-items:center;"><?= icon('x-circle') ?></span> Tidak Diperpanjang
            </label>
          </div>
        </div>

      </div>
    </div>

  </div>

  <!-- ── Pengirim & TTD ── -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:20px;" class="no-print">

    <div class="form-card">
      <div class="section-header"><div class="section-num">3</div>Pengirim Memorandum</div>
      <div class="form-grid" style="gap:13px;">
        <div class="form-group">
          <label class="form-label">Jabatan Pengirim <span class="req">*</span></label>
          <input type="text" name="dari_jabatan" class="form-control"
                 value="<?= e($val_dari_jabatan) ?>" required oninput="updatePreview()"
                 list="dari-list">
          <datalist id="dari-list">
            <option value="Aviation Services VP">
            <option value="Industrial Field Services VP">
            <option value="Industrial Field Services Manager">
            <option value="Engineering Services Manager">
            <option value="Operations Manager">
          </datalist>
        </div>
        <div class="form-group">
          <label class="form-label">Nama Pengirim <span class="req">*</span></label>
          <input type="text" name="dari_nama" class="form-control"
                 value="<?= e($val_dari_nama) ?>"
                 required oninput="updatePreview()"
                 placeholder="Nama pengirim memo">
        </div>
        <div class="form-group">
          <label class="form-label">Tembusan <span style="font-weight:400;color:var(--text-muted);font-size:11px">(pisah per baris)</span></label>
          <textarea name="tembusan" class="form-control" rows="3"
                    oninput="updatePreview()"
                    placeholder="HCGA Manager&#10;Finance VP&#10;Arsip"><?= e($val_tembusan) ?></textarea>
        </div>
      </div>
    </div>

    <div class="form-card">
      <div class="section-header"><div class="section-num">4</div>Otoritas Pengesahan Memo</div>
      <div class="form-grid" style="gap:13px;">
        <div class="form-group">
          <label class="form-label">Jabatan Penandatangan <span class="req">*</span></label>
          <input type="text" name="ttd_memo_jabatan" class="form-control"
                 value="<?= e($val_ttd_jabatan) ?>" required oninput="updatePreview()">
        </div>
        <div class="form-group">
          <label class="form-label">Nama Penandatangan <span class="req">*</span></label>
          <input type="text" name="ttd_memo_nama" class="form-control"
                 value="<?= e($val_ttd_nama) ?>"
                 required oninput="updatePreview()"
                 placeholder="Nama lengkap penandatangan">
        </div>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:var(--radius);padding:12px 14px;font-size:12.5px;color:#475569;line-height:1.6;display:flex;align-items:flex-start;gap:8px;">
          <span style="color:#0284c7;flex-shrink:0;margin-top:2px;"><?= icon('info') ?></span>
          <div><strong>Pengesahan Fisik:</strong> Tanda tangan fisik dan stempel perusahaan akan dibubuhkan langsung saat dokumen dicetak.</div>
        </div>
      </div>
    </div>

  </div>

  <!-- ── Section 5: Isi Memo ── -->
  <div style="margin-bottom:20px;" class="no-print">
    <div class="form-card">
      <div class="section-header"><div class="section-num">5</div>Redaksi Paragraf Memorandum
        <span style="font-weight:400;font-size:12px;color:var(--text-muted);margin-left:6px">— disinkronkan otomatis sesuai status perpanjangan</span>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-group">
          <label class="form-label">Paragraf 1 — Pembuka Dokumen</label>
          <textarea name="isi_paragraf1" id="isi_paragraf1" class="form-control" rows="4"
                    oninput="updatePreview()"
                    placeholder="Teks paragraf pertama memo..."
          ><?= e($val_par1 ?: ($diperpanjang
            ? 'Bersama ini kami sampaikan daftar nama para Personel yang masih dibutuhkan, dikarenakan terkait dengan Capability Perusahaan serta kebutuhan perusahaan.'
            : 'Bersama ini kami sampaikan daftar nama para Personel yang masa kontraknya telah berakhir dan tidak dapat diperpanjang, dikarenakan berbagai pertimbangan perusahaan.')) ?></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Paragraf 2 — Pengantar Tabel Nominatif</label>
          <textarea name="isi_paragraf2" id="isi_paragraf2" class="form-control" rows="4"
                    oninput="updatePreview()"
                    placeholder="Teks paragraf kedua memo..."
          ><?= e($val_par2 ?: ($diperpanjang
            ? 'Maka sehubungan dengan hal tersebut diatas, mohon agar kiranya dapat dilakukan perpanjangan masa kerja, dengan pengantar sebagai berikut :'
            : 'Maka sehubungan dengan hal tersebut diatas, dengan berat hati kami sampaikan bahwa masa kerja personel berikut tidak dapat diperpanjang, dengan keterangan sebagai berikut :')) ?></textarea>
        </div>
      </div>
      <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 14px;font-size:12px;color:#0369a1;margin-top:10px;display:flex;align-items:center;gap:8px;">
        <span style="flex-shrink:0"><?= icon('info') ?></span>
        <span>Redaksi kalimat tersinkronisasi dinamis ketika status perpanjangan dialihkan, dan tetap dapat disesuaikan manual sesuai kebutuhan.</span>
      </div>
    </div>
  </div>

  <!-- PREVIEW MEMO LIVE -->
  <div style="margin-bottom:20px;">
    <div class="memo-preview" id="memoPreview">
      <div class="memo-preview-header no-print" style="display:flex;align-items:center;gap:8px;">
        <?= icon('eye') ?> Live Preview Dokumen Memorandum Resmi (terkalkulasi otomatis)
      </div>
      <div class="memo-body" id="memoBodyContent">
        <!-- Diisi oleh JS -->
      </div>
    </div>
  </div>

  <!-- SUBMIT -->
  <div style="text-align:center;margin-bottom:30px;" class="no-print">
    <button type="submit" class="btn btn-primary btn-lg"
            style="padding:14px 44px;font-size:15px;display:inline-flex;align-items:center;gap:10px;justify-content:center;margin:0 auto;">
      <?= icon('save') ?> Terbitkan &amp; Simpan Memorandum
    </button>
    <div style="font-size:12px;color:var(--text-muted);margin-top:10px;">
      Dokumen memorandum yang diterbitkan akan langsung terintegrasi dengan lembar penilaian terkait.
    </div>
    <div class="skip-link" style="margin-top:12px">
      <a href="<?= APP_URL ?>/form_memo_skip.php?token=<?= urlencode($token) ?>" onclick="return confirm('Lewati pengisian memo? Data penilaian sudah tersimpan.')" style="color:var(--text-muted);font-size:13px;text-decoration:none;">
        Lewati pengisian memo &rarr;
      </a>
    </div>
  </div>

  </form>
</div>

<script>
const namaKaryawan = <?= json_encode($data['nama']) ?>;
const jabatanKaryawan = <?= json_encode($data['jabatan']) ?>;
const noPegawaiDefault = <?= json_encode($data['nik']) ?>;
const periodeStr = <?= json_encode($data['periode']) ?>;

const bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
function tglIndo(val) {
  if (!val) return '...';
  const [y,m,d] = val.split('-');
  return parseInt(d) + ' ' + (bulan[parseInt(m)] || m) + ' ' + y;
}
function fmtTglMemo(val) {
  if (!val) return '...';
  const [y,m,d] = val.split('-');
  return (bulan[parseInt(m)] || m).substring(0,3).toUpperCase() + ' ' + d + ', ' + y;
}
function gv(name) {
  const el = document.querySelector(`[name="${name}"]`);
  return el ? el.value : '';
}

function syncStatusToggle() {
  const dip  = document.getElementById('radio_diperpanjang').checked;
  const lblD = document.getElementById('lbl-diperpanjang');
  const lblT = document.getElementById('lbl-tidak');
  if (dip) {
    lblD.style.cssText += 'background:#f0fdf4;border-color:#22c55e;color:#15803d;';
    lblT.style.cssText += 'background:#f8fafc;border-color:#e2e8f0;color:#64748b;';
    // Auto-isi teks default Diperpanjang
    document.getElementById('isi_paragraf1').value = 'Bersama ini kami sampaikan daftar nama para Personel yang masih dibutuhkan, dikarenakan terkait dengan Capability Perusahaan serta kebutuhan perusahaan.';
    document.getElementById('isi_paragraf2').value = 'Maka sehubungan dengan hal tersebut diatas, mohon agar kiranya dapat dilakukan perpanjangan masa kerja, dengan pengantar sebagai berikut :';
  } else {
    lblD.style.cssText += 'background:#f8fafc;border-color:#e2e8f0;color:#64748b;';
    lblT.style.cssText += 'background:#fef2f2;border-color:#ef4444;color:#b91c1c;';
    // Auto-isi teks default Tidak Diperpanjang
    document.getElementById('isi_paragraf1').value = 'Bersama ini kami sampaikan daftar nama para Personel yang masa kontraknya telah berakhir dan tidak dapat diperpanjang, dikarenakan berbagai pertimbangan perusahaan.';
    document.getElementById('isi_paragraf2').value = 'Maka sehubungan dengan hal tersebut diatas, dengan berat hati kami sampaikan bahwa masa kerja personel berikut tidak dapat diperpanjang, dengan keterangan sebagai berikut :';
  }
  updatePreview();
}

function updatePreview() {
  const kota      = gv('kota_memo') || 'Tangerang Selatan';
  const tgl       = gv('tanggal_memo');
  const nomor     = gv('nomor_memo') || '.../.../.../.../...';
  const kepada    = gv('kepada_jabatan') || '...';
  const dari      = gv('dari_jabatan') || '...';
  const perihal   = gv('perihal') || 'Permohonan Perpanjangan Kesepakatan Kerja Personil';
  const kualif    = gv('kualifikasi_memo') || jabatanKaryawan;
  const mulai     = gv('tanggal_mulai_kontrak');
  const akhir     = gv('tanggal_akhir_kontrak');
  const noPeg     = gv('no_pegawai') || noPegawaiDefault;
  const ref       = gv('ref_kontrak');
  const ttdJab    = gv('ttd_memo_jabatan') || '...';
  const ttdNama   = gv('ttd_memo_nama') || '...';
  const tembusanRaw = gv('tembusan');
  const tembusanList = tembusanRaw.split('\n').filter(t=>t.trim());
  // Status perpanjangan dari radio
  const statusMemoEl = document.querySelector('[name="status_memo"]:checked');
  const statusMemo   = statusMemoEl ? statusMemoEl.value : 'Diperpanjang';
  const isDip        = (statusMemo === 'Diperpanjang');
  // Isi paragraf dari textarea
  const par1 = gv('isi_paragraf1') || (isDip
    ? 'Bersama ini kami sampaikan daftar nama para Personel yang masih dibutuhkan, dikarenakan terkait dengan Capability Perusahaan serta kebutuhan perusahaan.'
    : 'Bersama ini kami sampaikan daftar nama para Personel yang masa kontraknya telah berakhir dan tidak dapat diperpanjang, dikarenakan berbagai pertimbangan perusahaan.');
  const par2 = gv('isi_paragraf2') || (isDip
    ? 'Maka sehubungan dengan hal tersebut diatas, mohon agar kiranya dapat dilakukan perpanjangan masa kerja, dengan pengantar sebagai berikut :'
    : 'Maka sehubungan dengan hal tersebut diatas, dengan berat hati kami sampaikan bahwa masa kerja personel berikut tidak dapat diperpanjang, dengan keterangan sebagai berikut :');

  const APP_URL_JS = <?= json_encode(APP_URL) ?>;

  let refBlock = '';
  if (ref) {
    refBlock = `<p>Reff: ${ref}</p>`;
  }

  let tembusanHtml = '';
  if (tembusanList.length > 0) {
    tembusanHtml = `
      <div class="memo-tembusan">
        <p>Tembusan :</p>
        ${tembusanList.map(t=>`<p style="margin:0 0 2px 16px">- ${t.trim()}</p>`).join('')}
      </div>`;
  }

  document.getElementById('memoBodyContent').innerHTML = `
    <div class="memo-logo-row">
      <div class="memo-title-text">MEMORANDUM</div>
      <img src="${APP_URL_JS}/assets/img/logo_indopelita.jpg" style="height:70px;display:block;margin-left:auto;margin-top:-10px">
    </div>

    <table class="memo-meta-table">
      <tr><td colspan="3" style="white-space:nowrap">${kota}, ${tgl ? tglIndo(tgl) : '...'}</td></tr>
      <tr><td class="lbl">Nomor</td><td class="sep">:</td><td>${nomor}</td></tr>
      <tr><td class="lbl"></td></tr>
      <tr><td class="lbl">Kepada</td><td class="sep">:</td><td>${kepada}</td></tr>
      <tr><td class="lbl">Dari</td><td class="sep">:</td><td>${dari}</td></tr>
      <tr><td class="lbl"></td></tr>
      <tr><td class="lbl">Lampiran</td><td class="sep">:</td><td>-</td></tr>
      <tr><td class="lbl">Perihal</td><td class="sep">:</td><td><strong>${perihal}</strong></td></tr>
    </table>

    ${refBlock}

    <p>${par1}</p>
    <p>${par2}</p>

    <table class="memo-tbl">
      <thead>
        <tr>
          <th>No.</th>
          <th>Personil</th>
          <th>Kualifikasi</th>
          <th>Tanggal Dibutuhkan</th>
          <th style="text-align:center;width:80px">Diperpanjang</th>
          <th style="text-align:center;width:90px">Tidak Diperpanjang</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>01.</td>
          <td>${namaKaryawan}</td>
          <td>${kualif}</td>
          <td>${mulai && akhir ? tglIndo(mulai) + ' – ' + tglIndo(akhir) : '...'}</td>
          <td style="text-align:center;font-size:16px;font-weight:bold">${isDip ? '✓' : ''}</td>
          <td style="text-align:center;font-size:16px;font-weight:bold">${!isDip ? '✓' : ''}</td>
        </tr>
      </tbody>
    </table>

    <p>Demikian disampaikan, atas perhatian dan kerjasamanya diucapkan terima kasih.</p>

    <div class="memo-sig-area">
      <div class="memo-sig-block">
        <p>${ttdJab}</p>
        <br><br><br>
        <p class="memo-sig-name">${ttdNama}</p>
      </div>
    </div>

    ${tembusanHtml}
  `;
}

// Init on load
updatePreview();
</script>
</body>
</html>
