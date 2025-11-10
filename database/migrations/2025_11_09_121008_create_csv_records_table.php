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
        Schema::create('csv_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_upload_id')->constrained()->onDelete('cascade');
            $table->string('unique_key')->unique(); // UNIQUE_KEY from CSV - used for UPSERT
            $table->text('product_title')->nullable();
            $table->text('product_description')->nullable();
            $table->string('style_number')->nullable(); // STYLE#
            $table->string('sanmar_mainframe_color')->nullable();
            $table->string('size')->nullable();
            $table->string('color_name')->nullable();
            $table->decimal('piece_price', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('csv_records');
    }
};
