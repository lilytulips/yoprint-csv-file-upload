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
        Schema::create('file_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('file_hash')->unique(); // For idempotency check
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('total_rows')->nullable();
            $table->integer('processed_rows')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_uploads');
    }
};
