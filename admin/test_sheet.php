<?php
require_once __DIR__ . '/../config.php';
requireAdmin();

$URL = 'https://script.google.com/macros/s/AKfycbzWMequ9kdl2yCbHHj4wHEMvY09n5xg-GyFc0weffHS7zWRvlAeScabtBvdfXsQUFs09w/exec';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Diagnostik Spreadsheet</title>
<style>
body{font-family:monospace;padding:30px;background:#0f172a;color:#e2e8f0;line-height:1.8}
h1{color:#38bdf8;font-size:22px}
.box{background:#1e293b;border:1px solid #334155;border-radius:8px;padding:18px;margin-bottom:14px}
.ok{color:#4ade80}.err{color:#f87171}.warn{color:#fbbf24}.info{color:#94a3b8}
code{background:#0f172a;padding:2px 6px;border-radius:4px}
pre{background:#0f172a;padding:12px;border-radius:6px;overflow:auto;font-size:12px;white-space:pre-wrap;word-break:break-all}
hr{border:none;border-top:1px solid #334155;margin:20px 0}
</style>
</head>
<body>
<h1>&#128300; Diagnostik Koneksi Google Spreadsheet</h1>
<p class="info">URL: <code><?= htmlspecialchars($URL) ?></code></p>
<hr>
<?php

// --- 1. Cek Extensions ---
echo '<div class="box"><b>1. PHP Extensions</b><br><br>';
$curl_ok  = function_exists('curl_init');
$fopen_ok = (bool)ini_get('allow_url_fopen');
echo 'cURL         : ' . ($curl_ok  ? '<span class="ok">&#9989; Aktif</span>' : '<span class="err">&#10060; Tidak tersedia</span>') . '<br>';
echo 'allow_url_fopen: ' . ($fopen_ok ? '<span class="ok">&#9989; Aktif</span>' : '<span class="warn">&#9888;&#65039; Nonaktif</span>') . '<br>';
echo 'PHP versi    : <code>' . phpversion() . '</code><br>';
echo '</div>';

// --- 2. Test cURL ---
if ($curl_ok) {
    echo '<div class="box"><b>2. Test cURL ke Google Apps Script</b><br><br>';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (PHP-PKWT-Diag/1.0)',
    ]);
    $body    = curl_exec($ch);
    $errno   = curl_errno($ch);
    $errmsg  = curl_error($ch);
    $httpCode= curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl= curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    echo 'HTTP Code : <b>' . $httpCode . '</b><br>';
    echo 'cURL Error: ' . ($errno ? '<span class="err">' . $errno . ' &ndash; ' . htmlspecialchars($errmsg) . '</span>' : '<span class="ok">Tidak ada</span>') . '<br>';
    echo 'Final URL : <code>' . htmlspecialchars(substr($finalUrl, 0, 100)) . '</code><br>';
    echo '<br><b>Body (500 karakter pertama):</b>';
    echo '<pre>' . htmlspecialchars(substr($body ?: '(kosong)', 0, 500)) . '</pre>';

    if ($body) {
        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo '<span class="ok">&#9989; JSON valid</span><br>';
            echo 'status: <code>' . htmlspecialchars($decoded['status'] ?? '(tidak ada)') . '</code><br>';
            $cnt = count($decoded['data'] ?? []);
            echo 'Jumlah data: <b>' . $cnt . ' baris</b><br>';
            if ($cnt > 0) {
                echo '<br><b>Data pertama:</b>';
                echo '<pre>' . htmlspecialchars(json_encode($decoded['data'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
            }
        } else {
            echo '<span class="err">&#10060; JSON tidak valid: ' . json_last_error_msg() . '</span><br>';
        }
    }
    echo '</div>';
}

// --- 3. Test file_get_contents ---
echo '<div class="box"><b>3. Test file_get_contents (fallback)</b><br><br>';
if ($fopen_ok) {
    $ctx   = stream_context_create(['http' => ['timeout' => 15]]);
    $body2 = @file_get_contents($URL, false, $ctx);
    if ($body2 !== false) {
        echo '<span class="ok">&#9989; Berhasil</span><br>';
        echo '<pre>' . htmlspecialchars(substr($body2, 0, 300)) . '</pre>';
    } else {
        $le = error_get_last();
        echo '<span class="err">&#10060; Gagal</span><br>';
        echo 'Error: <code>' . htmlspecialchars($le['message'] ?? 'unknown') . '</code>';
    }
} else {
    echo '<span class="warn">&#9888;&#65039; allow_url_fopen = Off, dilewati.</span>';
}
echo '</div>';

// --- 4. Kesimpulan ---
echo '<div class="box"><b>4. Kesimpulan</b><br><br>';
if (!$curl_ok && !$fopen_ok) {
    echo '<span class="err">&#10060; Server tidak bisa konek ke URL eksternal.</span><br>';
    echo '&#8594; Hubungi support hosting untuk aktifkan cURL.<br>';
} elseif ($curl_ok) {
    if ($httpCode === 200 && ($decoded['status'] ?? '') === 'ok') {
        echo '<span class="ok">&#9989; Koneksi BERHASIL! Data terbaca.</span><br>';
        echo '&#8594; Pastikan file karyawan.php sudah di-upload versi terbaru.<br>';
    } elseif ($errno !== 0) {
        echo '<span class="err">&#10060; cURL error ' . $errno . ': ' . htmlspecialchars($errmsg) . '</span><br>';
        echo '&#8594; Firewall server memblokir koneksi ke Google.<br>';
    } elseif ($httpCode === 200) {
        echo '<span class="warn">&#9888;&#65039; Konek berhasil tapi format respons salah.</span><br>';
        echo '&#8594; Periksa kode Google Apps Script, harus return <code>{"status":"ok","data":[...]}</code><br>';
    } else {
        echo '<span class="err">&#10060; HTTP ' . $httpCode . ' dari Google.</span><br>';
        echo '&#8594; Deploy ulang Apps Script dengan akses <b>Anyone</b>.<br>';
    }
}
echo '</div>';
?>
<p class="warn">&#9888;&#65039; <b>HAPUS file test_sheet.php setelah selesai debugging!</b></p>
</body>
</html>
