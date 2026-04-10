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
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->string('identify')->unique()->comment('Hash định danh bài (vd: 78DD0E6C)'); // unique ở DB
            $table->string('slug')->nullable();
            $table->string('name')->nullable();
            $table->text('download_url')->nullable();
            $table->string('image_url')->nullable();
            $table->string('singer_id')->nullable();
            $table->string('name_singer')->nullable();
            $table->string('author_name')->nullable();
            $table->text('lyric')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
