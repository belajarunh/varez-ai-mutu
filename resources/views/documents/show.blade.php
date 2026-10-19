@extends('layouts.portal')

@section('title', $record->title . ' | Repositori dokumen')

@section('content')
    <main class="container py-5">
        <a href="{{ route('institutions.documents.index', $tenant->slug) }}" class="small text-decoration-none">&larr; Repositori dokumen</a>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mt-3 mb-4">
            <div>
                <p class="portal-eyebrow small fw-semibold text-uppercase mb-2">{{ $tenant->name }}</p>
                <h1 class="h2 fw-semibold">{{ $record->title }}</h1>
                <p class="portal-text-muted mb-0">{{ $record->category->name }}{{ $record->category->ppepp_stage ? ' · '.ucfirst($record->category->ppepp_stage) : '' }} · {{ strtoupper($record->document_type) }}</p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <span class="badge {{ $record->verification_status === 'verified' ? 'text-bg-success' : ($record->verification_status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ ucfirst($record->verification_status) }}</span>
                <span class="badge text-bg-light">Akses {{ ucfirst($record->access_level) }}</span>
            </div>
        </div>

        @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <div class="row g-4">
            <div class="col-lg-7">
                <section class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5">Metadata</h2>
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Unit</dt><dd class="col-sm-8">{{ $record->unit?->name ?? 'Seluruh institusi' }}</dd>
                            <dt class="col-sm-4">Periode</dt><dd class="col-sm-8">{{ $record->period?->name ?? '—' }}</dd>
                            <dt class="col-sm-4">Pemilik</dt><dd class="col-sm-8">{{ $record->owner->name }}</dd>
                            <dt class="col-sm-4">Retensi sampai</dt><dd class="col-sm-8">{{ $record->retention_until?->format('d M Y') ?? 'Tidak ditentukan' }}</dd>
                            <dt class="col-sm-4">Deskripsi</dt><dd class="col-sm-8">{{ $record->description ?: '—' }}</dd>
                            <dt class="col-sm-4">Status</dt><dd class="col-sm-8">{{ ucfirst($record->status) }}</dd>
                        </dl>
                    </div>
                </section>

                <section class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5">Riwayat versi</h2>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead><tr><th>Versi / file</th><th>Proses / scan</th><th>Checksum</th><th></th></tr></thead>
                                <tbody>
                                    @foreach ($record->versions as $version)
                                        <tr>
                                            <td>v{{ $version->version_number }}<div class="small text-secondary">{{ $version->original_filename }} · {{ number_format($version->size_bytes / 1_048_576, 2) }} MB</div></td>
                                            <td>
                                                <span class="badge {{ $version->extraction_status === 'ready' ? 'text-bg-success' : ($version->extraction_status === 'failed' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ str_replace('_', ' ', $version->extraction_status) }}</span>
                                                <div class="small text-secondary">Scan: {{ str_replace('_', ' ', $version->scan_status) }}</div>
                                                @if ($version->metadata_json['warnings'] ?? false)
                                                    <div class="small text-warning-emphasis">{{ implode(' ', $version->metadata_json['warnings']) }}</div>
                                                @endif
                                            </td>
                                            <td><code class="small">{{ substr($version->checksum_sha256, 0, 12) }}…</code></td>
                                            <td>
                                                @if (auth()->user()->hasPermission('documents.download', $tenant) && in_array($version->scan_status, ['clean', 'not_scanned'], true))
                                                    <a class="btn btn-sm btn-outline-success" href="{{ route('institutions.documents.versions.download', [$tenant->slug, $record->getKey(), $version->getKey()]) }}">Unduh</a>
                                                @elseif (auth()->user()->hasPermission('documents.download', $tenant))
                                                    <span class="small text-secondary">Menunggu hasil pemeriksaan keamanan</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if (auth()->user()->hasPermission('documents.upload', $tenant) && $record->status === 'active')
                            <form method="POST" enctype="multipart/form-data" action="{{ route('institutions.documents.versions.store', [$tenant->slug, $record->getKey()]) }}" class="mt-3">
                                @csrf
                                <label class="form-label" for="new-version-file">Unggah versi baru</label>
                                <div class="input-group"><input id="new-version-file" name="file" type="file" accept=".pdf,.docx,.xlsx,.csv,.pptx,.jpg,.jpeg,.png,.webp" class="form-control" required><button class="btn btn-outline-success" type="submit">Tambah versi</button></div>
                                <div class="form-text">Versi lama disimpan dan tetap dapat diunduh.</div>
                            </form>
                        @endif
                    </div>
                </section>

                <section class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h2 class="h5">Relasi bukti</h2>
                        @forelse ($record->relations as $relation)
                            <div class="d-flex justify-content-between border-bottom py-2">
                                <span>{{ ucfirst(str_replace('_', ' ', $relation->related_type)) }} #{{ $relation->related_id }} · {{ $relation->relation_type }}</span>
                                @if ($canManage)
                                    <form method="POST" action="{{ route('institutions.documents.relations.delete', [$tenant->slug, $record->getKey(), $relation->getKey()]) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Lepas</button></form>
                                @endif
                            </div>
                        @empty
                            <p class="text-secondary">Belum ada relasi.</p>
                        @endforelse
                        @if ($canManage && $projectOptions->isNotEmpty())
                            <form method="POST" action="{{ route('institutions.documents.relations.store', [$tenant->slug, $record->getKey()]) }}" class="row g-2 mt-2">
                                @csrf
                                <div class="col-md-8">
                                    <label for="related_target" class="visually-hidden">Proyek atau indikator</label>
                                    <select id="related_target" name="related_target" class="form-select" required>
                                        <option value="">Pilih proyek/indikator</option>
                                        @foreach ($projectOptions as $project)
                                            <option value="project:{{ $project->getKey() }}">Proyek: {{ $project->title }}</option>
                                            @foreach ($project->indicators as $indicator)
                                                <option value="project_indicator:{{ $indicator->getKey() }}">Indikator · {{ $project->title }} · {{ $indicator->snapshot_node_json['title'] ?? $indicator->snapshot_node_json['code'] ?? $indicator->getKey() }}</option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="relation_type" class="visually-hidden">Jenis relasi</label>
                                    <select id="relation_type" name="relation_type" class="form-select"><option value="supports">Mendukung</option><option value="references">Referensi</option></select>
                                </div>
                                <div class="col-md-2"><button class="btn btn-outline-success w-100" type="submit">Hubungkan</button></div>
                            </form>
                        @endif
                    </div>
                </section>
            </div>

            <div class="col-lg-5">
                @if ($canVerify)
                    <section class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-body p-4">
                            <h2 class="h5">Verifikasi bukti</h2>
                            <form method="POST" action="{{ route('institutions.documents.verify', [$tenant->slug, $record->getKey()]) }}" class="d-flex gap-2">
                                @csrf @method('PATCH')
                                <select name="verification_status" class="form-select">
                                    @foreach (['unverified' => 'Belum diverifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak'] as $value => $label)
                                        <option value="{{ $value }}" @selected($record->verification_status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-outline-success" type="submit">Simpan</button>
                            </form>
                        </div>
                    </section>
                @endif
                @if ($canManage)
                    <section class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <h2 class="h5">Kelola metadata dan akses</h2>
                            <form method="POST" action="{{ route('institutions.documents.update', [$tenant->slug, $record->getKey()]) }}" class="vstack gap-3">
                                @csrf @method('PATCH')
                                <div><label for="title" class="form-label">Judul</label><input id="title" name="title" value="{{ $record->title }}" class="form-control" maxlength="255" required></div>
                                <div><label for="description" class="form-label">Keterangan</label><textarea id="description" name="description" class="form-control" rows="2" maxlength="5000">{{ $record->description }}</textarea></div>
                                <div><label for="category_id" class="form-label">Kategori</label><select id="category_id" name="category_id" class="form-select" required>@foreach ($categories as $category)<option value="{{ $category->getKey() }}" @selected($record->category_id === $category->getKey())>{{ $category->name }}</option>@endforeach</select></div>
                                <div><label for="unit_id" class="form-label">Unit</label><select id="unit_id" name="unit_id" class="form-select"><option value="">Seluruh institusi</option>@foreach ($units as $unit)<option value="{{ $unit->getKey() }}" @selected($record->unit_id === $unit->getKey())>{{ $unit->name }}</option>@endforeach</select></div>
                                <div><label for="academic_period_id" class="form-label">Periode</label><select id="academic_period_id" name="academic_period_id" class="form-select"><option value="">Tanpa periode</option>@foreach ($periods as $period)<option value="{{ $period->getKey() }}" @selected($record->academic_period_id === $period->getKey())>{{ $period->name }}</option>@endforeach</select></div>
                                <div><label for="access_level" class="form-label">Tingkat akses</label><select id="access_level" name="access_level" class="form-select">@foreach (\App\Models\DocumentCategory::ACCESS_LEVELS as $level)<option value="{{ $level }}" @selected($record->access_level === $level)>{{ ucfirst($level) }}</option>@endforeach</select></div>
                                <div><label for="retention_until" class="form-label">Retensi sampai</label><input id="retention_until" name="retention_until" type="date" min="{{ now()->toDateString() }}" value="{{ $record->retention_until?->toDateString() }}" class="form-control"></div>
                                <div><label for="status" class="form-label">Status dokumen</label><select id="status" name="status" class="form-select"><option value="active" @selected($record->status === 'active')>Aktif</option><option value="archived" @selected($record->status === 'archived')>Arsip</option></select></div>
                                <button class="btn btn-varez" type="submit">Simpan perubahan</button>
                            </form>
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </main>
@endsection
