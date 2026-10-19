<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('billing_interval', 20)->default('monthly');
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('ai_quota_limit')->nullable();
            $table->unsignedInteger('storage_quota_mb')->nullable();
            $table->unsignedInteger('users_quota_limit')->nullable();
            $table->unsignedInteger('projects_quota_limit')->nullable();
            $table->unsignedInteger('unit_quota_limit')->nullable();
            $table->json('feature_flags_json')->nullable();
            $table->timestamps();
        });

        Schema::create('institution_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('saas_plan_id');
            $table->string('status', 30)->default('trial');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('manual_activation')->default(true);
            $table->string('gateway', 40)->nullable();
            $table->string('external_customer_id', 120)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->cascadeOnDelete();
            $table->foreign('saas_plan_id')->references('id')->on('saas_plans')->restrictOnDelete();
            $table->index(['institution_id', 'status']);
        });

        Schema::create('billing_invoices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('invoice_number', 120)->unique();
            $table->string('status', 30)->default('draft');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('line_items_json')->nullable();
            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('institution_subscriptions')->nullOnDelete();
            $table->index(['institution_id', 'status']);
        });

        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('gateway', 40)->default('manual');
            $table->string('external_reference', 160)->nullable();
            $table->string('idempotency_key', 160)->nullable()->unique();
            $table->string('status', 30)->default('pending');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->string('provider_status', 40)->nullable();
            $table->json('payload_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('billing_invoices')->nullOnDelete();
            $table->index(['institution_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('institution_subscriptions');
        Schema::dropIfExists('saas_plans');
    }
};
