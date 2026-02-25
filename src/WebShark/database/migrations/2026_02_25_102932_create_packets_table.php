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
        Schema::create('packet', function (Blueprint $table) {
            $table->id();
            $table->string('src_ip');
            $table->string('dst_ip');
            $table->integer('src_port');
            $table->integer('dst_port');
            $table->string('tcp_flag');
            $table->integer('tcp_window');
            $table->integer('original_packet_length');
            $table->integer('captured_packet_length');
            $table->time('timestamp', precision: 0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packet');
    }
};
