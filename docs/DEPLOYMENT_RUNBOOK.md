# Deployment runbook

Runbook ini adalah prosedur dasar untuk staging/production; isi nilai, pemilik,
RTO/RPO, dan approval environment sebelum pelaksanaan. Jangan menyalin secret ke
repo, tiket, terminal log, atau laporan QA.

## 1. Prasyarat dan environment

- PHP 8.3+, Composer 2, Node.js 22 untuk build, database MySQL 8+, storage privat,
  serta queue worker persisten.
- Provision `.env` dari secret manager, bukan dengan mengedit `.env.example` lalu
  mengunggahnya. Tetapkan `APP_ENV=production`, `APP_DEBUG=false`, HTTPS,
  `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`,
  `SESSION_ENCRYPT=true`, database/cache/queue production, `LOG_LEVEL=info`,
  disk bukti privat, dan kunci aplikasi stabil.
- Biarkan `BILLING_GATEWAY_ENABLED=false` sampai satu gateway dipilih,
  adapter/provider dan endpoint webhook production diterapkan, secret signature
  disediakan, lalu end-to-end test provider dan rekonsiliasi disetujui.
- Jangan konfigurasi OpenAI sebagai provider produksi sebelum placeholder
  diganti dengan integrasi nyata, error/timeout/retry ditangani, data outbound
  disetujui, dan evaluasi AI lolos. Mock hanya untuk testing.
- Migrasi di database `migrate:status` harus disetujui. Jangan menjalankan
  migrasi production sebelum backup dan rehearsal berhasil.

## 2. Backup dan restore

Sebelum perubahan schema atau deployment:

1. Catat waktu, release, database, disk object storage, operator, dan approval.
2. Ambil snapshot database konsisten menggunakan mekanisme backup yang disetujui
   DBA; enkripsi dan simpan di lokasi terpisah dari host aplikasi.
3. Backup/versioning object storage bukti secara terpisah; pastikan metadata
   akses dan file version ikut terlindungi.
4. Pada non-production, restore database dan object storage ke environment
   terisolasi. Verifikasi jumlah tabel/record, hash beberapa file, download
   dengan user tenant yang benar, serta penolakan akses lintas tenant.
5. Catat durasi, hasil, RPO/RTO aktual, dan tindak lanjut. **Jangan rilis sebelum
   restore drill lulus.**

## 3. Build dan migrasi

1. Pin release commit/artifact dan simpan hasil `composer audit`, `npm audit`,
   `composer check`, serta `npm run build`.
2. Pasang dependency production (`composer install --no-dev
   --prefer-dist --optimize-autoloader`) dan deploy asset hasil build.
3. Jalankan smoke test staging dengan konfigurasi setara production.
4. Aktifkan maintenance mode atau drain traffic sesuai prosedur platform.
5. Pastikan backup baru selesai; jalankan `php artisan migrate --force`.
6. Jika migrasi gagal, hentikan rollout, jangan ulangi migrasi secara membabi
   buta; kumpulkan log redacted dan lakukan recovery sesuai bagian rollback.
7. Jalankan `php artisan optimize`, health check, login, tenant access,
   private upload/download, dan validasi queue sebelum membuka traffic.

## 4. Queue dan scheduler

- Jalankan worker persisten yang diawasi process manager:
  `php artisan queue:work database --sleep=3 --tries=3 --timeout=240`.
- `DB_QUEUE_RETRY_AFTER` harus lebih besar daripada timeout worker/job (default
  aplikasi 300 detik untuk timeout dokumen 240 detik).
- Pantau `failed_jobs`, usia job tertua, retry, memory, dan exception; uji
  `php artisan queue:failed` serta prosedur replay di staging. Jangan replay
  massal tanpa memastikan job idempotent.
- Jalankan scheduler setiap menit melalui scheduler platform:
  `php artisan schedule:run`. Verifikasi daftar task aktif dan log jadwal.
- Restart worker setelah deployment/config berubah dengan `php artisan
  queue:restart`; pastikan supervisor menghidupkan kembali worker.

## 5. Logging, monitoring, dan akses operasi

- Kirim error aplikasi, auth/security events, queue failures, DB/storage health,
  dan audit log ke sink yang terproteksi; redaksi token, secret, isi dokumen, dan
  prompt sensitif.
- Buat alert untuk error rate, queue backlog/failed jobs, disk/DB pressure,
  kegagalan backup, provider timeout, serta lonjakan usage.
- Batasi akses production shell, log, backup, dan admin platform dengan least
  privilege; gunakan MFA bila tersedia dan tinjau akses secara berkala.
- Pastikan health endpoint tidak membocorkan konfigurasi atau exception detail.

## 6. Rollback

1. Hentikan rollout dan catat waktu, release, gejala, dampak tenant, serta
   operator.
2. Kembalikan artifact aplikasi sebelumnya jika kompatibel dengan schema.
3. Jangan mengandalkan `migrate:rollback` untuk migrasi yang telah menerima data;
   migration down dapat menghapus tabel/kolom. Restore backup tervalidasi pada
   salinan non-production terlebih dahulu, lalu lakukan keputusan DBA/incident
   owner untuk production.
4. Verifikasi antrean, login, tenant isolation, akses dokumen, dan konsistensi
   database sebelum membuka traffic.
5. Dokumentasikan keputusan, dampak, dan remediasi. Jangan hapus log/audit trail.

## 7. Incident response

1. Triage severity dan batasi akses/traffic bila ada indikasi kebocoran lintas
   tenant, credential exposure, malware upload, atau kehilangan data.
2. Cabut/rotasi secret yang terdampak melalui secret manager; jangan memasukkan
   secret lama ke tiket atau command history.
3. Pertahankan log, audit, queue, backup metadata, dan bukti relevan dengan
   akses terbatas.
4. Beri tahu security owner, incident commander, dan pemilik institusi sesuai
   prosedur/legal yang berlaku.
5. Pulihkan dari backup terverifikasi, uji tenant isolation, dokumentasikan
   timeline/root cause, dan lakukan review pasca-insiden.

## 8. Go/no-go

Go hanya setelah blocker [RELEASE_CHECKLIST.md](./RELEASE_CHECKLIST.md) ditutup,
restore drill dan UAT disetujui, instrumen domain diverifikasi, provider/gateway
yang dipakai benar-benar terintegrasi, dan rollback owner memberi persetujuan.
