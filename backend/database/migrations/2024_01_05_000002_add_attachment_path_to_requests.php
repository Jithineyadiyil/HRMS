<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds attachment_path and manager_notes to employee_requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_requests', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('copies_needed')
                      ->comment('Path to supporting document uploaded by employee');
            }
            if (!Schema::hasColumn('employee_requests', 'manager_notes')) {
                $table->text('manager_notes')->nullable()->after('manager_approved_at')
                      ->comment('Notes added by manager when approving');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_requests', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'manager_notes']);
        });
    }
};
