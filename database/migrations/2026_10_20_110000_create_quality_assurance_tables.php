<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quality_standards')) {
            Schema::create('quality_standards', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('institution_id');
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->string('code', 80);
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->string('standard_type', 80)->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->string('status', 20)->default('draft');
                $table->unsignedBigInteger('owner_user_id')->nullable();
                $table->timestamps();
                $table->unique(['institution_id', 'id']);
                $table->unique(['institution_id', 'code', 'version']);
                $table->foreign(['institution_id', 'unit_id'], 'quality_standard_tenant_unit_fk')
                    ->references(['institution_id', 'id'])->on('organizational_units')->restrictOnDelete();
                $table->foreign(['institution_id', 'owner_user_id'], 'quality_standard_owner_fk')
                    ->references(['institution_id', 'user_id'])->on('institution_users')->restrictOnDelete();
                $table->index(['institution_id', 'status', 'effective_from'], 'quality_standard_status_idx');
            });
        }

        if (! Schema::hasTable('quality_indicators')) {
            Schema::create('quality_indicators', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('institution_id');
                $table->unsignedBigInteger('standard_id');
                $table->string('code', 80);
                $table->string('name', 255);
                $table->json('target_json')->nullable();
                $table->text('measurement_method')->nullable();
                $table->string('frequency', 80)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['institution_id', 'id']);
                $table->unique(['institution_id', 'standard_id', 'id'], 'quality_indicator_standard_id_unique');
                $table->unique(['institution_id', 'standard_id', 'code'], 'quality_indicator_code_unique');
                $table->foreign(['institution_id', 'standard_id'], 'quality_indicator_standard_fk')
                    ->references(['institution_id', 'id'])->on('quality_standards')->restrictOnDelete();
                $table->index(['institution_id', 'standard_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('ppepp_activities')) {
            Schema::create('ppepp_activities', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('institution_id');
                $table->unsignedBigInteger('standard_id');
                $table->unsignedBigInteger('indicator_id')->nullable();
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->unsignedBigInteger('academic_period_id')->nullable();
                $table->string('stage', 24);
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->unsignedBigInteger('owner_user_id')->nullable();
                $table->date('due_date')->nullable();
                $table->string('status', 24)->default('planned');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['institution_id', 'id']);
                $table->foreign(['institution_id', 'standard_id'], 'ppepp_activity_standard_fk')
                    ->references(['institution_id', 'id'])->on('quality_standards')->restrictOnDelete();
                $table->foreign(['institution_id', 'standard_id', 'indicator_id'], 'ppepp_activity_indicator_fk')
                    ->references(['institution_id', 'standard_id', 'id'])->on('quality_indicators')->restrictOnDelete();
                $table->foreign(['institution_id', 'unit_id'], 'ppepp_activity_unit_fk')
                    ->references(['institution_id', 'id'])->on('organizational_units')->restrictOnDelete();
                $table->foreign(['institution_id', 'academic_period_id'], 'ppepp_activity_period_fk')
                    ->references(['institution_id', 'id'])->on('academic_periods')->restrictOnDelete();
                $table->foreign(['institution_id', 'owner_user_id'], 'ppepp_activity_owner_fk')
                    ->references(['institution_id', 'user_id'])->on('institution_users')->restrictOnDelete();
                $table->index(['institution_id', 'status', 'due_date'], 'ppepp_activity_due_status_idx');
                $table->index(['institution_id', 'stage', 'status'], 'ppepp_activity_stage_idx');
            });
        }

        if (! Schema::hasTable('ppepp_evidence')) {
            Schema::create('ppepp_evidence', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('institution_id');
                $table->unsignedBigInteger('activity_id');
                $table->unsignedBigInteger('document_id');
                $table->unsignedBigInteger('document_version_id');
                $table->unsignedBigInteger('linked_by');
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(
                    ['institution_id', 'activity_id', 'document_id', 'document_version_id'],
                    'ppepp_evidence_unique',
                );
                $table->foreign(['institution_id', 'activity_id'], 'ppepp_evidence_activity_fk')
                    ->references(['institution_id', 'id'])->on('ppepp_activities')->cascadeOnDelete();
                $table->foreign(['institution_id', 'document_id'], 'ppepp_evidence_document_fk')
                    ->references(['institution_id', 'id'])->on('documents')->restrictOnDelete();
                $table->foreign(['institution_id', 'document_id', 'document_version_id'], 'ppepp_evidence_version_fk')
                    ->references(['institution_id', 'document_id', 'id'])->on('document_versions')->restrictOnDelete();
                $table->foreign(['institution_id', 'linked_by'], 'ppepp_evidence_linker_fk')
                    ->references(['institution_id', 'user_id'])->on('institution_users')->restrictOnDelete();
                $table->index(['institution_id', 'document_id']);
            });
        }

        if (! Schema::hasTable('audit_sessions')) {
            Schema::create('audit_sessions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('institution_id');
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->unsignedBigInteger('academic_period_id')->nullable();
                $table->string('title', 255);
                $table->json('scope_json')->nullable();
                $table->unsignedBigInteger('lead_auditor_id');
                $table->timestamp('starts_at');
                $table->timestamp('ends_at')->nullable();
                $table->string('status', 20)->default('planned');
                $table->timestamps();
                $table->unique(['institution_id', 'id']);
                $table->foreign(['institution_id', 'unit_id'], 'audit_session_unit_fk')
                    ->references(['institution_id', 'id'])->on('organizational_units')->restrictOnDelete();
                $table->foreign(['institution_id', 'academic_period_id'], 'audit_session_period_fk')
                    ->references(['institution_id', 'id'])->on('academic_periods')->restrictOnDelete();
                $table->foreign(['institution_id', 'lead_auditor_id'], 'audit_session_lead_auditor_fk')
                    ->references(['institution_id', 'user_id'])->on('institution_users')->restrictOnDelete();
                $table->index(['institution_id', 'status', 'starts_at'], 'audit_session_status_date_idx');
            });
        }

        if (! Schema::hasTable('audit_session_auditors')) {
            Schema::create('audit_session_auditors', function (Blueprint $table): void {
                $table->unsignedBigInteger('institution_id');
                $table->unsignedBigInteger('audit_session_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamp('assigned_at');
                $table->primary(['audit_session_id', 'user_id']);
                $table->foreign(['institution_id', 'audit_session_id'], 'audit_session_auditors_session_fk')
                    ->references(['institution_id', 'id'])->on('audit_sessions')->cascadeOnDelete();
                $table->foreign(['institution_id', 'user_id'], 'audit_session_auditors_membership_fk')
                    ->references(['institution_id', 'user_id'])->on('institution_users')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('audit_checklist_items')) {
            Schema::create('audit_checklist_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('institution_id');
                $table->unsignedBigInteger('audit_session_id');
                $table->unsignedBigInteger('standard_id')->nullable();
                $table->unsignedBigInteger('indicator_id')->nullable();
                $table->text('question');
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('status', 24)->default('pending');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['institution_id', 'id']);
                $table->unique(['institution_id', 'audit_session_id', 'id'], 'audit_checklist_session_item_unique');
                $table->foreign(['institution_id', 'audit_session_id'], 'audit_checklist_session_fk')
                    ->references(['institution_id', 'id'])->on('audit_sessions')->cascadeOnDelete();
                $table->foreign(['institution_id', 'standard_id'], 'audit_checklist_standard_fk')
                    ->references(['institution_id', 'id'])->on('quality_standards')->restrictOnDelete();
                $table->foreign(['institution_id', 'standard_id', 'indicator_id'], 'audit_checklist_indicator_fk')
                    ->references(['institution_id', 'standard_id', 'id'])->on('quality_indicators')->restrictOnDelete();
                $table->index(
                    ['institution_id', 'audit_session_id', 'sort_order'],
                    'audit_checklist_sort_idx',
                );
            });
        }
        if (! Schema::hasIndex('audit_checklist_items', 'audit_checklist_sort_idx')) {
            Schema::table('audit_checklist_items', function (Blueprint $table): void {
                $table->index(
                    ['institution_id', 'audit_session_id', 'sort_order'],
                    'audit_checklist_sort_idx',
                );
            });
        }

        if (! Schema::hasTable('audit_findings')) {
            Schema::create('audit_findings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('institution_id');
                $table->unsignedBigInteger('audit_session_id');
                $table->unsignedBigInteger('checklist_item_id')->nullable();
                $table->string('finding_code', 80)->nullable();
                $table->string('severity', 32);
                $table->text('description');
                $table->text('root_cause')->nullable();
                $table->text('recommendation')->nullable();
                $table->unsignedBigInteger('owner_user_id')->nullable();
                $table->date('due_date')->nullable();
                $table->string('status', 24)->default('open');
                $table->timestamps();
                $table->unique(['institution_id', 'id']);
                $table->foreign(['institution_id', 'audit_session_id'], 'audit_finding_session_fk')
                    ->references(['institution_id', 'id'])->on('audit_sessions')->cascadeOnDelete();
                $table->foreign(['institution_id', 'audit_session_id', 'checklist_item_id'], 'audit_finding_checklist_fk')
                    ->references(['institution_id', 'audit_session_id', 'id'])->on('audit_checklist_items')->restrictOnDelete();
                $table->foreign(['institution_id', 'owner_user_id'], 'audit_finding_owner_fk')
                    ->references(['institution_id', 'user_id'])->on('institution_users')->restrictOnDelete();
                $table->index(['institution_id', 'status', 'due_date'], 'audit_finding_due_status_idx');
            });
        }

        if (! Schema::hasTable('audit_finding_evidence')) {
            Schema::create('audit_finding_evidence', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('institution_id');
                $table->unsignedBigInteger('finding_id');
                $table->unsignedBigInteger('document_id');
                $table->unsignedBigInteger('document_version_id');
                $table->unsignedBigInteger('linked_by');
                $table->text('note')->nullable();
                $table->timestamps();
                $table->unique(
                    ['institution_id', 'finding_id', 'document_id', 'document_version_id'],
                    'audit_finding_evidence_unique',
                );
                $table->foreign(['institution_id', 'finding_id'], 'audit_finding_evidence_finding_fk')
                    ->references(['institution_id', 'id'])->on('audit_findings')->cascadeOnDelete();
                $table->foreign(['institution_id', 'document_id'], 'audit_finding_evidence_document_fk')
                    ->references(['institution_id', 'id'])->on('documents')->restrictOnDelete();
                $table->foreign(['institution_id', 'document_id', 'document_version_id'], 'audit_finding_evidence_version_fk')
                    ->references(['institution_id', 'document_id', 'id'])->on('document_versions')->restrictOnDelete();
                $table->foreign(['institution_id', 'linked_by'], 'audit_finding_evidence_linker_fk')
                    ->references(['institution_id', 'user_id'])->on('institution_users')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('corrective_actions')) {
            Schema::create('corrective_actions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('institution_id');
                $table->unsignedBigInteger('finding_id');
                $table->text('action_plan');
                $table->unsignedBigInteger('owner_user_id');
                $table->date('due_date');
                $table->text('verification_notes')->nullable();
                $table->string('effectiveness_status', 24)->nullable();
                $table->unsignedBigInteger('verified_by')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->string('status', 24)->default('planned');
                $table->timestamps();
                $table->unique(['institution_id', 'id']);
                $table->foreign(['institution_id', 'finding_id'], 'corrective_action_finding_fk')
                    ->references(['institution_id', 'id'])->on('audit_findings')->cascadeOnDelete();
                $table->foreign(['institution_id', 'owner_user_id'], 'corrective_action_owner_fk')
                    ->references(['institution_id', 'user_id'])->on('institution_users')->restrictOnDelete();
                $table->foreign(['institution_id', 'verified_by'], 'corrective_action_verifier_fk')
                    ->references(['institution_id', 'user_id'])->on('institution_users')->restrictOnDelete();
                $table->index(['institution_id', 'status', 'due_date'], 'corrective_action_due_status_idx');
            });
        }

        if (! Schema::hasTable('quality_policies')) {
            Schema::create('quality_policies', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('institution_id')->unique();
                $table->boolean('require_effectiveness_verification')->default(true);
                $table->boolean('require_independent_verifier')->default(false);
                $table->json('settings_json')->nullable();
                $table->timestamps();
                $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('quality_histories')) {
            Schema::create('quality_histories', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('institution_id');
                $table->string('entity_type', 48);
                $table->unsignedBigInteger('entity_id');
                $table->string('event', 80);
                $table->json('changes_json')->nullable();
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->timestamp('created_at');
                $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
                $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
                $table->index(['institution_id', 'entity_type', 'entity_id', 'created_at'], 'quality_history_entity_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_histories');
        Schema::dropIfExists('quality_policies');
        Schema::dropIfExists('corrective_actions');
        Schema::dropIfExists('audit_finding_evidence');
        Schema::dropIfExists('audit_findings');
        Schema::dropIfExists('audit_checklist_items');
        Schema::dropIfExists('audit_session_auditors');
        Schema::dropIfExists('audit_sessions');
        Schema::dropIfExists('ppepp_evidence');
        Schema::dropIfExists('ppepp_activities');
        Schema::dropIfExists('quality_indicators');
        Schema::dropIfExists('quality_standards');
    }
};
