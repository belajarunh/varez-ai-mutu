# Varez AI Mutu

Varez AI Mutu memakai Laravel 13, Filament untuk panel administrasi, dan Blade + Bootstrap 5 untuk portal. Fondasi tenancy, autentikasi, RBAC, undangan, serta audit log tersedia; modul akreditasi dan domain mutu belum diimplementasikan.

## Kebutuhan lokal

- PHP 8.3+ dengan ekstensi yang diperlukan Laravel dan `pdo_mysql`
- Composer 2.x
- Node.js/npm yang kompatibel dengan Vite
- MySQL 8.0+

## Instalasi

Dari direktori proyek:

```powershell
composer install
npm ci
Copy-Item .env.example .env
```

Buat database MySQL lokal, lalu isi `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` pada `.env`. `.env.example` hanya memuat nilai contoh; jangan menaruh kredensial nyata ke Git.

```powershell
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
```

`DatabaseSeeder` menambahkan katalog role/permission sistem. Pada environment `local` dan `testing`, seeder juga membuat data institusi fiktif; akun demo mendapat password acak yang tidak diketahui dan bukan kredensial untuk login. Seeder demo ditolak di environment lain.

Daftarkan akun operator melalui `/admin/register` dan verifikasi email dari mail log lokal (`storage/logs/laravel.log`). Setelah role sistem tersedia dan akun aktif serta terverifikasi, berikan akses platform secara eksplisit:

```powershell
php artisan platform:grant-super-admin operator@example.test
```

Perintah meminta konfirmasi dan mencatat pemberian role di audit log. `--force` hanya untuk otomasi tepercaya. Jangan memakai akun atau data demo sebagai kredensial produksi. `APP_DEBUG` pada `.env.example` nonaktif; pastikan tetap `false` di production.

## Identitas dan tenancy

- Tenant context berasal dari institusi aktif yang dicocokkan dengan membership aktif user di server; parameter tenant dari URL bukan bukti otorisasi.
- User dapat memiliki membership pada beberapa institusi. Role tenant dan role platform memiliki scope terpisah.
- Hanya platform Super Admin aktif yang dapat membuat institusi atau mengubah statusnya. Status tenant dan perubahan membership/struktur dicatat di audit log.
- Akses tenant memerlukan akun aktif, email terverifikasi, membership aktif, dan permission yang sesuai. Akses unit juga dibatasi pada unit yang ditugaskan, kecuali role memiliki `units.access_all`.
- Undangan terikat pada email user terverifikasi, kedaluwarsa setelah tujuh hari, dan menyimpan hash token saja. Saat ini endpoint mengembalikan token satu kali; pengiriman email belum diintegrasikan. Kirim token melalui kanal tepercaya dan jangan menaruhnya pada log.
- Role dan permission didefinisikan oleh seeder; UI untuk mengelola permission khusus belum dibuat. Login Filament tersedia untuk Super Admin aktif, anggota tenant yang memiliki `panel.access`, atau akun dengan undangan aktif.

Jalankan `php artisan db:seed` ulang setelah perubahan registry permission. Tes memakai SQLite in-memory. Migrasi harus diuji lagi pada MySQL Laragon sebelum memakai database MySQL lokal; koneksi MySQL belum diverifikasi di lingkungan ini.

## Menjalankan aplikasi

```powershell
php artisan serve
```

- Portal: `http://127.0.0.1:8000/`
- Panel Filament: `http://127.0.0.1:8000/admin`
- Health check Laravel: `http://127.0.0.1:8000/up`

Panel admin mewajibkan autentikasi. Untuk memproses pekerjaan queue secara lokal, jalankan di terminal terpisah:

```powershell
php artisan queue:work
```

Portal menggunakan entrypoint Vite tersendiri (`portal.css` / `portal.js`) agar Bootstrap tidak bercampur dengan aset Tailwind Filament.

## Quality gates

```powershell
composer format
composer format:check
composer analyse
composer test
```

Atau jalankan seluruh pemeriksaan berurutan:

```powershell
composer check
```

`composer install` menggunakan `composer.lock`; `npm ci` menggunakan `package-lock.json`. GitHub Actions menjalankan quality gates PHP dan build frontend untuk setiap push dan pull request; gunakan hasil workflow sebagai pemeriksaan sebelum merge. Penggunaan MySQL, pengiriman email undangan, MFA, serta fitur akreditasi masih menjadi pekerjaan/keputusan berikutnya.
