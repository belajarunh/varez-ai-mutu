# Release checklist

Status terakhir: **belum siap untuk production atau pilot dengan data nyata**.
Gunakan checklist ini sebelum deployment; hasil lokal tidak menggantikan UAT,
review pemilik domain, atau restore drill pada lingkungan non-production.

## Quality gates lokal

- [x] `composer check` (Pint, PHPStan level 5, dan seluruh test suite) lulus.
- [x] `composer audit --no-interaction` tidak menemukan advisory.
- [x] `npm.cmd audit --audit-level=moderate` tidak menemukan vulnerability.
- [x] `npm.cmd run build` berhasil.
- [x] Regression tests tersedia untuk tenant isolation, upload berbahaya,
  download IDOR, retrieval lintas tenant/unit, provider tidak terkonfigurasi,
  abstention saat bukti kosong, idempotency usage, kuota, dan webhook duplikat.
- [ ] Jalankan dan simpan bukti UAT untuk lingkungan target. Skenario ada di
  [UAT_SCENARIOS.md](./UAT_SCENARIOS.md).

## Blocker sebelum pilot/production

- [ ] Implementasikan dan verifikasi provider AI nyata. Provider OpenAI saat ini
  masih placeholder; `OPENAI_API_KEY` bukan bukti bahwa request provider benar.
- [ ] Implementasikan ekspor file nyata dan unduhan yang terotorisasi. Alur saat
  ini menyimpan manifest ekspor, bukan membuat artefak file yang bisa diunduh.
- [ ] Pilih dan integrasikan satu payment gateway atau tetapkan prosedur aktivasi
  manual dengan administrator yang ditunjuk. Endpoint webhook produksi belum
  tersedia; jangan mengaktifkan `BILLING_GATEWAY_ENABLED`.
- [ ] Lakukan backup database dan object storage, lalu restore drill pada
  lingkungan non-production. Belum ada bukti restore dari sesi QA ini.
- [ ] Selesaikan UAT akses tenant, security, queue, AI evaluation, dan billing.
- [ ] Dapatkan verifikasi tertulis pemilik domain untuk instrumen pilot sekolah
  dan perguruan tinggi. Sampai saat itu instrumen harus berstatus internal/demo
  dan tidak boleh disebut instrumen resmi.
- [ ] Pastikan dashboard SaaS benar-benar terhubung ke UI/Filament dan ukurannya
  sesuai definisi bisnis. Saat ini metrik tersedia sebagai service, bukan
  dashboard yang telah di-UAT.
- [ ] Verifikasi konfigurasi produksi: `APP_DEBUG=false`, HTTPS, cookie session
  secure/HTTP-only, secret yang dikelola di secret manager, logging/alerting,
  kapasitas queue, serta hak akses operator.
- [ ] Jalankan seluruh migrasi pada salinan database target dan tinjau rencana
  rollback sebelum deployment. Empat migrasi AI/billing tambahan masih berstatus
  pending pada database lokal yang diperiksa.

## Sebelum deployment

- [ ] Snapshot/backup database serta object storage dan konfirmasi retensi.
- [ ] Pastikan storage bukti privat; bucket production tidak public dan enkripsi
  serta versioning/backup sesuai kebijakan institusi.
- [ ] Tinjau `.env.example` dan bandingkan semua nilai produksi tanpa menyalin
  secret ke repository atau log.
- [ ] Deploy release candidate ke staging, migrasikan, jalankan smoke test, dan
  verifikasi worker, scheduler, health check, email/invitation, upload, download,
  serta kegagalan provider.
- [ ] Rekam approval pemilik domain, administrator tenant, keamanan, dan release
  owner.

## Keputusan release

- [ ] Production-ready
- [ ] Pilot terbatas dengan data nyata
- [x] **Belum siap** — internal UAT hanya dengan data dummy/non-sensitif sampai
  blocker di atas selesai.
