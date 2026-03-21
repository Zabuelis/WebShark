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
            $table->id('packet_id');
            $table->uuid('redis_id');
            $table->string('l3_protocol')->nullable(true);
            $table->string('l4_protocol')->nullable(true);
            $table->string('l7_protocol')->nullable(true);
            $table->string('src_ip')->nullable(true);
            $table->string('dst_ip')->nullable(true);
            $table->integer('src_port')->nullable(true);
            $table->integer('dst_port')->nullable(true);
            $table->string('tcp_flag')->nullable(true);
            $table->integer('tcp_window')->nullable(true);
            $table->integer('original_packet_length')->nullable(true);
            $table->integer('captured_packet_length')->nullable(true);
            $table->decimal('timestamp', 20, 6)->nullable();
            $table->text('raw_hex')->nullable();
            
            $table->foreign('redis_id')->references('redis_id')->on('redis_job')->onDelete('cascade');
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
