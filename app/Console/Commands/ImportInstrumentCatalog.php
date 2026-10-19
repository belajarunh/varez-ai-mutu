<?php

namespace App\Console\Commands;

use App\Models\InstrumentNode;
use App\Services\Instruments\CatalogImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;

class ImportInstrumentCatalog extends Command
{
    protected $signature = 'catalog:import {file : Local JSON file using docs/instrument_catalog_import.schema.json}';

    protected $description = 'Import an instrument catalog as an internal draft for review.';

    public function handle(CatalogImportService $catalogImportService): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path) || ! is_readable($path) || filesize($path) > 10_000_000) {
            $this->error('The catalog file must be a readable local file no larger than 10 MB.');

            return self::FAILURE;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            $this->error('The catalog file could not be read.');

            return self::FAILURE;
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error('Catalog JSON is invalid: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! is_array($payload) || array_is_list($payload)) {
            $this->error('Catalog JSON must be an object matching the documented import format.');

            return self::FAILURE;
        }

        $validator = Validator::make($payload, [
            'body' => ['required', 'array:domain_type,code,name,official_url'],
            'body.domain_type' => ['required', 'in:higher_education,school'],
            'body.code' => ['required', 'string', 'max:80'],
            'body.name' => ['required', 'string', 'max:255'],
            'body.official_url' => ['nullable', 'url', 'max:2048'],
            'instrument' => ['required', 'array:code,name,instrument_type,description'],
            'instrument.code' => ['required', 'string', 'max:100'],
            'instrument.name' => ['required', 'string', 'max:255'],
            'instrument.instrument_type' => ['required', 'string', 'max:80'],
            'instrument.description' => ['nullable', 'string', 'max:10000'],
            'version' => ['required', 'array:version_label,effective_from,effective_to,source_url,source_checksum,notes'],
            'version.version_label' => ['required', 'string', 'max:80'],
            'version.effective_from' => ['nullable', 'date'],
            'version.effective_to' => ['nullable', 'date', 'after_or_equal:version.effective_from'],
            'version.source_url' => ['nullable', 'url', 'max:2048'],
            'version.source_checksum' => ['nullable', 'regex:/^[a-fA-F0-9]{64}$/'],
            'version.notes' => ['nullable', 'string', 'max:10000'],
            'nodes' => ['required', 'array', 'min:1', 'max:10000'],
            'nodes.*' => ['required', 'array:parent_code,node_type,code,title,guidance_text,answer_type,validation_json,weight,sort_order,required,rules'],
            'nodes.*.parent_code' => ['nullable', 'string', 'max:100'],
            'nodes.*.node_type' => ['required', 'in:'.implode(',', InstrumentNode::TYPES)],
            'nodes.*.code' => ['required', 'string', 'max:100', 'distinct'],
            'nodes.*.title' => ['required', 'string', 'max:255'],
            'nodes.*.guidance_text' => ['nullable', 'string', 'max:10000'],
            'nodes.*.answer_type' => ['nullable', 'string', 'max:32'],
            'nodes.*.validation_json' => ['nullable', 'array'],
            'nodes.*.weight' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'nodes.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'nodes.*.required' => ['sometimes', 'boolean'],
            'nodes.*.rules' => ['sometimes', 'array', 'max:100'],
            'nodes.*.rules.*' => ['required', 'array:rule_type,rule_json,severity,message'],
            'nodes.*.rules.*.rule_type' => ['required', 'string', 'max:80'],
            'nodes.*.rules.*.rule_json' => ['required', 'array'],
            'nodes.*.rules.*.severity' => ['required', 'in:info,warning,error'],
            'nodes.*.rules.*.message' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->toJson());

            return self::FAILURE;
        }

        try {
            $version = $catalogImportService->import($validator->validated());
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $this->info("Imported {$version->instrument->code} {$version->version_label} as internal draft. Review and verify it before official use.");

        return self::SUCCESS;
    }
}
