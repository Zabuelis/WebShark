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
        Schema::create('ip_marker', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address');
            $table->dateTime('expires_at', precision: 0);
            $table->integer('analyze_counter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_marker');
    }
};
