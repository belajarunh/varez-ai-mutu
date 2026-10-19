<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 100);
            $table->string('subject_id', 100)->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent_summary', 255)->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->uuid('correlation_id')->index();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['institution_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
