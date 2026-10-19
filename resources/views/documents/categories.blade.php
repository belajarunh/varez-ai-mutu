@extends('layouts.portal')

@section('title', 'Kategori dokumen | ' . $tenant->name)

@section('content')
    <main class="container py-5">
        <a href="{{ route('institutions.documents.index', $tenant->slug) }}" class="small text-decoration-none">&larr; Repositori dokumen</a>
        <div class="mt-3 mb-4">
            <p class="portal-eyebrow small fw-semibold text-uppercase mb-2">{{ $tenant->name }}</p>
            <h1 class="h2 fw-semibold">Kategori dan siklus PPEPP</h1>
            <p class="portal-text-muted">Kelola kategori institusi dan default akses. Stage PPEPP dapat dibiarkan kosong untuk kategori lain.</p>
        </div>
        @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <section class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h2 class="h5">Tambah kategori</h2>
                <form method="POST" action="{{ route('institutions.documents.categories.store', $tenant->slug) }}" class="row g-3">
                    @csrf
                    <div class="col-md-3"><label class="form-label" for="code">Kode</label><input id="code" name="code" class="form-control" maxlength="80" pattern="[a-z][a-z0-9_-]*" required></div>
                    <div class="col-md-3"><label class="form-label" for="name">Nama</label><input id="name" name="name" class="form-control" maxlength="160" required></div>
                    <div class="col-md-3">
                        <label class="form-label" for="ppepp_stage">Tahap PPEPP</label>
                        <select id="ppepp_stage" name="ppepp_stage" class="form-select">
                            <option value="">Tidak khusus PPEPP</option>
                            @foreach (\App\Models\DocumentCategory::PPEPP_STAGES as $stage)<option value="{{ $stage }}">{{ ucfirst($stage) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="access_default">Akses default</label>
                        <select id="access_default" name="access_default" class="form-select" required>
                            <option value="private">Pemilik</option><option value="unit">Unit</option><option value="institution" selected>Institusi</option>
                        </select>
                    </div>
                    <div class="col-12"><button class="btn btn-varez" type="submit">Simpan kategori</button></div>
                </form>
            </div>
        </section>

        <div class="vstack gap-3">
            @forelse ($categories as $category)
                <section class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <form method="POST" action="{{ route('institutions.documents.categories.update', [$tenant->slug, $category->getKey()]) }}" class="row g-3 align-items-end">
                            @csrf @method('PATCH')
                            <div class="col-md-2"><label class="form-label">Kode</label><input class="form-control" value="{{ $category->code }}" disabled></div>
                            <div class="col-md-3"><label class="form-label" for="name-{{ $category->id }}">Nama</label><input id="name-{{ $category->id }}" name="name" value="{{ $category->name }}" class="form-control" maxlength="160" required></div>
                            <div class="col-md-2">
                                <label class="form-label" for="stage-{{ $category->id }}">PPEPP</label>
                                <select id="stage-{{ $category->id }}" name="ppepp_stage" class="form-select">
                                    <option value="">—</option>
                                    @foreach (\App\Models\DocumentCategory::PPEPP_STAGES as $stage)<option value="{{ $stage }}" @selected($category->ppepp_stage === $stage)>{{ ucfirst($stage) }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="access-{{ $category->id }}">Default akses</label>
                                <select id="access-{{ $category->id }}" name="access_default" class="form-select">
                                    @foreach (\App\Models\DocumentCategory::ACCESS_LEVELS as $level)<option value="{{ $level }}" @selected($category->access_default === $level)>{{ ucfirst($level) }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label" for="active-{{ $category->id }}">Aktif</label>
                                <select id="active-{{ $category->id }}" name="is_active" class="form-select"><option value="1" @selected($category->is_active)>Ya</option><option value="0" @selected(! $category->is_active)>Tidak</option></select>
                            </div>
                            <div class="col-md-2 d-flex justify-content-between align-items-center">
                                <span class="small text-secondary">{{ $category->documents_count }} dokumen</span>
                                <button class="btn btn-sm btn-outline-success" type="submit">Simpan</button>
                            </div>
                        </form>
                    </div>
                </section>
            @empty
                <p class="text-secondary">Belum ada kategori.</p>
            @endforelse
        </div>
        <div class="mt-3">{{ $categories->links() }}</div>
    </main>
@endsection
