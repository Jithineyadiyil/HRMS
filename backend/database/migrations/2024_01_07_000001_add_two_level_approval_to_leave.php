<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Adds 2-level approval workflow to leave_requests:
 *   pending → manager_approved → approved
 *
 * Sick leave (and any type with skip_manager_approval=true) goes:
 *   pending → approved  (bypasses manager stage)
 *
 * Also adds skip_manager_approval flag to leave_types table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── leave_types: add skip_manager_approval flag ─────────────────
        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('skip_manager_approval')->default(false)->after('is_active')
                  ->comment('If true, requests go straight to HR without manager approval (e.g. sick leave)');
        });

        // ── leave_requests: expand status enum + add manager approval cols ─
        // MySQL requires re-creating the enum to add a value
        DB::statement("ALTER TABLE leave_requests MODIFY COLUMN status ENUM(
            'pending',
            'manager_approved',
            'approved',
            'rejected',
            'cancelled'
        ) NOT NULL DEFAULT 'pending'");

        Schema::table('leave_requests', function (Blueprint $table) {
            // Manager approval (level 1)
            $table->unsignedBigInteger('manager_approved_by')->nullable()->after('approved_at');
            $table->timestamp('manager_approved_at')->nullable()->after('manager_approved_by');
            $table->text('manager_notes')->nullable()->after('manager_approved_at');

            // HR rejection notes (level 2)
            $table->text('hr_notes')->nullable()->after('manager_notes');

            // Track which stage rejected
            $table->string('rejected_stage', 20)->nullable()->after('rejection_reason')
                  ->comment('manager or hr');

            $table->foreign('manager_approved_by')->references('id')->on('users')->onDelete('set null');
        });

        // ── Mark sick leave to skip manager approval ─────────────────────
        DB::table('leave_types')
            ->whereRaw("LOWER(name) LIKE '%sick%' OR LOWER(code) LIKE '%sick%'")
            ->update(['skip_manager_approval' => true]);
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropForeign(['manager_approved_by']);
            $table->dropColumn([
                'manager_approved_by', 'manager_approved_at', 'manager_notes',
                'hr_notes', 'rejected_stage',
            ]);
        });

        DB::statement("ALTER TABLE leave_requests MODIFY COLUMN status ENUM(
            'pending','approved','rejected','cancelled'
        ) NOT NULL DEFAULT 'pending'");

        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('skip_manager_approval');
        });
    }
};
