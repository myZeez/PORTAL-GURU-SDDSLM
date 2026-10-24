# Rilis Hostinger

Jalankan dari PowerShell pada folder proyek:

```powershell
.\scripts\package-hostinger.ps1
```

Hasilnya adalah `output/portal-guru-hostinger.zip`. ZIP sudah menyertakan `vendor` dan hasil Vite di `public/build`, sehingga Hostinger tidak perlu menjalankan Composer atau npm.

## Upload awal

1. Pilih PHP 8.4 atau lebih tinggi pada hPanel.
2. Buat database MySQL dan impor database aplikasi.
3. Ekstrak ZIP langsung ke folder domain `public_html`.
4. Salin `.env.production.example` menjadi `.env`, isi kredensial database, lalu buat `APP_KEY` yang baru. Jangan beri spasi sebelum nama variabel di `.env`.
5. Ubah permission `storage` dan `bootstrap/cache` menjadi dapat ditulis oleh server.

Struktur paket memakai `.htaccess` root untuk meneruskan request ke `public/`, sesuai keterbatasan shared hosting Hostinger. Tidak ada file `.env`, log, `node_modules`, data pengujian, atau konfigurasi lokal di dalam ZIP.

## Rilis revisi

Ekstrak ZIP baru ke folder yang sama dan pertahankan `.env` yang sudah ada. Bila sebuah rilis menambah skema database, impor berkas SQL pada `database/hostinger/` melalui phpMyAdmin sebelum membuka aplikasi. Impor berkas yang belum pernah diimpor di database tersebut, berurutan sesuai tanggal pada namanya, masing-masing satu kali:

1. `database/hostinger/2026_10_05_add_early_leave_reason.sql`
2. `database/hostinger/2026_10_24_client_revisions.sql` (tabel baru untuk revisi klien dan jejak persetujuan Outing Class dan PID)

Berkas kedua sudah mencatat migrasinya sendiri di tabel `migrations`, jadi tidak perlu `php artisan migrate`.
