<?php

namespace Database\Seeders;

use App\Models\AccreditationBody;
use App\Models\Instrument;
use App\Models\InstrumentNode;
use App\Models\InstrumentVersion;
use Illuminate\Database\Seeder;
use RuntimeException;

class InstrumentCatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo instrument catalog is limited to local and testing environments.');
        }

        foreach ([
            'higher_education' => ['DEMO-PT', 'Perguruan tinggi'],
            'school' => ['DEMO-SCHOOL', 'Sekolah'],
        ] as $domain => [$code, $domainLabel]) {
            $body = AccreditationBody::query()->firstOrCreate(
                ['domain_type' => $domain, 'code' => $code],
                [
                    'name' => 'DATA DEMO — BUKAN INSTRUMEN RESMI — Lembaga contoh '.$domainLabel,
                    'official_url' => null,
                    'status' => AccreditationBody::STATUS_INTERNAL,
                ],
            );
            $instrument = Instrument::query()->firstOrCreate(
                ['domain_type' => $domain, 'code' => 'DEMO-GENERIC-'.strtoupper($domain)],
                [
                    'accreditation_body_id' => $body->getKey(),
                    'name' => 'DATA DEMO — BUKAN INSTRUMEN RESMI — Katalog generik '.$domainLabel,
                    'instrument_type' => 'demo_internal',
                    'description' => 'Struktur uji fiktif untuk development; tidak mewakili standar, butir, bobot, atau instrumen resmi.',
                    'status' => Instrument::STATUS_INTERNAL,
                ],
            );
            $version = InstrumentVersion::query()->firstOrCreate(
                ['instrument_id' => $instrument->getKey(), 'version_label' => 'DEMO-1'],
                [
                    'effective_from' => null,
                    'effective_to' => null,
                    'status' => InstrumentVersion::STATUS_DRAFT,
                    'verification_status' => InstrumentVersion::VERIFICATION_INTERNAL,
                    'source_url' => null,
                    'source_checksum' => null,
                    'notes' => 'DATA DEMO — BUKAN INSTRUMEN RESMI',
                ],
            );

            foreach ([
                ['code' => 'DEMO-ROOT', 'node_type' => 'standard', 'title' => 'DATA DEMO — BUKAN INSTRUMEN RESMI — Bagian contoh', 'parent_id' => null, 'sort_order' => 1],
                ['code' => 'DEMO-ITEM', 'node_type' => 'indicator', 'title' => 'DATA DEMO — BUKAN INSTRUMEN RESMI — Indikator contoh', 'parent_id' => null, 'sort_order' => 2],
            ] as $node) {
                InstrumentNode::query()->firstOrCreate(
                    [
                        'instrument_version_id' => $version->getKey(),
                        'parent_id' => $node['parent_id'],
                        'code' => $node['code'],
                    ],
                    [
                        'node_type' => $node['node_type'],
                        'title' => $node['title'],
                        'sort_order' => $node['sort_order'],
                    ],
                );
            }
        }
    }
}
