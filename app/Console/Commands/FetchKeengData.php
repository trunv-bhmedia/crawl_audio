<?php

namespace App\Console\Commands;

use App\Http\Controllers\FetchKengController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchKeengData extends Command
{
    protected $signature = 'keeng:fetch
                            {target : Loại data cần fetch (song/video/all)}';

    protected $description = 'Fetch data từ Keeng API và lưu vào database';
    # Fetch song
    // php artisan keeng:fetch song

    // # Fetch video
    // php artisan keeng:fetch video

    // # Fetch tất cả
    // php artisan keeng:fetch all
    public function handle(FetchKengController $controller): int
    {
        ini_set('memory_limit', '256M');

        $target = $this->argument('target');
        $start  = now();

        $this->info("🎵 Bắt đầu fetch [{$target}]...");
        $this->newLine();

        try {
            $response = match ($target) {
                'song'  => $controller->fetchSongs(),
                'video' => $controller->fetchVideos(),
                'all'   => $controller->fetchAllKeeng(),
                default => throw new \InvalidArgumentException("Target không hợp lệ: [{$target}]. Dùng: song | video | all"),
            };

            $data = json_decode($response->getContent(), true);

            // fetchAllKeeng trả về nested {songs: {}, videos: {}}
            // fetchSongs/fetchVideos trả về flat {inserted: 0, updated: 0}
            if (isset($data['songs'])) {
                $this->table(
                    ['Bảng', 'Inserted', 'Updated'],
                    [
                        ['songs',  $data['songs']['inserted']  ?? 0, $data['songs']['updated']  ?? 0],
                        ['videos', $data['videos']['inserted'] ?? 0, $data['videos']['updated'] ?? 0],
                    ]
                );
            } else {
                $this->table(
                    ['Inserted', 'Updated'],
                    [[$data['inserted'] ?? 0, $data['updated'] ?? 0]]
                );
            }

            $elapsed = now()->diffInSeconds($start);
            $memory  = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

            $this->newLine();
            $this->info("✅ Hoàn thành trong {$elapsed}s | 💾 Memory peak: {$memory} MB");

            return self::SUCCESS;

        } catch (\InvalidArgumentException $e) {
            $this->error("❌ " . $e->getMessage());
            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error("❌ Lỗi: " . $e->getMessage());
            Log::error('FetchKeengData failed', [
                'target' => $target,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }
}
