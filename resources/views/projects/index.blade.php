@extends('layouts.portal')

@section('title', 'Proyek akreditasi | ' . $tenant->name)

@section('content')
    <main class="container py-5">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <a href="{{ route('portal.home') }}" class="small text-decoration-none">&larr; Beranda</a>
                <p class="portal-eyebrow small fw-semibold text-uppercase mt-3 mb-2">{{ $tenant->name }}</p>
                <h1 class="h2 fw-semibold mb-1">Proyek akreditasi</h1>
                <p class="portal-text-muted mb-0">Proyek dan indikator hanya terlihat di ruang institusi Anda.</p>
            </div>
            @if (auth()->user()->hasPermission('projects.create', $tenant))
                <a href="{{ route('institutions.projects.create', $tenant->slug) }}" class="btn btn-varez">
                    Buat proyek
                </a>
            @endif
            @if (auth()->user()->hasPermission('performance_data.view', $tenant))
                <a href="{{ route('institutions.performance.index', $tenant->slug) }}" class="btn btn-outline-success">
                    Data kinerja
                </a>
            @endif
            @if (auth()->user()->hasPermission('documents.view', $tenant))
                <a href="{{ route('institutions.documents.index', $tenant->slug) }}" class="btn btn-outline-success">
                    Repositori bukti
                </a>
            @endif
            @if (auth()->user()->hasPermission('quality.view', $tenant))
                <a href="{{ route('institutions.quality.index', $tenant->slug) }}" class="btn btn-outline-success">
                    Penjaminan mutu
                </a>
            @endif
        </div>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        @forelse ($projects as $project)
            <a class="card border-0 shadow-sm rounded-4 text-decoration-none text-reset mb-3"
               href="{{ route('institutions.projects.show', [$tenant->slug, $project->getKey()]) }}">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <h2 class="h5 fw-semibold mb-1">{{ $project->title }}</h2>
                            <p class="portal-text-muted mb-0">
                                {{ $project->instrumentVersion->instrument->name }}
                                · {{ $project->instrumentVersion->version_label }}
                                · {{ $project->period_label }}
                            </p>
                        </div>
                        <span class="badge text-bg-light align-self-start">{{ str_replace('_', ' ', $project->status) }}</span>
                    </div>
                </div>
            </a>
        @empty
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-5 text-center">
                    <h2 class="h5 fw-semibold">Belum ada proyek</h2>
                    <p class="portal-text-muted mb-0">Proyek hanya dapat dibuat menggunakan versi katalog terverifikasi yang sesuai dengan tipe institusi.</p>
                </div>
            </div>
        @endforelse

        <div class="mt-4">{{ $projects->links() }}</div>
    </main>
@endsection
