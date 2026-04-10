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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('identify')->unique()->comment('Hash định danh video (vd: Juz0qOpQ)');
            $table->string('slug')->nullable()->comment('URL slug của video');
            $table->string('name')->nullable()->comment('tên video');
            $table->text('download_url')->nullable()->comment('URL tải video');
            $table->string('singer_id')->nullable()->comment('id ca sĩ');
            $table->string('name_singer')->nullable()->comment('tên ca sĩ');
            $table->text('image')->nullable()->comment('ảnh thumbnail video');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
