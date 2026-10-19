<?php

namespace App\Services\Instruments;

use App\Models\InstrumentNode;
use App\Models\InstrumentVersion;

class InstrumentSnapshotBuilder
{
    /**
     * @return array{snapshot: array<string, mixed>, checksum: string, indicator_nodes: list<array<string, mixed>>}
     */
    public function build(InstrumentVersion $version): array
    {
        $version->loadMissing(['instrument.accreditationBody', 'nodes']);
        $nodes = $version->nodes
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values();
        $children = [];
        foreach ($nodes as $node) {
            $children[(int) ($node->parent_id ?? 0)][] = $node;
        }

        $snapshot = [
            'instrument' => [
                'id' => $version->instrument->getKey(),
                'domain_type' => $version->instrument->domain_type,
                'code' => $version->instrument->code,
                'name' => $version->instrument->name,
                'instrument_type' => $version->instrument->instrument_type,
                'accreditation_body' => [
                    'code' => $version->instrument->accreditationBody->code,
                    'name' => $version->instrument->accreditationBody->name,
                    'official_url' => $version->instrument->accreditationBody->official_url,
                ],
            ],
            'version' => [
                'id' => $version->getKey(),
                'label' => $version->version_label,
                'effective_from' => $version->effective_from?->toDateString(),
                'effective_to' => $version->effective_to?->toDateString(),
                'verification_status' => $version->verification_status,
                'source_url' => $version->source_url,
                'source_checksum' => $version->source_checksum,
            ],
            'nodes' => $this->buildChildren($children, 0),
        ];

        $json = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return [
            'snapshot' => $snapshot,
            'checksum' => hash('sha256', $json),
            'indicator_nodes' => $this->flattenIndicatorNodes($snapshot['nodes']),
        ];
    }

    /**
     * @param  array<int, list<InstrumentNode>>  $children
     * @return list<array<string, mixed>>
     */
    private function buildChildren(array $children, int $parentId): array
    {
        $result = [];
        foreach ($children[$parentId] ?? [] as $node) {
            $result[] = [
                'source_node_id' => $node->getKey(),
                'parent_id' => $node->parent_id,
                'node_type' => $node->node_type,
                'code' => $node->code,
                'title' => $node->title,
                'guidance_text' => $node->guidance_text,
                'answer_type' => $node->answer_type,
                'validation' => $node->validation_json,
                'weight' => $node->weight,
                'sort_order' => $node->sort_order,
                'required' => $node->required,
                'children' => $this->buildChildren($children, (int) $node->getKey()),
            ];
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function flattenIndicatorNodes(array $nodes): array
    {
        $indicators = [];
        foreach ($nodes as $node) {
            if (in_array($node['node_type'], ['indicator', 'item', 'subitem'], true)) {
                $indicators[] = $node;
            }
            array_push($indicators, ...$this->flattenIndicatorNodes($node['children']));
        }

        return $indicators;
    }
}
