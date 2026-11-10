<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->index('order_id');
            $table->index(['installer_id', 'status']);
            $table->index('status');
        });

        DB::statement(
            "ALTER TABLE production_jobs ADD CONSTRAINT production_jobs_status_check
             CHECK (status IN ('pending', 'cutting', 'edge_banding', 'assembly', 'ready_for_install', 'installed'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('production_jobs');
    }
};
