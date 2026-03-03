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
        Schema::create('redis_job', function (Blueprint $table) {
            $table->uuid('redis_id')->unique();
            $table->string('file_path');
            $table->dateTime('expires_at', precision: 0)->nullable(true);
            $table->string('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('redis_job');
    }
};
