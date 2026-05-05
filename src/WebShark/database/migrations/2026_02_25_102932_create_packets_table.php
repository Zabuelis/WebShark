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
            $table->uuid('analysis_id');
            $table->integer('packet_number');
            $table->string('l3_protocol')->nullable(true);
            $table->string('l4_protocol')->nullable(true);
            $table->string('src_ip')->nullable(true);
            $table->string('dst_ip')->nullable(true);
            $table->integer('src_port')->nullable(true);
            $table->integer('dst_port')->nullable(true);
            $table->string('tcp_flag')->nullable(true);
            $table->integer('flow')->nullable(true);
            $table->integer('tcp_window')->nullable(true);
            $table->bigInteger('tcp_ack_number')->nullable(true);
            $table->bigInteger('tcp_seq_number')->nullable(true);
            $table->integer('original_packet_length')->nullable(true);
            $table->integer('captured_packet_length')->nullable(true);
            $table->jsonb('l7_attributes')->nullable(true);
            $table->decimal('timestamp', 20, 6)->nullable();
            $table->text('raw_hex')->nullable();
            
            $table->foreign('analysis_id')->references('analysis_id')->on('analysis_job')->onDelete('cascade');
            $table->index(['analysis_id', 'packet_number']);
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
