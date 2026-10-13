<?php
require_once __DIR__ . '/../config.php';
requireAdmin();
$db = db();
$id = intval($_GET['id'] ?? 0);
if (!$id) redirect(APP_URL . '/admin/penilaian.php');

$stmt = $db->prepare("
    SELECT p.*, p.tanda_tangan, k.nama, k.jabatan, k.nik, k.atasan_langsung, k.mulai_bekerja, k.id AS kid,
           ft.periode, ft.shift, ft.cuti, ft.ijin, ft.periode_ke, ft.catatan_hrd,
           ft.submitted_ip, ft.submitted_at AS ft_submitted,
           m.id AS memo_id, m.nomor_memo, m.tanggal_memo, m.is_printed AS memo_is_printed
    FROM penilaian p
    JOIN karyawan k ON k.id=p.karyawan_id
    JOIN form_tokens ft ON ft.id=p.token_id
    LEFT JOIN memo_perpanjangan m ON m.penilaian_id=p.id
    WHERE p.id=?
");
$stmt->bind_param('i',$id); $stmt->execute();
$r = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$r) redirect(APP_URL . '/admin/penilaian.php');

$riwayat = $db->query("SELECT p.id,p.jumlah_nilai,p.status_perpanjangan,p.tanggal_penilaian,ft.periode FROM penilaian p JOIN form_tokens ft ON ft.id=p.token_id WHERE p.karyawan_id={$r['kid']} ORDER BY p.created_at DESC");

$kriteria = [
    ['key'=>'sikap',      'label'=>'Tingkat Sikap / Attitude'],
    ['key'=>'kerjasama',  'label'=>'Kerjasama / Teamwork'],
    ['key'=>'inisiatif',  'label'=>'Inisiatif & Kreativitas'],
    ['key'=>'ketelitian', 'label'=>'Ketelitian'],
    ['key'=>'ketepatan',  'label'=>'Ketepatan Waktu'],
    ['key'=>'pengetahuan','label'=>'Pengetahuan Pekerjaan'],
    ['key'=>'kualitas',   'label'=>'Kualitas Pekerjaan'],
    ['key'=>'kuantitas',  'label'=>'Kuantitas Pekerjaan'],
    ['key'=>'belajar',    'label'=>'Kemampuan Belajar'],
    ['key'=>'tjawab',     'label'=>'Tanggung Jawab'],
];
$warna=[1=>'#ef4444',2=>'#f97316',3=>'#eab308',4=>'#16a34a',5=>'#059669'];
$label=[1=>'Tidak Baik',2=>'Kurang Baik',3=>'Cukup Baik',4=>'Baik',5=>'Sangat Baik'];

$pageTitle='Detail Penilaian'; $activePage='penilaian';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Breadcrumb + Actions -->
<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:14px;margin-bottom:22px;">
  <div>
    <div class="breadcrumb" style="margin-bottom:5px;">
      <a href="dashboard.php">Dashboard</a><span class="sep">›</span>
      <a href="penilaian.php">Hasil Penilaian</a><span class="sep">›</span>
      <span><?= e($r['nama']) ?></span>
    </div>
    <h1 style="font-size:20px;font-weight:800;color:var(--gray-900)"><?= e($r['nama']) ?></h1>
    <div style="font-size:13px;color:var(--gray-500);margin-top:2px">
      <?= e($r['jabatan']) ?> &nbsp;·&nbsp; Periode: <strong style="color:var(--blue)"><?= e($r['periode']) ?></strong>
    </div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
    <a href="penilaian.php" class="btn btn-light">← Kembali</a>
    <a href="edit_karyawan.php?id=<?= $r['kid'] ?>" class="btn btn-light">👤 Profil</a>
    <?php if(!empty($r['memo_id'])): ?>
      <a href="detail_memo.php?id=<?= $r['memo_id'] ?>" class="btn btn-warning" title="Lihat Memo yang sudah dibuat">📄 Lihat Memo</a>
      <a href="<?= APP_URL ?>/cetak_memo.php?id=<?= $r['memo_id'] ?>" class="btn btn-primary" target="_blank" title="Cetak Memo PDF">🖨️ Cetak Memo</a>
      <a href="<?= APP_URL ?>/form_memo.php?penilaian_id=<?= $id ?>&edit=1" class="btn btn-light" title="Edit Memo">✏️ Edit Memo</a>
    <?php else: ?>
      <a href="<?= APP_URL ?>/form_memo.php?penilaian_id=<?= $id ?>" class="btn btn-success" style="box-shadow:0 2px 10px rgba(22,163,74,0.35);font-weight:700;">📝 Buat Memo</a>
    <?php endif; ?>
    <a href="cetak_penilaian.php?id=<?= $id ?>&auto=1" class="btn btn-navy" target="_blank">🖨️ Cetak / Save PDF</a>
    <a href="hapus_penilaian.php?id=<?= $id ?>" class="btn btn-danger" onclick="return confirm('Hapus data ini? Tidak dapat dibatalkan.')">🗑️ Hapus</a>
  </div>
</div>

<!-- Section 1: Top Summary -->
<div style="display:grid;grid-template-columns:200px 1fr 260px;gap:18px;margin-bottom:20px;">

  <!-- Nilai Box -->
  <div class="card" style="text-align:center;padding:28px 16px;display:flex;flex-direction:column;align-items:center;justify-content:center;">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--gray-400);margin-bottom:8px;">NILAI TOTAL</div>
    <div style="font-size:64px;font-weight:900;line-height:1;color:<?= $r['jumlah_nilai']>=28?'var(--green)':'var(--red)' ?>">
      <?= $r['jumlah_nilai'] ?>
    </div>
    <div style="font-size:12px;color:var(--gray-400);margin-top:4px;">dari 50 poin</div>
    <div style="width:100%;background:var(--gray-100);border-radius:99px;height:8px;margin:14px 0 12px;">
      <div style="height:8px;border-radius:99px;width:<?= ($r['jumlah_nilai']/50*100) ?>%;background:<?= $r['jumlah_nilai']>=28?'var(--green)':'var(--red)' ?>;"></div>
    </div>
    <?php if($r['status_perpanjangan']==='Diperpanjang'): ?>
      <span class="badge badge-green" style="font-size:12px;padding:5px 14px;">✅ DIPERPANJANG</span>
    <?php else: ?>
      <span class="badge badge-red" style="font-size:12px;padding:5px 14px;">❌ TIDAK DIPERPANJANG</span>
    <?php endif; ?>
    <div style="font-size:10px;color:var(--gray-400);margin-top:8px;">Threshold ≥ 28</div>
  </div>

  <!-- Radar Chart -->
  <div class="card" style="padding:18px 20px;">
    <div style="font-size:13px;font-weight:700;color:var(--gray-700);margin-bottom:12px;">🕸️ Profil Kompetensi</div>
    <canvas id="radarChart" height="200"></canvas>
  </div>

  <!-- Info Karyawan -->
  <div class="card" style="padding:18px 20px;">
    <div style="font-size:13px;font-weight:700;color:var(--gray-700);border-bottom:1px solid var(--gray-100);padding-bottom:10px;margin-bottom:12px;">👤 Info Karyawan</div>
    <?php foreach([
      ['NIK',         e($r['nik'])],
      ['Jabatan',     e($r['jabatan'])],
      ['Atasan',      e($r['atasan_langsung']??'-')],
      ['Mulai Kerja', $r['mulai_bekerja']?formatTanggal($r['mulai_bekerja']):'-'],
      ['Periode',     '<strong>'.e($r['periode']).'</strong>'],
      ['Shift',       e($r['shift']??'-')],
      ['Cuti',        e($r['cuti']??'-')],
      ['Ijin',        e($r['ijin']??'-')],
      ['Periode Ke-', e($r['periode_ke']??'-')],
    ] as [$l,$v]): ?>
    <div style="display:flex;font-size:12px;padding:3px 0;border-bottom:1px solid var(--gray-50);">
      <span style="color:var(--gray-400);width:90px;flex-shrink:0"><?= $l ?></span>
      <span style="color:var(--gray-700)"><?= $v ?></span>
    </div>
    <?php endforeach; ?>
    <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--gray-100);">
      <div style="font-size:10px;color:var(--gray-400);margin-bottom:3px">PENILAIAN KESELURUHAN</div>
      <div style="font-size:13px;font-weight:700;color:var(--navy)"><?= e($r['penilaian_keseluruhan']) ?></div>
    </div>
    <div style="margin-top:8px;">
      <div style="font-size:10px;color:var(--gray-400);margin-bottom:3px">DINILAI OLEH</div>
      <div style="font-size:13px;font-weight:600;"><?= e($r['penilai_nama']) ?></div>
      <div style="font-size:11px;color:var(--gray-500)"><?= formatTanggal($r['tanggal_penilaian']) ?></div>
    </div>
    <?php if(!empty($r['tanda_tangan'])): ?>
    <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--gray-100);">
      <div style="font-size:10px;color:var(--gray-400);margin-bottom:5px">✍️ TANDA TANGAN DIGITAL</div>
      <div style="border:1px solid var(--gray-200);border-radius:8px;padding:4px;background:#fff;">
        <img src="<?= e($r['tanda_tangan']) ?>" alt="TTD" style="max-width:100%;max-height:60px;display:block;">
      </div>
      <div style="font-size:10px;color:var(--green);margin-top:3px">✅ Terverifikasi</div>
    </div>
    <?php endif; ?>
    <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--gray-50);">
      <div style="font-size:10px;color:var(--gray-400)">Submit: <?= $r['ft_submitted']?date('d/m/Y H:i',strtotime($r['ft_submitted'])):'-' ?></div>
      <div style="font-size:10px;color:var(--gray-300);font-family:monospace">IP: <?= e($r['submitted_ip']??'-') ?></div>
    </div>
  </div>

</div>

<!-- Section 2: Nilai Per Kriteria -->
<div class="card" style="margin-bottom:20px;overflow:hidden;">
  <div class="card-header">
    <div class="card-title"><div class="card-title-icon">📋</div>Nilai Per Kriteria</div>
    <div style="font-size:12px;color:var(--gray-400)">10 faktor × maks 5 = 50 poin</div>
  </div>
  <table class="tbl">
    <thead>
      <tr>
        <th style="width:34px">#</th>
        <th>Faktor Kinerja</th>
        <th style="text-align:center;width:70px">Nilai</th>
        <th style="width:150px">Predikat</th>
        <th>Indikator</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($kriteria as $i=>$k):
        $val=(int)($r[$k['key'].'_nilai']??0);
        $c=$warna[$val]??'#888'; $l=$label[$val]??'-'; $pct=($val/5)*100;
      ?>
      <tr <?= $val<=2?'style="background:#fef2f2;"':'' ?>>
        <td style="color:var(--gray-300);font-size:12px"><?= $i+1 ?></td>
        <td><span style="font-weight:600;font-size:13px"><?= e($k['label']) ?></span></td>
        <td style="text-align:center"><span style="font-size:26px;font-weight:900;color:<?= $c ?>"><?= $val ?></span></td>
        <td><span style="background:<?= $c ?>20;color:<?= $c ?>;padding:4px 12px;border-radius:99px;font-size:11.5px;font-weight:700;border:1px solid <?= $c ?>40"><?= $l ?></span></td>
        <td>
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="flex:1;max-width:140px;background:var(--gray-100);border-radius:99px;height:7px;">
              <div style="height:7px;border-radius:99px;width:<?= $pct ?>%;background:<?= $c ?>"></div>
            </div>
            <span style="font-size:11px;color:var(--gray-400)"><?= $val ?>/5</span>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="background:var(--gray-50);border-top:2px solid var(--gray-200);">
        <td colspan="2" style="padding:13px 16px;text-align:right;font-weight:700;color:var(--gray-600)">JUMLAH NILAI</td>
        <td style="text-align:center;padding:13px"><span style="font-size:30px;font-weight:900;color:<?= $r['jumlah_nilai']>=28?'var(--green)':'var(--red)' ?>"><?= $r['jumlah_nilai'] ?></span></td>
        <td colspan="2" style="padding:13px 16px">
          <?= $r['status_perpanjangan']==='Diperpanjang'
            ? '<span class="badge badge-green" style="font-size:12px;padding:5px 14px">✅ Diperpanjang (≥28)</span>'
            : '<span class="badge badge-red" style="font-size:12px;padding:5px 14px">❌ Tidak Diperpanjang (&lt;28)</span>' ?>
        </td>
      </tr>
    </tfoot>
  </table>
</div>

<!-- Section 3: Kualitatif -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;">
  <?php foreach([
    ['🌟','#f0fdf4','#16a34a','Bidang Unggul / Menonjol',$r['bidang_unggul']??''],
    ['📈','#fffbeb','#d97706','Perlu Diperhatikan & Ditingkatkan',$r['bidang_tingkatkan']??''],
    ['🎓','#eff6ff','#1d4ed8','Rencana Pelatihan / Pengembangan',$r['rencana_pelatihan']??''],
    ['💬','#f5f3ff','#6d28d9','Pesan / Komentar Tambahan',$r['pesan_tambahan']??''],
  ] as [$ico,$bg,$col,$ttl,$val]): ?>
  <div class="card" style="padding:18px 20px;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
      <span style="width:28px;height:28px;background:<?= $bg ?>;color:<?= $col ?>;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0"><?= $ico ?></span>
      <span style="font-size:13px;font-weight:700;color:var(--gray-700)"><?= $ttl ?></span>
    </div>
    <?php if($val): ?>
      <p style="font-size:13px;line-height:1.8;color:var(--gray-600)"><?= nl2br(e($val)) ?></p>
    <?php else: ?>
      <p style="font-size:13px;color:var(--gray-300);font-style:italic">Tidak diisi</p>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<!-- Permohonan Perpanjangan & Kualifikasi Jabatan -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;">

  <!-- Permohonan Perpanjangan -->
  <div class="card" style="padding:18px 20px;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
      <span style="width:28px;height:28px;background:#e0f2fe;color:#0369a1;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0">📋</span>
      <span style="font-size:13px;font-weight:700;color:var(--gray-700)">Permohonan untuk Diperpanjang / Tidak Diperpanjang</span>
    </div>
    <?php if(!empty($r['permohonan_perpanjangan'])): ?>
      <?php if($r['permohonan_perpanjangan']==='Diperpanjang'): ?>
        <div style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border:2px solid #16a34a;border-radius:10px;background:#dcfce7;font-size:14px;font-weight:700;color:#15803d;">
          ✅ Diperpanjang
        </div>
      <?php else: ?>
        <div style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border:2px solid #dc2626;border-radius:10px;background:#fee2e2;font-size:14px;font-weight:700;color:#b91c1c;">
          ❌ Tidak Diperpanjang
        </div>
      <?php endif; ?>
    <?php else: ?>
      <p style="font-size:13px;color:var(--gray-300);font-style:italic">Tidak diisi</p>
    <?php endif; ?>
  </div>

  <!-- Kualifikasi Jabatan -->
  <div class="card" style="padding:18px 20px;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
      <span style="width:28px;height:28px;background:#fef3c7;color:#b45309;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0">🏷️</span>
      <span style="font-size:13px;font-weight:700;color:var(--gray-700)">Kualifikasi / Jabatan yang Bersangkutan Saat Ini</span>
    </div>
    <?php if(!empty($r['kualifikasi_jabatan'])): ?>
      <p style="font-size:13px;line-height:1.8;color:var(--gray-600)"><?= nl2br(e($r['kualifikasi_jabatan'])) ?></p>
    <?php else: ?>
      <p style="font-size:13px;color:var(--gray-300);font-style:italic">Tidak diisi</p>
    <?php endif; ?>
  </div>

</div>

<!-- Status Memo Perpanjangan Kontrak -->
<div class="card" style="padding:20px 24px;margin-bottom:18px;border-left:4px solid <?= !empty($r['memo_id']) ? 'var(--blue)' : 'var(--green)' ?>;">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
    <div style="display:flex;align-items:center;gap:14px;">
      <div style="width:46px;height:46px;border-radius:12px;background:<?= !empty($r['memo_id']) ? '#eff6ff' : '#f0fdf4' ?>;color:<?= !empty($r['memo_id']) ? 'var(--blue)' : 'var(--green)' ?>;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;">
        <?= !empty($r['memo_id']) ? '📄' : '📝' ?>
      </div>
      <div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
          <h3 style="font-size:15px;font-weight:800;color:var(--gray-900);margin:0;">Memo Perpanjangan Kontrak</h3>
          <?php if(!empty($r['memo_id'])): ?>
            <span class="badge badge-green">✅ Sudah Dibuat</span>
            <?php if($r['memo_is_printed']): ?>
              <span class="badge badge-blue">🖨️ Sudah Dicetak</span>
            <?php else: ?>
              <span class="badge badge-amber">⏳ Belum Dicetak</span>
            <?php endif; ?>
          <?php else: ?>
            <span class="badge badge-amber">⚠️ Belum Dibuat</span>
          <?php endif; ?>
        </div>
        <div style="font-size:13px;color:var(--gray-600);">
          <?php if(!empty($r['memo_id'])): ?>
            Nomor Memo: <strong style="color:var(--gray-900);"><?= e($r['nomor_memo']) ?></strong>
            &nbsp;·&nbsp; Tanggal: <?= formatTanggal($r['tanggal_memo']) ?>
          <?php else: ?>
            Memo perpanjangan kontrak belum dibuat untuk hasil penilaian ini. Klik tombol di samping untuk membuat memo otomatis.
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <?php if(!empty($r['memo_id'])): ?>
        <a href="detail_memo.php?id=<?= $r['memo_id'] ?>" class="btn btn-warning btn-sm">👁️ Lihat Detail Memo</a>
        <a href="<?= APP_URL ?>/cetak_memo.php?id=<?= $r['memo_id'] ?>" class="btn btn-primary btn-sm" target="_blank">🖨️ Cetak Memo PDF</a>
        <a href="<?= APP_URL ?>/form_memo.php?penilaian_id=<?= $id ?>&edit=1" class="btn btn-light btn-sm">✏️ Edit Memo</a>
      <?php else: ?>
        <a href="<?= APP_URL ?>/form_memo.php?penilaian_id=<?= $id ?>" class="btn btn-success" style="box-shadow:0 3px 12px rgba(22,163,74,0.35);font-weight:700;padding:10px 22px;">
          📝 Buat Memo Perpanjangan
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if($r['jobdesk_harian']): ?>
<div class="card" style="padding:18px 20px;margin-bottom:18px;">
  <div style="font-size:13px;font-weight:700;margin-bottom:10px;color:var(--gray-700)">📌 Jobdesk Harian / Mingguan</div>
  <div style="font-size:13px;line-height:2;color:var(--gray-600);columns:2;column-gap:24px"><?= nl2br(e($r['jobdesk_harian'])) ?></div>
</div>
<?php endif; ?>

<?php if($r['catatan_hrd']): ?>
<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:var(--radius-lg);padding:18px 20px;margin-bottom:18px;">
  <div style="font-size:13px;font-weight:700;margin-bottom:8px;color:#92400e">📝 Catatan HRD</div>
  <p style="font-size:13px;color:#78350f;line-height:1.7"><?= nl2br(e($r['catatan_hrd'])) ?></p>
</div>
<?php endif; ?>

<!-- Riwayat -->
<?php if($riwayat->num_rows>1): ?>
<div class="card">
  <div class="card-header">
    <div class="card-title"><div class="card-title-icon">🗂️</div>Riwayat Penilaian — <?= e($r['nama']) ?></div>
  </div>
  <table class="tbl">
    <thead><tr><th>Periode</th><th style="text-align:center">Nilai</th><th>Status</th><th>Tanggal Penilaian</th><th style="text-align:center">Aksi</th></tr></thead>
    <tbody>
      <?php while($rv=$riwayat->fetch_assoc()): ?>
      <tr <?= $rv['id']===$id?'style="background:var(--blue-pale);"':'' ?>>
        <td>
          <?= e($rv['periode']) ?>
          <?php if($rv['id']===$id): ?><span class="badge badge-blue" style="font-size:10px;margin-left:6px">Sedang dilihat</span><?php endif; ?>
        </td>
        <td style="text-align:center"><span style="font-size:22px;font-weight:800;color:<?= $rv['jumlah_nilai']>=28?'var(--green)':'var(--red)' ?>"><?= $rv['jumlah_nilai'] ?></span></td>
        <td><?= $rv['status_perpanjangan']==='Diperpanjang'?'<span class="badge badge-green">✅ Diperpanjang</span>':'<span class="badge badge-red">❌ Ditolak</span>' ?></td>
        <td style="font-size:12px;color:var(--gray-500)"><?= formatTanggal($rv['tanggal_penilaian']) ?></td>
        <td style="text-align:center">
          <?php if($rv['id']!==$id): ?>
            <a href="detail_penilaian.php?id=<?= $rv['id'] ?>" class="btn btn-primary btn-xs">👁 Lihat</a>
          <?php else: ?><span style="color:var(--gray-300);font-size:12px">—</span><?php endif; ?>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('radarChart'),{
  type:'radar',
  data:{
    labels:['Sikap','Kerjasama','Inisiatif','Ketelitian','Ketepatan','Pengetahuan','Kualitas','Kuantitas','Belajar','Tg.Jawab'],
    datasets:[{
      label:'Nilai',
      data:[<?= implode(',',array_map(fn($k)=>(int)($r[$k['key'].'_nilai']??0),$kriteria)) ?>],
      borderColor:'<?= $r['jumlah_nilai']>=28?'#16a34a':'#ef4444' ?>',
      backgroundColor:'<?= $r['jumlah_nilai']>=28?'rgba(22,163,74,0.12)':'rgba(239,68,68,0.10)' ?>',
      pointBackgroundColor:'<?= $r['jumlah_nilai']>=28?'#16a34a':'#ef4444' ?>',
      pointRadius:4,borderWidth:2
    }]
  },
  options:{responsive:true,plugins:{legend:{display:false}},scales:{r:{min:0,max:5,ticks:{stepSize:1,backdropColor:'transparent',font:{size:9}},pointLabels:{font:{size:9.5},color:'#64748b'},grid:{color:'#e2e8f0'},angleLines:{color:'#e2e8f0'}}}}
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
