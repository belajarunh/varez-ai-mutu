# Final QA report — Prompt 11

Tanggal pemeriksaan: 2026-10-19  
Keputusan: **belum siap untuk production atau pilot dengan data nyata**.

## Perubahan hardening

- Membetulkan tipe kontrak text-generation agar provider menerima daftar pesan
  `{role, content}` yang benar, serta membersihkan temuan PHPStan terkait.
- Memperbaiki kuota: tanpa subscription aktif ditolak; kuota `0` berarti tidak
  ada pemakaian; `null` berarti unlimited hanya untuk plan aktif; tipe kuota
  tidak dikenal ditolak.
- Mencatat subscription plan baru sebagai histori, menutup subscription lama,
  dan mensyaratkan permission `billing.manage` atau platform super-admin.
- Menjadikan usage ledger idempotent per institusi/jenis/periode/idempotency key,
  dengan unique index untuk menghindari biaya ganda pada retry.
- Menutup kebocoran retrieval lintas unit: RAG kini mensyaratkan permission
  `documents.view` serta menerapkan visibility unit/private/institution dan
  status dokumen yang sama dengan akses repository dokumen.
- Menolak webhook saat gateway nonaktif, memvalidasi signature, event id,
  invoice, institusi, status, jumlah, dan mata uang; hanya status paid yang
  tervalidasi menandai invoice sebagai paid. Payload tersimpan dibatasi ke data
  minimum.
- Menonaktifkan mock provider di luar environment testing, membuat stub OpenAI
  dan embedding gagal eksplisit alih-alih mengembalikan output/vector palsu, dan
  mencegah narasi sukses semu ketika provider tidak tersedia.
- Menyetel default `DB_QUEUE_RETRY_AFTER=300`, di atas timeout job dokumen
  240 detik.
- Menambahkan regression test untuk kasus-kasus di atas dan memformat kode
  dengan Pint.

## Perintah dan hasil

| Perintah | Hasil |
|---|---|
| `composer check` | Lulus: Pint passed, PHPStan level 5 tanpa error, 63 test passed / 374 assertions |
| `composer audit --no-interaction` | Lulus: tidak ada security advisory |
| `npm.cmd audit --audit-level=moderate` | Lulus: 0 vulnerabilities |
| `npm.cmd run build` | Lulus: Vite production build selesai |
| `php artisan migrate:status` | Berhasil membaca status; 4 migrasi AI/billing terbaru masih pending pada database lokal yang diperiksa |
| `php artisan migrate:fresh` | Tidak dijalankan pada database lokal yang dapat berisi data; seluruh migrasi diuji oleh suite SQLite in-memory |
| Backup/restore drill | Belum dilakukan |
| Manual UAT / AI evaluation | Belum dilakukan; skenario tercatat di [UAT_SCENARIOS.md](./UAT_SCENARIOS.md) |

Dependency audit memeriksa advisory registry yang tersedia saat pemeriksaan;
hasilnya tidak menyatakan seluruh dependensi bebas dari semua risiko.

## Temuan dan risiko berdasarkan severity

### Blocker / critical untuk release

1. **Provider AI OpenAI belum diimplementasikan.** Stub kini gagal eksplisit dan
   tidak menghasilkan teks/vector palsu, mock dibatasi ke environment testing,
   dan generasi tanpa provider berhenti tanpa klaim. Belum ada request nyata ke
   provider; konfigurasi API key saja tidak membuat generasi tersedia atau aman
   untuk pilot.
2. **Ekspor belum menghasilkan file.** Workflow review menyimpan manifest dan
   nama file, tetapi belum menulis artefak ke storage atau menyediakan download
   terotorisasi; kegagalan ekspor file belum dapat diuji.
3. **Backup dan restore object storage/database belum dibuktikan.** Tidak ada
   restore drill pada environment non-production dari pemeriksaan ini.
4. **Instrumen pilot belum dibuktikan diverifikasi pemilik domain.** Jangan
   menyatakan materi demo/internal sebagai instrumen resmi.

### High

1. **Payment gateway belum dipilih atau diintegrasikan.** Implementasi saat ini
   hanya menyediakan jalur manual dan service validasi generik; belum ada
   adapter provider nyata atau endpoint webhook produksi. Gateway harus tetap
   disabled.
2. **Dashboard SaaS belum diintegrasikan ke UI/Filament.** Service metrik dan
   pembatasan permission ada, tetapi tampilan role-specific belum di-UAT.
3. **Security/UAT produksi belum dilakukan.** Brute-force throttling, session
   cookie HTTPS, XSS/CSRF, provider outage, queue failure-injection, dan export
   failure belum dibuktikan secara manual pada environment target.

### Medium

1. **AI evaluation belum memadai untuk rilis.** Regression tests mencakup
   tenant-scoped retrieval, sitasi dasar, dan abstention; numeric fidelity,
   konflik sumber nyata, serta evaluasi unsupported claims memerlukan corpus
   berlabel dan reviewer domain. Regression test tambahan memastikan RAG tidak
   mengambil dokumen unit yang tidak dapat diakses.
2. **Migration deployment belum dilakukan.** Empat migrasi baru masih pending
   pada DB lokal; jalankan rehearsal pada salinan target dan sediakan backup
   terverifikasi sebelum migrasi production.

## Keputusan yang masih terbuka

- Pemilihan AI provider produksi, kebijakan retensi prompt/output, budget dan
  batas data yang boleh dikirim ke provider.
- Payment gateway, rekonsiliasi/refund, dan operator yang berwenang melakukan
  aktivasi manual.
- SLO/RTO/RPO, retensi backup, monitoring/alerting, serta pemilik incident
  response.
- Pemilik domain dan proses approval untuk instrumen sekolah/perguruan tinggi.
- Definisi dashboard final: periode, indikator, scope permission, dan sumber
  data.

## Rekomendasi rilis

**Belum siap untuk production atau pilot dengan data nyata.** Hanya internal UAT
dengan data dummy/non-sensitif dapat dipertimbangkan setelah deployment staging
dan access controls dikonfirmasi. Jangan mengaktifkan AI provider placeholder,
payment webhook, atau klaim instrumen resmi.

Langkah deployment, migrasi, queue, backup, monitoring, rollback, dan respons
insiden ada di [DEPLOYMENT_RUNBOOK.md](./DEPLOYMENT_RUNBOOK.md).
