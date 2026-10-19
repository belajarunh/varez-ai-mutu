@extends('layouts.portal')

@section('title', 'Data kinerja | ' . $tenant->name)

@section('content')
    <main class="container py-5">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <a href="{{ route('portal.home') }}" class="small text-decoration-none">&larr; Beranda</a>
                <p class="portal-eyebrow small fw-semibold text-uppercase mt-3 mb-2">{{ $tenant->name }}</p>
                <h1 class="h2 fw-semibold mb-1">Data kinerja</h1>
                <p class="portal-text-muted mb-0">Kelola dataset dan pantau riwayat impor yang dibatasi pada institusi ini.</p>
            </div>
            @if (auth()->user()->hasPermission('performance_data.import', $tenant))
                <a href="{{ route('institutions.performance.imports.create', $tenant->slug) }}" class="btn btn-varez">
                    Impor data
                </a>
            @endif
        </div>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <section class="mb-5" aria-labelledby="batches-heading">
            <h2 id="batches-heading" class="h4 fw-semibold mb-3">Riwayat impor</h2>
            @forelse ($batches as $batch)
                <a class="card border-0 shadow-sm rounded-4 text-decoration-none text-reset mb-3"
                   href="{{ route('institutions.performance.imports.show', [$tenant->slug, $batch->getKey()]) }}">
                    <div class="card-body p-4">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div>
                                <h3 class="h6 fw-semibold mb-1">{{ $batch->original_filename }}</h3>
                                <p class="portal-text-muted mb-0">
                                    {{ $batch->schema->name }} · {{ $batch->period->name }}
                                    · {{ $batch->created_at->format('Y-m-d H:i') }}
                                </p>
                            </div>
                            <span class="badge text-bg-light align-self-start">{{ str_replace('_', ' ', $batch->status) }}</span>
                        </div>
                        <p class="small portal-text-muted mt-3 mb-0">
                            {{ $batch->valid_count }} baris valid · {{ $batch->error_count }} error · SHA-256 {{ $batch->checksum_sha256 }}
                        </p>
                    </div>
                </a>
            @empty
                <p class="portal-text-muted">Belum ada batch impor.</p>
            @endforelse
            {{ $batches->links() }}
        </section>

        <section aria-labelledby="datasets-heading">
            <h2 id="datasets-heading" class="h4 fw-semibold mb-3">Dataset tersedia</h2>
            <div class="table-responsive bg-white rounded-4 shadow-sm">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Jenis</th><th>Periode</th><th>Unit</th><th>Versi schema</th><th>Records</th></tr></thead>
                    <tbody>
                    @forelse ($datasets as $dataset)
                        <tr>
                            <td>{{ $dataset->schema->name }}</td>
                            <td>{{ $dataset->period->name }}</td>
                            <td>{{ $dataset->unit?->name ?? 'Seluruh institusi' }}</td>
                            <td>{{ $dataset->schema_version }}</td>
                            <td>{{ number_format($dataset->records_count) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center portal-text-muted py-4">Belum ada dataset.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $datasets->links() }}</div>
        </section>
    </main>
@endsection
