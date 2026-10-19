<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_periods', function (Blueprint $table): void {
            $table->unique(['institution_id', 'id'], 'academic_period_tenant_id_unique');
        });

        Schema::create('document_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name', 160);
            $table->string('ppepp_stage', 32)->nullable();
            $table->string('access_default', 20)->default('institution');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['institution_id', 'id']);
            $table->unique(['institution_id', 'code']);
            $table->index(['institution_id', 'ppepp_stage', 'is_active']);
        });

        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('academic_period_id')->nullable();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('document_type', 16);
            $table->unsignedBigInteger('owner_user_id');
            $table->string('access_level', 20)->default('institution');
            $table->string('verification_status', 20)->default('unverified');
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->date('retention_until')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->unique(['institution_id', 'id']);
            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
            $table->foreign(['institution_id', 'unit_id'], 'document_tenant_unit_fk')
                ->references(['institution_id', 'id'])->on('organizational_units')->restrictOnDelete();
            $table->foreign(['institution_id', 'category_id'], 'document_tenant_category_fk')
                ->references(['institution_id', 'id'])->on('document_categories')->restrictOnDelete();
            $table->foreign(['institution_id', 'academic_period_id'], 'document_tenant_period_fk')
                ->references(['institution_id', 'id'])->on('academic_periods')->restrictOnDelete();
            $table->foreign(['institution_id', 'owner_user_id'], 'document_owner_membership_fk')
                ->references(['institution_id', 'user_id'])->on('institution_users')->restrictOnDelete();
            $table->foreign(['institution_id', 'created_by'], 'document_creator_membership_fk')
                ->references(['institution_id', 'user_id'])->on('institution_users')->restrictOnDelete();
            $table->index(['institution_id', 'status', 'updated_at'], 'document_tenant_status_updated_idx');
            $table->index(['institution_id', 'category_id'], 'document_tenant_category_idx');
            $table->index(['institution_id', 'unit_id', 'access_level'], 'document_tenant_unit_access_idx');
        });

        Schema::create('document_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('document_id');
            $table->unsignedInteger('version_number');
            $table->string('storage_disk', 64);
            $table->string('storage_key', 1024);
            $table->string('original_filename', 255);
            $table->string('file_extension', 16);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('uploaded_at');
            $table->string('extraction_status', 20)->default('queued');
            $table->string('scan_status', 20)->default('not_scanned');
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedBigInteger('extracted_character_count')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'id']);
            $table->unique(['institution_id', 'document_id', 'id'], 'document_version_tenant_doc_id_unique');
            $table->unique(['institution_id', 'document_id', 'version_number'], 'document_version_number_unique');
            $table->foreign(['institution_id', 'document_id'], 'document_version_tenant_document_fk')
                ->references(['institution_id', 'id'])->on('documents')->cascadeOnDelete();
            $table->foreign(['institution_id', 'uploaded_by'], 'document_version_uploader_membership_fk')
                ->references(['institution_id', 'user_id'])->on('institution_users')->restrictOnDelete();
            $table->index(['institution_id', 'extraction_status'], 'document_version_tenant_status_idx');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->foreign(['institution_id', 'id', 'current_version_id'], 'document_current_version_fk')
                ->references(['institution_id', 'document_id', 'id'])
                ->on('document_versions')
                ->restrictOnDelete();
        });

        Schema::create('document_relations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('document_id');
            $table->string('related_type', 40);
            $table->unsignedBigInteger('related_id');
            $table->string('relation_type', 40)->default('supports');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->foreign(['institution_id', 'document_id'], 'document_relation_document_fk')
                ->references(['institution_id', 'id'])->on('documents')->cascadeOnDelete();
            $table->foreign(['institution_id', 'created_by'], 'document_relation_creator_membership_fk')
                ->references(['institution_id', 'user_id'])->on('institution_users')->restrictOnDelete();
            $table->unique(
                ['institution_id', 'document_id', 'related_type', 'related_id', 'relation_type'],
                'document_relation_unique',
            );
            $table->index(['institution_id', 'related_type', 'related_id'], 'document_relation_target_idx');
        });

        Schema::create('document_chunks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('document_version_id');
            $table->unsignedInteger('chunk_index');
            $table->longText('content_text');
            $table->unsignedInteger('page_start')->nullable();
            $table->unsignedInteger('page_end')->nullable();
            $table->string('section_title', 255)->nullable();
            $table->unsignedInteger('token_estimate');
            $table->char('content_hash', 64);
            $table->json('metadata_json')->nullable();
            $table->string('index_status', 20)->default('ready');
            $table->timestamps();
            $table->foreign(['institution_id', 'document_version_id'], 'document_chunk_tenant_version_fk')
                ->references(['institution_id', 'id'])->on('document_versions')->cascadeOnDelete();
            $table->unique(
                ['document_version_id', 'chunk_index', 'content_hash'],
                'document_chunk_version_index_hash_unique',
            );
            $table->index(['institution_id', 'document_version_id', 'chunk_index'], 'document_chunk_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('document_relations');
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropForeign('document_current_version_fk');
        });
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_categories');
        Schema::table('academic_periods', function (Blueprint $table): void {
            $table->dropUnique('academic_period_tenant_id_unique');
        });
    }
};
