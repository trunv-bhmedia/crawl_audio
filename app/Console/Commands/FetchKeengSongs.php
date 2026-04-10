<?php

namespace App\Console\Commands;

use App\Http\Controllers\SongController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchKeengSongs extends Command
{
    protected $signature = 'keeng:fetch-songs
                            {--type=all : Loại bài hát cần fetch (all/hot/new)}';

    protected $description = 'Fetch danh sách bài hát từ Keeng API và lưu vào database';

    public function handle(SongController $controller): int
    {
        // Tăng memory limit cho command này
        ini_set('memory_limit', '256M');

        $type = $this->option('type');

        $this->info("🎵 Bắt đầu fetch bài hát từ Keeng API... [type: {$type}]");
        $start = now();

        try {
            $response = $controller->fetchSongs($type);
            $data     = json_decode($response->getContent(), true);

            $elapsed = now()->diffInSeconds($start);

            $this->newLine();
            $this->table(
                ['Metric', 'Số lượng'],
                [
                    ['Inserted', $data['inserted'] ?? 0],
                    ['Updated',  $data['updated']  ?? 0],
                ]
            );

            $this->newLine();
            $this->info("✅ {$data['message']}");
            $this->line("⏱  Hoàn thành trong {$elapsed} giây");
            $this->line("💾 Memory peak: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Lỗi: " . $e->getMessage());
            Log::error("FetchKeengSongs failed", ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }
}