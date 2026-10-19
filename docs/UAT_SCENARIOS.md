# UAT scenarios

Jalankan pada staging/non-production dengan dua tenant terpisah dan data dummy.
Catat build, tester, waktu, hasil aktual, bukti, dan issue ID untuk setiap run.
Status `Pending` berarti belum dijalankan manual; test otomatis yang relevan
dicatat sebagai bukti pendukung, bukan pengganti UAT.

| ID | Area | Skenario dan langkah ringkas | Hasil yang diharapkan | Status |
|---|---|---|---|---|
| UAT-01 | Tenant / IDOR | User tenant A mencoba membuka project, unit, dokumen, download, import, dan evidence tenant B dengan mengganti ID URL. | Ditolak (404/403), tidak ada data atau file tenant B terungkap, akses tercatat bila diwajibkan. | Automated coverage; manual pending |
| UAT-02 | Unit scope | User yang hanya ditugaskan ke satu unit mencoba membuka dokumen/aktivitas unit lain di institusi sama. | Ditolak di server, bukan hanya disembunyikan di UI. | Automated coverage; manual pending |
| UAT-03 | Upload berbahaya | Upload file terlalu besar, ekstensi palsu, executable/script, office XML rusak, dan file macro yang tidak diizinkan. | Upload ditolak atau quarantined; tak ada ekstraksi/unduhan sampai scan/review aman. | Automated coverage; manual pending |
| UAT-04 | Download | User tanpa permission, user tenant lain, dan user dengan akses dokumen private mencoba download versi. | Semua ditolak; user berizin mendapat versi yang benar dan audit log tidak berisi secret. | Automated coverage; manual pending |
| UAT-05 | Login/session | Uji login berulang, logout, session fixation, cookie di HTTPS, perubahan status user, dan akses setelah membership dicabut. | Rate limit aktif; session lama tidak memberi akses; cookie secure/HTTP-only di production. | Pending; rate limit/session behavior belum dibuktikan |
| UAT-06 | XSS/CSRF | Masukkan markup/script pada judul, catatan, sitasi, nama file, dan komentar; kirim mutasi lintas-site tanpa token. | Input ditampilkan escaped; mutasi ditolak tanpa token CSRF; tak ada stored/reflected script. | Pending manual |
| UAT-07 | Prompt injection | Tambahkan instruksi adversarial ke dokumen sumber dan coba retrieval antar-tenant. | Dokumen diperlakukan sebagai data; sumber tetap tenant/access scoped; instruksi tidak diikuti. | Cross-tenant/guardrail tests ada; red-team UAT pending |
| UAT-08 | Evidence/AI | Coba narasi tanpa bukti, dengan bukti konflik, klaim angka, dan sitasi yang tidak mendukung klaim. | Abstain bila bukti kurang; konflik ditandai; angka/sitasi cocok dengan versi sumber dan klaim unsupported tidak disajikan sebagai fakta. | Abstention/basic citations tested; numeric/citation evaluation pending |
| UAT-09 | Provider failure | Jalankan generasi dengan provider tidak terkonfigurasi, timeout, dan respons provider gagal. | Status kegagalan terlihat; tidak ada sukses semu, draft unsupported, atau charge duplikat. | Mock/unconfigured path covered; provider outage UAT pending |
| UAT-10 | Queue | Simulasikan retry, timeout, failed job, restart worker, dan proses ulang dokumen/import. | Status terminal tercatat; retry aman/idempotent; job pulih tidak menggandakan usage/import; failed job dapat diinspeksi. | Sebagian regression tests ada; production-like queue test pending |
| UAT-11 | Import partial failure | Impor file dengan baris valid, invalid, dan duplikat; hentikan/retry worker saat commit. | Baris/commit sesuai mode yang dipilih, error report tenant-scoped, retry tidak menggandakan data. | Automated import coverage; failure-injection UAT pending |
| UAT-12 | Export | Ekspor draft, approved, dan sumber yang berubah/tidak lagi diakses; coba akses hasil ekspor oleh tenant lain. | Draft selalu diberi watermark; approved hanya diekspor oleh role berizin; manifest menyertakan versi sumber; akses tenant lain ditolak. | Manifest logic partially tested; file generation/download/UAT pending |
| UAT-13 | Quota/SaaS | Uji batas tepat kuota, kuota nol, unlimited (null), tanpa subscription, perubahan plan, dan retry job yang sama. | Batas konsisten; `0` menolak, `null` unlimited hanya untuk paket aktif; retry usage tidak tercatat ganda; histori plan terjaga. | Automated coverage |
| UAT-14 | Billing | Gateway disabled, signature salah, invoice/amount/currency salah, event duplikat, status pending/failed/paid. | Gateway disabled tidak menerima webhook; signature/data salah ditolak; event duplikat idempotent; invoice hanya paid untuk pembayaran tervalidasi. | Service tests; real gateway UAT pending |
| UAT-15 | Dashboard | Role pimpinan, admin mutu, tim akreditasi, admin unit, dan platform admin membuka metrik yang sesuai. | Hanya data sesuai tenant/permission terlihat; angka memiliki periode, definisi, sumber, serta empty/error state yang jelas. | Service-level permission tested; UI/role UAT pending |
| UAT-16 | Backup/restore | Restore snapshot database dan object storage pada environment non-production, lalu verifikasi dokumen/versi/metadata dan akses. | Restore selesai dalam target RTO/RPO dan data aplikasi dapat dibaca; hasil serta durasi dicatat. | Not run; release blocker |
| UAT-17 | Instrument domain | Pemilik domain sekolah/perguruan tinggi memeriksa sumber, versi, pemetaan, dan status instrumen. | Versi resmi hanya dapat diterbitkan setelah verifikasi pemilik domain; materi demo/internal diberi label jelas. | Pending owner verification |

UAT tidak boleh memakai data pribadi/rahasia produksi sebelum backup, access
control, retensi, logging, dan persetujuan pemilik data diverifikasi.
