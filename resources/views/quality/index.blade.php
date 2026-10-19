@extends('layouts.portal')

@section('title', 'Penjaminan mutu | ' . $tenant->name)

@section('content')
    <main class="container py-5">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <a href="{{ route('portal.home') }}" class="small text-decoration-none">&larr; Beranda</a>
                <p class="portal-eyebrow small fw-semibold text-uppercase mt-3 mb-2">{{ $tenant->name }}</p>
                <h1 class="h2 fw-semibold mb-1">Penjaminan mutu</h1>
                <p class="portal-text-muted mb-0">Standar, siklus PPEPP, AMI, temuan, tindakan korektif, dan bukti dalam satu ruang kerja.</p>
            </div>
            @if ($user->hasPermission('documents.view', $tenant))
                <a href="{{ route('institutions.documents.index', $tenant->slug) }}" class="btn btn-outline-success">Repositori bukti</a>
            @endif
        </div>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <strong>Periksa kembali isian berikut:</strong>
                <ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-secondary small">Aktivitas PPEPP overdue</div><div class="fs-3 fw-semibold">{{ $overdueActivities }}</div></div></div></div>
            <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-secondary small">Temuan overdue</div><div class="fs-3 fw-semibold">{{ $overdueFindings }}</div></div></div></div>
            <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="text-secondary small">Tindakan overdue</div><div class="fs-3 fw-semibold">{{ $overdueActions }}</div></div></div></div>
        </div>

        @if ($user->hasPermission('quality.policies.manage', $tenant))
            <section class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold">Kebijakan penutupan RTL/CAPA</h2>
                    <p class="small text-secondary">Terapkan kebijakan institusi; verifikasi efektivitas diwajibkan secara default. Verifikator independen dapat diwajibkan terpisah.</p>
                    <form method="post" action="{{ route('institutions.quality.policies.update', $tenant->slug) }}" class="d-flex flex-wrap align-items-center gap-3">
                        @csrf
                        <label class="form-check"><input class="form-check-input" type="checkbox" name="require_effectiveness_verification" value="1" @checked($policy->require_effectiveness_verification)><span class="form-check-label">Wajibkan verifikasi efektivitas</span></label>
                        <label class="form-check"><input class="form-check-input" type="checkbox" name="require_independent_verifier" value="1" @checked($policy->require_independent_verifier)><span class="form-check-label">Verifikator harus berbeda dari PIC</span></label>
                        <label class="form-check"><input class="form-check-input" type="checkbox" name="activity_completion_requires_approval" value="1" @checked($policy->activity_completion_requires_approval)><span class="form-check-label">Wajibkan persetujuan aktivitas PPEPP sebelum selesai</span></label>
                        <button class="btn btn-sm btn-outline-success">Simpan kebijakan</button>
                    </form>
                </div>
            </section>
        @endif

        <section class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between gap-3 mb-3">
                    <div><h2 class="h4 fw-semibold mb-1">Standar dan indikator mutu</h2><p class="text-secondary mb-0">Standar yang dipublikasikan tidak dapat diedit; buat versi baru untuk revisi.</p></div>
                </div>
                @if ($user->hasPermission('quality.standards.manage', $tenant))
                    <form method="post" action="{{ route('institutions.quality.standards.store', $tenant->slug) }}" class="row g-2 border rounded-3 p-3 mb-4">
                        @csrf
                        <div class="col-md-3"><label class="form-label">Kode</label><input class="form-control" name="code" required maxlength="80" value="{{ old('code') }}"></div>
                        <div class="col-md-5"><label class="form-label">Nama standar</label><input class="form-control" name="title" required maxlength="255" value="{{ old('title') }}"></div>
                        <div class="col-md-2"><label class="form-label">Versi</label><input class="form-control" type="number" name="version" min="1" value="{{ old('version', 1) }}" required></div>
                        <div class="col-md-2"><label class="form-label">Unit pemilik</label><select class="form-select" name="unit_id"><option value="">Institusi</option>@foreach ($units as $unit)<option value="{{ $unit->getKey() }}">{{ $unit->name }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label">Jenis standar</label><input class="form-control" name="standard_type" maxlength="80" placeholder="Sesuai kebijakan institusi"></div>
                        <div class="col-md-3"><label class="form-label">Berlaku mulai</label><input class="form-control" type="date" name="effective_from"></div>
                        <div class="col-md-3"><label class="form-label">Berlaku sampai</label><input class="form-control" type="date" name="effective_to"></div>
                        <div class="col-md-3"><label class="form-label">Penanggung jawab</label><select class="form-select" name="owner_user_id"><option value="">Belum ditentukan</option>@foreach ($members as $membership)<option value="{{ $membership->user->getKey() }}">{{ $membership->user->name }}</option>@endforeach</select></div>
                        <div class="col-12"><label class="form-label">Deskripsi</label><textarea class="form-control" name="description" rows="2" maxlength="10000"></textarea></div>
                        <div class="col-12"><button class="btn btn-varez">Buat draft standar</button></div>
                    </form>
                @endif

                <div class="d-grid gap-3">
                    @forelse ($standards as $standard)
                        <article class="border rounded-3 p-3">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <div>
                                    <h3 class="h6 fw-semibold mb-1">{{ $standard->code }} — {{ $standard->title }} <span class="text-secondary">v{{ $standard->version }}</span></h3>
                                    <div class="small text-secondary">{{ $standard->unit?->name ?? 'Seluruh institusi' }} · {{ $standard->status }} · {{ $standard->effective_from?->format('Y-m-d') ?? 'Tanggal berlaku belum ditentukan' }}</div>
                                </div>
                                @if ($user->hasPermission('quality.standards.manage', $tenant) && $standard->status === 'draft' && $standard->indicators->isNotEmpty())
                                    <form method="post" action="{{ route('institutions.quality.standards.status', [$tenant->slug, $standard->getKey()]) }}">
                                        @csrf @method('PATCH')<input type="hidden" name="status" value="published">
                                        <button class="btn btn-sm btn-outline-success">Publikasikan</button>
                                    </form>
                                @elseif ($user->hasPermission('quality.standards.manage', $tenant) && $standard->status === 'published')
                                    <form method="post" action="{{ route('institutions.quality.standards.status', [$tenant->slug, $standard->getKey()]) }}">
                                        @csrf @method('PATCH')<input type="hidden" name="status" value="retired">
                                        <button class="btn btn-sm btn-outline-secondary">Pensiunkan versi</button>
                                    </form>
                                @endif
                            </div>
                            @if ($standard->description)<p class="small mt-2 mb-2">{{ $standard->description }}</p>@endif
                            @if ($user->hasPermission('quality.standards.manage', $tenant) && $standard->status === 'draft')
                                <form method="post" action="{{ route('institutions.quality.standards.update', [$tenant->slug, $standard->getKey()]) }}" class="row g-2 border-top pt-3 mt-3">
                                    @csrf @method('PATCH')
                                    <div class="col-md-2"><label class="form-label small">Kode</label><input class="form-control form-control-sm" name="code" required maxlength="80" value="{{ $standard->code }}"></div>
                                    <div class="col-md-4"><label class="form-label small">Nama</label><input class="form-control form-control-sm" name="title" required maxlength="255" value="{{ $standard->title }}"></div>
                                    <div class="col-md-2"><label class="form-label small">Versi</label><input class="form-control form-control-sm" type="number" name="version" min="1" value="{{ $standard->version }}" required></div>
                                    <div class="col-md-2"><label class="form-label small">Berlaku dari</label><input class="form-control form-control-sm" type="date" name="effective_from" value="{{ $standard->effective_from?->format('Y-m-d') }}"></div>
                                    <div class="col-md-2"><label class="form-label small">Berlaku sampai</label><input class="form-control form-control-sm" type="date" name="effective_to" value="{{ $standard->effective_to?->format('Y-m-d') }}"></div>
                                    <div class="col-md-3"><label class="form-label small">Jenis</label><input class="form-control form-control-sm" name="standard_type" maxlength="80" value="{{ $standard->standard_type }}"></div>
                                    <div class="col-md-3"><label class="form-label small">Unit</label><select class="form-select form-select-sm" name="unit_id"><option value="">Institusi</option>@foreach($units as $unit)<option value="{{ $unit->getKey() }}" @selected($standard->unit_id === $unit->getKey())>{{ $unit->name }}</option>@endforeach</select></div>
                                    <div class="col-md-3"><label class="form-label small">PIC</label><select class="form-select form-select-sm" name="owner_user_id"><option value="">Belum ditentukan</option>@foreach($members as $membership)<option value="{{ $membership->user->getKey() }}" @selected($standard->owner_user_id === $membership->user->getKey())>{{ $membership->user->name }}</option>@endforeach</select></div>
                                    <div class="col-md-3"><label class="form-label small">Deskripsi</label><input class="form-control form-control-sm" name="description" maxlength="10000" value="{{ $standard->description }}"></div>
                                    <div class="col-12"><button class="btn btn-sm btn-outline-secondary">Simpan draft standar</button></div>
                                </form>
                            @endif
                            <ul class="small mb-2">
                                @forelse ($standard->indicators as $indicator)
                                    <li>{{ $indicator->code }} — {{ $indicator->name }}
                                        @if ($indicator->target_json)<span class="text-secondary">· target {{ collect($indicator->target_json)->filter()->implode(' / ') }}</span>@endif
                                        @if ($indicator->frequency)<span class="text-secondary">· {{ $indicator->frequency }}</span>@endif
                                        @if ($user->hasPermission('quality.standards.manage', $tenant) && $standard->status === 'draft')
                                            <form method="post" action="{{ route('institutions.quality.indicators.update', [$tenant->slug, $standard->getKey(), $indicator->getKey()]) }}" class="row g-2 mt-1">
                                                @csrf @method('PATCH')
                                                <div class="col-md-2"><input class="form-control form-control-sm" name="code" required maxlength="80" value="{{ $indicator->code }}" aria-label="Kode indikator"></div>
                                                <div class="col-md-3"><input class="form-control form-control-sm" name="name" required maxlength="255" value="{{ $indicator->name }}" aria-label="Nama indikator"></div>
                                                <div class="col-md-2"><input class="form-control form-control-sm" name="target_json[value]" maxlength="255" value="{{ data_get($indicator->target_json, 'value') }}" placeholder="Target" aria-label="Target indikator"></div>
                                                <div class="col-md-2"><input class="form-control form-control-sm" name="frequency" maxlength="80" value="{{ $indicator->frequency }}" placeholder="Frekuensi"></div>
                                                <div class="col-md-2"><input class="form-control form-control-sm" name="measurement_method" maxlength="5000" value="{{ $indicator->measurement_method }}" placeholder="Metode"></div>
                                                <div class="col-md-1"><button class="btn btn-sm btn-outline-secondary w-100">Simpan</button></div>
                                                <input type="hidden" name="sort_order" value="{{ $indicator->sort_order }}">
                                            </form>
                                        @endif
                                    </li>
                                @empty
                                    <li class="text-secondary">Belum ada indikator.</li>
                                @endforelse
                            </ul>
                            @if ($user->hasPermission('quality.standards.manage', $tenant) && $standard->status === 'draft')
                                <form method="post" action="{{ route('institutions.quality.indicators.store', [$tenant->slug, $standard->getKey()]) }}" class="row g-2 mt-3">
                                    @csrf
                                    <div class="col-md-2"><input class="form-control form-control-sm" name="code" placeholder="Kode indikator" required maxlength="80"></div>
                                    <div class="col-md-3"><input class="form-control form-control-sm" name="name" placeholder="Nama indikator" required maxlength="255"></div>
                                    <div class="col-md-2"><input class="form-control form-control-sm" name="frequency" placeholder="Frekuensi"></div>
                                    <div class="col-md-3"><input class="form-control form-control-sm" name="measurement_method" placeholder="Metode pengukuran"></div>
                                    <div class="col-md-1"><input class="form-control form-control-sm" type="number" name="sort_order" min="0" value="0" aria-label="Urutan"></div>
                                    <div class="col-md-1"><button class="btn btn-sm btn-outline-success w-100">Tambah</button></div>
                                </form>
                            @endif
                        </article>
                    @empty
                        <p class="text-secondary mb-0">Belum ada standar mutu untuk ruang lingkup yang dapat Anda akses.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h2 class="h4 fw-semibold">Aktivitas PPEPP</h2>
                <p class="text-secondary">Lima tahap dapat diatur sesuai kebijakan mutu institusi.</p>
                @if ($user->hasPermission('quality.activities.manage', $tenant))
                    <form method="post" action="{{ route('institutions.quality.activities.store', $tenant->slug) }}" class="row g-2 border rounded-3 p-3 mb-4">
                        @csrf
                        <div class="col-md-4"><label class="form-label">Standar terbit</label><select class="form-select" name="standard_id" required><option value="">Pilih standar</option>@foreach ($standards->where('status', 'published') as $standard)<option value="{{ $standard->getKey() }}">{{ $standard->code }} — {{ $standard->title }} v{{ $standard->version }}</option>@endforeach</select></div>
                        <div class="col-md-2"><label class="form-label">Tahap</label><select class="form-select" name="stage" required>@foreach (\App\Models\PpeppActivity::STAGES as $stage)<option value="{{ $stage }}">{{ ucfirst($stage) }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label">Judul kegiatan</label><input class="form-control" name="title" required maxlength="255"></div>
                        <div class="col-md-3"><label class="form-label">Indikator terkait (opsional)</label><select class="form-select" name="indicator_id"><option value="">Tidak ditentukan</option>@foreach ($standards->where('status', 'published') as $standard)@foreach ($standard->indicators as $indicator)<option value="{{ $indicator->getKey() }}">{{ $standard->code }} / {{ $indicator->code }} — {{ $indicator->name }}</option>@endforeach @endforeach</select></div>
                        <div class="col-md-3"><label class="form-label">Unit</label><select class="form-select" name="unit_id"><option value="">Institusi</option>@foreach ($units as $unit)<option value="{{ $unit->getKey() }}">{{ $unit->name }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label">Periode</label><select class="form-select" name="academic_period_id"><option value="">Tidak ditentukan</option>@foreach ($periods as $period)<option value="{{ $period->getKey() }}">{{ $period->name }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label">PIC</label><select class="form-select" name="owner_user_id"><option value="">Belum ditentukan</option>@foreach ($members as $membership)<option value="{{ $membership->user->getKey() }}">{{ $membership->user->name }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label">Tenggat</label><input class="form-control" type="date" name="due_date"></div>
                        <div class="col-12"><label class="form-label">Deskripsi / kriteria selesai</label><textarea class="form-control" name="description" rows="2" maxlength="10000"></textarea></div>
                        <div class="col-12"><button class="btn btn-varez">Buat aktivitas</button></div>
                    </form>
                @endif

                <div class="d-grid gap-3">
                    @forelse ($activities as $activity)
                        <article class="border rounded-3 p-3">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <div><h3 class="h6 fw-semibold mb-1">{{ ucfirst($activity->stage) }} — {{ $activity->title }}</h3><div class="small text-secondary">{{ $activity->standard->code }} · {{ $activity->unit?->name ?? 'Institusi' }} · PIC {{ $activity->owner?->name ?? 'belum ditentukan' }} · Tenggat {{ $activity->due_date?->format('Y-m-d') ?? '—' }} · {{ str_replace('_', ' ', $activity->status) }}@if($activity->approved_by) · disetujui {{ $activity->approver?->name }}@endif</div></div>
                                @if ($user->hasPermission('quality.activities.manage', $tenant) && in_array($activity->status, ['planned', 'in_progress'], true))
                                    <form method="post" action="{{ route('institutions.quality.activities.status', [$tenant->slug, $activity->getKey()]) }}" class="d-flex gap-2">
                                        @csrf @method('PATCH')
                                        @if ($activity->status === 'planned')<button class="btn btn-sm btn-outline-primary" name="status" value="in_progress">Mulai</button>@endif
                                        @if ($activity->status === 'in_progress')<button class="btn btn-sm btn-outline-success" name="status" value="{{ $policy->activity_completion_requires_approval ? 'pending_approval' : 'completed' }}">{{ $policy->activity_completion_requires_approval ? 'Ajukan persetujuan' : 'Selesai' }}</button>@endif
                                        <button class="btn btn-sm btn-outline-secondary" name="status" value="cancelled">Batalkan</button>
                                    </form>
                                @endif
                            </div>
                            @if ($activity->description)<p class="small mt-2 mb-2">{{ $activity->description }}</p>@endif
                            @if ($activity->status === 'pending_approval' && $user->hasPermission('quality.activities.approve', $tenant))
                                <form method="post" action="{{ route('institutions.quality.activities.approval', [$tenant->slug, $activity->getKey()]) }}" class="row g-2 mt-2">
                                    @csrf
                                    <div class="col-md-3"><select class="form-select form-select-sm" name="decision" required><option value="approve">Setujui</option><option value="return">Kembalikan untuk perbaikan</option></select></div>
                                    <div class="col-md-7"><input class="form-control form-control-sm" name="approval_notes" required maxlength="5000" placeholder="Catatan persetujuan/revisi"></div>
                                    <div class="col-md-2"><button class="btn btn-sm btn-outline-success w-100">Kirim keputusan</button></div>
                                </form>
                            @endif
                            @if ($activity->evidence->isNotEmpty())
                                <div class="small mt-2"><strong>Bukti versi tertaut:</strong> @foreach ($activity->evidence as $evidence)<a href="{{ route('institutions.documents.show', [$tenant->slug, $evidence->document_id]) }}">{{ $evidence->document?->title ?? 'Dokumen' }} v{{ $evidence->documentVersion?->version_number }}</a>@if (!$loop->last), @endif @endforeach</div>
                            @endif
                            @if ($user->hasPermission('quality.activities.manage', $tenant) && $user->hasPermission('documents.view', $tenant) && $documents->isNotEmpty())
                                <form method="post" action="{{ route('institutions.quality.activities.evidence.store', [$tenant->slug, $activity->getKey()]) }}" class="row g-2 mt-2">
                                    @csrf
                                    <div class="col-md-9"><select class="form-select form-select-sm" name="evidence_version" required><option value="">Pilih dokumen dan versi</option>@foreach ($documents as $document)@foreach ($document->versions as $version)<option value="{{ $document->getKey() }}:{{ $version->getKey() }}">{{ $document->title }} · v{{ $version->version_number }}</option>@endforeach @endforeach</select></div>
                                    <div class="col-md-2"><input class="form-control form-control-sm" name="note" maxlength="2000" placeholder="Catatan (opsional)"></div>
                                    <div class="col-md-1"><button class="btn btn-sm btn-outline-success w-100">Tautkan</button></div>
                                </form>
                            @endif
                        </article>
                    @empty
                        <p class="text-secondary mb-0">Belum ada aktivitas PPEPP.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h2 class="h4 fw-semibold">Audit Mutu Internal</h2>
                <p class="text-secondary">Jadwal, ruang lingkup, auditor, checklist, bukti, dan temuan disimpan dalam tenant institusi.</p>
                @if ($user->hasPermission('quality.audits.manage', $tenant))
                    <form method="post" action="{{ route('institutions.quality.audits.store', $tenant->slug) }}" class="row g-2 border rounded-3 p-3 mb-4">
                        @csrf
                        <div class="col-md-4"><label class="form-label">Nama audit</label><input class="form-control" name="title" required maxlength="255"></div>
                        <div class="col-md-4"><label class="form-label">Ruang lingkup</label><input class="form-control" name="scope" required maxlength="5000"></div>
                        <div class="col-md-4"><label class="form-label">Unit</label><select class="form-select" name="unit_id"><option value="">Institusi</option>@foreach ($units as $unit)<option value="{{ $unit->getKey() }}">{{ $unit->name }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label">Periode</label><select class="form-select" name="academic_period_id"><option value="">Tidak ditentukan</option>@foreach ($periods as $period)<option value="{{ $period->getKey() }}">{{ $period->name }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label">Ketua auditor</label><select class="form-select" name="lead_auditor_id" required><option value="">Pilih</option>@foreach ($members as $membership)<option value="{{ $membership->user->getKey() }}">{{ $membership->user->name }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label">Auditor lain</label><select class="form-select" name="auditor_ids[]" multiple size="1">@foreach ($members as $membership)<option value="{{ $membership->user->getKey() }}">{{ $membership->user->name }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label">Mulai (waktu lokal {{ $tenant->timezone }})</label><input class="form-control" type="datetime-local" name="starts_at" required></div>
                        <div class="col-md-3"><label class="form-label">Selesai (opsional)</label><input class="form-control" type="datetime-local" name="ends_at"></div>
                        <div class="col-12"><button class="btn btn-varez">Rencanakan AMI</button></div>
                    </form>
                @endif

                <div class="d-grid gap-3">
                    @forelse ($audits as $audit)
                        <article class="border rounded-3 p-3">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <div><h3 class="h6 fw-semibold mb-1">{{ $audit->title }}</h3><div class="small text-secondary">{{ $audit->unit?->name ?? 'Institusi' }} · {{ $audit->period?->name ?? 'Periode tidak ditentukan' }} · Ketua {{ $audit->leadAuditor?->name }} · {{ $audit->starts_at->timezone($tenant->timezone)->format('Y-m-d H:i') }} {{ $tenant->timezone }} · {{ $audit->status }}</div><div class="small mt-1">{{ $audit->scope_json['description'] ?? '' }}</div></div>
                                @if (($user->hasPermission('quality.audits.manage', $tenant) || ($user->hasPermission('quality.audits.perform', $tenant) && $audit->auditors->contains('id', $user->getKey()))) && in_array($audit->status, ['planned', 'in_progress'], true))
                                    <form method="post" action="{{ route('institutions.quality.audits.status', [$tenant->slug, $audit->getKey()]) }}" class="d-flex gap-2">@csrf @method('PATCH')@if($audit->status === 'planned')<button class="btn btn-sm btn-outline-primary" name="status" value="in_progress">Mulai audit</button>@endif<button class="btn btn-sm btn-outline-success" name="status" value="completed">Selesaikan audit</button><button class="btn btn-sm btn-outline-secondary" name="status" value="cancelled">Batalkan</button></form>
                                @endif
                            </div>
                            @if ($audit->auditors->isNotEmpty())<div class="small text-secondary mt-1">Auditor: {{ $audit->auditors->pluck('name')->join(', ') }}</div>@endif
                            <div class="mt-3">
                                <h4 class="small fw-semibold">Checklist</h4>
                                @foreach ($audit->checklistItems as $item)
                                    <div class="border-top py-2">
                                        <div class="small">{{ $item->question }}</div>
                                        @if ($user->hasPermission('quality.audits.manage', $tenant) || ($user->hasPermission('quality.audits.perform', $tenant) && $audit->auditors->contains('id', $user->getKey())))
                                            <form method="post" action="{{ route('institutions.quality.checklist.update', [$tenant->slug, $item->getKey()]) }}" class="d-flex gap-2 mt-1">@csrf @method('PATCH')<select class="form-select form-select-sm w-auto" name="status">@foreach(\App\Models\AuditChecklistItem::STATUSES as $status)<option value="{{ $status }}" @selected($item->status === $status)>{{ str_replace('_', ' ', $status) }}</option>@endforeach</select><input class="form-control form-control-sm" name="notes" value="{{ $item->notes }}" placeholder="Catatan"><button class="btn btn-sm btn-outline-success">Simpan</button></form>
                                        @else<div class="small text-secondary">{{ str_replace('_', ' ', $item->status) }} {{ $item->notes }}</div>@endif
                                    </div>
                                @endforeach
                                @if ($user->hasPermission('quality.audits.manage', $tenant) && in_array($audit->status, ['planned', 'in_progress'], true))
                                    <form method="post" action="{{ route('institutions.quality.checklist.store', [$tenant->slug, $audit->getKey()]) }}" class="row g-2 mt-2">@csrf<div class="col-md-7"><input class="form-control form-control-sm" name="question" required maxlength="5000" placeholder="Butir checklist"></div><div class="col-md-3"><select class="form-select form-select-sm" name="standard_id"><option value="">Standar (opsional)</option>@foreach($standards as $standard)<option value="{{ $standard->getKey() }}">{{ $standard->code }} · {{ $standard->title }}</option>@endforeach</select></div><div class="col-md-1"><input class="form-control form-control-sm" name="sort_order" type="number" min="0" value="0" aria-label="Urutan"></div><div class="col-md-1"><button class="btn btn-sm btn-outline-success w-100">Tambah</button></div></form>
                                @endif
                            </div>
                            <div class="mt-3">
                                <h4 class="small fw-semibold">Temuan</h4>
                                @foreach ($audit->findings as $finding)
                                    <div class="border-top py-2">
                                        <div class="d-flex flex-wrap justify-content-between gap-2"><div><strong class="small">{{ $finding->finding_code ?: 'Temuan' }} · {{ ucfirst($finding->severity) }}</strong><span class="small text-secondary"> · {{ str_replace('_', ' ', $finding->status) }} · PIC {{ $finding->owner?->name ?? 'belum ditentukan' }} · Tenggat {{ $finding->due_date?->format('Y-m-d') ?? '—' }}</span><p class="small mb-1">{{ $finding->description }}</p>@if($finding->root_cause)<div class="small"><strong>Akar masalah:</strong> {{ $finding->root_cause }}</div>@endif @if($finding->recommendation)<div class="small"><strong>Rekomendasi:</strong> {{ $finding->recommendation }}</div>@endif</div></div>
                                        @if ($finding->evidence->isNotEmpty())<div class="small mt-1">Bukti: @foreach($finding->evidence as $evidence)<a href="{{ route('institutions.documents.show', [$tenant->slug, $evidence->document_id]) }}">{{ $evidence->document?->title }} v{{ $evidence->documentVersion?->version_number }}</a>@if(!$loop->last), @endif @endforeach</div>@endif
                                        @foreach ($finding->correctiveActions as $action)
                                            <div class="bg-light rounded-2 p-2 mt-2"><div class="small fw-semibold">RTL/CAPA — {{ str_replace('_', ' ', $action->status) }}</div><div class="small">{{ $action->action_plan }}</div><div class="small text-secondary">PIC {{ $action->owner?->name }} · tenggat {{ $action->due_date->format('Y-m-d') }}@if($action->effectiveness_status) · efektivitas {{ $action->effectiveness_status }}@endif</div>
                                                @if ($user->hasPermission('quality.actions.manage', $tenant) && in_array($action->status, ['planned', 'in_progress'], true))
                                                    <form method="post" action="{{ route('institutions.quality.actions.status', [$tenant->slug, $action->getKey()]) }}" class="d-flex gap-2 mt-2">@csrf @method('PATCH')@if($action->status === 'planned')<button class="btn btn-sm btn-outline-primary" name="status" value="in_progress">Mulai tindakan</button>@endif @if($action->status === 'in_progress')<button class="btn btn-sm btn-outline-warning" name="status" value="pending_verification">Ajukan verifikasi</button>@if(!$policy->require_effectiveness_verification && !$policy->require_independent_verifier)<button class="btn btn-sm btn-outline-success" name="status" value="closed">Tutup sesuai kebijakan</button>@endif @endif</form>
                                                @endif
                                                @if ($user->hasPermission('quality.actions.verify', $tenant) && $action->status === 'pending_verification')
                                                    <form method="post" action="{{ route('institutions.quality.actions.verify', [$tenant->slug, $action->getKey()]) }}" class="row g-2 mt-2">@csrf<div class="col-md-3"><select class="form-select form-select-sm" name="effectiveness_status" required><option value="effective">Efektif</option><option value="ineffective">Belum efektif</option></select></div><div class="col-md-7"><input class="form-control form-control-sm" name="verification_notes" required maxlength="10000" placeholder="Catatan verifikasi"></div><div class="col-md-2"><button class="btn btn-sm btn-outline-success w-100">Verifikasi</button></div></form>
                                                @endif
                                            </div>
                                        @endforeach
                                        @if ($user->hasPermission('quality.findings.manage', $tenant) && $user->hasPermission('documents.view', $tenant) && $documents->isNotEmpty())
                                            <form method="post" action="{{ route('institutions.quality.findings.evidence.store', [$tenant->slug, $finding->getKey()]) }}" class="row g-2 mt-2">@csrf<div class="col-md-10"><select class="form-select form-select-sm" name="evidence_version" required><option value="">Pilih dokumen dan versi</option>@foreach($documents as $document)@foreach($document->versions as $version)<option value="{{ $document->getKey() }}:{{ $version->getKey() }}">{{ $document->title }} · v{{ $version->version_number }}</option>@endforeach @endforeach</select></div><div class="col-md-2"><button class="btn btn-sm btn-outline-success w-100">Tautkan bukti</button></div></form>
                                        @endif
                                        @if ($user->hasPermission('quality.actions.manage', $tenant) && $finding->status !== 'closed')
                                            <form method="post" action="{{ route('institutions.quality.actions.store', [$tenant->slug, $finding->getKey()]) }}" class="row g-2 mt-2">@csrf<div class="col-md-5"><input class="form-control form-control-sm" name="action_plan" required maxlength="10000" placeholder="Rencana perbaikan"></div><div class="col-md-3"><select class="form-select form-select-sm" name="owner_user_id" required><option value="">PIC tindakan</option>@foreach($members as $membership)<option value="{{ $membership->user->getKey() }}">{{ $membership->user->name }}</option>@endforeach</select></div><div class="col-md-2"><input class="form-control form-control-sm" type="date" name="due_date" required></div><div class="col-md-2"><button class="btn btn-sm btn-outline-success w-100">Buat RTL/CAPA</button></div></form>
                                        @endif
                                    </div>
                                @endforeach
                                @if ($user->hasPermission('quality.findings.manage', $tenant) && in_array($audit->status, ['planned', 'in_progress'], true))
                                    <form method="post" action="{{ route('institutions.quality.findings.store', [$tenant->slug, $audit->getKey()]) }}" class="row g-2 mt-2">@csrf<div class="col-md-2"><input class="form-control form-control-sm" name="finding_code" maxlength="80" placeholder="Kode"></div><div class="col-md-2"><select class="form-select form-select-sm" name="severity" required>@foreach(\App\Models\AuditFinding::SEVERITIES as $severity)<option value="{{ $severity }}">{{ ucfirst($severity) }}</option>@endforeach</select></div><div class="col-md-3"><select class="form-select form-select-sm" name="checklist_item_id"><option value="">Checklist (opsional)</option>@foreach($audit->checklistItems as $item)<option value="{{ $item->getKey() }}">{{ $item->question }}</option>@endforeach</select></div><div class="col-md-3"><select class="form-select form-select-sm" name="owner_user_id"><option value="">PIC temuan</option>@foreach($members as $membership)<option value="{{ $membership->user->getKey() }}">{{ $membership->user->name }}</option>@endforeach</select></div><div class="col-md-2"><input class="form-control form-control-sm" type="date" name="due_date"></div><div class="col-12"><textarea class="form-control form-control-sm" name="description" required maxlength="10000" rows="2" placeholder="Deskripsi temuan"></textarea></div><div class="col-md-6"><textarea class="form-control form-control-sm" name="root_cause" maxlength="10000" rows="2" placeholder="Akar masalah"></textarea></div><div class="col-md-6"><textarea class="form-control form-control-sm" name="recommendation" maxlength="10000" rows="2" placeholder="Rekomendasi"></textarea></div><div class="col-12"><button class="btn btn-sm btn-outline-danger">Catat temuan</button></div></form>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="text-secondary mb-0">Belum ada rencana AMI.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <h2 class="h4 fw-semibold">Histori perubahan</h2>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Waktu ({{ $tenant->timezone }})</th><th>Peristiwa</th><th>Objek</th><th>Pengguna</th><th>Perubahan status</th></tr></thead><tbody>
                    @forelse($histories as $history)
                        <tr><td>{{ $history->created_at->timezone($tenant->timezone)->format('Y-m-d H:i') }}</td><td>{{ $history->event }}</td><td>{{ $history->entity_type }} #{{ $history->entity_id }}</td><td>{{ $history->actor?->name ?? 'Sistem' }}</td><td>{{ data_get($history->changes_json, 'before.status', '—') }} → {{ data_get($history->changes_json, 'after.status', '—') }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">Belum ada perubahan tercatat.</td></tr>
                    @endforelse
                </tbody></table></div>
            </div>
        </section>
    </main>
@endsection
