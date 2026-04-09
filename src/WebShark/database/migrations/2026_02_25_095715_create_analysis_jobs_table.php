<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('analysis_job', function (Blueprint $table) {
            $table->uuid('analysis_id')->unique();
            $table->string('file_path');
            $table->dateTime('expires_at', precision: 0)->nullable(true);
            $table->string('status');
            $table->text('error_message')->nullable()->after('status');
            $table->integer('progress_percentage')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_job');
    }
};
