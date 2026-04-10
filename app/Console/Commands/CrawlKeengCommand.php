<?php

namespace App\Console\Commands;

use App\Services\Crawl\CrawlHttpClient;
use App\Services\Crawl\KeengCrawlerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CrawlKeengCommand extends Command
{
    protected $signature = 'crawl:keeng
                            {url? : URL bắt đầu (mặc định từ config crawl.keeng.entry_url)}
                            {--single : Chỉ crawl đúng URL đã cho, không thu thập link con}
                            {--no-mp3 : Không tải file MP3 về disk}
                            {--max= : Giới hạn số URL xử lý (mặc định theo config)}';

    protected $description = 'Crawl keeng.vn: thu thập link từ trang entry, parse metadata và lưu bảng crawled_tracks; tùy chọn tải MP3.';

    public function handle(CrawlHttpClient $http, KeengCrawlerService $keeng): int
    {
        $start = $this->argument('url') ?: config('crawl.keeng.entry_url');
        $downloadMp3 = ! $this->option('no-mp3');
        $single = (bool) $this->option('single');
        $maxOpt = $this->option('max');
        $max = $maxOpt !== null && $maxOpt !== ''
            ? max(1, (int) $maxOpt)
            : (int) config('crawl.max_links_per_page', 100);

        $this->info('Bắt đầu Keeng: '.$start);
        $this->line('Tải MP3: '.($downloadMp3 ? 'có' : 'không'));

        try {
            if ($single) {
                $track = $keeng->crawlSingleUrl((string) $start, $downloadMp3);
                $this->components->info('Đã lưu: '.$track->title.' (#'.$track->id.')');

                return self::SUCCESS;
            }

            $html = $http->get((string) $start);
            $links = $keeng->collectInternalLinks($html, (string) $start);
            $links = array_slice($links, 0, $max);

            $this->info('Số link thu được (sau giới hạn): '.count($links));

            $bar = $this->output->createProgressBar(count($links));
            $bar->start();

            $ok = 0;
            foreach ($links as $u) {
                try {
                    $keeng->crawlSingleUrl($u, $downloadMp3);
                    $ok++;
                } catch (Throwable $e) {
                    Log::error('crawl.keeng.page_failed', ['url' => $u, 'message' => $e->getMessage()]);
                    $this->newLine();
                    $this->components->error($u.' — '.$e->getMessage());
                }
                $bar->advance();
            }
            $bar->finish();
            $this->newLine(2);
            $this->components->info('Hoàn tất. URL thành công: '.$ok.'/'.count($links));

            return self::SUCCESS;
        } catch (Throwable $e) {
            Log::error('crawl.keeng.command_failed', ['message' => $e->getMessage()]);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
