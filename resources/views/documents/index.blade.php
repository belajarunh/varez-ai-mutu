@extends('layouts.portal')

@section('title', 'Repositori dokumen | ' . $tenant->name)

@section('content')
    <main class="container py-5">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <p class="portal-eyebrow small fw-semibold text-uppercase mb-2">{{ $tenant->name }}</p>
                <h1 class="h2 fw-semibold">Repositori dokumen</h1>
                <p class="portal-text-muted mb-0">Bukti disimpan privat, berversi, dan dikelompokkan sesuai kategori PPEPP.</p>
            </div>
            <div class="d-flex gap-2">
                @if (auth()->user()->hasPermission('documents.categories.manage', $tenant))
                    <a class="btn btn-outline-success" href="{{ route('institutions.documents.categories', $tenant->slug) }}">Kelola kategori</a>
                @endif
                @if (auth()->user()->hasPermission('documents.upload', $tenant))
                    <a class="btn btn-varez" href="{{ route('institutions.documents.create', $tenant->slug) }}">Unggah dokumen</a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <div class="card border-0 shadow-sm rounded-4">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Judul</th><th>Kategori / PPEPP</th><th>Unit</th><th>Periode</th><th>Akses</th><th>Pemrosesan</th></tr></thead>
                    <tbody>
                        @forelse ($documents as $document)
                            <tr>
                                <td>
                                    <a class="fw-semibold text-decoration-none" href="{{ route('institutions.documents.show', [$tenant->slug, $document->getKey()]) }}">{{ $document->title }}</a>
                                    <div class="small text-secondary">v{{ $document->currentVersion?->version_number ?? '—' }} · {{ strtoupper($document->document_type) }}</div>
                                </td>
                                <td>{{ $document->category->name }}<div class="small text-secondary">{{ $document->category->ppepp_stage ? ucfirst($document->category->ppepp_stage) : 'Umum' }}</div></td>
                                <td>{{ $document->unit?->name ?? 'Seluruh institusi' }}</td>
                                <td>{{ $document->period?->name ?? '—' }}</td>
                                <td><span class="badge text-bg-light">{{ ucfirst($document->access_level) }}</span></td>
                                <td>
                                    @php($status = $document->currentVersion?->extraction_status ?? 'unknown')
                                    <span class="badge {{ $status === 'ready' ? 'text-bg-success' : ($status === 'failed' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ str_replace('_', ' ', ucfirst($status)) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-5">Belum ada dokumen yang dapat diakses.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $documents->links() }}</div>
    </main>
@endsection
