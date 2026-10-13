-- ============================================================
-- DATABASE: PKWT Employee Performance Assessment System
-- PT Indopelita Aircraft Industries
-- ============================================================

CREATE DATABASE IF NOT EXISTS pkwt_assessment CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pkwt_assessment;

-- ============================================================
-- TABEL: karyawan
-- ============================================================
CREATE TABLE karyawan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nik VARCHAR(20) UNIQUE NOT NULL,
    nama VARCHAR(100) NOT NULL,
    jabatan VARCHAR(100) NOT NULL,
    atasan_langsung VARCHAR(100),
    mulai_bekerja DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABEL: form_tokens
-- Token unik per karyawan per periode penilaian
-- Konsep privasi: setiap penilai hanya bisa akses via token
-- ============================================================
CREATE TABLE form_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    karyawan_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    periode VARCHAR(30) NOT NULL,
    catatan_hrd VARCHAR(255),
    shift VARCHAR(50),
    cuti VARCHAR(50),
    ijin VARCHAR(50),
    periode_ke VARCHAR(20),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_submitted TINYINT(1) DEFAULT 0,
    submitted_at DATETIME NULL,
    submitted_ip VARCHAR(45) NULL,
    FOREIGN KEY (karyawan_id) REFERENCES karyawan(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_karyawan_periode (karyawan_id, periode)
) ENGINE=InnoDB;

-- ============================================================
-- TABEL: penilaian
-- Data penilaian kinerja lengkap
-- ============================================================
CREATE TABLE penilaian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_id INT NOT NULL UNIQUE,
    karyawan_id INT NOT NULL,
    periode VARCHAR(30) NOT NULL,

    -- Halaman 1: Kriteria Umum
    sikap_nilai TINYINT NOT NULL,
    kerjasama_nilai TINYINT NOT NULL,
    inisiatif_nilai TINYINT NOT NULL,
    ketelitian_nilai TINYINT NOT NULL,
    ketepatan_nilai TINYINT NOT NULL,
    pengetahuan_nilai TINYINT NOT NULL,

    -- Halaman 2: Kriteria Lanjutan
    kualitas_nilai TINYINT NOT NULL,
    kuantitas_nilai TINYINT NOT NULL,
    belajar_nilai TINYINT NOT NULL,
    tjawab_nilai TINYINT NOT NULL,

    -- Kalkulasi
    jumlah_nilai INT NOT NULL,
    status_perpanjangan ENUM('Diperpanjang','Tidak Diperpanjang') NOT NULL,

    -- Halaman 3: Ringkasan Kualitatif
    penilaian_keseluruhan ENUM('Sangat Baik','Baik','Cukup Baik','Kurang Baik','Tidak Baik') NOT NULL,
    bidang_unggul TEXT,
    bidang_tingkatkan TEXT,
    rencana_pelatihan TEXT,
    jobdesk_harian TEXT,
    pesan_tambahan TEXT,

    -- Penilai
    penilai_nama VARCHAR(100) NOT NULL,
    tanggal_penilaian DATE NOT NULL,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (token_id) REFERENCES form_tokens(id),
    FOREIGN KEY (karyawan_id) REFERENCES karyawan(id),
    INDEX idx_karyawan (karyawan_id),
    INDEX idx_status (status_perpanjangan),
    INDEX idx_periode (periode)
) ENGINE=InnoDB;

-- ============================================================
-- TABEL: hr_admin
-- ============================================================
CREATE TABLE hr_admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABEL: audit_log
-- ============================================================
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    aksi VARCHAR(100) NOT NULL,
    keterangan TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES hr_admin(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- DEFAULT ADMIN
-- Username: admin | Password: admin123
-- GANTI PASSWORD SEGERA SETELAH LOGIN PERTAMA!
-- ============================================================
INSERT INTO hr_admin (username, password_hash, nama_lengkap, email) VALUES
('admin', '$2y$10$D3aO/7ySl0af9j1C3APMSuC6o.0hB5u6bnHmDIynPzcO7ni57lGrW', 'HRD Administrator', 'hrd@indopelita.com');

-- ============================================================
-- SAMPLE DATA KARYAWAN
-- ============================================================
INSERT INTO karyawan (nik, nama, jabatan, atasan_langsung, mulai_bekerja) VALUES
('EMP001', 'Ahmad Fauzi', 'Operator Produksi', 'Budi Santoso', '2024-01-15'),
('EMP002', 'Siti Rahayu', 'Teknisi Mesin', 'Budi Santoso', '2024-02-01'),
('EMP003', 'Rizky Pratama', 'Staff Administrasi', 'Dewi Lestari', '2024-03-10'),
('EMP004', 'Dewi Kusuma', 'Quality Control', 'Budi Santoso', '2024-01-20'),
('EMP005', 'Hendra Wijaya', 'Operator Gudang', 'Dewi Lestari', '2024-04-05');

-- ============================================================
-- MIGRASI: Tambah kolom tanda_tangan (jalankan jika database sudah ada)
-- ============================================================
ALTER TABLE penilaian ADD COLUMN IF NOT EXISTS tanda_tangan MEDIUMTEXT NULL AFTER tanggal_penilaian;

-- ============================================================
-- MIGRASI: Tambah kolom permohonan_perpanjangan & kualifikasi_jabatan
-- Jalankan jika database sudah ada sebelum fitur ini ditambahkan
-- ============================================================
ALTER TABLE penilaian
  ADD COLUMN IF NOT EXISTS permohonan_perpanjangan ENUM('Diperpanjang','Tidak Diperpanjang') NULL AFTER pesan_tambahan,
  ADD COLUMN IF NOT EXISTS kualifikasi_jabatan TEXT NULL AFTER permohonan_perpanjangan;

-- ============================================================
-- TABEL: memo_perpanjangan
-- Data memo perpanjangan kontrak yang diisi setelah penilaian
-- ============================================================
CREATE TABLE IF NOT EXISTS memo_perpanjangan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    penilaian_id INT NOT NULL,
    karyawan_id INT NOT NULL,
    token_id INT NOT NULL,

    -- Header memo
    nomor_memo VARCHAR(100) NOT NULL,
    tanggal_memo DATE NOT NULL,
    kota_memo VARCHAR(100) DEFAULT 'Tangerang Selatan',

    -- Pihak-pihak
    dari_jabatan VARCHAR(150),
    dari_nama VARCHAR(100),
    kepada_jabatan VARCHAR(150),
    perihal VARCHAR(255) DEFAULT 'Permohonan Perpanjangan Kesepakatan Kerja Personil',

    -- Isi memo
    kualifikasi_memo VARCHAR(150),
    tanggal_mulai_kontrak DATE,
    tanggal_akhir_kontrak DATE,
    no_pegawai VARCHAR(50),
    ref_kontrak VARCHAR(150),
    tembusan TEXT,

    -- Penandatangan
    ttd_memo_nama VARCHAR(100),
    ttd_memo_jabatan VARCHAR(150),

    -- Status cetak
    is_printed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (penilaian_id) REFERENCES penilaian(id) ON DELETE CASCADE,
    FOREIGN KEY (karyawan_id) REFERENCES karyawan(id) ON DELETE CASCADE,
    FOREIGN KEY (token_id) REFERENCES form_tokens(id) ON DELETE CASCADE,
    INDEX idx_penilaian (penilaian_id),
    INDEX idx_karyawan (karyawan_id)
) ENGINE=InnoDB;

-- ============================================================
-- MIGRASI: Tambah kolom status_memo ke memo_perpanjangan
-- Jalankan jika database sudah ada sebelum fitur ini ditambahkan
-- ============================================================
ALTER TABLE memo_perpanjangan
  ADD COLUMN IF NOT EXISTS status_memo ENUM('Diperpanjang','Tidak Diperpanjang') NULL AFTER tanggal_akhir_kontrak;

-- ============================================================
-- MIGRASI: Tambah kolom isi paragraf isi memo (bisa diedit)
-- ============================================================
ALTER TABLE memo_perpanjangan
  ADD COLUMN IF NOT EXISTS isi_paragraf1 TEXT NULL AFTER status_memo,
  ADD COLUMN IF NOT EXISTS isi_paragraf2 TEXT NULL AFTER isi_paragraf1;

  