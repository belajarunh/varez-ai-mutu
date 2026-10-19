<?php

namespace App\Services\Instruments;

use App\Models\AccreditationBody;
use App\Models\AuditLog;
use App\Models\Instrument;
use App\Models\InstrumentNode;
use App\Models\InstrumentNodeRule;
use App\Models\InstrumentVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogImportService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function import(array $payload): InstrumentVersion
    {
        return DB::transaction(function () use ($payload): InstrumentVersion {
            $bodyData = $payload['body'];
            $instrumentData = $payload['instrument'];
            $versionData = $payload['version'];

            if (AccreditationBody::query()
                ->where('domain_type', $bodyData['domain_type'])
                ->where('code', $bodyData['code'])
                ->exists()) {
                throw ValidationException::withMessages(['body.code' => 'Kode lembaga sudah ada dalam katalog.']);
            }

            $body = AccreditationBody::query()->create([
                ...$bodyData,
                'status' => AccreditationBody::STATUS_INTERNAL,
            ]);

            if (Instrument::query()
                ->where('domain_type', $bodyData['domain_type'])
                ->where('code', $instrumentData['code'])
                ->exists()) {
                throw ValidationException::withMessages(['instrument.code' => 'Kode instrumen sudah ada dalam domain ini.']);
            }

            $instrument = Instrument::query()->create([
                ...$instrumentData,
                'domain_type' => $bodyData['domain_type'],
                'accreditation_body_id' => $body->getKey(),
                'status' => Instrument::STATUS_INTERNAL,
            ]);

            if ($instrument->versions()->where('version_label', $versionData['version_label'])->exists()) {
                throw ValidationException::withMessages(['version.version_label' => 'Label versi sudah digunakan pada instrumen ini.']);
            }

            $version = $instrument->versions()->create([
                ...$versionData,
                'status' => InstrumentVersion::STATUS_DRAFT,
                'verification_status' => InstrumentVersion::VERIFICATION_INTERNAL,
            ]);

            $nodesByCode = [];
            $pending = $payload['nodes'];

            while ($pending !== []) {
                $remaining = [];
                $createdInPass = false;

                foreach ($pending as $nodeData) {
                    $parentCode = $nodeData['parent_code'] ?? null;
                    if ($parentCode !== null && ! isset($nodesByCode[$parentCode])) {
                        $remaining[] = $nodeData;

                        continue;
                    }

                    $node = InstrumentNode::query()->create([
                        'instrument_version_id' => $version->getKey(),
                        'parent_id' => $parentCode === null ? null : $nodesByCode[$parentCode]->getKey(),
                        'node_type' => $nodeData['node_type'],
                        'code' => $nodeData['code'],
                        'title' => $nodeData['title'],
                        'guidance_text' => $nodeData['guidance_text'] ?? null,
                        'answer_type' => $nodeData['answer_type'] ?? null,
                        'validation_json' => $nodeData['validation_json'] ?? null,
                        'weight' => $nodeData['weight'] ?? null,
                        'sort_order' => $nodeData['sort_order'] ?? 0,
                        'required' => $nodeData['required'] ?? false,
                    ]);
                    $nodesByCode[$nodeData['code']] = $node;
                    foreach ($nodeData['rules'] ?? [] as $ruleData) {
                        InstrumentNodeRule::query()->create([
                            'instrument_node_id' => $node->getKey(),
                            ...$ruleData,
                        ]);
                    }
                    $createdInPass = true;
                }

                if (! $createdInPass) {
                    throw ValidationException::withMessages([
                        'nodes' => 'Daftar node memuat parent_code yang tidak ditemukan atau membentuk siklus.',
                    ]);
                }

                $pending = $remaining;
            }

            AuditLog::query()->create([
                'actor_user_id' => auth()->id(),
                'action' => 'instrument.catalog.imported_internal',
                'subject_type' => Instrument::class,
                'subject_id' => (string) $instrument->getKey(),
                'after_json' => [
                    'body_code' => $body->code,
                    'instrument_code' => $instrument->code,
                    'version_label' => $version->version_label,
                    'node_count' => count($payload['nodes']),
                    'status' => InstrumentVersion::STATUS_DRAFT,
                    'verification_status' => InstrumentVersion::VERIFICATION_INTERNAL,
                ],
                'correlation_id' => (string) Str::uuid(),
            ]);

            return $version->load(['instrument.accreditationBody', 'nodes']);
        });
    }
}
