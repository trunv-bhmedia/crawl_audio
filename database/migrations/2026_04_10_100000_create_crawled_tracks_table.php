<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng lưu metadata + đường dẫn file MP3 đã tải (theo nguồn crawl).
     */
    public function up(): void
    {
        Schema::create('crawled_tracks', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32)->index();
            $table->string('external_id')->nullable()->index();
            $table->string('title')->default('');
            $table->text('canonical_url');
            $table->char('url_hash', 64);
            $table->json('performers')->nullable();
            $table->json('composers')->nullable();
            $table->text('mp3_source_url')->nullable();
            $table->string('mp3_storage_path', 1024)->nullable();
            $table->longText('lyric')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('crawled_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'url_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawled_tracks');
    }
};
