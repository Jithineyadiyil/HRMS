<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddExtraFieldsToEmployeesTable extends Migration
{
    public function up()
    {
        // Fix status enum to include probation
        DB::statement("ALTER TABLE employees MODIFY COLUMN status ENUM('active','inactive','terminated','on_leave','probation') DEFAULT 'active'");

        Schema::table('employees', function (Blueprint $table) {
            // Personal
            $table->string('prefix', 10)->nullable()->after('employee_code');
            $table->string('arabic_name', 200)->nullable()->after('last_name');
            $table->string('work_phone', 20)->nullable()->after('phone');
            $table->string('extension', 10)->nullable()->after('work_phone');
            $table->string('nationality', 100)->nullable()->after('country');

            // Employment
            $table->string('mode_of_employment', 50)->nullable()->after('employment_type');
            $table->string('role', 50)->nullable()->after('mode_of_employment');
            $table->date('confirmation_date')->nullable()->after('hire_date');
            $table->unsignedTinyInteger('probation_period')->default(0)->after('confirmation_date');
            $table->unsignedSmallInteger('years_of_experience')->nullable()->after('probation_period');

            // Emergency
            $table->string('emergency_contact_relation', 50)->nullable()->after('emergency_contact_phone');
        });
    }

    public function down()
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'prefix','arabic_name','work_phone','extension','nationality',
                'mode_of_employment','role','confirmation_date','probation_period',
                'years_of_experience','emergency_contact_relation',
            ]);
        });
        DB::statement("ALTER TABLE employees MODIFY COLUMN status ENUM('active','inactive','terminated','on_leave') DEFAULT 'active'");
    }
}
