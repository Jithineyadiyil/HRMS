<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('job_applications', function (Blueprint $table) {
            // CV Bank fields — null job_posting_id = standalone CV bank entry
            $table->boolean('is_cv_bank')->default(false)->after('job_posting_id');
            $table->string('position_applied', 150)->nullable()->after('is_cv_bank');
            $table->string('nationality', 100)->nullable()->after('position_applied');
            $table->integer('experience_years')->nullable()->after('nationality');
            $table->text('skills')->nullable()->after('experience_years');
            $table->string('source', 80)->nullable()->after('skills'); // e.g. LinkedIn, Walk-in, Referral
            $table->string('rating', 10)->nullable()->after('source'); // shortlist, hold, reject
            $table->text('notes')->nullable()->after('rating');
            // Make job_posting_id nullable for standalone CV bank entries
            $table->unsignedBigInteger('job_posting_id')->nullable()->change();
        });
    }

    public function down(): void {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn(['is_cv_bank','position_applied','nationality','experience_years','skills','source','rating','notes']);
            $table->unsignedBigInteger('job_posting_id')->nullable(false)->change();
        });
    }
};
