@extends('layouts.portal')

@section('title', 'Impor data kinerja | ' . $tenant->name)

@section('content')
    <main class="container py-5">
        <a href="{{ route('institutions.performance.index', $tenant->slug) }}" class="small text-decoration-none">&larr; Data kinerja</a>
        <div class="mt-3 mb-4">
            <p class="portal-eyebrow small fw-semibold text-uppercase mb-2">{{ $tenant->name }}</p>
            <h1 class="h2 fw-semibold">Impor data kinerja</h1>
            <p class="portal-text-muted">File disimpan privat. Pilih schema, periode, lalu unggah CSV UTF-8 atau XLSX (maksimal 10 MB).</p>
        </div>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">Periksa kembali data berikut:</p>
                <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if ($schemas->isEmpty())
            <div class="alert alert-info" role="status">
                Belum ada schema dataset yang diterbitkan. Platform Super Admin perlu menyepakati dan menerbitkan schema di
                <a href="{{ url('/admin/performance-dataset-schemas') }}">panel administrasi</a> terlebih dahulu.
                Tidak ada definisi data kinerja resmi yang diasumsikan oleh aplikasi.
            </div>
        @endif
        @if ($schemas->isNotEmpty())
            <div class="d-flex flex-wrap gap-2 mb-3" aria-label="Template dataset tersedia">
                @foreach ($schemas as $schema)
                    <a class="btn btn-sm btn-outline-success" href="{{ route('institutions.performance.schemas.template', [$tenant->slug, $schema->getKey(), 'csv']) }}">
                        Template CSV: {{ $schema->name }} v{{ $schema->schema_version }}
                    </a>
                    <a class="btn btn-sm btn-outline-success" href="{{ route('institutions.performance.schemas.template', [$tenant->slug, $schema->getKey(), 'xlsx']) }}">
                        Template XLSX
                    </a>
                @endforeach
            </div>
        @endif

        @if ($periods->isEmpty())
            <div class="alert alert-warning" role="status">Tambahkan periode akademik terlebih dahulu melalui formulir di bawah.</div>
        @endif

        <section class="card border-0 shadow-sm rounded-4 mb-5">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h5 fw-semibold mb-4">Unggah file</h2>
                <form method="POST" enctype="multipart/form-data" action="{{ route('institutions.performance.imports.store', $tenant->slug) }}">
                    @csrf
                    <div class="mb-3">
                        <label for="performance_dataset_schema_id" class="form-label">Schema dataset</label>
                        <select id="performance_dataset_schema_id" name="performance_dataset_schema_id" class="form-select" required>
                            <option value="">Pilih schema</option>
                            @foreach ($schemas as $schema)
                                <option value="{{ $schema->getKey() }}" @selected(old('performance_dataset_schema_id') == $schema->getKey())>
                                    {{ $schema->name }} · {{ $schema->dataset_type }} v{{ $schema->schema_version }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="academic_period_id" class="form-label">Periode akademik</label>
                        <select id="academic_period_id" name="academic_period_id" class="form-select" required>
                            <option value="">Pilih periode</option>
                            @foreach ($periods as $period)
                                <option value="{{ $period->getKey() }}" @selected(old('academic_period_id') == $period->getKey())>{{ $period->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="unit_id" class="form-label">Unit (opsional)</label>
                        <select id="unit_id" name="unit_id" class="form-select">
                            <option value="">Seluruh institusi</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->getKey() }}" @selected(old('unit_id') == $unit->getKey())>{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="file" class="form-label">File CSV atau XLSX</label>
                        <input id="file" name="file" type="file" class="form-control" accept=".csv,.xlsx" required>
                        <div class="form-text">Tidak menerima XLS lama (.xls), macro, file lebih dari 10 MB, atau encoding selain UTF-8.</div>
                    </div>
                    <button class="btn btn-varez" type="submit" @disabled($schemas->isEmpty() || $periods->isEmpty())>Lanjut ke pemetaan kolom</button>
                </form>
            </div>
        </section>

        <section class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h5 fw-semibold mb-2">Tambah periode akademik</h2>
                <p class="portal-text-muted">Periode disimpan di tenant aktif dan dapat dipilih untuk impor berikutnya.</p>
                <form method="POST" action="{{ route('institutions.performance.periods.store', $tenant->slug) }}" class="row g-3">
                    @csrf
                    <div class="col-md-4"><label class="form-label" for="period-code">Kode</label><input id="period-code" name="code" class="form-control" maxlength="80" required></div>
                    <div class="col-md-8"><label class="form-label" for="period-name">Nama periode</label><input id="period-name" name="name" class="form-control" maxlength="160" required></div>
                    <div class="col-md-4"><label class="form-label" for="starts-on">Mulai</label><input id="starts-on" name="starts_on" type="date" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label" for="ends-on">Selesai</label><input id="ends-on" name="ends_on" type="date" class="form-control"></div>
                    <div class="col-12"><button class="btn btn-outline-success" type="submit">Simpan periode</button></div>
                </form>
            </div>
        </section>
    </main>
@endsection
