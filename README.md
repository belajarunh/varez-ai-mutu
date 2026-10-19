# Varez AI Mutu

Varez AI Mutu memakai Laravel 13, Filament untuk panel administrasi, dan Blade + Bootstrap 5 untuk portal. Fondasi tenancy, autentikasi, RBAC, audit log, katalog instrumen berversi, proyek akreditasi, serta fondasi impor data kinerja tersedia. Repositori bukti, mutu, dan AI belum diimplementasikan.

## Kebutuhan lokal

- PHP 8.3+ dengan ekstensi Laravel dan PhpSpreadsheet (`pdo_mysql`, `gd`, `zip`, `dom`, `xml`, `xmlreader`, `xmlwriter`, `mbstring`)
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

Buat database MySQL lokal bernama `varez_ai_mutu` melalui phpMyAdmin (pilih **New**, isi nama database, gunakan collation `utf8mb4_unicode_ci`, lalu **Create**) atau jalankan SQL berikut di tab SQL:

```sql
CREATE DATABASE varez_ai_mutu
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Isi `.env` lokal dengan `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=varez_ai_mutu`, serta username/password MySQL Laragon milik Anda. Instalasi Laragon standar sering menggunakan `root` tanpa password; bila sudah diubah, pakai kredensial lokal Anda. `.env.example` tidak memuat kredensial nyata dan jangan commit `.env`.

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

## Katalog instrumen dan proyek

Platform Super Admin mengelola lembaga akreditasi, instrumen, versi, dan hierarki node di panel `/admin`. Draft internal/demo tidak ditampilkan sebagai pilihan proyek resmi. Versi resmi perlu sumber lembaga dan versi terverifikasi, lalu diterbitkan dari panel. Import JSON lokal yang hanya membuat entri internal/draft didokumentasikan pada [docs/INSTRUMENT_CATALOG_IMPORT.md](./docs/INSTRUMENT_CATALOG_IMPORT.md).

Pengguna tenant dengan permission `projects.create` dapat membuka `/institutions/{slug}/projects/create` untuk membuat proyek menggunakan versi terverifikasi yang sesuai dengan domain institusi. Proyek menyimpan snapshot dan checksum katalog saat dibuat; perubahan katalog berikutnya tidak mengganti snapshot. Status indikator, PIC, dan tenggat dapat dikelola dari halaman proyek. Tidak ada konten instrumen resmi yang dibuat oleh kode atau seed demo.

Jalankan `php artisan db:seed` ulang setelah perubahan registry permission. Tes otomatis memakai SQLite in-memory; migration juga telah dijalankan dan diverifikasi pada MySQL 8.4.3 Laragon. Database `varez_ai_mutu` dapat dilihat di phpMyAdmin.

## Impor data kinerja

Platform Super Admin membuat schema berversi pada `/admin/performance-dataset-schemas`, mengisi field, tipe (`string`, `integer`, `decimal`, `date`, `boolean`), dan menandai `record_key` sebagai wajib serta unik; schema kemudian diterbitkan. Tidak ada jenis dataset atau nilai kinerja domain yang di-hardcode maupun diklaim resmi sebelum disepakati dengan pemilik domain. Versi yang sudah terbit tidak dapat diedit; buat versi baru untuk perubahan schema.

Pengguna tenant dengan permission `performance_data.import` dapat membuka `/institutions/{slug}/performance/imports/create`. Buat/pilih periode akademik, unggah CSV UTF-8 atau XLSX hingga 10 MB, petakan kolom, tinjau pratinjau/error, lalu konfirmasi. File disimpan di private local storage pada development dengan checksum SHA-256; gunakan disk/object storage privat yang sesuai untuk deployment. Pemeriksaan file, validasi baris, dan commit berjalan di queue. Jalankan `php artisan queue:work` di terminal terpisah. Error report hanya tersedia setelah otorisasi tenant server-side.

Strategi `append` mempertahankan records lama dan menolak key duplikat. `replace` memerlukan dataset tujuan yang sama jenis/schema/periode/unit, konfirmasi penggantian saat pemetaan, dan konfirmasi kedua sebelum commit; penggantian dijalankan dalam transaksi. Baris invalid tidak pernah diimpor, dan impor parsial memerlukan konfirmasi eksplisit. Retry job tidak mengulang batch yang telah selesai. Periode, unit, schema, dan dataset tujuan diverifikasi terhadap tenant.

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

`composer install` menggunakan `composer.lock`; `npm ci` menggunakan `package-lock.json`. GitHub Actions menjalankan quality gates PHP dan build frontend untuk setiap push dan pull request; gunakan hasil workflow sebagai pemeriksaan sebelum merge. MySQL lokal sudah diverifikasi; persetujuan schema dataset pilot, pengiriman email undangan, MFA, SSO, modul dokumen/mutu/AI, dan operasi produksi masih menjadi pekerjaan berikutnya.
