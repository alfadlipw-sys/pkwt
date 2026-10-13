<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$db = db();

// ── HELPER: query aman, tidak crash jika kolom/tabel tidak ada ───────────────
function dbval($db, $sql, $col = 'c', $default = 0) {
    $res = $db->query($sql);
    if (!$res) return $default;
    $row = $res->fetch_assoc();
    return $row[$col] ?? $default;
}
function dbrow($db, $sql) {
    $res = $db->query($sql);
    if (!$res) return null;
    return $res->fetch_assoc();
}
function dbresult($db, $sql) {
    $res = $db->query($sql);
    if (!$res) return null;
    return $res;
}

// ── Cek kolom yang ada di tabel (agar query dinamis) ─────────────────────────
function hasColumn($db, $table, $column) {
    $res = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $res && $res->num_rows > 0;
}
$ft_has_updated_at  = hasColumn($db, 'form_tokens', 'updated_at');
$ft_has_created_at  = hasColumn($db, 'form_tokens', 'created_at');
$kar_has_created_at = hasColumn($db, 'karyawan', 'created_at');
$kar_has_dept       = hasColumn($db, 'karyawan', 'departemen');
$kar_has_tgl_mulai  = hasColumn($db, 'karyawan', 'tanggal_mulai');
$kar_has_tgl_selesai= hasColumn($db, 'karyawan', 'tanggal_selesai');
$pen_has_created_at = hasColumn($db, 'penilaian', 'created_at');

// ── KPI UTAMA ────────────────────────────────────────────────────────────────
$total_karyawan = dbval($db, "SELECT COUNT(*) c FROM karyawan WHERE is_active=1");
$total_selesai  = dbval($db, "SELECT COUNT(*) c FROM form_tokens WHERE is_submitted=1");
$total_pending  = dbval($db, "SELECT COUNT(*) c FROM form_tokens WHERE is_submitted=0 AND expires_at>=NOW()");
$total_expired  = dbval($db, "SELECT COUNT(*) c FROM form_tokens WHERE is_submitted=0 AND expires_at<NOW()");
$diperpanjang   = dbval($db, "SELECT COUNT(*) c FROM penilaian WHERE status_perpanjangan='Diperpanjang'");
$tidak          = dbval($db, "SELECT COUNT(*) c FROM penilaian WHERE status_perpanjangan='Tidak Diperpanjang'");
$avg_nilai      = dbval($db, "SELECT ROUND(AVG(jumlah_nilai),1) v FROM penilaian", 'v', 0);
$lulus_pct      = ($diperpanjang + $tidak) > 0 ? round($diperpanjang / ($diperpanjang + $tidak) * 100) : 0;

// KPI bulan ini vs bulan lalu — hanya jika kolom ada
$karyawan_baru = 0;
if ($kar_has_created_at) {
    $karyawan_baru = dbval($db, "SELECT COUNT(*) c FROM karyawan WHERE is_active=1 AND created_at>=DATE_FORMAT(NOW(),'%Y-%m-01')");
}

$selesai_bulan = 0;
$selesai_lalu  = 0;
if ($ft_has_updated_at) {
    $selesai_bulan = dbval($db, "SELECT COUNT(*) c FROM form_tokens WHERE is_submitted=1 AND updated_at>=DATE_FORMAT(NOW(),'%Y-%m-01')");
    $selesai_lalu  = dbval($db, "SELECT COUNT(*) c FROM form_tokens WHERE is_submitted=1 AND updated_at>=DATE_FORMAT(DATE_SUB(NOW(),INTERVAL 1 MONTH),'%Y-%m-01') AND updated_at<DATE_FORMAT(NOW(),'%Y-%m-01')");
} elseif ($ft_has_created_at) {
    // fallback: pakai created_at token sebagai proxy
    $selesai_bulan = dbval($db, "SELECT COUNT(*) c FROM form_tokens WHERE is_submitted=1 AND created_at>=DATE_FORMAT(NOW(),'%Y-%m-01')");
    $selesai_lalu  = dbval($db, "SELECT COUNT(*) c FROM form_tokens WHERE is_submitted=1 AND created_at>=DATE_FORMAT(DATE_SUB(NOW(),INTERVAL 1 MONTH),'%Y-%m-01') AND created_at<DATE_FORMAT(NOW(),'%Y-%m-01')");
}

$avg_lalu = 0;
if ($pen_has_created_at) {
    $avg_lalu = dbval($db, "SELECT ROUND(AVG(jumlah_nilai),1) v FROM penilaian WHERE created_at>=DATE_FORMAT(DATE_SUB(NOW(),INTERVAL 1 MONTH),'%Y-%m-01') AND created_at<DATE_FORMAT(NOW(),'%Y-%m-01')", 'v', 0);
}

// ── TREN 12 BULAN ────────────────────────────────────────────────────────────
$chartLabels = $chartLanjut = $chartTidak = $chartPending = [];
$bulanNama = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei','06'=>'Jun','07'=>'Jul','08'=>'Ags','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des'];

if ($pen_has_created_at) {
    $res = dbresult($db, "
        SELECT DATE_FORMAT(p.created_at,'%Y-%m') bln,
               SUM(p.status_perpanjangan='Diperpanjang') lanjut,
               SUM(p.status_perpanjangan='Tidak Diperpanjang') tidak
        FROM penilaian p
        WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY bln ORDER BY bln ASC
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            [, $m] = explode('-', $r['bln']);
            $chartLabels[] = ($bulanNama[$m] ?? $m) . ' ' . substr($r['bln'], 0, 4);
            $chartLanjut[] = (int)$r['lanjut'];
            $chartTidak[]  = (int)$r['tidak'];
        }
    }
} else {
    // Tidak ada kolom created_at di penilaian — ambil semua tanpa filter waktu
    $res = dbresult($db, "
        SELECT SUM(status_perpanjangan='Diperpanjang') lanjut,
               SUM(status_perpanjangan='Tidak Diperpanjang') tidak
        FROM penilaian
    ");
    if ($res) {
        $r = $res->fetch_assoc();
        $chartLabels[] = date('M Y');
        $chartLanjut[] = (int)($r['lanjut'] ?? 0);
        $chartTidak[]  = (int)($r['tidak'] ?? 0);
    }
}

// Pending per bulan
$pendingMap = [];
if ($ft_has_created_at) {
    $resPending = dbresult($db, "
        SELECT DATE_FORMAT(created_at,'%Y-%m') bln, COUNT(*) c
        FROM form_tokens
        WHERE is_submitted=0 AND created_at>=DATE_SUB(NOW(),INTERVAL 12 MONTH)
        GROUP BY bln ORDER BY bln ASC
    ");
    if ($resPending) {
        while ($r = $resPending->fetch_assoc()) $pendingMap[$r['bln']] = (int)$r['c'];
    }
}
foreach ($chartLabels as $i => $lbl) {
    $chartPending[] = 0;
}

// ── DISTRIBUSI NILAI ──────────────────────────────────────────────────────────
$dist = dbrow($db, "
    SELECT SUM(jumlah_nilai BETWEEN 10 AND 19) r10,
           SUM(jumlah_nilai BETWEEN 20 AND 27) r20,
           SUM(jumlah_nilai BETWEEN 28 AND 34) r28,
           SUM(jumlah_nilai BETWEEN 35 AND 42) r35,
           SUM(jumlah_nilai BETWEEN 43 AND 50) r43
    FROM penilaian
") ?? ['r10'=>0,'r20'=>0,'r28'=>0,'r35'=>0,'r43'=>0];

// ── RATA-RATA PER KOMPETENSI ──────────────────────────────────────────────────
// Cek kolom kompetensi yang benar-benar ada
$kompCols = [];
$kompMap  = [
    'sikap'       => 'sikap_nilai',
    'kerjasama'   => 'kerjasama_nilai',
    'inisiatif'   => 'inisiatif_nilai',
    'ketelitian'  => 'ketelitian_nilai',
    'ketepatan'   => 'ketepatan_nilai',
    'pengetahuan' => 'pengetahuan_nilai',
    'kualitas'    => 'kualitas_nilai',
    'kuantitas'   => 'kuantitas_nilai',
    'belajar'     => 'belajar_nilai',
    'tjawab'      => 'tjawab_nilai',
];
foreach ($kompMap as $alias => $col) {
    if (hasColumn($db, 'penilaian', $col)) {
        $kompCols[$alias] = "ROUND(AVG(`{$col}`),2) {$alias}";
    }
}

$avgK = null;
if (!empty($kompCols)) {
    $avgK = dbrow($db, "SELECT " . implode(', ', $kompCols) . " FROM penilaian");
}

// ── RATA-RATA NILAI PER BULAN (tren avg) ─────────────────────────────────────
$avgTrenLabels = $avgTrenData = [];
if ($pen_has_created_at) {
    $resAvg = dbresult($db, "
        SELECT DATE_FORMAT(created_at,'%Y-%m') bln, ROUND(AVG(jumlah_nilai),1) avg_v
        FROM penilaian
        WHERE created_at>=DATE_SUB(NOW(),INTERVAL 12 MONTH)
        GROUP BY bln ORDER BY bln ASC
    ");
    if ($resAvg) {
        while ($r = $resAvg->fetch_assoc()) {
            [, $m] = explode('-', $r['bln']);
            $avgTrenLabels[] = ($bulanNama[$m] ?? $m);
            $avgTrenData[]   = (float)$r['avg_v'];
        }
    }
} else {
    // Tidak ada created_at — tampilkan rata-rata keseluruhan saja
    if ($avg_nilai > 0) {
        $avgTrenLabels[] = date('M Y');
        $avgTrenData[]   = (float)$avg_nilai;
    }
}

// ── PERFORMA PER JABATAN ──────────────────────────────────────────────────────
$jabatanPerf = dbresult($db, "
    SELECT k.jabatan,
           ROUND(AVG(p.jumlah_nilai),1) avg_nilai,
           COUNT(*) total,
           SUM(p.status_perpanjangan='Diperpanjang') lulus
    FROM penilaian p
    JOIN karyawan k ON k.id = p.karyawan_id
    GROUP BY k.jabatan
    ORDER BY avg_nilai DESC
    LIMIT 8
");

// ── HEATMAP DEPARTEMEN ────────────────────────────────────────────────────────
$hmData = [];
if ($pen_has_created_at) {
    $deptCol = $kar_has_dept ? "k.departemen" : "k.jabatan";
    $hmQuery = dbresult($db, "
        SELECT
            IFNULL({$deptCol}, 'Lainnya') dept,
            DATE_FORMAT(p.created_at,'%Y-%m') bln,
            COUNT(*) c
        FROM penilaian p
        JOIN karyawan k ON k.id = p.karyawan_id
        WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY dept, bln
        ORDER BY dept, bln
    ");
    if ($hmQuery) {
        while ($r = $hmQuery->fetch_assoc()) {
            $hmData[$r['dept']][$r['bln']] = (int)$r['c'];
        }
    }
}

// ── RESPONS RATE TOKEN PER BULAN ──────────────────────────────────────────────
$respLabels = $respData = [];
if ($ft_has_created_at) {
    $resResp = dbresult($db, "
        SELECT DATE_FORMAT(created_at,'%Y-%m') bln,
               COUNT(*) total,
               SUM(is_submitted=1) submitted
        FROM form_tokens
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY bln ORDER BY bln ASC
    ");
    if ($resResp) {
        while ($r = $resResp->fetch_assoc()) {
            [, $m] = explode('-', $r['bln']);
            $respLabels[] = ($bulanNama[$m] ?? $m);
            $respData[]   = $r['total'] > 0 ? round($r['submitted'] / $r['total'] * 100) : 0;
        }
    }
}
if (empty($respLabels) && ($total_selesai + $total_pending + $total_expired) > 0) {
    $total_token  = $total_selesai + $total_pending + $total_expired;
    $respLabels[] = date('M Y');
    $respData[]   = round($total_selesai / $total_token * 100);
}

// ── FUNNEL PROSES ─────────────────────────────────────────────────────────────
$funnel_undangan  = dbval($db, "SELECT COUNT(*) c FROM form_tokens");
$funnel_dibuka    = dbval($db, "SELECT COUNT(DISTINCT token_id) c FROM penilaian");
$funnel_diisi     = $total_selesai;
$funnel_keputusan = $diperpanjang + $tidak;

// ── DISTRIBUSI DURASI KONTRAK ─────────────────────────────────────────────────
$kontrakLabels = $kontrakData = [];
if ($kar_has_tgl_mulai && $kar_has_tgl_selesai) {
    $kontrakQuery = dbresult($db, "
        SELECT
            CASE
                WHEN DATEDIFF(tanggal_selesai, tanggal_mulai) <= 92  THEN '3 bulan'
                WHEN DATEDIFF(tanggal_selesai, tanggal_mulai) <= 185 THEN '6 bulan'
                WHEN DATEDIFF(tanggal_selesai, tanggal_mulai) <= 370 THEN '1 tahun'
                ELSE '2 tahun'
            END durasi,
            COUNT(*) c
        FROM karyawan
        WHERE is_active=1
          AND tanggal_mulai IS NOT NULL
          AND tanggal_selesai IS NOT NULL
        GROUP BY durasi
        ORDER BY FIELD(durasi,'3 bulan','6 bulan','1 tahun','2 tahun')
    ");
    if ($kontrakQuery) {
        while ($r = $kontrakQuery->fetch_assoc()) {
            $kontrakLabels[] = $r['durasi'];
            $kontrakData[]   = (int)$r['c'];
        }
    }
}
if (empty($kontrakLabels)) {
    $kontrakLabels = ['Karyawan Aktif'];
    $kontrakData   = [(int)$total_karyawan];
}

// ── TABEL TERBARU & PENDING ───────────────────────────────────────────────────
$recentOrderBy = $pen_has_created_at ? "p.created_at DESC" : "p.id DESC";
$recent = dbresult($db, "
    SELECT p.id, p.jumlah_nilai, p.status_perpanjangan,
           " . ($pen_has_created_at ? "p.created_at," : "NULL AS created_at,") . "
           k.nama, k.jabatan, ft.periode
    FROM penilaian p
    JOIN karyawan k ON k.id = p.karyawan_id
    JOIN form_tokens ft ON ft.id = p.token_id
    ORDER BY {$recentOrderBy} LIMIT 7
");

$pendingOrderBy = $ft_has_created_at ? "ft.created_at DESC" : "ft.id DESC";
$pending = dbresult($db, "
    SELECT ft.token, ft.periode, ft.expires_at, k.nama, k.jabatan
    FROM form_tokens ft
    JOIN karyawan k ON k.id = ft.karyawan_id
    WHERE ft.is_submitted=0 AND ft.expires_at>=NOW()
    ORDER BY {$pendingOrderBy} LIMIT 6
");

// ── AKTIVITAS TERBARU (feed) ──────────────────────────────────────────────────
$feedOrderBy = $pen_has_created_at ? "p.created_at DESC" : "p.id DESC";
$feed = dbresult($db, "
    SELECT p.status_perpanjangan, p.jumlah_nilai,
           " . ($pen_has_created_at ? "p.created_at," : "NULL AS created_at,") . "
           k.nama, k.jabatan
    FROM penilaian p
    JOIN karyawan k ON k.id = p.karyawan_id
    ORDER BY {$feedOrderBy} LIMIT 8
");

$pageTitle = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/admin_header.php';

// Helper: diff nilai
$diff_lulus = $lulus_pct - 74; // 74% = target baseline, sesuaikan
$diff_avg   = $avg_nilai - $avg_lalu;
?>

<style>
/* ── RESET & BASE ─────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

/* ── HEADER ──────────────────────────────────────────────────── */
.db-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1.25rem 0 1rem;
    border-bottom: 0.5px solid var(--border);
    margin-bottom: 1.5rem;
}
.db-header h1 { font-size: 20px; font-weight: 600; }
.db-header p  { font-size: 13px; color: var(--gray-400); margin-top: 3px; }
.db-period-pills { display: flex; gap: 6px; }
.pill-btn {
    font-size: 12px; padding: 5px 14px; border-radius: 20px; cursor: pointer;
    border: 1px solid var(--border); background: transparent;
    color: var(--gray-500); transition: all .15s;
}
.pill-btn:hover { background: var(--gray-50); }
.pill-btn.active {
    background: var(--blue); color: #fff; border-color: var(--blue);
}

/* ── KPI GRID ────────────────────────────────────────────────── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}
.kpi-card {
    background: var(--white);
    border: 0.5px solid var(--border);
    border-radius: 10px;
    padding: 14px 16px;
    position: relative;
    overflow: hidden;
}
.kpi-label {
    font-size: 11px; color: var(--gray-400);
    text-transform: uppercase; letter-spacing: .05em;
    margin-bottom: 8px;
}
.kpi-value {
    font-size: 28px; font-weight: 700; line-height: 1;
    color: var(--gray-700);
}
.kpi-value span { font-size: 16px; font-weight: 400; }
.kpi-badge {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 10px; font-weight: 600;
    padding: 2px 8px; border-radius: 10px;
    margin-top: 6px;
}
.badge-up   { background: #dcfce7; color: #16a34a; }
.badge-down { background: #fee2e2; color: #dc2626; }
.badge-neu  { background: #f1f5f9; color: #64748b; }
.kpi-spark  { display: flex; align-items: flex-end; gap: 2px; height: 28px; margin-top: 8px; }
.kpi-spark-bar { flex: 1; border-radius: 2px 2px 0 0; min-width: 5px; }

/* ── SECTION LABEL ───────────────────────────────────────────── */
.section-label {
    font-size: 11px; font-weight: 600; color: var(--gray-400);
    text-transform: uppercase; letter-spacing: .08em;
    margin: 1.5rem 0 .9rem;
}

/* ── CARD ────────────────────────────────────────────────────── */
.db-card {
    background: var(--white);
    border: 0.5px solid var(--border);
    border-radius: 12px;
    padding: 16px 18px;
    overflow: hidden;
}
.db-card-hdr {
    display: flex; align-items: flex-start; justify-content: space-between;
    margin-bottom: 14px;
}
.db-card-title { font-size: 13px; font-weight: 600; color: var(--gray-700); }
.db-card-sub   { font-size: 11px; color: var(--gray-400); margin-top: 2px; }
.db-card-tag   {
    font-size: 11px; color: var(--gray-400);
    background: var(--gray-50); padding: 2px 8px; border-radius: 8px;
    white-space: nowrap;
}

/* ── GRID LAYOUTS ────────────────────────────────────────────── */
.grid-2-1 { display: grid; grid-template-columns: 1.65fr 1fr; gap: 14px; margin-bottom: 14px; }
.grid-3   { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 14px; }
.grid-2   { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
.grid-3b  { display: grid; grid-template-columns: 1.5fr 1fr 1fr; gap: 14px; margin-bottom: 14px; }

@media (max-width: 900px) {
    .grid-2-1, .grid-3, .grid-2, .grid-3b { grid-template-columns: 1fr; }
}

/* ── LEGEND ──────────────────────────────────────────────────── */
.chart-legend { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 10px; }
.legend-item  { display: flex; align-items: center; gap: 5px; font-size: 11px; color: var(--gray-400); }
.leg-dot      { width: 8px; height: 8px; border-radius: 2px; flex-shrink: 0; }

/* ── PROGRESS BARS ───────────────────────────────────────────── */
.prog-row   { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.prog-label { font-size: 11px; color: var(--gray-500); width: 82px; flex-shrink: 0; text-align: right; }
.prog-bg    { flex: 1; height: 6px; background: var(--gray-100); border-radius: 4px; overflow: hidden; }
.prog-fill  { height: 100%; border-radius: 4px; transition: width .8s cubic-bezier(.4,0,.2,1); }
.prog-val   { font-size: 12px; font-weight: 600; color: var(--gray-700); width: 32px; text-align: right; flex-shrink: 0; }

/* ── DONUT SIDEBAR ───────────────────────────────────────────── */
.donut-row  { display: flex; align-items: center; gap: 16px; }
.donut-stat { flex: 1; }
.donut-stat-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 6px 0; border-bottom: 0.5px solid var(--border);
    font-size: 12px;
}
.donut-stat-row:last-child { border-bottom: none; }

/* ── HEATMAP ─────────────────────────────────────────────────── */
.heatmap-wrap { overflow-x: auto; }
.heatmap-grid {
    display: grid;
    gap: 2px;
    font-size: 10px;
    min-width: 380px;
}
.hm-head  { text-align: center; color: var(--gray-400); padding: 2px 0; }
.hm-label { color: var(--gray-400); line-height: 20px; text-align: right; padding-right: 4px; font-size: 10px; }
.hm-cell  { height: 20px; border-radius: 2px; cursor: default; }

/* ── RANK TABLE ──────────────────────────────────────────────── */
.rank-tbl { width: 100%; border-collapse: collapse; font-size: 12px; }
.rank-tbl th {
    font-weight: 600; color: var(--gray-400); text-align: left;
    padding: 4px 6px; border-bottom: 0.5px solid var(--border); font-size: 11px;
}
.rank-tbl td { padding: 8px 6px; border-bottom: 0.5px solid var(--border); color: var(--gray-700); vertical-align: middle; }
.rank-tbl tr:last-child td { border-bottom: none; }

/* ── FEED ────────────────────────────────────────────────────── */
.feed-item { display: flex; gap: 10px; padding: 8px 0; border-bottom: 0.5px solid var(--border); }
.feed-item:last-child { border-bottom: none; }
.feed-dot  { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
.feed-text { font-size: 12px; color: var(--gray-700); line-height: 1.5; }
.feed-time { font-size: 11px; color: var(--gray-400); margin-top: 2px; }

/* ── FUNNEL STAT ─────────────────────────────────────────────── */
.funnel-stat-row { display:flex; justify-content:space-between; align-items:center; padding:5px 0; font-size:12px; border-bottom:0.5px solid var(--border); }
.funnel-stat-row:last-child { border-bottom:none; }
.funnel-pct { font-size:11px; color:var(--gray-400); }
</style>

<!-- ══ HEADER ══════════════════════════════════════════════════════════════ -->
<div class="db-header">
    <div>
        <h1>Dashboard Kinerja PKWT</h1>
        <p>Data penilaian karyawan kontrak &mdash; <?= date('F Y') ?></p>
    </div>
    <div class="db-period-pills">
        <button class="pill-btn active">Semua Periode</button>
        <button class="pill-btn">Q1</button>
        <button class="pill-btn">Q2</button>
        <button class="pill-btn">YTD</button>
    </div>
</div>

<!-- ══ KPI ROW 1 ════════════════════════════════════════════════════════════ -->
<div class="kpi-grid">

    <div class="kpi-card">
        <div class="kpi-label">Karyawan PKWT Aktif</div>
        <div class="kpi-value"><?= $total_karyawan ?></div>
        <div>
            <span class="kpi-badge badge-up">+<?= $karyawan_baru ?> bulan ini</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-label">Tingkat Penyelesaian</div>
        <div class="kpi-value">
            <?= $total_karyawan > 0 ? round($total_selesai / $total_karyawan * 100) : 0 ?><span>%</span>
        </div>
        <div>
            <span class="kpi-badge <?= $selesai_bulan >= $selesai_lalu ? 'badge-up' : 'badge-down' ?>">
                <?= $selesai_bulan >= $selesai_lalu ? '+' : '' ?><?= $selesai_bulan - $selesai_lalu ?> vs bulan lalu
            </span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-label">Rata-rata Nilai</div>
        <div class="kpi-value"><?= $avg_nilai ?></div>
        <div>
            <span class="kpi-badge <?= $diff_avg >= 0 ? 'badge-up' : 'badge-down' ?>">
                <?= $diff_avg >= 0 ? '+' : '' ?><?= round($diff_avg, 1) ?> vs bulan lalu
            </span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-label">Tingkat Perpanjangan</div>
        <div class="kpi-value"><?= $lulus_pct ?><span>%</span></div>
        <div>
            <span class="kpi-badge <?= $diff_lulus >= 0 ? 'badge-up' : 'badge-down' ?>">
                <?= $diff_lulus >= 0 ? '+' : '' ?><?= $diff_lulus ?>% vs target
            </span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-label">Penilaian Selesai</div>
        <div class="kpi-value"><?= $total_selesai ?></div>
        <div class="kpi-spark" id="sparkSelesai"></div>
    </div>

    <div class="kpi-card">
        <div class="kpi-label">Menunggu Pengisian</div>
        <div class="kpi-value" style="color:var(--amber)"><?= $total_pending ?></div>
        <div><span class="kpi-badge badge-neu"><?= $total_pending ?> token aktif</span></div>
    </div>

    <div class="kpi-card">
        <div class="kpi-label">Token Kadaluarsa</div>
        <div class="kpi-value" style="color:var(--red)"><?= $total_expired ?></div>
        <div><span class="kpi-badge badge-down">Perlu tindak lanjut</span></div>
    </div>

    <div class="kpi-card">
        <div class="kpi-label">Tidak Diperpanjang</div>
        <div class="kpi-value" style="color:var(--red)"><?= $tidak ?></div>
        <div><span class="kpi-badge badge-neu">dari <?= $total_selesai ?> penilaian</span></div>
    </div>

</div>

<!-- ══ SECTION: ANALISIS KINERJA ════════════════════════════════════════════ -->
<div class="section-label">Analisis Kinerja</div>

<!-- Tren 12 bulan + Donut Status -->
<div class="grid-2-1">

    <div class="db-card">
        <div class="db-card-hdr">
            <div>
                <div class="db-card-title">Tren Penilaian 12 Bulan Terakhir</div>
                <div class="db-card-sub">Diperpanjang vs Tidak Diperpanjang per bulan</div>
            </div>
            <a href="laporan.php" class="btn btn-light btn-sm">Lihat Laporan</a>
        </div>
        <?php if (count($chartLabels) > 0): ?>
            <div style="position:relative;width:100%;height:190px">
                <canvas id="cTren" role="img"
                    aria-label="Tren penilaian 12 bulan terakhir — diperpanjang vs tidak diperpanjang">
                    Data tren penilaian 12 bulan.
                </canvas>
            </div>
        <?php else: ?>
            <div style="text-align:center;padding:60px 0;color:var(--gray-400)">
                <div style="font-size:32px;margin-bottom:8px">📭</div>
                <div style="font-size:13px">Belum ada data penilaian</div>
            </div>
        <?php endif; ?>
        <div class="chart-legend">
            <div class="legend-item"><div class="leg-dot" style="background:#16a34a"></div>Diperpanjang</div>
            <div class="legend-item"><div class="leg-dot" style="background:#ef4444"></div>Tidak Diperpanjang</div>
            <div class="legend-item"><div class="leg-dot" style="background:#e2e8f0"></div>Pending</div>
        </div>
    </div>

    <div class="db-card">
        <div class="db-card-hdr">
            <div>
                <div class="db-card-title">Status Keputusan</div>
                <div class="db-card-sub">Komposisi hasil penilaian</div>
            </div>
        </div>
        <?php if (($diperpanjang + $tidak) > 0): ?>
        <div class="donut-row">
            <div style="position:relative;width:120px;height:120px;flex-shrink:0">
                <canvas id="cDonut" role="img" aria-label="Donut chart status keputusan perpanjangan">
                    Status keputusan: <?= $diperpanjang ?> diperpanjang, <?= $tidak ?> tidak diperpanjang.
                </canvas>
                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none">
                    <div style="font-size:20px;font-weight:700;color:var(--gray-700)"><?= $total_selesai ?></div>
                    <div style="font-size:10px;color:var(--gray-400)">selesai</div>
                </div>
            </div>
            <div class="donut-stat">
                <div class="donut-stat-row">
                    <span style="display:flex;align-items:center;gap:6px">
                        <span style="width:8px;height:8px;background:#16a34a;border-radius:2px;display:inline-block"></span>
                        Diperpanjang
                    </span>
                    <strong style="color:#16a34a"><?= $diperpanjang ?> (<?= $lulus_pct ?>%)</strong>
                </div>
                <div class="donut-stat-row">
                    <span style="display:flex;align-items:center;gap:6px">
                        <span style="width:8px;height:8px;background:#ef4444;border-radius:2px;display:inline-block"></span>
                        Tidak Lanjut
                    </span>
                    <strong style="color:#ef4444"><?= $tidak ?> (<?= 100 - $lulus_pct ?>%)</strong>
                </div>
                <div class="donut-stat-row">
                    <span style="display:flex;align-items:center;gap:6px">
                        <span style="width:8px;height:8px;background:#94a3b8;border-radius:2px;display:inline-block"></span>
                        Menunggu
                    </span>
                    <strong style="color:var(--gray-400)"><?= $total_pending ?></strong>
                </div>
            </div>
        </div>
        <?php else: ?>
            <div style="text-align:center;padding:50px 0;color:var(--gray-400);font-size:13px">Belum ada data</div>
        <?php endif; ?>
    </div>

</div>

<!-- Kompetensi + Distribusi + Jabatan -->
<div class="grid-3">

    <!-- Profil Kompetensi (progress bars) -->
    <div class="db-card">
        <div class="db-card-hdr">
            <div>
                <div class="db-card-title">Profil Kompetensi</div>
                <div class="db-card-sub">Rata-rata per aspek (maks 5)</div>
            </div>
        </div>
        <?php if ($avgK):
            $komps = [
                'Sikap'        => $avgK['sikap'],
                'Kerjasama'    => $avgK['kerjasama'],
                'Inisiatif'    => $avgK['inisiatif'],
                'Ketelitian'   => $avgK['ketelitian'],
                'Ketepatan'    => $avgK['ketepatan'],
                'Pengetahuan'  => $avgK['pengetahuan'],
                'Kualitas'     => $avgK['kualitas'],
                'Kuantitas'    => $avgK['kuantitas'],
                'Belajar'      => $avgK['belajar'],
                'Tg. Jawab'    => $avgK['tjawab'],
            ];
            foreach ($komps as $label => $val):
                $pct  = round(($val / 5) * 100);
                $color = $pct >= 80 ? '#16a34a' : ($pct >= 65 ? '#378ADD' : '#ef8b2c');
        ?>
        <div class="prog-row">
            <div class="prog-label"><?= e($label) ?></div>
            <div class="prog-bg"><div class="prog-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div></div>
            <div class="prog-val"><?= $val ?></div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align:center;padding:32px;color:var(--gray-400);font-size:13px">Belum ada data</div>
        <?php endif; ?>
    </div>

    <!-- Distribusi Nilai -->
    <div class="db-card">
        <div class="db-card-hdr">
            <div>
                <div class="db-card-title">Distribusi Nilai</div>
                <div class="db-card-sub">Kategori skor akhir</div>
            </div>
        </div>
        <?php if ($total_selesai > 0): ?>
        <div style="position:relative;width:100%;height:150px">
            <canvas id="cDist" role="img" aria-label="Distribusi nilai total dalam 5 kategori skor">
                Distribusi nilai karyawan.
            </canvas>
        </div>
        <div style="margin-top:12px">
            <?php
            $distItems = [
                ['Buruk (10–19)',       intval($dist['r10'] ?? 0), '#ef4444'],
                ['Di bawah (20–27)',    intval($dist['r20'] ?? 0), '#f97316'],
                ['Cukup (28–34)',       intval($dist['r28'] ?? 0), '#eab308'],
                ['Baik (35–42)',        intval($dist['r35'] ?? 0), '#22c55e'],
                ['Sangat Baik (43–50)',intval($dist['r43'] ?? 0), '#16a34a'],
            ];
            foreach ($distItems as [$label, $count, $color]):
            ?>
            <div class="donut-stat-row" style="padding:4px 0">
                <span style="font-size:11px;color:var(--gray-400)"><?= $label ?></span>
                <span style="font-size:12px;font-weight:700;color:<?= $color ?>"><?= $count ?> karyawan</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div style="text-align:center;padding:40px;color:var(--gray-400);font-size:13px">Belum ada data</div>
        <?php endif; ?>
    </div>

    <!-- Performa per Jabatan -->
    <div class="db-card">
        <div class="db-card-hdr">
            <div>
                <div class="db-card-title">Performa per Jabatan</div>
                <div class="db-card-sub">Rata-rata nilai & tingkat lulus</div>
            </div>
        </div>
        <table class="rank-tbl">
            <thead>
                <tr><th>#</th><th>Jabatan</th><th>Avg</th><th>Lulus</th></tr>
            </thead>
            <tbody>
            <?php $no = 1; while ($jabatanPerf && $r = $jabatanPerf->fetch_assoc()):
                $pct_lulus = $r['total'] > 0 ? round($r['lulus'] / $r['total'] * 100) : 0;
                $avg_color = $r['avg_nilai'] >= 35 ? '#16a34a' : ($r['avg_nilai'] >= 28 ? '#eab308' : '#ef4444');
                $badge_cls = $pct_lulus >= 75 ? 'badge-up' : ($pct_lulus >= 50 ? 'badge-neu' : 'badge-down');
            ?>
            <tr>
                <td style="font-size:11px;color:var(--gray-400);font-weight:600"><?= $no++ ?></td>
                <td style="font-size:12px"><?= e($r['jabatan']) ?></td>
                <td><span style="font-size:13px;font-weight:700;color:<?= $avg_color ?>"><?= $r['avg_nilai'] ?></span></td>
                <td><span class="kpi-badge <?= $badge_cls ?>" style="font-size:10px"><?= $pct_lulus ?>%</span></td>
            </tr>
            <?php endwhile; ?>
            <?php if ($no === 1): ?>
            <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--gray-400)">Belum ada data</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- ══ SECTION: MONITORING ══════════════════════════════════════════════════ -->
<div class="section-label">Monitoring &amp; Aktivitas</div>

<!-- Heatmap + Radar -->
<div class="grid-2-1" style="grid-template-columns:1.4fr 1fr">

    <!-- Heatmap -->
    <div class="db-card">
        <div class="db-card-hdr">
            <div>
                <div class="db-card-title">Heatmap Aktivitas Penilaian</div>
                <div class="db-card-sub">Jumlah penilaian per departemen per bulan</div>
            </div>
        </div>
        <div class="heatmap-wrap">
            <?php
            // Bangun 12 label bulan terakhir
            $hmMonths = [];
            for ($i = 11; $i >= 0; $i--) {
                $key = date('Y-m', strtotime("-{$i} months"));
                $m   = substr($key, 5, 2);
                $hmMonths[$key] = $bulanNama[$m] ?? $m;
            }
            $depts = array_keys($hmData);
            $cols  = count($hmMonths) + 1; // +1 untuk label kolom
            ?>
            <div class="heatmap-grid" style="grid-template-columns:60px <?= implode(' ', array_fill(0, count($hmMonths), '1fr')) ?>">
                <div></div>
                <?php foreach ($hmMonths as $bln => $label): ?>
                    <div class="hm-head"><?= $label ?></div>
                <?php endforeach; ?>

                <?php foreach ($depts as $dept): ?>
                    <div class="hm-label"><?= e(mb_substr($dept, 0, 8)) ?></div>
                    <?php foreach ($hmMonths as $bln => $label):
                        $v = $hmData[$dept][$bln] ?? 0;
                        $bg = $v === 0 ? '#f8fafc' : ($v <= 3 ? '#e2f4ec' : ($v <= 6 ? '#9fe1cb' : ($v <= 9 ? '#1d9e75' : '#0f6e56')));
                    ?>
                        <div class="hm-cell" style="background:<?= $bg ?>" title="<?= e($dept) ?> · <?= $label ?> · <?= $v ?> penilaian"></div>
                    <?php endforeach; ?>
                <?php endforeach; ?>

                <?php if (empty($depts)): ?>
                    <div style="grid-column:1/-1;text-align:center;padding:20px;color:var(--gray-400);font-size:12px">Belum ada data</div>
                <?php endif; ?>
            </div>

            <div style="display:flex;align-items:center;gap:6px;margin-top:10px;font-size:10px;color:var(--gray-400)">
                <span>Rendah</span>
                <div style="width:10px;height:10px;border-radius:2px;background:#e2f4ec"></div>
                <div style="width:10px;height:10px;border-radius:2px;background:#9fe1cb"></div>
                <div style="width:10px;height:10px;border-radius:2px;background:#1d9e75"></div>
                <div style="width:10px;height:10px;border-radius:2px;background:#0f6e56"></div>
                <span>Tinggi</span>
            </div>
        </div>
    </div>

    <!-- Radar Kompetensi -->
    <div class="db-card">
        <div class="db-card-hdr">
            <div>
                <div class="db-card-title">Radar Kompetensi</div>
                <div class="db-card-sub">Aktual vs target perusahaan</div>
            </div>
        </div>
        <?php if ($avgK && $total_selesai > 0): ?>
        <div style="position:relative;width:100%;height:210px">
            <canvas id="cRadar" role="img" aria-label="Radar chart perbandingan kompetensi aktual vs target">
                Radar perbandingan kompetensi.
            </canvas>
        </div>
        <div class="chart-legend" style="margin-top:8px">
            <div class="legend-item"><div class="leg-dot" style="background:#378ADD"></div>Aktual</div>
            <div class="legend-item"><div class="leg-dot" style="background:#ef4444;opacity:0.6"></div>Target</div>
        </div>
        <?php else: ?>
            <div style="text-align:center;padding:50px;color:var(--gray-400);font-size:13px">Belum ada data</div>
        <?php endif; ?>
    </div>

</div>

<!-- Funnel + Feed -->
<div class="grid-2">

    <!-- Funnel Proses -->
    <div class="db-card">
        <div class="db-card-hdr">
            <div>
                <div class="db-card-title">Funnel Proses Penilaian</div>
                <div class="db-card-sub">Dari undangan hingga keputusan akhir</div>
            </div>
        </div>
        <?php if ($funnel_undangan > 0): ?>
        <div style="position:relative;width:100%;height:180px">
            <canvas id="cFunnel" role="img" aria-label="Horizontal bar chart funnel proses penilaian">
                Funnel proses penilaian.
            </canvas>
        </div>
        <div style="margin-top:12px">
            <?php
            $funnelItems = [
                ['Undangan Dikirim', $funnel_undangan, $funnel_undangan],
                ['Token Dibuka',     $funnel_dibuka,   $funnel_undangan],
                ['Form Diisi',       $funnel_diisi,    $funnel_undangan],
                ['Keputusan Diambil',$funnel_keputusan,$funnel_undangan],
            ];
            foreach ($funnelItems as [$lbl, $val, $base]):
                $pct = $base > 0 ? round($val / $base * 100) : 0;
            ?>
            <div class="funnel-stat-row">
                <span style="font-size:11px;color:var(--gray-500)"><?= $lbl ?></span>
                <span style="display:flex;align-items:center;gap:8px">
                    <span style="font-size:12px;font-weight:600;color:var(--gray-700)"><?= $val ?></span>
                    <span class="funnel-pct"><?= $pct ?>%</span>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div style="text-align:center;padding:40px;color:var(--gray-400);font-size:13px">Belum ada data</div>
        <?php endif; ?>
    </div>

    <!-- Activity Feed -->
    <div class="db-card">
        <div class="db-card-hdr">
            <div>
                <div class="db-card-title">Aktivitas Terbaru</div>
                <div class="db-card-sub">Log real-time penilaian masuk</div>
            </div>
            <a href="penilaian.php" class="btn btn-light btn-sm">Semua</a>
        </div>
        <?php $feedCount = 0; while ($feed && $r = $feed->fetch_assoc()):
            $isLanjut = $r['status_perpanjangan'] === 'Diperpanjang';
            $dotColor = $isLanjut ? '#16a34a' : '#ef4444';
            $waktu    = !empty($r['created_at']) ? strtotime($r['created_at']) : 0;
            if ($waktu > 0) {
                $diff = time() - $waktu;
                if ($diff < 3600)      $timeStr = round($diff/60) . ' menit lalu';
                elseif ($diff < 86400) $timeStr = round($diff/3600) . ' jam lalu';
                else                   $timeStr = date('d M Y', $waktu);
            } else {
                $timeStr = '—';
            }
            $feedCount++;
        ?>
        <div class="feed-item">
            <div class="feed-dot" style="background:<?= $dotColor ?>"></div>
            <div>
                <div class="feed-text">
                    <strong><?= e($r['nama']) ?></strong> —
                    <strong style="color:<?= $dotColor ?>"><?= e($r['status_perpanjangan']) ?></strong>
                    (nilai <?= $r['jumlah_nilai'] ?>)
                </div>
                <div class="feed-time"><?= $timeStr ?> · <?= e($r['jabatan']) ?></div>
            </div>
        </div>
        <?php endwhile; ?>
        <?php if ($feedCount === 0): ?>
            <div style="text-align:center;padding:32px;color:var(--gray-400);font-size:13px">Belum ada penilaian</div>
        <?php endif; ?>
    </div>

</div>

<!-- ══ ROW BAWAH: 3 chart kecil ═════════════════════════════════════════════ -->
<div class="grid-3">

    <!-- Respons Rate -->
    <div class="db-card">
        <div class="db-card-hdr">
            <div>
                <div class="db-card-title">Respons Rate Token</div>
                <div class="db-card-sub">Pengisian form per bulan (%)</div>
            </div>
        </div>
        <?php if (count($respLabels) > 0): ?>
        <div style="position:relative;width:100%;height:130px">
            <canvas id="cResp" role="img" aria-label="Line chart respons rate pengisian form per bulan">
                Tren respons rate pengisian form.
            </canvas>
        </div>
        <?php else: ?>
            <div style="text-align:center;padding:40px;color:var(--gray-400);font-size:13px">Belum ada data</div>
        <?php endif; ?>
    </div>

    <!-- Distribusi Durasi Kontrak -->
    <div class="db-card">
        <div class="db-card-hdr">
            <div>
                <div class="db-card-title">Durasi Kontrak</div>
                <div class="db-card-sub">Distribusi masa kontrak aktif</div>
            </div>
        </div>
        <div style="position:relative;width:100%;height:130px">
            <canvas id="cKontrak" role="img" aria-label="Pie chart distribusi durasi kontrak karyawan PKWT">
                Distribusi durasi kontrak karyawan.
            </canvas>
        </div>
    </div>

    <!-- Tren Nilai Rata-rata -->
    <div class="db-card">
        <div class="db-card-hdr">
            <div>
                <div class="db-card-title">Tren Nilai Rata-rata</div>
                <div class="db-card-sub">12 bulan terakhir vs batas lulus</div>
            </div>
        </div>
        <?php if (count($avgTrenLabels) > 0): ?>
        <div style="position:relative;width:100%;height:130px">
            <canvas id="cAvg" role="img" aria-label="Line chart tren nilai rata-rata 12 bulan terakhir">
                Tren nilai rata-rata penilaian.
            </canvas>
        </div>
        <?php else: ?>
            <div style="text-align:center;padding:40px;color:var(--gray-400);font-size:13px">Belum ada data</div>
        <?php endif; ?>
    </div>

</div>

<!-- ══ TABEL TERBARU + PENDING TOKEN ════════════════════════════════════════ -->
<div class="section-label">Detail Data</div>
<div class="grid-2" style="margin-bottom:0">

    <!-- Penilaian Terbaru -->
    <div class="db-card">
        <div class="db-card-hdr">
            <div class="db-card-title">Penilaian Terbaru</div>
            <a href="penilaian.php" class="btn btn-light btn-sm">Lihat Semua</a>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>Karyawan</th><th>Periode</th><th>Nilai</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php $rCount = 0; while ($recent && $r = $recent->fetch_assoc()): $rCount++; ?>
                <tr class="clickable" onclick="location.href='detail_penilaian.php?id=<?= $r['id'] ?>'">
                    <td>
                        <div style="font-weight:600;font-size:13px"><?= e($r['nama']) ?></div>
                        <div style="font-size:11px;color:var(--gray-400)"><?= e($r['jabatan']) ?></div>
                    </td>
                    <td style="font-size:12px;color:var(--gray-500)"><?= e($r['periode']) ?></td>
                    <td>
                        <span style="font-size:20px;font-weight:800;color:<?= $r['jumlah_nilai'] >= 28 ? 'var(--green)' : 'var(--red)' ?>">
                            <?= $r['jumlah_nilai'] ?>
                        </span>
                    </td>
                    <td>
                        <?= $r['status_perpanjangan'] === 'Diperpanjang'
                            ? '<span class="badge badge-green">✓ Lanjut</span>'
                            : '<span class="badge badge-red">✗ Ditolak</span>' ?>
                    </td>
                    <td onclick="event.stopPropagation()">
                        <a href="detail_penilaian.php?id=<?= $r['id'] ?>" class="btn btn-primary btn-xs">Detail</a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($rCount === 0): ?>
                <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--gray-400)">Belum ada penilaian</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pending Token -->
    <div class="db-card">
        <div class="db-card-hdr">
            <div class="db-card-title">Menunggu Pengisian</div>
            <a href="token.php" class="btn btn-light btn-sm">Kelola Token</a>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>Karyawan</th><th>Periode</th><th>Exp</th><th>Kirim</th></tr>
                </thead>
                <tbody>
                <?php $pCount = 0; while ($pending && $r = $pending->fetch_assoc()):
                    $pCount++;
                    $link = APP_URL . '/form.php?token=' . $r['token'];
                    $wa   = 'https://wa.me/?text=' . rawurlencode(
                        "Yth. Bapak/Ibu,\n\nMohon mengisi Form Penilaian Kinerja karyawan *{$r['nama']}* periode *{$r['periode']}*.\n\nLink:\n{$link}\n\nAktif hingga: " .
                        date('d/m/Y H:i', strtotime($r['expires_at'])) . "\n\nTerima kasih,\nHRD"
                    );
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:13px"><?= e($r['nama']) ?></div>
                        <div style="font-size:11px;color:var(--gray-400)"><?= e($r['jabatan']) ?></div>
                    </td>
                    <td style="font-size:12px"><?= e($r['periode']) ?></td>
                    <td style="font-size:11px;color:var(--gray-400)"><?= date('d/m/y', strtotime($r['expires_at'])) ?></td>
                    <td>
                        <div style="display:flex;gap:4px">
                            <button onclick="copyLink('<?= e($link) ?>')" class="btn btn-light btn-xs" title="Salin link">🔗</button>
                            <a href="<?= e($wa) ?>" target="_blank" class="btn btn-success btn-xs" title="Kirim WhatsApp">💬</a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($pCount === 0): ?>
                <tr><td colspan="4" style="text-align:center;padding:32px;color:var(--gray-400)">Semua sudah diisi ✓</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ══ SCRIPTS ═══════════════════════════════════════════════════════════════ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = "'Inter','Segoe UI',sans-serif";
Chart.defaults.font.size = 11;
Chart.defaults.color = '#64748b';

const gridColor = 'rgba(241,245,249,1)';

// ── SPARKLINE KPI ──────────────────────────────────────────────────────────
(function buildSparkline(){
    const data = <?= json_encode(array_values($chartLanjut)) ?: '[]' ?>;
    if (!data.length) return;
    const max = Math.max(...data, 1);
    const el = document.getElementById('sparkSelesai');
    if (!el) return;
    data.slice(-7).forEach((v, i, arr) => {
        const bar = document.createElement('div');
        bar.className = 'kpi-spark-bar';
        bar.style.height = Math.round((v / max) * 100) + '%';
        bar.style.background = i === arr.length - 1 ? '#378ADD' : '#3266ad';
        bar.style.opacity = 0.6 + (i / arr.length) * 0.4;
        el.appendChild(bar);
    });
})();

// ── TREN 12 BULAN ─────────────────────────────────────────────────────────
<?php if (count($chartLabels) > 0): ?>
new Chart(document.getElementById('cTren'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
            {
                label: 'Diperpanjang',
                data: <?= json_encode($chartLanjut) ?>,
                backgroundColor: '#16a34a',
                borderRadius: 4,
                borderSkipped: false,
                stack: 's'
            },
            {
                label: 'Tidak Diperpanjang',
                data: <?= json_encode($chartTidak) ?>,
                backgroundColor: '#ef4444',
                borderRadius: 4,
                borderSkipped: false,
                stack: 's'
            },
            {
                label: 'Pending',
                data: <?= json_encode($chartPending) ?>,
                backgroundColor: '#e2e8f0',
                borderRadius: 4,
                borderSkipped: false,
                stack: 's'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, border: { display: false } },
            y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: gridColor }, border: { display: false } }
        }
    }
});
<?php endif; ?>

// ── DONUT STATUS ──────────────────────────────────────────────────────────
<?php if (($diperpanjang + $tidak) > 0): ?>
new Chart(document.getElementById('cDonut'), {
    type: 'doughnut',
    data: {
        labels: ['Diperpanjang', 'Tidak Diperpanjang', 'Menunggu'],
        datasets: [{
            data: [<?= $diperpanjang ?>, <?= $tidak ?>, <?= $total_pending ?>],
            backgroundColor: ['#16a34a', '#ef4444', '#94a3b8'],
            borderWidth: 2,
            borderColor: '#fff',
            hoverOffset: 6
        }]
    },
    options: {
        cutout: '70%',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    }
});
<?php endif; ?>

// ── DISTRIBUSI NILAI ──────────────────────────────────────────────────────
<?php if ($total_selesai > 0): ?>
new Chart(document.getElementById('cDist'), {
    type: 'bar',
    data: {
        labels: ['10–19', '20–27', '28–34', '35–42', '43–50'],
        datasets: [{
            data: [
                <?= intval($dist['r10'] ?? 0) ?>,
                <?= intval($dist['r20'] ?? 0) ?>,
                <?= intval($dist['r28'] ?? 0) ?>,
                <?= intval($dist['r35'] ?? 0) ?>,
                <?= intval($dist['r43'] ?? 0) ?>
            ],
            backgroundColor: ['#ef4444','#f97316','#eab308','#22c55e','#16a34a'],
            borderRadius: 5,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    title: c => {
                        const l = ['Buruk','Di bawah standar','Cukup','Baik','Sangat Baik'];
                        return l[c[0].dataIndex] + ' (' + c[0].label + ')';
                    }
                }
            }
        },
        scales: {
            x: { grid: { display: false }, border: { display: false } },
            y: { beginAtZero: true, ticks: { stepSize: 5 }, grid: { color: gridColor }, border: { display: false } }
        }
    }
});

// ── RADAR KOMPETENSI ──────────────────────────────────────────────────────
new Chart(document.getElementById('cRadar'), {
    type: 'radar',
    data: {
        labels: ['Sikap','Kerjasama','Inisiatif','Ketelitian','Ketepatan','Pengetahuan','Kualitas','Kuantitas','Belajar','Tg.Jawab'],
        datasets: [
            {
                label: 'Aktual',
                data: [
                    <?= implode(',', [
                        $avgK['sikap']       ?? 0,
                        $avgK['kerjasama']   ?? 0,
                        $avgK['inisiatif']   ?? 0,
                        $avgK['ketelitian']  ?? 0,
                        $avgK['ketepatan']   ?? 0,
                        $avgK['pengetahuan'] ?? 0,
                        $avgK['kualitas']    ?? 0,
                        $avgK['kuantitas']   ?? 0,
                        $avgK['belajar']     ?? 0,
                        $avgK['tjawab']      ?? 0,
                    ]) ?>
                ],
                borderColor: '#378ADD',
                backgroundColor: 'rgba(55,138,221,0.12)',
                pointBackgroundColor: '#378ADD',
                borderWidth: 2,
                pointRadius: 3
            },
            {
                label: 'Target',
                data: [4.5, 4.5, 4.0, 4.5, 4.5, 4.0, 4.5, 4.0, 4.0, 4.5],
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239,68,68,0.06)',
                pointBackgroundColor: '#ef4444',
                borderWidth: 1.5,
                borderDash: [4, 3],
                pointRadius: 2
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            r: {
                min: 0, max: 5,
                ticks: { stepSize: 1, backdropColor: 'transparent', font: { size: 9 } },
                pointLabels: { font: { size: 9.5 } },
                grid: { color: '#e2e8f0' },
                angleLines: { color: '#e2e8f0' }
            }
        }
    }
});
<?php endif; ?>

// ── FUNNEL ────────────────────────────────────────────────────────────────
<?php if ($funnel_undangan > 0): ?>
new Chart(document.getElementById('cFunnel'), {
    type: 'bar',
    data: {
        labels: ['Undangan Dikirim', 'Token Dibuka', 'Form Diisi', 'Keputusan Diambil'],
        datasets: [{
            data: [<?= $funnel_undangan ?>, <?= $funnel_dibuka ?>, <?= $funnel_diisi ?>, <?= $funnel_keputusan ?>],
            backgroundColor: ['#1e6fd9','#378ADD','#5a9fe0','#16a34a'],
            borderRadius: 5,
            borderSkipped: false
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: c => ' ' + c.raw + ' karyawan (' + Math.round(c.raw / <?= max($funnel_undangan, 1) ?> * 100) + '%)'
                }
            }
        },
        scales: {
            x: { max: <?= max($funnel_undangan, 1) + 10 ?>, grid: { color: gridColor }, border: { display: false } },
            y: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 } } }
        }
    }
});
<?php endif; ?>

// ── RESPONS RATE ──────────────────────────────────────────────────────────
<?php if (count($respLabels) > 0): ?>
new Chart(document.getElementById('cResp'), {
    type: 'line',
    data: {
        labels: <?= json_encode($respLabels) ?>,
        datasets: [{
            label: 'Respons Rate',
            data: <?= json_encode($respData) ?>,
            borderColor: '#1e6fd9',
            backgroundColor: 'rgba(30,111,217,0.08)',
            fill: true,
            tension: 0.4,
            borderWidth: 2,
            pointRadius: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: c => ' ' + c.raw + '%' } }
        },
        scales: {
            x: { grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, maxTicksLimit: 6 } },
            y: { min: 0, max: 100, ticks: { callback: v => v + '%', stepSize: 20 }, grid: { color: gridColor }, border: { display: false } }
        }
    }
});
<?php endif; ?>

// ── DURASI KONTRAK ────────────────────────────────────────────────────────
new Chart(document.getElementById('cKontrak'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($kontrakLabels) ?>,
        datasets: [{
            data: <?= json_encode($kontrakData) ?>,
            backgroundColor: ['#5a9fe0','#378ADD','#1e6fd9','#0c447c'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: {
                display: true,
                position: 'right',
                labels: { boxWidth: 8, boxHeight: 8, font: { size: 10 }, padding: 6 }
            }
        }
    }
});

// ── TREN NILAI RATA-RATA ──────────────────────────────────────────────────
<?php if (count($avgTrenLabels) > 0): ?>
new Chart(document.getElementById('cAvg'), {
    type: 'line',
    data: {
        labels: <?= json_encode($avgTrenLabels) ?>,
        datasets: [
            {
                label: 'Rata-rata Nilai',
                data: <?= json_encode($avgTrenData) ?>,
                borderColor: '#16a34a',
                backgroundColor: 'rgba(22,163,74,0.08)',
                fill: true,
                tension: 0.4,
                borderWidth: 2,
                pointRadius: 2
            },
            {
                label: 'Batas Lulus (28)',
                data: <?= json_encode(array_fill(0, count($avgTrenLabels), 28)) ?>,
                borderColor: '#ef4444',
                borderDash: [4, 3],
                borderWidth: 1.5,
                pointRadius: 0,
                fill: false
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, maxTicksLimit: 6 } },
            y: { min: 20, max: 50, ticks: { stepSize: 5 }, grid: { color: gridColor }, border: { display: false } }
        }
    }
});
<?php endif; ?>

// ── PERIOD FILTER (UI only — reload dengan query param jika perlu) ────────
document.querySelectorAll('.pill-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.pill-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        // Tambahkan reload dengan ?periode=Q1 dst. jika ingin filter server-side
    });
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>