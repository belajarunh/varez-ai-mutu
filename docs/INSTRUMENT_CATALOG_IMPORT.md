# Format import katalog instrumen

Format data JSON generik tersedia pada [instrument_catalog_import.schema.json](./instrument_catalog_import.schema.json). Format ini hanya menetapkan struktur pertukaran data; format belum mewakili atau mengesahkan instrumen resmi tertentu.

Import lokal:

```powershell
php artisan catalog:import .\catalog.json
```

File maksimal 10 MB. `body.domain_type` memisahkan katalog `higher_education` dan `school`. `nodes` berbentuk daftar datar yang dihubungkan dengan `parent_code`; setiap `code` harus unik dalam satu file. Urutan node tidak harus topologis. Aturan validasi dapat disertakan sebagai JSON di `validation_json` atau `rules`.

Setiap import selalu membuat lembaga berstatus internal, instrumen internal, dan versi draft/internal. URL sumber atau checksum yang diimpor adalah metadata, bukan bukti verifikasi. Import tidak menerbitkan katalog, tidak mengesahkan sumber, dan tidak membuat data tampil sebagai instrumen resmi. Platform Super Admin tetap harus melakukan pemeriksaan domain/sumber dan alur publikasi secara eksplisit.

Sebelum mengimpor instrumen produksi, pemilik domain harus menyepakati format serta memberikan sumber dan izin penggunaan yang dapat diverifikasi. Jangan mengisi file dengan nama kriteria, bobot, ketentuan, atau contoh yang diklaim resmi tanpa persetujuan tersebut.
