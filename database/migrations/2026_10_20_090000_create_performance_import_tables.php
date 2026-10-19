<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name', 160);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['institution_id', 'id'], 'academic_period_scope_id_unique');
            $table->unique(['institution_id', 'code'], 'academic_period_scope_code_unique');
        });

        Schema::create('performance_dataset_schemas', function (Blueprint $table): void {
            $table->id();
            $table->string('dataset_type', 100);
            $table->unsignedInteger('schema_version');
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->json('fields_json');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['dataset_type', 'schema_version'], 'dataset_schema_type_version_unique');
            $table->index(['dataset_type', 'status'], 'dataset_schema_type_status_index');
        });

        Schema::create('import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('uploaded_by');
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->foreignId('performance_dataset_schema_id')
                ->constrained('performance_dataset_schemas')
                ->restrictOnDelete();
            $table->foreignId('academic_period_id');
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('target_dataset_id')->nullable();
            $table->string('source_type', 10);
            $table->string('original_filename', 255);
            $table->string('storage_key', 512);
            $table->char('checksum_sha256', 64);
            $table->string('status', 30)->default('inspecting');
            $table->string('strategy', 20)->nullable();
            $table->json('mapping_json')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('valid_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('fatal_error_count')->default(0);
            $table->json('preview_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'id'], 'import_batch_scope_id_unique');
            $table->index(['institution_id', 'status', 'created_at'], 'import_batch_scope_status_index');
            $table->foreign(['institution_id', 'academic_period_id'], 'import_batch_period_scope_fk')
                ->references(['institution_id', 'id'])
                ->on('academic_periods')
                ->restrictOnDelete();
            $table->foreign(['institution_id', 'unit_id'], 'import_batch_unit_scope_fk')
                ->references(['institution_id', 'id'])
                ->on('organizational_units')
                ->restrictOnDelete();
            $table->foreign(['institution_id', 'uploaded_by'], 'import_batch_uploader_membership_fk')
                ->references(['institution_id', 'user_id'])
                ->on('institution_users')
                ->restrictOnDelete();
            $table->foreign(['institution_id', 'confirmed_by'], 'import_batch_confirmer_membership_fk')
                ->references(['institution_id', 'user_id'])
                ->on('institution_users')
                ->restrictOnDelete();
        });

        Schema::create('import_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_batch_id');
            $table->foreignId('institution_id');
            $table->unsignedInteger('row_number')->nullable();
            $table->string('column_name', 150)->nullable();
            $table->string('error_code', 80);
            $table->text('message');
            $table->json('row_payload_json')->nullable();
            $table->boolean('is_fatal')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['import_batch_id', 'row_number'], 'import_error_batch_row_index');
            $table->foreign(['institution_id', 'import_batch_id'], 'import_error_batch_scope_fk')
                ->references(['institution_id', 'id'])
                ->on('import_batches')
                ->cascadeOnDelete();
        });

        Schema::create('performance_datasets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('academic_period_id');
            $table->string('dataset_type', 100);
            $table->unsignedInteger('schema_version');
            $table->unsignedBigInteger('performance_dataset_schema_id');
            $table->unsignedBigInteger('source_import_batch_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['institution_id', 'id'], 'performance_dataset_scope_id_unique');
            $table->unique('source_import_batch_id', 'performance_dataset_source_batch_unique');
            $table->index(
                ['institution_id', 'dataset_type', 'academic_period_id'],
                'performance_dataset_scope_type_period_index',
            );
            $table->foreign(['institution_id', 'unit_id'], 'performance_dataset_unit_scope_fk')
                ->references(['institution_id', 'id'])
                ->on('organizational_units')
                ->restrictOnDelete();
            $table->foreign(['institution_id', 'academic_period_id'], 'performance_dataset_period_scope_fk')
                ->references(['institution_id', 'id'])
                ->on('academic_periods')
                ->restrictOnDelete();
            $table->foreign('performance_dataset_schema_id', 'performance_dataset_schema_fk')
                ->references('id')
                ->on('performance_dataset_schemas')
                ->restrictOnDelete();
            $table->foreign('source_import_batch_id', 'performance_dataset_source_batch_fk')
                ->references('id')
                ->on('import_batches')
                ->nullOnDelete();
        });

        Schema::table('import_batches', function (Blueprint $table): void {
            $table->foreign(['institution_id', 'target_dataset_id'], 'import_batch_target_scope_fk')
                ->references(['institution_id', 'id'])
                ->on('performance_datasets')
                ->restrictOnDelete();
        });

        Schema::create('performance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('dataset_id');
            $table->string('record_key', 255);
            $table->json('record_payload_json');
            $table->unsignedInteger('source_row')->nullable();
            $table->string('validation_status', 20)->default('valid');
            $table->timestamps();
            $table->unique(['dataset_id', 'record_key'], 'performance_record_dataset_key_unique');
            $table->index(['institution_id', 'dataset_id'], 'performance_record_scope_dataset_index');
            $table->foreign(['institution_id', 'dataset_id'], 'performance_record_dataset_scope_fk')
                ->references(['institution_id', 'id'])
                ->on('performance_datasets')
                ->cascadeOnDelete();
        });

        Schema::create('indicator_data_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('instrument_node_id')->constrained('instrument_nodes')->restrictOnDelete();
            $table->string('dataset_type', 100);
            $table->unsignedInteger('version')->default(1);
            $table->json('mapping_json');
            $table->timestamps();
            $table->unique(
                ['institution_id', 'instrument_node_id', 'dataset_type', 'version'],
                'indicator_data_mapping_version_unique',
            );
            $table->index(['institution_id', 'dataset_type'], 'indicator_data_mapping_scope_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_data_mappings');
        Schema::dropIfExists('performance_records');
        Schema::table('import_batches', function (Blueprint $table): void {
            $table->dropForeign('import_batch_target_scope_fk');
        });
        Schema::dropIfExists('performance_datasets');
        Schema::dropIfExists('import_errors');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('performance_dataset_schemas');
        Schema::dropIfExists('academic_periods');
    }
};
