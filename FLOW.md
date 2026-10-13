# Alur & Diagram Sistem PKWT (Process Flow & Architecture)
### PT Indopelita Aircraft Industries

Dokumen ini mendokumentasikan secara menyeluruh alur kerja (*business workflow*), siklus hidup data (*state lifecycle*), algoritma kalkulasi skor, serta diagram sekuensial interaksi pada **Sistem Penilaian Kinerja Karyawan PKWT dan Penerbitan Dokumen Memorandum**.

---

## 📑 Daftar Isi Diagram

1. [Alur Bisnis End-to-End (Overall Workflow)](#1-alur-bisnis-end-to-end-overall-workflow)
2. [Siklus Hidup Token Evaluasi (Token State Machine)](#2-siklus-hidup-token-evaluasi-token-state-machine)
3. [Mesin Kalkulasi Skor & Logika Keputusan (Scoring Engine)](#3-mesin-kalkulasi-skor--logika-keputusan-scoring-engine)
4. [Alur Penyusunan & Sinkronisasi Dokumen Memorandum](#4-alur-penyusunan--sinkronisasi-dokumen-memorandum)
5. [Sequence Diagram Interaksi Pengguna & Sistem](#5-sequence-diagram-interaksi-pengguna--sistem)
6. [Arsitektur Keamanan, Akses, & Audit Trail](#6-arsitektur-keamanan-akses--audit-trail)
7. [Matriks Relasi & Integritas Status Data](#7-matriks-relasi--integritas-status-data)

---

## 1. Alur Bisnis End-to-End (Overall Workflow)

Diagram berikut menggambarkan perjalanan data mulai dari pendaftaran karyawan oleh HRD, penerbitan token, pengisian form penilaian oleh atasan langsung, kalkulasi kelayakan perpanjangan, hingga terbitnya dokumen memorandum resmi.

```mermaid
flowchart TD
    classDef hrd fill:#0f172a,stroke:#38bdf8,stroke-width:2px,color:#fff;
    classDef atasan fill:#064e3b,stroke:#34d399,stroke-width:2px,color:#fff;
    classDef system fill:#1e1b4b,stroke:#818cf8,stroke-width:2px,color:#fff;
    classDef decision fill:#701a75,stroke:#f472b6,stroke-width:2px,color:#fff;
    classDef doc fill:#14532d,stroke:#4ade80,stroke-width:2px,color:#fff;

    subgraph HRD_Flow["1. Divisi Human Capital / Admin HRD"]
        A[Input / Import Data Karyawan]:::hrd --> B[Buka Menu Token Penilaian]:::hrd
        B --> C[Generate Token Baru<br/>Pilih Karyawan & Periode]:::hrd
        C --> D[Kirim Tautan Unik via WhatsApp / Chat]:::hrd
    end

    subgraph Evaluation_Flow["2. Atasan Langsung (Penilai)"]
        D -.->|Membuka Tautan Unik| E[Akses Halaman form.php?token=xyz]:::atasan
        E --> F{Validasi Token Sistem}:::decision
        F -- Valid & Aktif --> G[Isi 10 Kriteria Penilaian<br/>Skala 1 - 5]:::atasan
        F -- Expired / Tidak Valid --> H[Tampilkan Halaman Blokir / Kadaluarsa]:::system
        F -- Sudah Pernah Diisi --> I[Tampilkan Halaman Penilaian Telah Selesai]:::system

        G --> J[Isi Ulasan Kualitatif, Pelatihan & Jobdesk]:::atasan
        J --> K[Bubuhkan Tanda Tangan Digital pada Canvas]:::atasan
        K --> L[Klik: Kirim Penilaian Kinerja]:::atasan
    end

    subgraph Scoring_Flow["3. Pemrosesan & Kalkulasi Otomatis"]
        L --> M[Kalkulasi Total Skor = sum(10 Kriteria)]:::system
        M --> N{Apakah Skor >= 28?}:::decision
        N -- Ya (>= 28) --> O[Status: DIPERPANJANG]:::system
        N -- Tidak (< 28) --> P[Status: TIDAK DIPERPANJANG]:::system
        O --> Q[Simpan Penilaian ke Database & Kunci Token]:::system
        P --> Q
        Q --> R[Redirect Otomatis ke form_memo.php]:::system
    end

    subgraph Memo_Flow["4. Penyusunan Memorandum Perpanjangan"]
        R --> S{Pilihan Tindakan Memo}:::decision
        S -- Langsung Dibuat --> T[Lengkapi Header & No. Dokumen Memo]:::atasan
        S -- Dilewati / Tunda --> U[Klik: Lewati Pengisian Memo]:::atasan
        U --> V[Akses Selesai (Memo Dibuat Nanti oleh HRD)]:::system

        T --> W[Verifikasi Status & Teks Sinkronisasi Otomatis]:::atasan
        W --> X[Cek Live Preview Dokumen Memorandum]:::atasan
        X --> Y[Klik: Terbitkan & Simpan Memorandum]:::atasan
    end

    subgraph Final_Flow["5. Output & Pengarsipan Resmi"]
        Y --> Z[Buka cetak_memo.php]:::doc
        Z --> AA[Unduh Berkas PDF atau Cetak Lembar A4 Fisik]:::doc
        AA --> AB[Pembubuhan Tanda Tangan Basah & Stempel Perusahaan]:::hrd
        AB --> AC[Dokumen Selesai & Terarsip di Database]:::doc
    end
```

---

## 2. Siklus Hidup Token Evaluasi (Token State Machine)

Token evaluasi adalah string acak sepanjang 64 karakter heksadesimal (`bin2hex(random_bytes(32))`) yang bertindak sebagai kunci akses sekali pakai (*one-time authentication*).

```mermaid
stateDiagram-v2
    [*] --> DRAFT : Admin membuat token di token.php
    DRAFT --> ACTIVE : Disimpan ke tabel form_tokens
    
    state ACTIVE {
        [*] --> IDLE : Menunggu dibuka oleh penilai
        IDLE --> VIEWED : Penilai membuka form.php
    }

    ACTIVE --> EXPIRED : Waktu saat ini > expires_at (7 hari)
    ACTIVE --> SUBMITTED : Penilai submit formulir penilaian
    
    state SUBMITTED {
        [*] --> LOCKED : is_submitted = 1, form terkunci permanen
        LOCKED --> MEMO_ATTACHED : Memo perpanjangan dibuat
    }

    SUBMITTED --> ACTIVE : Tindakan Khusus Admin: Hapus Penilaian (Reset Token)
    EXPIRED --> [*] : Tidak dapat diisi, penilai harus hubungi HRD
    SUBMITTED --> [*] : Dokumen terarsip lengkap
```

### Aturan Transisi Status Token:
| Status | Syarat Kondisi | Dampak pada Akses Pengguna |
| :--- | :--- | :--- |
| **Active (Aktif)** | `is_submitted = 0` dan `expires_at >= NOW()` | Formulir terbuka penuh, interaktif, dan dapat disubmit. |
| **Expired (Kedaluwarsa)** | `is_submitted = 0` dan `expires_at < NOW()` | Diblokir: Menampilkan pesan masa berlaku berakhir. Penilai diarahkan hubungi HRD. |
| **Submitted (Selesai)** | `is_submitted = 1` | Diblokir: Formulir tidak dapat diisi ulang. Menampilkan status konfirmasi arsip. |
| **Reset / Re-enabled** | Penilaian dihapus oleh Admin di `hapus_penilaian.php` | Sistem otomatis mereset `is_submitted = 0`, `submitted_at = NULL` agar bisa diisi ulang. |

---

## 3. Mesin Kalkulasi Skor & Logika Keputusan (Scoring Engine)

Sistem menggunakan formula akumulasi linear dari 10 faktor kinerja yang masing-masing memiliki rentang nilai 1 hingga 5 poin:

$$\text{Total Nilai} = \sum_{i=1}^{10} \text{Kriteria}_i \quad (\text{Rentang: } 10 - 50)$$

```mermaid
flowchart LR
    classDef input fill:#1e293b,stroke:#64748b,stroke-width:1.5px,color:#fff;
    classDef calc fill:#0f172a,stroke:#38bdf8,stroke-width:2px,color:#fff;
    classDef pass fill:#064e3b,stroke:#10b981,stroke-width:2px,color:#fff;
    classDef fail fill:#4c0519,stroke:#f43f5e,stroke-width:2px,color:#fff;

    subgraph Input_Kriteria["10 Faktor Kinerja (1 - 5 Poin)"]
        K1[1. Sikap / Attitude]:::input
        K2[2. Kerjasama / Teamwork]:::input
        K3[3. Inisiatif & Kreativitas]:::input
        K4[4. Ketelitian]:::input
        K5[5. Ketepatan Waktu]:::input
        K6[6. Pengetahuan Pekerjaan]:::input
        K7[7. Kualitas Pekerjaan]:::input
        K8[8. Kuantitas Pekerjaan]:::input
        K9[9. Kemampuan Belajar]:::input
        K10[10. Tanggung Jawab]:::input
    end

    Input_Kriteria --> SUM["Kalkulator Skor<br/>Total = K1 + ... + K10<br/>(Maksimal 50 Poin)"]:::calc

    SUM --> DECISION{"Total Nilai >= 28 ?"}:::calc

    DECISION -- "Ya (Skor 28 s/d 50)" --> LULUS["Status Rekomendasi:<br/>DIPERPANJANG"]:::pass
    DECISION -- "Tidak (Skor 10 s/d 27)" --> TIDAK["Status Rekomendasi:<br/>TIDAK DIPERPANJANG"]:::fail

    LULUS --> SET_MEMO1["Auto-Check Radio: Diperpanjang<br/>Default Paragraf: Personel masih dibutuhkan<br/>terkait Capability Perusahaan."]:::pass
    TIDAK --> SET_MEMO2["Auto-Check Radio: Tidak Diperpanjang<br/>Default Paragraf: Masa kontrak telah berakhir<br/>dan tidak dapat diperpanjang."]:::fail
```

### Tabel Predikat Nilai Individu:
* **Nilai 5**: Sangat Baik (*Excellent*)
* **Nilai 4**: Baik (*Good*)
* **Nilai 3**: Cukup Baik (*Satisfactory*)
* **Nilai 2**: Kurang Baik (*Needs Improvement*)
* **Nilai 1**: Tidak Baik (*Unsatisfactory*)

---

## 4. Alur Penyusunan & Sinkronisasi Dokumen Memorandum

Modul memorandum menghubungkan hasil penilaian dengan lembar persuratan dinas PT Indopelita secara otomatis.

```mermaid
flowchart TD
    classDef step fill:#0f172a,stroke:#0284c7,stroke-width:2px,color:#fff;
    classDef sync fill:#1e1b4b,stroke:#818cf8,stroke-width:2px,color:#fff;
    classDef preview fill:#064e3b,stroke:#10b981,stroke-width:2px,color:#fff;

    A[Data Masuk dari Hasil Penilaian]:::step --> B[Inisialisasi Nilai Default Memo]:::step
    B --> C[Set Nomor Memo, Tanggal, Kota, & Kualifikasi]:::step
    
    C --> D{Interaksi Radio Status Memo}:::sync
    D -- Klik: Diperpanjang --> E[Sinkronisasi Paragraf Pembuka 1:<br/>'Bersama ini kami sampaikan daftar nama para Personel<br/>yang masih dibutuhkan, dikarenakan terkait dengan<br/>Capability Perusahaan serta kebutuhan perusahaan.']:::sync
    D -- Klik: Tidak Diperpanjang --> F[Sinkronisasi Paragraf Pembuka 2:<br/>'Bersama ini kami sampaikan daftar nama para Personel<br/>yang masa kontraknya telah berakhir dan tidak dapat<br/>diperpanjang, dikarenakan berbagai pertimbangan perusahaan.']:::sync

    E --> G[Pembaruan Live Preview di Layar]:::preview
    F --> G[Pembaruan Live Preview di Layar]:::preview

    G --> H{Penilai / HRD Mengedit Paragraf Manual?}:::step
    H -- Ya --> I[Textarea Diperbarui Sesuai Kebutuhan Khusus]:::step
    H -- Tidak --> J[Gunakan Teks Standar Perusahaan]:::step

    I --> K[Simpan ke Tabel memo_perpanjangan]:::step
    J --> K

    K --> L[Generate Halaman Cetak Resmi: cetak_memo.php]:::preview
    L --> M[Render Kop Surat Resmi + Tabel Nominatif + Tanda Centang + Ruang Tanda Tangan]:::preview
```

---

## 5. Sequence Diagram Interaksi Pengguna & Sistem

Diagram sekuen berikut merinci komunikasi data antar entitas mulai dari Atasan Penilai, Aplikasi Web, MariaDB, hingga Ekspor PDF.

```mermaid
sequenceDiagram
    autonumber
    actor Penilai as Atasan Langsung (Penilai)
    actor HRD as Admin HRD
    participant App as Aplikasi Web PKWT (PHP)
    participant DB as Database (MariaDB)
    participant PDF as Generator PDF (html2pdf)

    HRD->>App: Input token baru (periode, cuti, shift)
    App->>DB: INSERT INTO form_tokens (token, expires_at)
    App-->>HRD: Token aktif + Tombol Bagikan WhatsApp
    HRD->>Penilai: Mengirim tautan evaluasi unik via WA

    Penilai->>App: GET /form.php?token=xyz
    App->>DB: SELECT * FROM form_tokens WHERE token=xyz
    DB-->>App: Data token & karyawan ditemukan
    App-->>Penilai: Render formulir evaluasi 10 kriteria + canvas TTD

    Penilai->>App: POST /form.php (Nilai 1-5, Ulasan, TTD Base64)
    App->>App: Validasi input & hitung Total Skor (>= 28 ?)
    App->>DB: INSERT INTO penilaian (...)
    App->>DB: UPDATE form_tokens SET is_submitted=1
    App-->>Penilai: Redirect ke /form_memo.php?token=xyz

    Penilai->>App: GET /form_memo.php
    App->>DB: SELECT * FROM penilaian WHERE id=penilaian_id
    DB-->>App: Data penilaian & identitas karyawan
    App-->>Penilai: Render formulir memorandum + live preview

    Penilai->>App: POST /form_memo.php (Header memo, No. Dokumen, TTD Memo)
    App->>DB: INSERT INTO memo_perpanjangan (...)
    App-->>Penilai: Menampilkan layar sukses & tautan cetak

    Penilai->>App: GET /cetak_memo.php?id=memo_id
    App->>DB: SELECT * FROM memo_perpanjangan WHERE id=memo_id
    DB-->>App: Data lengkap memorandum
    App-->>Penilai: Render lembar memorandum A4 resmi
    Penilai->>PDF: Klik tombol "Unduh PDF"
    PDF-->>Penilai: Berkas PDF A4 terunduh ke perangkat
```

---

## 6. Arsitektur Keamanan, Akses, & Audit Trail

```mermaid
flowchart TD
    classDef security fill:#450a0a,stroke:#f87171,stroke-width:2px,color:#fff;
    classDef gate fill:#0f172a,stroke:#38bdf8,stroke-width:2px,color:#fff;
    classDef audit fill:#064e3b,stroke:#34d399,stroke-width:2px,color:#fff;

    A[Permintaan Masuk / HTTP Request] --> B{Jalur Akses}:::gate

    subgraph Public_Guard["Proteksi Jalur Publik (form.php / form_memo.php)"]
        B -- Jalur Publik --> C[Validasi Parameter Token via GET/POST]:::security
        C --> D{Cek Status Token di DB}:::security
        D -- Token Tidak Ditemukan --> E[403: Akses Ditolak / Tautan Tidak Sah]:::security
        D -- is_submitted = 1 --> F[Blokir: Formulir Telah Dikunci]:::security
        D -- NOW > expires_at --> G[Blokir: Tautan Telah Kedaluwarsa]:::security
        D -- Valid & Terbuka --> H[Izinkan Akses Formulir]:::gate
    end

    subgraph Admin_Guard["Proteksi Jalur Administrasi (/admin/*)"]
        B -- Jalur Admin --> I{Cek Sesi: isAdminLoggedIn?}:::security
        I -- Tidak Ada Sesi --> J[Redirect ke /admin/login.php]:::security
        I -- Sesi Ada --> K{Cek Waktu Inaktivitas > 3600s?}:::security
        K -- Ya (Timeout) --> L[Hancurkan Sesi & Redirect ke Login]:::security
        K -- Tidak (Aktif) --> M[Perbarui login_time & Izinkan Akses]:::gate
    end

    subgraph Audit_Trail["Pencatatan Audit Trail (audit_log)"]
        H -.->|Submit Penilaian| N[Catat IP, Tanggal & Jam Penilaian]:::audit
        M -.->|Aksi Admin Terpilih| O[Fungsi auditLog: LOGIN, BUAT_TOKEN, MEMO, HAPUS]:::audit
        O --> P[(Tabel audit_log: admin_id, aksi, keterangan, ip_address)]:::audit
    end
```

---

## 7. Matriks Relasi & Integritas Status Data

Tabel berikut menunjukkan bagaimana status di entitas saling berkaitan sepanjang siklus hidup dokumen:

| Kondisi Workflow | `form_tokens.is_submitted` | `penilaian.status_perpanjangan` | `memo_perpanjangan.status_memo` | Status Dokumen |
| :--- | :---: | :---: | :---: | :--- |
| **Token Diterbitkan** | `0` | *(Belum ada baris)* | *(Belum ada baris)* | Menunggu evaluasi penilai |
| **Token Kedaluwarsa** | `0` | *(Belum ada baris)* | *(Belum ada baris)* | Kedaluwarsa, tidak dapat diisi |
| **Penilaian Lulus ($\ge 28$)** | `1` | `Diperpanjang` | *(Draft / Belum terbit)* | Evaluasi selesai, siap buat memo |
| **Penilaian Tidak Lulus ($< 28$)** | `1` | `Tidak Diperpanjang` | *(Draft / Belum terbit)* | Evaluasi selesai, siap buat memo |
| **Memo Diterbitkan (Lanjut)** | `1` | `Diperpanjang` | `Diperpanjang` | Berkas final siap cetak & TTD basah |
| **Memo Diterbitkan (Berhenti)** | `1` | `Tidak Diperpanjang` | `Tidak Diperpanjang` | Berkas final siap cetak & TTD basah |
| **Penilaian Dihapus oleh Admin** | Direset ke `0` | Dihapus (`DELETE`) | Terhapus otomatis (`CASCADE`) | Token kembali aktif untuk evaluasi ulang |

---

**Hak Cipta &copy; 2026 PT Indopelita Aircraft Industries.**  
*Dokumentasi Teknis & Alur Kerja Sistem Penilaian PKWT.*
