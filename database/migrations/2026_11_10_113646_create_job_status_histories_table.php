<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('production_jobs')->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['job_id', 'created_at']);
        });

        DB::statement(
            "ALTER TABLE job_status_histories ADD CONSTRAINT job_status_histories_from_status_check
             CHECK (from_status IS NULL OR from_status IN
               ('pending', 'cutting', 'edge_banding', 'assembly', 'ready_for_install', 'installed'))"
        );
        DB::statement(
            "ALTER TABLE job_status_histories ADD CONSTRAINT job_status_histories_to_status_check
             CHECK (to_status IN
               ('pending', 'cutting', 'edge_banding', 'assembly', 'ready_for_install', 'installed'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('job_status_histories');
    }
};
