@extends('layouts.portal')

@section('title', 'Unggah dokumen | ' . $tenant->name)

@section('content')
    <main class="container py-5">
        <a href="{{ route('institutions.documents.index', $tenant->slug) }}" class="small text-decoration-none">&larr; Repositori dokumen</a>
        <div class="mt-3 mb-4">
            <p class="portal-eyebrow small fw-semibold text-uppercase mb-2">{{ $tenant->name }}</p>
            <h1 class="h2 fw-semibold">Unggah bukti</h1>
            <p class="portal-text-muted">PDF, DOCX, XLSX, CSV, PPTX, JPG, PNG, atau WebP hingga 25 MB. File disimpan pada private storage dan diproses di queue.</p>
        </div>
        @if ($errors->any())
            <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        @if ($categories->isEmpty())
            <div class="alert alert-warning">
                Belum ada kategori aktif. Minta pengelola membuat kategori PPEPP sebelum mengunggah.
                @if (auth()->user()->hasPermission('documents.categories.manage', $tenant))
                    <a href="{{ route('institutions.documents.categories', $tenant->slug) }}">Kelola kategori</a>
                @endif
            </div>
        @endif

        <section class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-lg-5">
                <form method="POST" enctype="multipart/form-data" action="{{ route('institutions.documents.store', $tenant->slug) }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="title" class="form-label">Judul dokumen</label>
                            <input id="title" name="title" value="{{ old('title') }}" class="form-control" maxlength="255" required>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Keterangan (opsional)</label>
                            <textarea id="description" name="description" class="form-control" rows="3" maxlength="5000">{{ old('description') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="category_id" class="form-label">Kategori</label>
                            <select id="category_id" name="category_id" class="form-select" required>
                                <option value="">Pilih kategori</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->getKey() }}" @selected(old('category_id') == $category->getKey())>
                                        {{ $category->name }}{{ $category->ppepp_stage ? ' · '.ucfirst($category->ppepp_stage) : '' }} · akses {{ $category->access_default }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="unit_id" class="form-label">Unit pemilik (opsional)</label>
                            <select id="unit_id" name="unit_id" class="form-select">
                                <option value="">Seluruh institusi</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->getKey() }}" @selected(old('unit_id') == $unit->getKey())>{{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="academic_period_id" class="form-label">Periode (opsional)</label>
                            <select id="academic_period_id" name="academic_period_id" class="form-select">
                                <option value="">Tanpa periode</option>
                                @foreach ($periods as $period)
                                    <option value="{{ $period->getKey() }}" @selected(old('academic_period_id') == $period->getKey())>{{ $period->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="retention_until" class="form-label">Retensi sampai (opsional)</label>
                            <input id="retention_until" name="retention_until" type="date" min="{{ now()->toDateString() }}" value="{{ old('retention_until') }}" class="form-control">
                        </div>
                        <div class="col-12">
                            <label for="file" class="form-label">File</label>
                            <input id="file" name="file" type="file" accept=".pdf,.docx,.xlsx,.csv,.pptx,.jpg,.jpeg,.png,.webp" class="form-control" required>
                            <div class="form-text">File dengan macro dan objek tertanam aktif ditolak. Ekstraksi yang gagal atau tidak meyakinkan memerlukan pemeriksaan manual.</div>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-varez" type="submit" @disabled($categories->isEmpty())>Unggah dan proses</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </main>
@endsection
