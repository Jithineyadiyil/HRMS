<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendanceDevicesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('attendance_devices')) Schema::create('attendance_devices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);                          // e.g. "Main Entrance"
            $table->string('brand', 50)->default('zkteco');       // zkteco | hikvision | rest | custom
            $table->string('ip_address', 45);
            $table->integer('port')->default(4370);               // ZKTeco default 4370, HTTP 80/443
            $table->string('protocol', 20)->default('tcp');       // tcp | http | https
            $table->string('api_path')->nullable();               // For HTTP: /iclock/getrequest
            $table->string('api_key')->nullable();                // API key / token for REST devices
            $table->string('username', 100)->nullable();          // HTTP Basic auth
            $table->string('password', 255)->nullable();          // encrypted
            $table->integer('timeout_seconds')->default(30);
            $table->string('employee_number_field', 50)->default('employee_code'); // field to match
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status', 20)->nullable();   // success | failed | partial
            $table->integer('last_sync_count')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamps();
        });

        if (!Schema::hasTable('device_attendance_logs')) Schema::create('device_attendance_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('device_id');
            $table->string('device_employee_number', 50);  // raw ID from device
            $table->unsignedBigInteger('employee_id')->nullable(); // matched employee
            $table->timestamp('punch_time');
            $table->tinyInteger('punch_type')->default(0); // 0=check-in, 1=check-out (ZKTeco codes)
            $table->string('verification_mode', 20)->nullable(); // fingerprint, card, face, pin
            $table->boolean('processed')->default(false);
            $table->timestamps();
            $table->foreign('device_id')->references('id')->on('attendance_devices')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('set null');
            $table->index(['device_employee_number','punch_time']);
            $table->index(['employee_id','punch_time']);
            $table->unique(['device_id','device_employee_number','punch_time'], 'dal_device_empno_punchtime_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('device_attendance_logs');
        Schema::dropIfExists('attendance_devices');
    }
}
