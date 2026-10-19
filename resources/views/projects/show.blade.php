@extends('layouts.portal')

@section('title', $record->title . ' | ' . $tenant->name)

@section('content')
    <main class="container py-5">
        <a href="{{ route('institutions.projects.index', $tenant->slug) }}" class="small text-decoration-none">&larr; Semua proyek</a>
        @if (session('status'))
            <div class="alert alert-success mt-3" role="status">{{ session('status') }}</div>
        @endif
        <div class="d-flex flex-wrap justify-content-between gap-3 mt-3 mb-4">
            <div>
                <p class="portal-eyebrow small fw-semibold text-uppercase mb-2">{{ $tenant->name }} · {{ $record->period_label }}</p>
                <h1 class="h2 fw-semibold">{{ $record->title }}</h1>
                <p class="portal-text-muted mb-0">
                    {{ $record->instrumentVersion->instrument->accreditationBody->name }}
                    · {{ $record->instrumentVersion->instrument->name }}
                    · versi {{ $record->instrumentVersion->version_label }}
                </p>
            </div>
            <span class="badge text-bg-light align-self-start">{{ str_replace('_', ' ', $record->status) }}</span>
        </div>

        <section class="card border-0 shadow-sm rounded-4 mb-4" aria-labelledby="project-progress">
            <div class="card-body p-4">
                <h2 id="project-progress" class="h5 fw-semibold">Progres indikator</h2>
                <div class="progress" role="progressbar" aria-label="Indikator disetujui" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-success" style="width: {{ $progress }}%">{{ $progress }}%</div>
                </div>
                <p class="small portal-text-muted mt-2 mb-0">{{ $record->indicators->where('status', 'approved')->count() }} dari {{ $record->indicators->count() }} indikator berstatus disetujui. Ini adalah progres internal, bukan prediksi hasil akreditasi.</p>
            </div>
        </section>

        <section aria-labelledby="indicator-list">
            <h2 id="indicator-list" class="h5 fw-semibold mb-3">Indikator dalam snapshot</h2>
            @forelse ($record->indicators as $indicator)
                <article class="card border-0 shadow-sm rounded-4 mb-2">
                    <div class="card-body p-3 p-md-4 d-flex flex-wrap justify-content-between gap-3">
                        <div>
                            <span class="small text-muted">{{ $indicator->snapshot_node_json['code'] }}</span>
                            <h3 class="h6 fw-semibold mb-1">{{ $indicator->snapshot_node_json['title'] }}</h3>
                            @if ($indicator->snapshot_node_json['guidance_text'])
                                <p class="small portal-text-muted mb-0">{{ $indicator->snapshot_node_json['guidance_text'] }}</p>
                            @endif
                        </div>
                        <div class="text-md-end">
                            <span class="badge text-bg-light">{{ str_replace('_', ' ', $indicator->status) }}</span>
                            @if ($indicator->owner)
                                <p class="small portal-text-muted mt-2 mb-0">{{ $indicator->owner->name }}</p>
                            @endif
                            @if ($indicator->due_date)
                                <p class="small portal-text-muted mb-0">Tenggat {{ $indicator->due_date->format('d-m-Y') }}</p>
                            @endif
                        </div>
                    </div>
                    @if (auth()->user()->hasPermission('projects.manage', $tenant))
                        <div class="card-footer bg-white border-0 pt-0 px-3 px-md-4">
                            <form method="post" action="{{ route('institutions.projects.indicators.update', [$tenant->slug, $record->getKey(), $indicator->getKey()]) }}" class="row g-2 align-items-end">
                                @csrf
                                @method('PATCH')
                                <div class="col-sm-4">
                                    <label class="form-label small" for="indicator-status-{{ $indicator->getKey() }}">Status</label>
                                    <select class="form-select form-select-sm" id="indicator-status-{{ $indicator->getKey() }}" name="status" required>
                                        @foreach (\App\Models\ProjectIndicator::STATUSES as $status)
                                            <option value="{{ $status }}" @selected($indicator->status === $status)>{{ str_replace('_', ' ', $status) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <label class="form-label small" for="indicator-owner-{{ $indicator->getKey() }}">PIC</label>
                                    <select class="form-select form-select-sm" id="indicator-owner-{{ $indicator->getKey() }}" name="owner_user_id">
                                        <option value="">Belum ditentukan</option>
                                        @foreach ($record->members as $member)
                                            <option value="{{ $member->getKey() }}" @selected($indicator->owner_user_id === $member->getKey())>{{ $member->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-3">
                                    <label class="form-label small" for="indicator-due-{{ $indicator->getKey() }}">Tenggat</label>
                                    <input class="form-control form-control-sm" type="date" id="indicator-due-{{ $indicator->getKey() }}" name="due_date" value="{{ $indicator->due_date?->toDateString() }}">
                                </div>
                                <div class="col-sm-1">
                                    <button class="btn btn-sm btn-outline-success w-100" type="submit" aria-label="Simpan indikator {{ $indicator->snapshot_node_json['code'] }}">
                                        <i class="bi bi-check2" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                </article>
            @empty
                <div class="alert alert-info">Snapshot versi ini belum memiliki node indikator, butir, atau subbutir.</div>
            @endforelse
        </section>
    </main>
@endsection
