<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_ledgers', function (Blueprint $table): void {
            $table->string('idempotency_key', 64)->nullable()->after('period_key');
            $table->unique(
                ['institution_id', 'usage_type', 'period_key', 'idempotency_key'],
                'usage_ledger_idempotency_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('usage_ledgers', function (Blueprint $table): void {
            $table->dropUnique('usage_ledger_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
