# Scope Proyek — Portal Guru (SiPeka v2)

| | |
|---|---|
| **Sekolah** | SD Islam Darussalam Palangka Raya |
| **Sumber analisis** | https://sdidpky.my.canva.site/sipeka — *SiPeka, Sistem Penjadwalan & Kalender Akademik* |
| **Tanggal analisis** | 11 September 2026 |
| **Status** | Versi 0.2 — pertanyaan terbuka sudah dijawab sekolah (11 September 2026), lihat §15 |
| **Keputusan utama** | Bangun ulang sebagai web app: Laravel 13 + Filament 5, login email + password, di `sipeka.sdislamdarussalam.id` |

> Analisis dilakukan dari source code yang dimuat halaman publik, tanpa login dan tanpa mengubah data apa pun.

---

## 1. Ringkasan

SiPeka adalah portal akademik guru yang dibuat dengan **Canva Code** dan dipasang di **Canva Sites**. Aplikasi ini punya 18 menu yang mencakup hampir semua administrasi harian guru: jadwal pelajaran, absensi guru, jurnal mengajar, penggantian guru, kokurikuler, ekstrakurikuler, manajemen tugas, supervisi, peminjaman PID, outing class, penilaian sumatif, monitoring & evaluasi, dan monitoring administrasi kelas. Penggunanya 34 guru/staf untuk 14 rombel.

Secara fungsi SiPeka sudah lengkap dan alurnya sudah mengikuti kebiasaan sekolah. Masalahnya ada di fondasi:

- Login cuma pakai **kode 2 huruf tanpa password**, dan daftar kodenya bisa dilihat siapa saja di source code.
- **Hak akses dan PIN persetujuan hanya dicek di browser.**
- Semua data ada di **satu Canva Sheet** berisi 40 kolom untuk 23 jenis data, dan sebagian field terbuang waktu disimpan.
- **Notifikasi dan status online disimpan di browser masing-masing**, jadi tidak sampai ke guru lain.
- **Data guru, kelas, dan mapel ditulis langsung di kode.**

**Keputusan sekolah (§15):** SiPeka dibangun ulang sebagai Portal Guru, yaitu web app sungguhan dengan login email + password per guru, database MySQL, hak akses yang ditegakkan di server, arsip per tahun ajaran/semester, dan alur persetujuan yang jelas. Semua alur yang sudah dipakai sekolah dipertahankan, kecuali fitur yang diputuskan untuk dihapus.

| Angka kunci | Nilai |
|---|---|
| Menu aktif | 18 (+2 fitur tersembunyi) |
| Jenis data (`type`) | 23 |
| Kolom Canva Sheet | 40 |
| Guru/staf | 34 (33 wajib absen & jurnal) |
| Rombel | 14 (I-A s.d. VI-B) |
| Mata pelajaran | 18 |
| Slot waktu | 19 (06.00–16.30) |
| Ukuran kode | ±2.150 baris JavaScript dalam 1 file (±300 KB) |

---

## 2. Cara kerja SiPeka saat ini

### Lapisan teknis

1. **Canva Site** `sdidpky.my.canva.site/sipeka` hanya berisi satu blok *Konten yang disematkan* (iframe).
2. **Canva Code (codelet)**: satu file HTML ±300 KB yang berjalan di domain `*.canvacode.com`. Isinya HTML + JavaScript murni (tanpa framework), Tailwind CSS 3.4 via CDN, ikon Lucide, SheetJS 0.18 untuk ekspor Excel, dan font DM Sans.
3. **Data SDK Canva** (`window.dataSdk`): `init()` memuat **seluruh** isi Canva Sheet ke memori (`allData`), sedangkan `create` / `update` / `delete` dipakai untuk menulis. Setiap perubahan memicu `onDataChanged`, lalu seluruh halaman aktif di-render ulang.
4. **Canva Sheet**: satu tabel. Setiap baris punya kolom `type` yang menandai jenis datanya (`schedule`, `journal`, `attendance`, dst.).
5. **localStorage browser**: menyimpan notifikasi lokal, status guru online, riwayat login, riwayat jadwal monitoring, dan salinan jadwal. Data ini **hanya ada di perangkat itu**.

### Alur login

- Guru mengetik kode 2 huruf (inisial), lalu kode itu dicocokkan dengan daftar 34 guru yang ditulis di dalam kode.
- Peran ditentukan dari kode: `JJ` = Kepala Sekolah, `KN`/`FB` = admin, `MK` = koordinator ekskul, guru yang punya `kelas` = wali kelas, jabatan "Pendamping Wali Kelas" = pendamping.
- Tidak ada password, tidak ada sesi di server. Logout hanya mengosongkan tampilan.

### Alur simpan

- Data form dipetakan ke 40 kolom Canva Sheet. **Field yang tidak ada di daftar kolom dibuang diam-diam.**
- Semua operasi tulis masuk antrean dengan jeda **0,55 detik**, karena Canva Sheet menolak tulis yang terlalu rapat. Kalau gagal, SDK disambungkan ulang lalu dicoba sekali lagi.
- Hasilnya ditampilkan lewat toast (berhasil/gagal).

### Data referensi yang ditulis di kode

Daftar guru (34), rombel (14, masing-masing bernama tokoh ilmuwan muslim, mis. I-A *Ibnu Sina*, VI-B *Jabbir bin Hayyan*), mapel (18), slot waktu (19), rutinitas harian (Senin, Selasa–Kamis, Jumat), lokasi PID (Perpustakaan + 14 kelas), kutipan motivasi, dan aturan hak akses per kode guru.

---

## 3. Peran pengguna

### Kondisi di SiPeka saat ini

| Peran | Siapa | Cakupan |
|---|---|---|
| Kepala Sekolah | `JJ` | Melihat semua data & dashboard pimpinan; tidak mengisi absensi/jurnal; bisa mengubah/menghapus riwayat penggantian |
| Wakakur (admin utama) | `KN` | Mengelola jadwal, tugas, kalender, absensi guru lain. **Khusus KN:** jadwal kokurikuler, monitoring administrasi kelas, persetujuan outing, input pulang awal untuk guru lain |
| Admin Kurikulum | `FB` (juga Wali Kelas II-A) | Hak admin sama dengan KN, kecuali fitur khusus KN |
| Koordinator Ekskul | `MK` (Waka Kesiswaan) | Mengelola jadwal ekstrakurikuler, dinding pengumuman, komentar |
| Wali Kelas | 13 guru (+ FB) | Input kegiatan kokurikuler kelasnya; menerima jadwal monitoring kelas |
| Pendamping Wali Kelas | 14 guru | Checklist pengisian SIMPATI harian |
| Guru lain | `IN`, `JW`, `BR` (Wakasek) | Fitur guru umum |

### Peran di Portal Guru (sesuai keputusan §15)

| Peran | Siapa | Hak di aplikasi baru |
|---|---|---|
| Kepala Sekolah | `JJ` | **Hanya membaca** semua data + **menyetujui** (persetujuan apa saja masih dikonfirmasi, lihat tindak lanjut §15). Tidak ada tambah/ubah/hapus di modul mana pun |
| Waka Kurikulum (admin utama) | `KN` | Admin penuh. Satu-satunya yang **menjadwalkan monitoring administrasi kelas**. **Menyetujui outing class** |
| Admin Kurikulum | `FB` | Sama dengan KN, **kecuali penjadwalan monitoring administrasi kelas** |
| Waka Sarpras | `JW` | Fitur guru + **menyetujui reservasi PID** |
| Koordinator Ekskul | `MK` | Tetap: mengelola ekstrakurikuler, pengumuman, komentar |
| Wali Kelas | 13 guru (+ FB) | Tetap |
| Pendamping Wali Kelas | 14 guru | Tetap |
| Guru lain | `IN`, `BR` | Tetap |

Satu orang bisa punya lebih dari satu peran (mis. FB = Admin Kurikulum + Wali Kelas II-A; JW = guru + Waka Sarpras). Semua guru selain Kepala Sekolah (33 orang) wajib mengisi absensi dan jurnal.

---

## 4. Inventaris fitur

### A. Akademik & jadwal

**Dashboard** — semua; versi pimpinan untuk KS/KN/FB/MK
- *Pimpinan:* sapaan, status hari (Efektif/Libur/Kegiatan/Non-Efektif) + jam WIB, rekap absensi guru hari ini per status (KN & JJ), rekap SIMPATI per kelas, kartu statistik, **analisis beban kerja & JP** per kelas dan per guru, status jurnal hari ini, guru digantikan hari ini, status PID, ringkasan monitoring kelas.
- *Guru:* pengingat kelengkapan harian (setelah 15.00 WIB), widget jadwal monitoring (wali/pendamping), penggantian hari ini, status PID.
- *Semua:* **matriks jadwal mingguan** berisi 14 kolom rombel × slot waktu per hari, dengan blok rutinitas, sel berurutan yang digabung otomatis, dan jadwal guru yang sedang login di-highlight.

**Jadwal Pelajaran** — kelola: KN, FB · data: `schedule`, `schedule_history`
- Form: hari, slot, mapel, guru (autocomplete), JP otomatis, multi-kelas.
- Mapel khusus terisi otomatis: Ummi/Al-Qur'an & Penguatan Hafalan → *Tim Ummi*; PRAMUKA → *Pembina PRAMUKA*; JP 0 untuk Hafalan & Pramuka.
- Cek bentrok kelas/guru pada hari & slot yang sama (dikecualikan: PJOK, PRAMUKA hari Kamis).
- Daftar per hari dengan slot berurutan digabung, edit lewat modal, hapus dengan konfirmasi + peringatan data terkait, riwayat perubahan.
- Guru lain hanya melihat "Jadwal Mengajar Saya" (dan matriks di dashboard).

**Profil** — semua
- Jadwal mengajar (slot digabung), tugas utama (JP per mapel/kelas), tugas tambahan, total JP.

### B. Administrasi harian guru

**Absensi Guru** — input: semua kecuali KS; kelola: KN/FB · data: `attendance`, `teacher_leave`, `early_leave`, `simpati`, `academic_calendar`
- Status Hadir/Izin/Sakit/Cuti. Cuti menyimpan periode tanggal mulai–selesai.
- Aturan: mulai 13 Juli 2026, hanya Senin–Jumat, tidak di hari Libur, absensi hari ini dibuka pukul 06.00 WIB. Ada label *Terlambat diisi* kalau diisi setelah tanggalnya.
- Pengajuan pulang awal (jam + alasan) → notifikasi ke semua guru.
- Admin: kelola hari sekolah efektif (Libur/Kegiatan), edit/hapus riwayat, input untuk guru lain.
- KS/KN: diagram kehadiran bulan ini dan tingkat kehadiran (Hadir ÷ 33 guru × hari efektif berjalan), daftar guru yang belum absen setelah 14.00 WIB, riwayat cuti per guru.
- Pendamping: checklist SIMPATI + tautan ke portal SIMPATI, riwayat SIMPATI.
- Peringatan di perangkat pukul 13.30 kalau absensi/SIMPATI belum diisi.

**Jurnal Guru** — input: semua kecuali KS · data: `journal`
- Kelas & jam terisi otomatis dari jadwal (slot berurutan digabung). Guru pengganti mewarisi jadwal guru yang digantikan.
- Isian: materi, halaman, link modul/RPP, deskripsi, kehadiran siswa (hadir/izin/sakit/alpa).
- Validasi: hanya hari Efektif; cegah duplikat (guru + tanggal + kelas + mapel + jam).
- Riwayat + filter tanggal, detail + cetak, edit/hapus (pemilik/admin), ekspor Excel harian (sekaligus membuka Google Spreadsheet).

**Penggantian Guru** — input: semua kecuali KS (guru hanya untuk dirinya) · data: `substitution`
- Kelas & jam terfilter dari jadwal guru yang digantikan pada hari itu; alasan wajib.
- Riwayat: KS/admin melihat semua + filter tanggal + edit/hapus; guru melihat miliknya sendiri/saat jadi pengganti.
- Tombol simpan nonaktif saat guru sedang cuti.

**Manajemen Tugas** — KN/FB · data: `task`, `task_extra`
- Tugas tambahan (Utama/Tambahan + JP), masuk ke hitungan beban kerja.
- Tugas guru: judul, penerima (bisa semua guru), tanggal mulai, tenggat, deskripsi, link → checklist penyelesaian per tugas.
- Tampilan "Tugas Saya" untuk guru sudah ada di kode, **tapi menunya tidak muncul untuk guru** (lihat T4).

### C. Program & kegiatan

**Kokurikuler** — jadwal: KN; input: wali kelas; laporan: pimpinan · data: `cocurricular_schedule`, `cocurricular`
- KN menetapkan tanggal/tema + target kelas.
- Wali kelas memilih jadwal, bentuk kegiatan (Proyek/Kunjungan/Aksi Sosial/Lainnya), deskripsi, dan **8 Dimensi Profil Lulusan**.

**Kurikulum** — kelola: KN/FB; lihat: semua · data: `curriculum`
- Dokumen resmi berupa judul + link Google Drive, dengan thumbnail otomatis, pratinjau (iframe/gambar), lightbox, dan unduh.

**Ekstrakurikuler** — kelola: MK · data: `extracurricular`, `extracurricular_attendance`, `extracurricular_comment`
- Jadwal ekskul: nama, hari (Selasa–Kamis), waktu (14.15–15.15 / 15.30–16.30), tempat, guru pendamping (multi), pelatih luar.
- Absensi guru ekskul hari ini (Hadir/Izin/Sakit/Digantikan), hanya tampil kalau guru terjadwal.
- Dinding pengumuman (judul, teks, gambar Drive) + komentar diskusi.

**Penilaian Sumatif Akhir Bab** — input: semua kecuali KS · data: `assessment`
- Kelas, mapel, tanggal, bab, status selesai, link soal & link hasil (Drive).

### D. Sarana & pengajuan

**PID (Papan Interaktif Digital)** — reservasi: semua kecuali KS; setujui: KN/FB · data: `pid`
- Reservasi tanggal, jam, tempat, mapel, keperluan. Ditolak kalau slot sudah dipesan.
- Persetujuan admin pakai PIN. Setelah disetujui, muncul tombol WhatsApp dengan pesan peminjaman yang sudah terisi otomatis ke pengelola PID.

**Outing Class** — ajukan: semua kecuali KS; setujui: KN + PIN · data: `outing`
- Tanggal, kelas, lokasi tujuan, deskripsi → status Menunggu/Disetujui.

**Supervisi** — tetapkan: KN/FB · data: `supervision`
- Guru, mapel, tanggal, tempat, supervisor → status Terjadwal. **Belum ada instrumen/hasil supervisi.**

### E. Monitoring & evaluasi

**Monitoring & Evaluasi (Monev)** — pimpinan: 33 guru; guru: dirinya
- Filter bulan, statistik jurnal/absensi/penilaian/supervisi.
- Lampu status per guru: **hijau** = lengkap; **merah** = jurnal hari ini atau penilaian sumatif belum ada; **kuning** = kehadiran di bawah 80%.
- Detail: bab penilaian, % kehadiran, indikator jurnal 4 minggu (tepat waktu / terlambat / kosong).

**Monitoring Administrasi Kelas** — lihat: KS/KN/FB; jadwalkan: KN · data: `class_monitoring_schedule`, `class_monitoring`
- Dua langkah: jadwalkan (tanggal + kelas → notifikasi ke wali & pendamping), lalu *Mulai Monitoring*.
- Checklist **28 butir dalam 5 kelompok** (Administrasi Pembelajaran, Wali Kelas, Peserta Didik, Dokumen Digital, Lingkungan Kelas) dengan pilihan Lengkap / Perlu Diperbaiki / Belum Ada / Tidak Relevan.
- Skor = Lengkap ÷ butir relevan. ≥ 90% *Sangat Lengkap*, 75–89% *Perlu Perhatian*, < 75% atau ada "Belum Ada" = *Perlu Tindak Lanjut*. Ditambah catatan, rekomendasi, dan tenggat.
- Hasil checklist disimpan sebagai JSON di kolom `notes`.

### F. Lintas modul

- **Notifikasi:** lonceng + badge (mobile & desktop), popover 20 terakhir, riwayat. Sumbernya data sheet (penggantian, PID, absensi, jurnal, jadwal monitoring; + kurikulum & pengumuman untuk pimpinan) dan notifikasi lokal (jadwal monitoring, pulang awal, tenggat).
- **Guru online:** indikator jumlah guru aktif (dianggap idle setelah 2 menit) + riwayat login.
- **Kalender akademik:** status hari yang menentukan kewajiban absensi/jurnal/monitoring (§6).
- **Responsif:** sidebar berubah jadi menu hamburger di HP. Ada juga modal, toast, lightbox, dan halaman login dengan kutipan bergilir.
- **Tersembunyi (tidak ada di menu):** *Asisten AI Guru* (7 template prompt; hasilnya teks template, bukan AI sungguhan; ekspor Word/PNG) dan *RPP Pembelajaran Mendalam* (form CP/TP → ringkasan RPP).

---

## 5. Matriks hak akses

### Kondisi saat ini

**Kelola** = tambah/ubah/hapus semua data · **Input** = mengisi untuk diri sendiri · **Lihat** = melihat data semua guru · **Sendiri** = hanya data milik sendiri · **—** = tidak ada akses

| Modul | KS | KN | FB | MK | Wali | Pendamping | Guru lain |
|---|---|---|---|---|---|---|---|
| Dashboard | Pimpinan | Pimpinan | Pimpinan | Pimpinan¹ | Guru | Guru | Guru |
| Jadwal | Matriks | Kelola | Kelola | Matriks | Matriks | Matriks | Matriks |
| Penggantian | Lihat, ubah | Kelola | Kelola | Input | Input | Input | Input |
| Jurnal | Lihat | Kelola | Kelola | Input | Input | Input | Input |
| Absensi | Lihat, rekap | Kelola, rekap | Kelola | Input | Input | Input + SIMPATI | Input |
| Hari efektif | — | Kelola | Kelola | — | — | — | — |
| Kokurikuler | Lihat | Jadwal, lihat | Lihat, input kelas | Sendiri | Input kelas | Sendiri | Sendiri |
| Kurikulum | Lihat | Kelola | Kelola | Lihat | Lihat | Lihat | Lihat |
| Ekstrakurikuler | Lihat | Sendiri, rekap | Sendiri, rekap | Kelola | Sendiri | Sendiri | Sendiri |
| Manajemen Tugas | — | Kelola | Kelola | —² | —² | —² | —² |
| Data Guru & Kelas | Lihat | Lihat | Lihat | — | — | — | — |
| Supervisi | Lihat | Kelola | Kelola | Sendiri | Sendiri | Sendiri | Sendiri |
| PID | Lihat | Input, setujui | Input, setujui | Input | Input | Input | Input |
| Outing Class | Lihat | Input, setujui | Input | Input | Input | Input | Input |
| Penilaian Sumatif | Lihat | Input | Input | Input | Input | Input | Input |
| Monev | Lihat | Lihat | Lihat | Sendiri | Sendiri | Sendiri | Sendiri |
| Monitoring Adm. Kelas | Lihat, periksa | Kelola | Lihat, periksa | — | Widget | Widget | — |

¹ MK memakai tata letak dashboard pimpinan dengan statistik pribadi.
² Guru seharusnya bisa melihat "Tugas Saya" (temuan T4).

### Perubahan di Portal Guru

- **Kepala Sekolah:** semua akses "ubah/hapus/periksa" jadi **lihat saja**, ditambah hak **menyetujui**.
- **FB:** mendapat semua fitur khusus KN (jadwal kokurikuler, input pulang awal untuk guru lain, dll.), **kecuali penjadwalan monitoring administrasi kelas**.
- **PID:** disetujui **Waka Sarpras (JW)**, bukan admin + PIN.
- **Outing class:** disetujui **Waka Kurikulum (KN)**, tanpa PIN.
- **Semua PIN dihapus.** Setiap persetujuan mencatat siapa yang menyetujui dan kapan.
- **Guru:** bisa melihat dan menandai "Tugas Saya".
- **Supervisi:** supervisor bisa menulis **komentar hasil supervisi**, dan guru yang disupervisi bisa membacanya.

---

## 6. Aturan jam & kalender

### Jam penting (WIB)

| Jam | Yang terjadi di SiPeka |
|---|---|
| 06.00 | Absensi hari ini mulai bisa diisi |
| 13.30 | Peringatan di perangkat guru kalau absensi (dan SIMPATI untuk pendamping) belum diisi; batasnya 14.00 |
| 14.00 | Batas absensi & SIMPATI. Dashboard pimpinan beralih menampilkan kelas yang **belum** mengisi SIMPATI; daftar guru belum absen muncul di menu Absensi |
| 15.00 | Dashboard guru menampilkan pengingat kelengkapan harian (absensi/jurnal) |

### Status hari & kewajiban

| Status | Absensi | Jurnal | SIMPATI | Monitoring kelas |
|---|---|---|---|---|
| `EFEKTIF` (default Senin–Jumat) | Wajib | Wajib | Wajib | Boleh |
| `KEGIATAN` | Wajib | Tidak ditagih | Wajib | Boleh |
| `KEGIATAN_KHUSUS` | Wajib | Tidak ditagih | Wajib | Boleh |
| `NON_EFEKTIF` | Tidak | Tidak | Wajib³ | Tidak boleh |
| `LIBUR` | Tidak | Tidak | Tidak | Tidak boleh |

Form admin hanya menawarkan *Libur* dan *Kegiatan*; `NON_EFEKTIF` dikenali sistem tapi tidak bisa dipilih.
³ SIMPATI hanya diblokir saat Libur.

### Rutinitas harian (tertanam di matriks jadwal)

- **Semua hari:** Sarapan Makanan Bergizi Gratis 06.00–06.40 · Pelita Hidup 07.00–07.30 · Istirahat 09.30–10.00 · ISOMA 11.00–12.00
- **Senin:** Upacara Bendera 06.40–07.00 · Power Nap 13.00–13.30 · Pengisian SIMPATI 13.30–13.50
- **Selasa–Kamis:** Salat Dhuha 06.40–07.00 · Power Nap 13.00–13.30 · Pengisian SIMPATI 13.30–13.50 · Ekstrakurikuler 15.30–16.30
- **Jumat:** Sekolah Sehat & Adiwiyata 06.40–07.00 · Kokurikuler / Career Day / Pesona Manasai 07.30–08.30 · Pengisian SIMPATI 13.00–13.30 · Persiapan Pulang 13.30–13.50
- **Pulang (Senin–Kamis):** kelas I–III mulai 13.50, kelas IV–VI mulai 14.50

### Aturan JP

1 slot jadwal = 1 JP (Penguatan Hafalan & PRAMUKA = 0 JP). **Beban kerja = JP mengajar + JP tugas tambahan.**

---

## 7. Temuan & risiko

### Kritis

- **K1 — Login tanpa password.** Cukup kode 2 huruf, dan daftar 34 kode + nama ada di source yang bisa dibuka siapa pun. Artinya siapa saja bisa masuk sebagai Kepala Sekolah atau Wakakur.
- **K2 — Hak akses hanya di tampilan.** Pembatasan peran dan PIN persetujuan PID/outing dicek di browser, dan PIN-nya tertulis di kode. Siapa pun yang sudah masuk bisa menulis atau menghapus data apa pun lewat SDK.

### Tinggi

- **T1 — Notifikasi tidak lintas perangkat.** Notifikasi jadwal monitoring, pulang awal, tenggat, status online, dan riwayat monitoring disimpan di localStorage, jadi hanya muncul di browser tempat dibuat. Wali kelas tidak pernah menerima notifikasi jadwal monitoring dari KN.
- **T2 — Field terbuang saat simpan.** Field di luar 40 kolom (`class_ids` kokurikuler; `pendamping`, `wali`, `class_name` jadwal monitoring; `id` jadwal) dibuang. Akibatnya wali kelas tidak melihat jadwal kokurikuler kelasnya, dan widget monitoring untuk pendamping tidak pernah muncul.
- **T3 — Tanggal "hari ini" pakai UTC.** `getToday()` memakai UTC, jadi pukul 00.00–06.59 WIB tanggal default masih kemarin. Absensi yang diisi pukul 06.00–07.00 WIB bisa tercatat di tanggal yang salah. Jamnya dicek dengan WIB, tapi tanggalnya dengan UTC.
- **T4 — Guru tidak bisa melihat tugasnya.** Menu Manajemen Tugas hanya muncul untuk KN/FB. Tampilan "Tugas Saya" tidak bisa dijangkau, dan tugas tidak masuk notifikasi guru.

### Sedang

- **S1 — Master data di kode.** Guru, kelas, mapel, jam, rutinitas, dan hak akses per kode guru ditulis di kode. Setiap mutasi guru atau tahun ajaran baru berarti harus edit kode.
- **S2 — Tidak skalabel.** Semua data dimuat ke browser dan di-render ulang setiap ada perubahan. Tulis dijeda 0,55 detik per baris, jadi menyimpan jadwal untuk 6 kelas = 12 operasi ≈ 7 detik. Data jurnal & absensi bertambah setiap hari.
- **S3 — Notifikasi tidak difilter per penerima.** Guru menerima notifikasi absensi & jurnal milik semua guru.
- **S4 — Status kalender tidak konsisten.** Ada `Libur` sekaligus `LIBUR`. Form edit mengubah "Kegiatan" menjadi `KEGIATAN_KHUSUS`, dan Monev menganggap `KEGIATAN_KHUSUS` sebagai libur.
- **S5 — Data sensitif di source publik.** Nomor WhatsApp pengelola PID, link Google Spreadsheet jurnal, dan nama lengkap semua guru tertanam di kode.
- **S6 — Tanggal aturan hardcoded.** 13 Juli 2026 (mulai absensi) dan 27 Agustus 2026 (mulai pengingat jurnal & label terlambat) ditulis di kode, dan salah satu pesan error malah menyebut "26 Agustus 2026".

### Rendah

- **R1 — Status PID "Sedang Digunakan" tidak pernah diisi**, jadi selalu tampil "Tidak Digunakan".
- **R2 — Fitur setengah jadi:** Asisten AI Guru dan RPP Mendalam ada di kode tapi tidak di menu.
- **R3 — Kode ganda:** `renderMonitoringKelas` didefinisikan dua kali, dan versi lamanya jadi kode mati.

---

## 8. Scope proyek baru

### Tujuan

1. Menggantikan SiPeka dengan aplikasi web mandiri yang aman: **satu akun per guru (email + password)**, hak akses ditegakkan di server.
2. **Paritas fitur:** semua 18 menu dan aturan sekolah di §4–§6 tetap berjalan, kecuali yang diputuskan dihapus di §15, supaya guru tidak perlu belajar ulang.
3. Memperbaiki semua temuan Kritis & Tinggi (§7).
4. Master data, aturan (jam batas, tanggal aktivasi, kalender), dan pergantian semester bisa dikelola admin tanpa mengubah kode.
5. Notifikasi sampai ke penerimanya di perangkat mana pun.

### Masuk scope

- Login email + password per guru, reset password oleh admin, dan manajemen peran (satu orang bisa punya beberapa peran).
- Master data: guru/staf, rombel, mapel, slot waktu, rutinitas harian, lokasi, dan **tahun ajaran & semester**.
- **Arsip per semester:** jadwal dan data transaksi terikat ke semester; data semester lalu tetap bisa dibaca.
- Semua modul di §4, kecuali yang dihapus di §15. Termasuk "Tugas Saya" untuk guru dan status PID sedang digunakan.
- **Alur persetujuan ringan:** PID oleh Waka Sarpras, outing class oleh Waka Kurikulum, dan persetujuan oleh Kepala Sekolah (cakupannya dikonfirmasi di §15).
- **Supervisi:** penjadwalan + hasil berupa komentar.
- Notifikasi in-app per penerima, disimpan di server, dengan pengingat terjadwal (13.30 dan 15.00 WIB).
- Ekspor Excel & cetak (jurnal, absensi, rekap).
- Data awal: master data dari daftar SiPeka, akun 34 guru, dan form input absensi untuk KN/FB (§13).
- Tampilan responsif, dengan HP sebagai perangkat utama guru.

### Tidak masuk scope

- **Migrasi data dari Canva Sheet** (data diisi ulang secara manual, §13).
- **Notifikasi WhatsApp**, termasuk tombol pesan WhatsApp untuk peminjaman PID.
- **Asisten AI Guru** dan RPP Mendalam (fitur tersembunyi di SiPeka lama).
- Login dengan akun Google.
- Portal SIMPATI itu sendiri. Portal Guru hanya berbagi server/domain induk dengan SIMPATI, dan checklist + tautan SIMPATI tetap ada.
- Data & nilai per siswa, e-rapor, PPDB, keuangan/SPP, penggajian.
- Integrasi Dapodik.
- Aplikasi native Android/iOS (cukup web responsif; PWA opsional).
- Portal orang tua/siswa.

### Asumsi

- ±34 pengguna, satu sekolah, zona waktu WIB.
- Server SIMPATI mendukung PHP ≥ 8.3, MySQL 8, cron, dan SSL untuk subdomain `sipeka.sdislamdarussalam.id` (perlu dicek, §15).
- Dokumen (kurikulum, soal, hasil ujian, gambar pengumuman) tetap di Google Drive; aplikasi hanya menyimpan tautan.
- Dikerjakan 1 developer, dengan Waka Kurikulum (KN) sebagai product owner yang meninjau.

---

## 9. Tahapan kerja

Estimasi kasar untuk 1 developer penuh waktu. Totalnya tetap sama dengan versi 0.1: pekerjaan migrasi hilang, tapi arsip semester dan alur persetujuan bertambah.

| Fase | Isi | Durasi |
|---|---|---|
| **0. Persiapan** | Konfirmasi tindak lanjut §15, cek spesifikasi server SIMPATI, desain database, wireframe. Project Laravel 13 + Filament 5 sudah disiapkan (11 Sep 2026) | 1 minggu |
| **1. Fondasi & inti harian** | Login email + password & peran, master data + tahun ajaran/semester, kalender akademik, jadwal + matriks, absensi (cuti, pulang awal, persetujuan), jurnal, penggantian, dashboard, notifikasi | 4–5 minggu |
| **2. Program & layanan** | Tugas + tugas tambahan + Tugas Saya, profil & JP, kokurikuler, kurikulum, ekstrakurikuler (+ pengumuman & komentar), supervisi (+ komentar hasil), PID (+ persetujuan Waka Sarpras), outing (+ persetujuan Waka Kurikulum), penilaian sumatif, SIMPATI | 3–4 minggu |
| **3. Monitoring & laporan** | Monev, monitoring administrasi kelas (checklist 28 butir), analisis kehadiran & beban kerja, ekspor laporan, tampilan arsip per semester | 2–3 minggu |
| **4. Uji coba & peluncuran** | Deploy ke `sipeka.sdislamdarussalam.id`, pembuatan akun 34 guru, input data awal oleh KN/FB, UAT bersama KN/FB/JJ + perwakilan guru, pelatihan singkat, go-live, pendampingan 2 minggu | 1–2 minggu |
| **Total** | | **±11–15 minggu** |

**Opsional setelah go-live:** PWA + push notification.

---

## 10. Arsitektur

Stack sudah diputuskan dan project-nya sudah disiapkan pada 11 September 2026.

| Lapisan | Pilihan | Keterangan |
|---|---|---|
| Framework | **Laravel 13** (PHP 8.4) | Sudah terpasang; minimal PHP 8.3 di server |
| Panel & UI | **Filament 5** + Livewire 4, Tailwind CSS 4 | CRUD, tabel, form, filter, ekspor Excel, dan notifikasi database bawaan. Halaman khusus (matriks jadwal, dashboard) dibuat dengan Livewire. Warna utama emerald, mengikuti SiPeka |
| Database | **MySQL 8** | Database `portal_guru`, utf8mb4 |
| Login & hak akses | Login Filament (email + password), **Laravel Policy** + tabel peran | Setiap aksi dicek di server, bukan hanya disembunyikan di tampilan |
| Pengingat terjadwal | **Laravel Scheduler** (cron tiap menit) | Peringatan tenggat 13.30 dan pengingat kelengkapan 15.00 WIB |
| Zona waktu | `Asia/Jakarta` di konfigurasi aplikasi | Menutup temuan T3 |
| Hosting | Server yang sama dengan SIMPATI, subdomain **`sipeka.sdislamdarussalam.id`** | Butuh PHP ≥ 8.3, MySQL 8, cron, SSL |

---

## 11. Model data usulan

Satu tabel ber-`type` diganti dengan tabel relasional. **Semua tabel transaksi menyimpan `semester_id`**, supaya data bisa diarsip per semester.

| Tabel baru | Padanan di SiPeka | Catatan |
|---|---|---|
| `users` | `TEACHERS` (di kode) | Akun guru/staf: nama, email, password, kode 2 huruf, jabatan, status aktif |
| `roles`, `role_user` | aturan per kode guru | Satu orang bisa punya beberapa peran |
| `classes` | `CLASSES` (di kode) | `wali_id`, `pendamping_id` |
| `subjects` · `time_slots` · `daily_routines` | `SUBJECTS`, `TIME_SLOTS`, `ROUTINES_*` | Bisa dikelola admin |
| `semesters` | — (baru) | Tahun ajaran + semester; satu aktif, sisanya arsip |
| `calendar_days` | `academic_calendar`, `attendance_exception` | Status jadi enum yang konsisten |
| `schedules` · `schedule_audits` | `schedule`, `schedule_history` | Unik per semester + hari + slot + kelas |
| `substitutions` | `substitution` | Guru pengganti disimpan sebagai ID, bukan nama |
| `journals` | `journal` | + jumlah kehadiran siswa (hadir/izin/sakit/alpa) |
| `teacher_attendances` | `attendance` | Waktu pengisian dicatat server |
| `leaves` · `early_leaves` | `teacher_leave`, `early_leave` | + status persetujuan, `approved_by`, `approved_at` |
| `simpati_checks` | `simpati` | |
| `tasks` · `task_assignees` | `task` | Satu tugas, banyak penerima |
| `additional_duties` | `task_extra` | JP tugas tambahan |
| `cocurricular_schedules` (+ kelas target) | `cocurricular_schedule` | Target kelas jadi relasi |
| `cocurricular_activities` | `cocurricular` | Dimensi profil lulusan jadi relasi |
| `curriculum_documents` | `curriculum` | |
| `extracurriculars` (+ pendamping) | `extracurricular` (Jadwal Ekskul) | Pendamping jadi relasi ke guru |
| `extracurricular_attendances` | `extracurricular_attendance` | |
| `announcements` · `comments` | `extracurricular` (Pengumuman), `extracurricular_comment` | |
| `summative_assessments` | `assessment` | |
| `supervisions` | `supervision` | + komentar hasil supervisi |
| `pid_reservations` | `pid` | + status sedang digunakan, persetujuan Waka Sarpras |
| `outings` | `outing` | + persetujuan Waka Kurikulum |
| `monitoring_schedules` | `class_monitoring_schedule` | Hanya KN yang membuat |
| `monitoring_results` · `monitoring_items` | `class_monitoring` | Satu baris per butir checklist |
| `notifications` | localStorage | Per penerima, dengan `read_at` |
| `audit_logs` | — (baru) | Siapa mengubah apa & kapan |

---

## 12. Kebutuhan non-fungsional

- **Keamanan:** akun per guru (email + password), sesi server, hak akses dicek di server lewat policy, tidak ada PIN/rahasia di kode klien, HTTPS, pembatasan percobaan login.
- **Privasi:** mengikuti UU Pelindungan Data Pribadi (UU 27/2022): data seperlunya, akses sesuai peran, log akses.
- **Waktu:** semua aturan jam & tanggal dihitung di server dengan zona Asia/Jakarta.
- **Performa:** halaman utama terbuka < 2 detik di 4G; data dipaginasi & difilter di server; simpan massal (jadwal multi-kelas) dalam satu transaksi.
- **Arsip:** data semester lalu tetap bisa dibaca dan diekspor, tapi tidak bisa diubah.
- **Keandalan:** backup harian otomatis; audit log untuk setiap ubah/hapus.
- **Kemudahan pakai:** mobile-first, Bahasa Indonesia, target sentuh ≥ 44 px, kontras teks cukup.
- **Pemeliharaan:** master data dan aturan (jam batas, tanggal aktivasi) jadi pengaturan, bukan hardcode.

---

## 13. Data awal & input manual

Sesuai keputusan §15, **tidak ada migrasi otomatis dari Canva Sheet.**

1. Master data (34 guru, 14 rombel, 18 mapel, 19 slot waktu, rutinitas harian, lokasi PID) dimasukkan lewat seeder dari daftar di SiPeka, lalu dikoreksi admin lewat aplikasi.
2. Admin membuat akun untuk 34 guru (email + password awal), dan guru wajib mengganti password saat pertama kali masuk.
3. Semester aktif pertama dibuat (mis. 2026/2027 Ganjil).
4. Jadwal pelajaran diinput ulang oleh KN/FB.
5. Data absensi awal diisi manual oleh KN dan FB. Karena itu form absensi untuk admin perlu bisa mengisi banyak guru sekaligus.
6. Jurnal diisi masing-masing guru.
7. SiPeka lama tetap bisa dibuka sebagai referensi sampai Portal Guru go-live.

---

## 14. Kriteria selesai

- [ ] Setiap guru masuk dengan email + password sendiri; kode 2 huruf saja tidak bisa dipakai masuk.
- [ ] Mengubah URL atau memanggil API langsung tidak bisa melewati hak akses (diuji per peran sesuai §3 dan §5).
- [ ] Kepala Sekolah tidak punya tombol tambah/ubah/hapus di modul mana pun, tapi bisa menyetujui.
- [ ] Reservasi PID hanya bisa disetujui Waka Sarpras, outing class hanya oleh Waka Kurikulum, dan setiap persetujuan mencatat siapa & kapan.
- [ ] FB tidak bisa membuat jadwal monitoring administrasi kelas.
- [ ] Absensi yang diisi pukul 06.15 WIB tercatat pada tanggal hari itu.
- [ ] Jadwal monitoring yang dibuat KN muncul sebagai notifikasi di akun wali & pendamping kelas tersebut, di perangkat mana pun.
- [ ] Wali kelas melihat jadwal kokurikuler yang menargetkan kelasnya.
- [ ] Guru melihat tugas yang diberikan kepadanya dan bisa menandainya selesai.
- [ ] Admin bisa menambah guru, rombel, mapel, hari libur, dan membuka semester baru tanpa mengubah kode. Jadwal dan data semester lalu tetap bisa dilihat sebagai arsip.
- [ ] Menyimpan jadwal untuk 6 kelas selesai dalam kurang dari 2 detik.

---

## 15. Keputusan sekolah

Jawaban atas pertanyaan terbuka versi 0.1, diterima 11 September 2026.

| No | Pertanyaan | Keputusan |
|---|---|---|
| 1 | Bangun ulang dari nol atau perbaiki SiPeka di Canva? | **Bangun ulang.** Tidak lagi memakai Canva; dibangun sebagai web app sungguhan |
| 2 | Login email + password atau akun Google? | **Email + password** |
| 3 | Hak FB sama persis dengan KN? | **Hampir sama**, tapi FB tidak bisa mengisi penjadwalan monitoring administrasi kelas; wewenang itu khusus Waka Kurikulum (KN) |
| 4 | KS perlu bisa mengubah/menghapus riwayat penggantian? | **Tidak.** KS hanya punya hak baca dan persetujuan, demi menjaga validitas data |
| 5 | Siapa yang menyetujui PID dan outing? | **Persetujuan bertingkat ringan:** PID cukup disetujui Waka Sarpras; outing class cukup disetujui Waka Kurikulum |
| 6 | Supervisi perlu instrumen & hasil? | **Penjadwalan + hasil berupa komentar** |
| 7 | Kehadiran siswa di jurnal per nama atau jumlah? | **Cukup jumlah** (mis. Hadir 20, Sakit 2, Izin 1) |
| 8 | Perlu notifikasi WhatsApp? | **Dihapus** |
| 9 | Asisten AI perlu AI sungguhan? | **Dihapus** |
| 10 | Hosting, domain, anggaran? | **Satu server dengan aplikasi SIMPATI**, subdomain `sipeka.sdislamdarussalam.id` |
| 11 | Data lama mana yang dimigrasi? | **Tidak ada migrasi otomatis.** Data absensi awal diisi manual oleh KN dan FB; jurnal diisi masing-masing guru |
| 12 | Jadwal per semester dan perlu arsip? | **Sangat perlu.** Database wajib punya kolom tahun ajaran/semester sebagai arsip berkala |

### Tindak lanjut yang masih perlu dikonfirmasi

| No | Pertanyaan | Ditanyakan ke |
|---|---|---|
| A | Persetujuan apa saja yang dipegang Kepala Sekolah? Usulan: pengajuan cuti guru dan pulang awal. Di SiPeka keduanya sudah punya status persetujuan, tapi belum ada yang menyetujui | KS |
| B | Apakah FB juga boleh menyetujui outing class (mengikuti keputusan 3), atau hanya KN (mengikuti keputusan 5)? | KN |
| C | Spesifikasi server SIMPATI: versi PHP (butuh ≥ 8.3), MySQL, akses SSH/Composer, cron, dan SSL untuk subdomain | Pengelola server SIMPATI |
| D | "Digabung dengan aplikasi SIMPATI" artinya hanya satu server & domain induk, atau juga satu login dengan SIMPATI? | KS, pengelola SIMPATI |
| E | Reset password lewat email (butuh SMTP sekolah) atau cukup direset admin? | KN |
| F | Tombol WhatsApp untuk minta pinjam PID ikut dihapus bersama notifikasi WhatsApp? Dokumen ini mengasumsikan ya | KN, Waka Sarpras |
