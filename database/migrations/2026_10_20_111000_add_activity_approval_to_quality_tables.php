<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quality_policies', function (Blueprint $table): void {
            $table->boolean('activity_completion_requires_approval')->default(false);
        });

        Schema::table('ppepp_activities', function (Blueprint $table): void {
            $table->unsignedBigInteger('approved_by')->nullable()->after('completed_at');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_notes')->nullable()->after('approved_at');
            $table->foreign(['institution_id', 'approved_by'], 'ppepp_activity_approver_fk')
                ->references(['institution_id', 'user_id'])->on('institution_users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ppepp_activities', function (Blueprint $table): void {
            $table->dropForeign('ppepp_activity_approver_fk');
            $table->dropColumn(['approved_by', 'approved_at', 'approval_notes']);
        });

        Schema::table('quality_policies', function (Blueprint $table): void {
            $table->dropColumn('activity_completion_requires_approval');
        });
    }
};
