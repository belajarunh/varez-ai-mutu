<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('institution_type', 32)->default('other');
            $table->string('legal_identifier')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->json('settings_json')->nullable();
            $table->timestamps();
        });

        Schema::create('organizational_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('unit_type', 32)->default('other');
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->unique(['institution_id', 'id']);
            $table->unique(['institution_id', 'code']);
            $table->index(['institution_id', 'parent_id']);
            $table->foreign(['institution_id', 'parent_id'])
                ->references(['institution_id', 'id'])
                ->on('organizational_units')
                ->restrictOnDelete();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 20);
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['scope', 'slug']);
            $table->unique(['id', 'scope']);
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 20);
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['scope', 'slug']);
            $table->unique(['id', 'scope']);
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('role_scope', 20)->default('platform');
            $table->primary(['user_id', 'role_id']);
            $table->foreign(['role_id', 'role_scope'])
                ->references(['id', 'scope'])
                ->on('roles')
                ->cascadeOnDelete();
        });

        Schema::create('institution_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->string('role_scope', 20)->default('tenant');
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'user_id']);
            $table->unique(['institution_id', 'user_id', 'role_id']);
            $table->foreign(['role_id', 'role_scope'])
                ->references(['id', 'scope'])
                ->on('roles')
                ->restrictOnDelete();
        });

        Schema::create('user_unit_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('organizational_unit_id');
            $table->string('access_level', 20)->default('member');
            $table->timestamps();
            $table->unique(
                ['institution_id', 'user_id', 'organizational_unit_id'],
                'unit_assignment_scope_unique',
            );
            $table->foreign(['institution_id', 'user_id'], 'unit_assignment_membership_fk')
                ->references(['institution_id', 'user_id'])
                ->on('institution_users')
                ->cascadeOnDelete();
            $table->foreign(['institution_id', 'organizational_unit_id'], 'unit_assignment_unit_fk')
                ->references(['institution_id', 'id'])
                ->on('organizational_units')
                ->restrictOnDelete();
        });

        Schema::create('invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['institution_id', 'email', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('user_unit_assignments');
        Schema::dropIfExists('institution_users');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('organizational_units');
        Schema::dropIfExists('institutions');
    }
};
