<?php
// ============================================================
// config.php — Konfigurasi Utama Sistem PKWT
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'pkwt_assessment');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'Sistem Penilaian Kinerja PKWT');
define('APP_URL', 'http://localhost/pkwt/'); // Sesuaikan
define('TOKEN_DAYS', 7);          // Token aktif 7 hari
define('SESSION_TIMEOUT', 3600);  // 1 jam

// Mulai session aman
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// ── Koneksi Database ──
function db(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('<div style="font-family:sans-serif;padding:30px;background:#fee;border:1px solid #c00;margin:30px auto;max-width:500px;border-radius:8px;">
                <h3 style="color:#c00;">Koneksi Database Gagal</h3>
                <p>' . htmlspecialchars($conn->connect_error) . '</p>
                <p style="font-size:12px;">Periksa konfigurasi di <code>config.php</code></p>
            </div>');
        }
        $conn->set_charset('utf8mb4');
        $conn->query("SET time_zone = '+07:00'");
    }
    return $conn;
}

// ── Helper Functions ──
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function isAdminLoggedIn(): bool {
    if (!isset($_SESSION['admin_id'], $_SESSION['login_time'])) return false;
    if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
        session_destroy();
        return false;
    }
    $_SESSION['login_time'] = time();
    return true;
}

function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        redirect(APP_URL . '/admin/login.php');
    }
}

function auditLog(int $adminId, string $aksi, string $ket = ''): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = db()->prepare("INSERT INTO audit_log (admin_id, aksi, keterangan, ip_address) VALUES (?,?,?,?)");
    $stmt->bind_param('isss', $adminId, $aksi, $ket, $ip);
    $stmt->execute();
    $stmt->close();
}

function getPredikat(int $nilai): string {
    return match($nilai) {
        5 => 'Sangat Baik',
        4 => 'Baik',
        3 => 'Cukup Baik',
        2 => 'Kurang Baik',
        1 => 'Tidak Baik',
        default => '-'
    };
}

function generateToken(): string {
    return bin2hex(random_bytes(32));
}

function formatTanggal(string $date): string {
    if (!$date) return '-';
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    $d = explode('-', $date);
    return intval($d[2]) . ' ' . $bulan[intval($d[1])] . ' ' . $d[0];
}

function formatBulan(string $ym): string {
    if (!$ym) return '-';
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    $parts = explode('-', $ym);
    if (count($parts) < 2) return $ym;
    $m = intval($parts[1]);
    return ($bulan[$m] ?? $parts[1]) . ' ' . ($parts[0] ?? '');
}

