<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accreditation_bodies', function (Blueprint $table): void {
            $table->id();
            $table->string('domain_type', 32);
            $table->string('code', 80);
            $table->string('name');
            $table->string('official_url', 2048)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamps();
            $table->unique(['domain_type', 'code'], 'body_domain_code_unique');
            $table->unique(['id', 'domain_type'], 'body_id_domain_unique');
        });

        Schema::create('instruments', function (Blueprint $table): void {
            $table->id();
            $table->string('domain_type', 32);
            $table->unsignedBigInteger('accreditation_body_id');
            $table->string('code', 100);
            $table->string('name');
            $table->string('instrument_type', 80);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('internal')->index();
            $table->timestamps();
            $table->unique(['domain_type', 'code'], 'instrument_domain_code_unique');
            $table->unique(['id', 'domain_type'], 'instrument_id_domain_unique');
            $table->foreign(['accreditation_body_id', 'domain_type'], 'instrument_body_domain_fk')
                ->references(['id', 'domain_type'])
                ->on('accreditation_bodies')
                ->restrictOnDelete();
        });

        Schema::create('instrument_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('instrument_id');
            $table->string('version_label', 80);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->string('verification_status', 20)->default('internal')->index();
            $table->string('source_url', 2048)->nullable();
            $table->char('source_checksum', 64)->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['instrument_id', 'version_label'], 'instrument_version_label_unique');
            $table->unique(['id', 'instrument_id'], 'version_id_instrument_unique');
            $table->foreign('instrument_id')->references('id')->on('instruments')->restrictOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('instrument_nodes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('instrument_version_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('node_type', 32);
            $table->string('code', 100);
            $table->string('title');
            $table->text('guidance_text')->nullable();
            $table->string('answer_type', 32)->nullable();
            $table->json('validation_json')->nullable();
            $table->decimal('weight', 10, 4)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('required')->default(false);
            $table->timestamps();
            $table->unique(['instrument_version_id', 'parent_id', 'code'], 'instrument_node_code_unique');
            $table->unique(['id', 'instrument_version_id'], 'node_id_version_unique');
            $table->foreign('instrument_version_id')
                ->references('id')
                ->on('instrument_versions')
                ->cascadeOnDelete();
            $table->foreign(['parent_id', 'instrument_version_id'], 'instrument_node_parent_version_fk')
                ->references(['id', 'instrument_version_id'])
                ->on('instrument_nodes')
                ->restrictOnDelete();
            $table->index(['instrument_version_id', 'parent_id', 'sort_order'], 'instrument_node_tree_idx');
        });

        Schema::create('instrument_node_rules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('instrument_node_id');
            $table->string('rule_type', 80);
            $table->json('rule_json');
            $table->string('severity', 20)->default('warning');
            $table->string('message', 500)->nullable();
            $table->timestamps();
            $table->foreign('instrument_node_id')->references('id')->on('instrument_nodes')->cascadeOnDelete();
            $table->index(['instrument_node_id', 'rule_type'], 'instrument_node_rule_type_idx');
        });

        Schema::create('accreditation_projects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('instrument_version_id');
            $table->string('project_type', 32);
            $table->string('title');
            $table->string('period_label', 100);
            $table->string('status', 32)->default('planning')->index();
            $table->date('target_date')->nullable();
            $table->unsignedBigInteger('owner_user_id');
            $table->unsignedBigInteger('created_by');
            $table->json('instrument_snapshot_json');
            $table->char('instrument_snapshot_checksum', 64);
            $table->timestamps();
            $table->unique(['institution_id', 'id'], 'project_institution_id_unique');
            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
            $table->foreign(['institution_id', 'unit_id'], 'project_institution_unit_fk')
                ->references(['institution_id', 'id'])
                ->on('organizational_units')
                ->restrictOnDelete();
            $table->foreign('instrument_version_id')
                ->references('id')
                ->on('instrument_versions')
                ->restrictOnDelete();
            $table->foreign(['institution_id', 'owner_user_id'], 'project_owner_membership_fk')
                ->references(['institution_id', 'user_id'])
                ->on('institution_users')
                ->restrictOnDelete();
            $table->foreign(['institution_id', 'created_by'], 'project_creator_membership_fk')
                ->references(['institution_id', 'user_id'])
                ->on('institution_users')
                ->restrictOnDelete();
            $table->index(['institution_id', 'status', 'target_date'], 'project_tenant_status_date_idx');
        });

        Schema::create('project_members', function (Blueprint $table): void {
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id');
            $table->string('project_role', 32)->default('member');
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['project_id', 'user_id']);
            $table->foreign(['institution_id', 'project_id'], 'project_member_project_fk')
                ->references(['institution_id', 'id'])
                ->on('accreditation_projects')
                ->cascadeOnDelete();
            $table->foreign(['institution_id', 'user_id'], 'project_member_membership_fk')
                ->references(['institution_id', 'user_id'])
                ->on('institution_users')
                ->cascadeOnDelete();
            $table->index(['institution_id', 'user_id'], 'project_member_tenant_user_idx');
        });

        Schema::create('project_indicators', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('source_instrument_node_id');
            $table->json('snapshot_node_json');
            $table->string('status', 32)->default('not_started')->index();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->date('due_date')->nullable();
            $table->json('progress_metadata_json')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'source_instrument_node_id'], 'project_indicator_source_unique');
            $table->foreign(['institution_id', 'project_id'], 'project_indicator_project_fk')
                ->references(['institution_id', 'id'])
                ->on('accreditation_projects')
                ->cascadeOnDelete();
            $table->foreign('source_instrument_node_id')
                ->references('id')
                ->on('instrument_nodes')
                ->restrictOnDelete();
            $table->foreign(['institution_id', 'owner_user_id'], 'project_indicator_owner_membership_fk')
                ->references(['institution_id', 'user_id'])
                ->on('institution_users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_indicators');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('accreditation_projects');
        Schema::dropIfExists('instrument_node_rules');
        Schema::dropIfExists('instrument_nodes');
        Schema::dropIfExists('instrument_versions');
        Schema::dropIfExists('instruments');
        Schema::dropIfExists('accreditation_bodies');
    }
};
