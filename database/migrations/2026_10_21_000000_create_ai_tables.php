<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompt_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 120);
            $table->string('version', 40)->default('v1');
            $table->string('task_type', 80)->default('general');
            $table->text('system_template')->nullable();
            $table->text('user_template')->nullable();
            $table->json('output_schema_json')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['code', 'version']);
            $table->index(['task_type', 'status']);
        });

        Schema::create('ai_generations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('prompt_template_id')->nullable();
            $table->string('task_type', 80)->default('general');
            $table->string('provider', 60)->default('mock');
            $table->string('model', 120)->nullable();
            $table->string('status', 30)->default('queued');
            $table->string('input_hash', 128)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('estimated_cost', 12, 6)->nullable();
            $table->longText('result_text')->nullable();
            $table->json('result_json')->nullable();
            $table->json('safety_flags_json')->nullable();
            $table->string('correlation_id', 100)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('prompt_template_id')->references('id')->on('ai_prompt_templates')->nullOnDelete();
            $table->index(['institution_id', 'status']);
            $table->index(['institution_id', 'project_id']);
            $table->index(['correlation_id']);
        });

        Schema::create('embeddings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('document_chunk_id')->nullable();
            $table->string('provider', 60)->default('mock');
            $table->string('model', 120)->nullable();
            $table->unsignedInteger('dimensions')->default(1536);
            $table->json('vector_json')->nullable();
            $table->char('vector_checksum', 64)->nullable();
            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
            $table->foreign('document_chunk_id')->references('id')->on('document_chunks')->cascadeOnDelete();
            $table->unique(['document_chunk_id', 'provider', 'model']);
            $table->index(['institution_id', 'provider', 'model']);
        });

        Schema::create('ai_generation_sources', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ai_generation_id');
            $table->unsignedBigInteger('document_chunk_id')->nullable();
            $table->unsignedBigInteger('document_version_id')->nullable();
            $table->unsignedBigInteger('instrument_node_id')->nullable();
            $table->string('dataset_record_ref', 255)->nullable();
            $table->unsignedInteger('rank')->default(1);
            $table->decimal('relevance_score', 10, 6)->nullable();
            $table->longText('quoted_text')->nullable();
            $table->json('citation_metadata_json')->nullable();
            $table->timestamps();

            $table->foreign('ai_generation_id')->references('id')->on('ai_generations')->cascadeOnDelete();
            $table->foreign('document_chunk_id')->references('id')->on('document_chunks')->nullOnDelete();
            $table->foreign('document_version_id')->references('id')->on('document_versions')->nullOnDelete();
            $table->index(['ai_generation_id', 'rank']);
        });

        Schema::create('ai_citations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ai_generation_id');
            $table->string('citation_key', 120);
            $table->string('source_type', 60)->default('document');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('display_label', 255)->nullable();
            $table->string('page_or_section', 255)->nullable();
            $table->longText('quoted_text')->nullable();
            $table->string('verified_status', 20)->default('unverified');
            $table->timestamps();

            $table->foreign('ai_generation_id')->references('id')->on('ai_generations')->cascadeOnDelete();
            $table->index(['ai_generation_id', 'citation_key']);
        });

        Schema::create('usage_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('usage_type', 80);
            $table->decimal('quantity', 12, 3)->default(0);
            $table->string('unit', 40)->default('tokens');
            $table->string('related_entity_type', 80)->nullable();
            $table->unsignedBigInteger('related_entity_id')->nullable();
            $table->string('period_key', 20)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['institution_id', 'usage_type', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_ledgers');
        Schema::dropIfExists('ai_citations');
        Schema::dropIfExists('ai_generation_sources');
        Schema::dropIfExists('embeddings');
        Schema::dropIfExists('ai_generations');
        Schema::dropIfExists('ai_prompt_templates');
    }
};
