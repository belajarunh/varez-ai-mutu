@extends('layouts.portal')

@section('title', 'Pemetaan impor | ' . $tenant->name)

@section('content')
    <main class="container py-5">
        <a href="{{ route('institutions.performance.imports.create', $tenant->slug) }}" class="small text-decoration-none">&larr; Unggah ulang</a>
        <div class="mt-3 mb-4">
            <p class="portal-eyebrow small fw-semibold text-uppercase mb-2">{{ $tenant->name }}</p>
            <h1 class="h2 fw-semibold">Pemetaan kolom</h1>
            <p class="portal-text-muted mb-0">{{ $record->original_filename }} · {{ $record->schema->name }} v{{ $record->schema->schema_version }} · {{ $record->period->name }}</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" action="{{ route('institutions.performance.imports.validate', [$tenant->slug, $record->getKey()]) }}">
            @csrf
            <section class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">Kolom file ke field schema</h2>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Field schema</th><th>Tipe</th><th>Kolom file</th></tr></thead>
                            <tbody>
                            @foreach ($record->schema->fields_json as $field)
                                <tr>
                                    <th scope="row">{{ $field['label'] }} <code>{{ $field['key'] }}</code> @if ($field['required'])<span class="text-danger" aria-label="wajib">*</span>@endif</th>
                                    <td>{{ $field['type'] }} @if ($field['unique'])· unique @endif</td>
                                    <td>
                                        <label class="visually-hidden" for="mapping-{{ $field['key'] }}">Kolom untuk {{ $field['label'] }}</label>
                                        <select id="mapping-{{ $field['key'] }}" name="mapping[{{ $field['key'] }}]" class="form-select" @required($field['required'])>
                                            <option value="">— Tidak dipetakan —</option>
                                            @foreach ($headers as $header)
                                                <option value="{{ $header }}" @selected(old("mapping.{$field['key']}") === $header)>{{ $header }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 fw-semibold mb-3">Tujuan impor</h2>
                    <div class="mb-3">
                        <label class="form-label" for="strategy">Strategi</label>
                        <select id="strategy" name="strategy" class="form-select" required>
                            <option value="append" @selected(old('strategy', 'append') === 'append')>Append — tambahkan records, tanpa mengubah data lama</option>
                            <option value="replace" @selected(old('strategy') === 'replace')>Replace — ganti seluruh records dataset tujuan</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="target_dataset_id">Dataset tujuan</label>
                        <select id="target_dataset_id" name="target_dataset_id" class="form-select">
                            <option value="">Buat dataset baru (hanya untuk append)</option>
                            @foreach ($datasets as $dataset)
                                <option value="{{ $dataset->getKey() }}" @selected(old('target_dataset_id') == $dataset->getKey())>
                                    Dataset #{{ $dataset->getKey() }} · {{ number_format($dataset->records_count) }} records · {{ $dataset->created_at->format('Y-m-d') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check mb-4">
                        <input class="form-check-input" id="replace-confirmation" name="replace_confirmation" type="checkbox" value="1" @checked(old('replace_confirmation'))>
                        <label class="form-check-label" for="replace-confirmation">Saya memahami strategi replace menghapus isi dataset tujuan dan menggantinya dengan records valid dari batch ini.</label>
                    </div>
                    <p class="small portal-text-muted">Replace hanya tersedia untuk dataset dengan periode, unit, jenis, dan versi schema yang sama. Semua hasil pratinjau tetap memerlukan konfirmasi sebelum proses.</p>
                    <button class="btn btn-varez" type="submit">Validasi dan buat pratinjau</button>
                </div>
            </section>
        </form>
    </main>
@endsection
