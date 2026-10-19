<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generation_reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ai_generation_id');
            $table->unsignedBigInteger('reviewer_user_id')->nullable();
            $table->string('decision', 40)->default('submitted');
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('ai_generation_id')->references('id')->on('ai_generations')->cascadeOnDelete();
            $table->foreign('reviewer_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['ai_generation_id', 'decision']);
        });

        Schema::create('ai_generation_exports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ai_generation_id');
            $table->unsignedBigInteger('exported_by_user_id')->nullable();
            $table->string('format', 20)->default('json');
            $table->string('file_name', 255)->nullable();
            $table->json('manifest_json')->nullable();
            $table->boolean('watermarked')->default(true);
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();

            $table->foreign('ai_generation_id')->references('id')->on('ai_generations')->cascadeOnDelete();
            $table->foreign('exported_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['ai_generation_id', 'exported_at']);
        });

        Schema::create('ai_generation_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ai_generation_id');
            $table->unsignedInteger('version_number')->default(1);
            $table->json('result_json')->nullable();
            $table->char('snapshot_checksum', 64)->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('ai_generation_id')->references('id')->on('ai_generations')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['ai_generation_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generation_versions');
        Schema::dropIfExists('ai_generation_exports');
        Schema::dropIfExists('ai_generation_reviews');
    }
};
