# Portal Guru — SD Islam Darussalam Palangka Raya

Pembangunan ulang **SiPeka** (Sistem Penjadwalan & Kalender Akademik) dari Canva Code menjadi aplikasi web mandiri. Scope lengkapnya ada di [docs/SCOPE-PROJECT.md](docs/SCOPE-PROJECT.md).

| | |
|---|---|
| Framework | Laravel 13 |
| Panel & UI | Filament 5 (Livewire 4), Tailwind CSS 4 + Vite |
| Database | MySQL 8 (`portal_guru`, utf8mb4) |
| PHP | 8.4 (minimal 8.3) |
| Zona waktu | `Asia/Jakarta` (WIB) |

## Kebutuhan

- PHP ≥ 8.3 dengan ekstensi `intl`, `zip`, `pdo_mysql`, `gd`, `mbstring`, `fileinfo`, `openssl`, `curl`, `exif`
- Composer 2
- Node.js 20+ dan npm
- MySQL 8

Di laptop development, PHP 8.4 ada di `C:\laragon\bin\php\php-8.4.25-Win32-vs17-x64`. Pilih versi ini lewat menu Laragon (**PHP › Version**) supaya perintah `php` di terminal Laragon memakai 8.4.

## Setup pertama kali

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan make:filament-user
```

Sebelum `migrate`, buat dulu database `portal_guru` (charset `utf8mb4`, collation `utf8mb4_unicode_ci`).

## Menjalankan

```bash
php artisan serve
```

Buka http://127.0.0.1:8000. Panel Filament `portal` berjalan langsung di root URL, dan halaman login ada di `/login`.

`composer run dev` menjalankan server, queue, log, dan Vite sekaligus.

## Struktur penting

- `app/Providers/Filament/PortalPanelProvider.php` — konfigurasi panel utama (id `portal`, path `/`)
- `app/Filament/Resources/` — resource CRUD per modul
- `docs/` — dokumen proyek

## Catatan Windows

Kalau Composer dipanggil lewat `composer.bat`, cmd akan membuang karakter `^` dari constraint versi. Pakai `php path\ke\composer.phar require "vendor/paket:^1.0"`, atau jalankan `composer require vendor/paket` tanpa constraint.
