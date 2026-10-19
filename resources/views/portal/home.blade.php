@extends('layouts.portal')

@section('title', 'Beranda | ' . config('app.name'))

@section('content')
    <a class="visually-hidden-focusable position-absolute top-0 start-0 p-3 bg-white" href="#main-content">
        Lewati ke konten utama
    </a>

    <nav class="navbar navbar-expand-lg portal-navbar border-bottom" aria-label="Navigasi utama">
        <div class="container py-2">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-semibold" href="{{ route('portal.home') }}">
                <span class="portal-brand-mark" aria-hidden="true"><i class="bi bi-check2-square"></i></span>
                <span>Varez AI Mutu</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#portalNavigation"
                    aria-controls="portalNavigation" aria-expanded="false" aria-label="Buka navigasi">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="portalNavigation">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
                    <li class="nav-item"><a class="nav-link" href="#kapabilitas">Kapabilitas</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pendekatan">Pendekatan</a></li>
                    <li class="nav-item"><a class="btn btn-varez px-3 ms-lg-2" href="{{ url('/admin') }}">Masuk</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main id="main-content">
        <section class="portal-hero py-5 py-lg-5">
            <div class="container py-lg-5">
                <div class="row align-items-center g-5">
                    <div class="col-lg-7">
                        <p class="portal-eyebrow small fw-semibold text-uppercase mb-3">Penjaminan mutu pendidikan</p>
                        <h1 class="display-4 fw-semibold lh-sm mb-4">
                            Kerja mutu yang tertata, dengan bukti yang dapat ditelusuri.
                        </h1>
                        <p class="portal-text-muted fs-5 mb-4">
                            Ruang kerja untuk mengelola data, dokumen, instrumen, dan tindak lanjut mutu.
                            AI dirancang membantu menyusun draf, bukan menggantikan verifikasi manusia.
                        </p>
                        <a class="btn btn-varez btn-lg px-4" href="{{ url('/admin') }}">
                            Masuk ke panel <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
                        </a>
                    </div>
                    <div class="col-lg-5">
                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-body p-4 p-lg-5">
                                <span class="portal-feature-icon mb-4" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                                <h2 class="h4 fw-semibold">Bukti sebelum kesimpulan</h2>
                                <p class="portal-text-muted mb-0">
                                    Sumber, status verifikasi, dan proses review menjadi bagian dari alur kerja,
                                    sehingga draf dapat diperiksa sebelum digunakan.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="kapabilitas" class="py-5 py-lg-5">
            <div class="container py-lg-4">
                <div class="row mb-4 mb-lg-5">
                    <div class="col-lg-7">
                        <p class="portal-eyebrow small fw-semibold text-uppercase mb-2">Ruang kerja terpadu</p>
                        <h2 class="display-6 fw-semibold">Dari data dan bukti ke tindak lanjut.</h2>
                    </div>
                </div>
                <div class="row g-3 g-lg-4">
                    <div class="col-md-4">
                        <article class="card portal-feature-card shadow-sm">
                            <div class="card-body p-4">
                                <span class="portal-feature-icon mb-3" aria-hidden="true"><i class="bi bi-folder2-open"></i></span>
                                <h3 class="h5 fw-semibold">Repositori bukti</h3>
                                <p class="portal-text-muted mb-0">Kelola dokumen, versi, dan keterkaitannya dengan pekerjaan mutu.</p>
                            </div>
                        </article>
                    </div>
                    <div class="col-md-4">
                        <article class="card portal-feature-card shadow-sm">
                            <div class="card-body p-4">
                                <span class="portal-feature-icon mb-3" aria-hidden="true"><i class="bi bi-list-check"></i></span>
                                <h3 class="h5 fw-semibold">Instrumen dan proyek</h3>
                                <p class="portal-text-muted mb-0">Susun pekerjaan berdasarkan instrumen dan versi yang telah diverifikasi.</p>
                            </div>
                        </article>
                    </div>
                    <div class="col-md-4">
                        <article class="card portal-feature-card shadow-sm">
                            <div class="card-body p-4">
                                <span class="portal-feature-icon mb-3" aria-hidden="true"><i class="bi bi-arrow-repeat"></i></span>
                                <h3 class="h5 fw-semibold">Siklus peningkatan</h3>
                                <p class="portal-text-muted mb-0">Hubungkan evaluasi, temuan, tindakan, dan verifikasi dalam satu alur.</p>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section id="pendekatan" class="border-top bg-white py-5">
            <div class="container">
                <div class="row align-items-center g-3">
                    <div class="col-lg-8">
                        <h2 class="h4 fw-semibold mb-2">AI sebagai asisten, manusia tetap memutuskan.</h2>
                        <p class="portal-text-muted mb-0">Draf berbantuan AI perlu ditinjau dan disetujui sebelum menjadi dokumen final.</p>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <a class="link-success fw-semibold text-decoration-none" href="{{ url('/admin') }}">
                            Buka panel administrasi <i class="bi bi-arrow-up-right ms-1" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="py-4">
        <div class="container d-flex flex-column flex-sm-row justify-content-between gap-2">
            <span class="small portal-text-muted">&copy; {{ date('Y') }} Varez AI Mutu</span>
            <span class="small portal-text-muted">Draf dan rekomendasi memerlukan verifikasi manusia.</span>
        </div>
    </footer>
@endsection
