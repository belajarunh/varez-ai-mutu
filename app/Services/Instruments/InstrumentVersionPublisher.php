<?php

namespace App\Services\Instruments;

use App\Models\AccreditationBody;
use App\Models\AuditLog;
use App\Models\Instrument;
use App\Models\InstrumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InstrumentVersionPublisher
{
    public function publish(InstrumentVersion $version, User $actor): InstrumentVersion
    {
        return DB::transaction(function () use ($version, $actor): InstrumentVersion {
            $version = InstrumentVersion::query()
                ->whereKey($version->getKey())
                ->lockForUpdate()
                ->with('instrument.accreditationBody')
                ->firstOrFail();

            if ($version->status !== InstrumentVersion::STATUS_DRAFT) {
                throw ValidationException::withMessages(['version' => 'Hanya versi draft yang dapat diterbitkan.']);
            }

            if ($version->instrument->status === Instrument::STATUS_RETIRED) {
                throw ValidationException::withMessages(['instrument' => 'Instrumen yang dipensiunkan tidak dapat diterbitkan.']);
            }

            if ($version->verification_status === InstrumentVersion::VERIFICATION_VERIFIED) {
                if (
                    $version->instrument->accreditationBody->status !== AccreditationBody::STATUS_VERIFIED
                    || blank($version->instrument->accreditationBody->official_url)
                    || blank($version->source_url)
                ) {
                    throw ValidationException::withMessages([
                        'verification_status' => 'Referensi lembaga, sumber versi, dan persetujuan harus tersedia untuk versi resmi.',
                    ]);
                }
                $version->forceFill(['approved_by' => $actor->getKey(), 'approved_at' => $version->approved_at ?? now()]);
            } elseif ($version->verification_status !== InstrumentVersion::VERIFICATION_INTERNAL) {
                throw ValidationException::withMessages([
                    'verification_status' => 'Tandai versi sebagai internal atau lengkapi verifikasi resmi sebelum diterbitkan.',
                ]);
            }

            if (! $version->nodes()->exists()) {
                throw ValidationException::withMessages(['nodes' => 'Tambahkan setidaknya satu node katalog sebelum menerbitkan versi.']);
            }

            $version->forceFill(['status' => InstrumentVersion::STATUS_PUBLISHED])->save();

            if ($version->verification_status === InstrumentVersion::VERIFICATION_VERIFIED) {
                $version->instrument->forceFill(['status' => Instrument::STATUS_PUBLISHED])->save();
            }

            AuditLog::query()->create([
                'actor_user_id' => $actor->getKey(),
                'action' => $version->verification_status === InstrumentVersion::VERIFICATION_VERIFIED
                    ? 'instrument_version.published'
                    : 'instrument_version.published_internal',
                'subject_type' => InstrumentVersion::class,
                'subject_id' => (string) $version->getKey(),
                'after_json' => [
                    'instrument_id' => $version->instrument_id,
                    'version_label' => $version->version_label,
                    'status' => $version->status,
                    'verification_status' => $version->verification_status,
                ],
                'correlation_id' => (string) Str::uuid(),
            ]);

            return $version->refresh();
        });
    }

    public function retire(InstrumentVersion $version, User $actor): InstrumentVersion
    {
        return DB::transaction(function () use ($version, $actor): InstrumentVersion {
            $version = InstrumentVersion::query()->whereKey($version->getKey())->lockForUpdate()->firstOrFail();
            if ($version->status !== InstrumentVersion::STATUS_PUBLISHED) {
                throw ValidationException::withMessages(['version' => 'Hanya versi terbit yang dapat dipensiunkan.']);
            }

            $version->forceFill(['status' => InstrumentVersion::STATUS_RETIRED])->save();
            AuditLog::query()->create([
                'actor_user_id' => $actor->getKey(),
                'action' => 'instrument_version.retired',
                'subject_type' => InstrumentVersion::class,
                'subject_id' => (string) $version->getKey(),
                'before_json' => ['status' => InstrumentVersion::STATUS_PUBLISHED],
                'after_json' => ['status' => InstrumentVersion::STATUS_RETIRED],
                'correlation_id' => (string) Str::uuid(),
            ]);

            return $version->refresh();
        });
    }
}
