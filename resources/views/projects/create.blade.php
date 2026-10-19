@extends('layouts.portal')

@section('title', 'Buat proyek akreditasi | ' . $tenant->name)

@section('content')
    <main class="container py-5">
        <a href="{{ route('institutions.projects.index', $tenant->slug) }}" class="small text-decoration-none">&larr; Kembali ke proyek</a>
        <div class="mt-3 mb-4">
            <p class="portal-eyebrow small fw-semibold text-uppercase mb-2">{{ $tenant->name }}</p>
            <h1 class="h2 fw-semibold">Buat proyek akreditasi</h1>
            <p class="portal-text-muted mb-0">Pilihan instrumen difilter untuk domain institusi dan versi terverifikasi yang berlaku.</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">Periksa kembali informasi berikut:</p>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('institutions.projects.store', $tenant->slug) }}" class="card border-0 shadow-sm rounded-4">
            @csrf
            <div class="card-body p-4 p-lg-5">
                <ol class="row list-unstyled g-3 mb-5" aria-label="Langkah pembuatan proyek">
                    <li class="col-sm-6 col-lg-4"><span class="badge text-bg-success me-2">1</span>Tipe institusi dan unit</li>
                    <li class="col-sm-6 col-lg-4"><span class="badge text-bg-success me-2">2</span>Lembaga, instrumen, versi</li>
                    <li class="col-sm-6 col-lg-4"><span class="badge text-bg-success me-2">3</span>Periode, tim, tenggat</li>
                    <li class="col-sm-6 col-lg-4"><span class="badge text-bg-success me-2">4</span>Konfirmasi</li>
                </ol>

                <section class="mb-4" aria-labelledby="institution-step">
                    <h2 id="institution-step" class="h5 fw-semibold mb-3">1. Institusi dan unit</h2>
                    <div class="mb-3">
                        <label class="form-label" for="domain_type">Tipe institusi</label>
                        <input class="form-control" id="domain_type" value="{{ $domainType === 'higher_education' ? 'Perguruan tinggi' : ($domainType === 'school' ? 'Sekolah' : 'Belum ditentukan') }}" readonly>
                        <div class="form-text">Tipe berasal dari profil tenant dan tidak dapat diganti melalui formulir proyek.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="unit_id">Unit organisasi <span class="text-muted">(opsional)</span></label>
                        <select class="form-select" id="unit_id" name="unit_id">
                            <option value="">Tingkat institusi</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->getKey() }}" @selected(old('unit_id') == $unit->getKey())>
                                    {{ $unit->name }}{{ $unit->code ? ' · ' . $unit->code : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </section>

                <section class="mb-4" aria-labelledby="instrument-step">
                    <h2 id="instrument-step" class="h5 fw-semibold mb-3">2. Lembaga dan instrumen</h2>
                    @if ($instrumentVersions->isEmpty())
                        <div class="alert alert-warning" role="status">
                            Belum ada versi instrumen resmi yang terverifikasi dan berlaku untuk tipe institusi ini.
                            Katalog internal/demo tidak ditampilkan sebagai instrumen resmi.
                        </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label" for="instrument_version_id">Instrumen dan versi</label>
                        <select class="form-select" id="instrument_version_id" name="instrument_version_id" required @disabled($instrumentVersions->isEmpty())>
                            <option value="">Pilih versi instrumen</option>
                            @foreach ($instrumentVersions as $version)
                                <option value="{{ $version->getKey() }}" @selected(old('instrument_version_id') == $version->getKey())>
                                    {{ $version->instrument->accreditationBody->name }} — {{ $version->instrument->name }}
                                    ({{ $version->instrument->code }} · {{ $version->version_label }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="title">Nama proyek</label>
                        <input class="form-control" id="title" name="title" value="{{ old('title') }}" maxlength="255" required>
                    </div>
                </section>

                <section class="mb-4" aria-labelledby="team-step">
                    <h2 id="team-step" class="h5 fw-semibold mb-3">3. Periode, tim, dan tenggat</h2>
                    <div class="mb-3">
                        <label class="form-label" for="period_label">Periode penilaian/pelaporan</label>
                        <input class="form-control" id="period_label" name="period_label" value="{{ old('period_label') }}" maxlength="100" placeholder="Contoh: periode yang disepakati institusi" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="owner_user_id">Pemilik proyek</label>
                        <select class="form-select" id="owner_user_id" name="owner_user_id" required>
                            @foreach ($members as $member)
                                <option value="{{ $member->getKey() }}" @selected(old('owner_user_id', auth()->id()) == $member->getKey())>{{ $member->name }} ({{ $member->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="team_user_ids">Anggota tim <span class="text-muted">(opsional)</span></label>
                        <select class="form-select" id="team_user_ids" name="team_user_ids[]" multiple size="{{ min(max($members->count(), 3), 6) }}">
                            @foreach ($members as $member)
                                <option value="{{ $member->getKey() }}" @selected(in_array($member->getKey(), old('team_user_ids', [])))>{{ $member->name }} ({{ $member->email }})</option>
                            @endforeach
                        </select>
                        <div class="form-text">Hanya anggota aktif dari tenant ini yang dapat dipilih. Gunakan Ctrl/Command untuk memilih lebih dari satu.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="target_date">Target tanggal selesai <span class="text-muted">(opsional)</span></label>
                        <input class="form-control" type="date" id="target_date" name="target_date" value="{{ old('target_date') }}" min="{{ today()->toDateString() }}">
                    </div>
                </section>

                <section class="mb-4" aria-labelledby="confirm-step">
                    <h2 id="confirm-step" class="h5 fw-semibold mb-3">4. Konfirmasi snapshot</h2>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmation" name="confirmation" value="1" @checked(old('confirmation')) required>
                        <label class="form-check-label" for="confirmation">
                            Saya mengonfirmasi proyek memakai snapshot versi instrumen terpilih. Perubahan katalog berikutnya tidak mengubah snapshot proyek ini.
                        </label>
                    </div>
                </section>

                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-varez" type="submit" @disabled($instrumentVersions->isEmpty())>Buat proyek</button>
                    <a class="btn btn-outline-secondary" href="{{ route('institutions.projects.index', $tenant->slug) }}">Batal</a>
                </div>
            </div>
        </form>
    </main>
@endsection
