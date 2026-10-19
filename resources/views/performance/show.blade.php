@extends('layouts.portal')

@section('title', 'Batch impor #' . $record->getKey() . ' | ' . $tenant->name)

@section('content')
    <main class="container py-5">
        <a href="{{ route('institutions.performance.index', $tenant->slug) }}" class="small text-decoration-none">&larr; Data kinerja</a>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mt-3 mb-4">
            <div>
                <p class="portal-eyebrow small fw-semibold text-uppercase mb-2">{{ $tenant->name }}</p>
                <h1 class="h2 fw-semibold mb-1">Batch impor #{{ $record->getKey() }}</h1>
                <p class="portal-text-muted mb-0">{{ $record->original_filename }} · {{ $record->schema->name }} v{{ $record->schema->schema_version }}</p>
            </div>
            <span class="badge text-bg-light fs-6">{{ str_replace('_', ' ', $record->status) }}</span>
        </div>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <section class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Periode</dt><dd class="col-sm-9">{{ $record->period->name }}</dd>
                    <dt class="col-sm-3">Unit</dt><dd class="col-sm-9">{{ $record->unit?->name ?? 'Seluruh institusi' }}</dd>
                    <dt class="col-sm-3">Pengunggah</dt><dd class="col-sm-9">{{ $record->uploader->name }}</dd>
                    <dt class="col-sm-3">Checksum SHA-256</dt><dd class="col-sm-9 text-break"><code>{{ $record->checksum_sha256 }}</code></dd>
                    <dt class="col-sm-3">Baris</dt><dd class="col-sm-9">{{ number_format($record->row_count) }} total · {{ number_format($record->valid_count) }} valid · {{ number_format($record->error_count) }} error</dd>
                    <dt class="col-sm-3">Strategi</dt><dd class="col-sm-9">{{ $record->strategy ?? 'Belum dipilih' }} @if ($record->target_dataset_id)· dataset #{{ $record->target_dataset_id }}@endif</dd>
                </dl>
            </div>
        </section>

        @if (in_array($record->status, ['inspecting', 'validating', 'queued', 'processing'], true))
            <div class="alert alert-info" role="status">
                Pemeriksaan file atau pemrosesan batch berjalan secara asinkron. Muat ulang halaman ini untuk melihat status terbaru.
                Pastikan queue worker aktif dengan <code>php artisan queue:work</code>.
                <a href="{{ url()->current() }}" class="alert-link ms-2">Muat ulang</a>
            </div>
        @elseif ($record->status === 'awaiting_mapping' && auth()->user()->hasPermission('performance_data.import', $tenant))
            <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2" role="status">
                <span>Pemeriksaan awal selesai. Petakan kolom file ke schema sebelum validasi baris.</span>
                <a href="{{ route('institutions.performance.imports.mapping', [$tenant->slug, $record->getKey()]) }}" class="btn btn-sm btn-varez">Petakan kolom</a>
            </div>
        @elseif ($record->status === 'failed')
            <div class="alert alert-danger" role="alert">Batch gagal dan tidak melakukan perubahan parsial. Periksa error report sebelum mengunggah ulang.</div>
        @elseif ($record->status === 'preview_with_errors')
            <div class="alert alert-warning" role="alert">
                Hanya {{ number_format($record->valid_count) }} baris valid yang dapat diproses. Baris error akan dilewati.
                Konfirmasi eksplisit diperlukan untuk melanjutkan impor sebagian.
            </div>
        @elseif ($record->status === 'preview_ready')
            <div class="alert alert-success" role="status">Validasi selesai. Periksa ringkasan sebelum mengonfirmasi.</div>
        @endif

        @if (in_array($record->status, ['preview_ready', 'preview_with_errors'], true) && ! empty($record->preview_json))
            <section class="mb-4" aria-labelledby="preview-heading">
                <h2 id="preview-heading" class="h5 fw-semibold mb-3">Pratinjau hingga 10 baris valid</h2>
                <div class="table-responsive bg-white rounded-4 shadow-sm">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr>@foreach (array_keys($record->preview_json[0]) as $key)<th>{{ $key }}</th>@endforeach</tr></thead>
                        <tbody>
                        @foreach ($record->preview_json as $previewRow)
                            <tr>@foreach ($record->preview_json[0] as $key => $unused)<td>{{ is_bool($previewRow[$key] ?? null) ? (($previewRow[$key] ?? false) ? 'Ya' : 'Tidak') : ($previewRow[$key] ?? '—') }}</td>@endforeach</tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($record->error_count > 0)
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h5 fw-semibold mb-0">Error validasi</h2>
                <a class="btn btn-outline-success btn-sm" href="{{ route('institutions.performance.imports.errors', [$tenant->slug, $record->getKey()]) }}">Unduh CSV error</a>
            </div>
            <div class="table-responsive bg-white rounded-4 shadow-sm mb-4">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Baris</th><th>Kolom</th><th>Kode</th><th>Pesan</th><th>Data terbatas</th></tr></thead>
                    <tbody>
                    @foreach ($importErrors as $error)
                        <tr>
                            <td>{{ $error->row_number ?? '—' }}</td>
                            <td>{{ $error->column_name ?? '—' }}</td>
                            <td><code>{{ $error->error_code }}</code></td>
                            <td>{{ $error->message }}</td>
                            <td><code>{{ json_encode($error->row_payload_json, JSON_UNESCAPED_UNICODE) }}</code></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $importErrors->links() }}
        @endif

        @if (in_array($record->status, ['preview_ready', 'preview_with_errors'], true) && $record->fatal_error_count === 0 && $record->valid_count > 0)
            <form method="POST" action="{{ route('institutions.performance.imports.confirm', [$tenant->slug, $record->getKey()]) }}" class="card border-0 shadow-sm rounded-4">
                @csrf
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold">Konfirmasi pemrosesan</h2>
                    <p class="portal-text-muted">Pemrosesan dilakukan melalui queue dan dicatat pada audit log.</p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" id="confirmation" name="confirmation" type="checkbox" value="1" required>
                        <label class="form-check-label" for="confirmation">Saya telah meninjau pemetaan dan ringkasan validasi batch ini.</label>
                    </div>
                    @if ($record->error_count > 0)
                        <div class="form-check mb-3">
                            <input class="form-check-input" id="partial-confirmation" name="partial_confirmation" type="checkbox" value="1" required>
                            <label class="form-check-label" for="partial-confirmation">Saya setuju mengimpor hanya baris valid dan melewati seluruh baris error di atas.</label>
                        </div>
                    @endif
                    @if ($record->strategy === 'replace')
                        <div class="form-check mb-3">
                            <input class="form-check-input" id="replace-confirmation" name="replace_confirmation" type="checkbox" value="1" required>
                            <label class="form-check-label" for="replace-confirmation">Saya mengonfirmasi penggantian seluruh records dataset tujuan.</label>
                        </div>
                    @endif
                    @if ($errors->has('confirmation') || $errors->has('partial_confirmation') || $errors->has('replace_confirmation'))
                        <div class="alert alert-danger">{{ $errors->first('confirmation') }} {{ $errors->first('partial_confirmation') }} {{ $errors->first('replace_confirmation') }}</div>
                    @endif
                    <button class="btn btn-varez" type="submit">Konfirmasi dan masukkan ke queue</button>
                </div>
            </form>
        @endif
    </main>
@endsection
