<?php

namespace App\Services\Crawl;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * HTTP client dùng chung cho crawl: User-Agent, timeout, nghỉ giữa các request.
 */
class CrawlHttpClient
{
    public function get(string $url): string
    {
        try {
            $response = Http::withHeaders($this->defaultHeaders())
                ->timeout((int) config('crawl.http_timeout_seconds', 60))
                ->get($url);

            $this->throttle();

            if (! $response->successful()) {
                throw new \RuntimeException('HTTP '.$response->status().' khi GET '.$url);
            }

            return (string) $response->body();
        } catch (Throwable $e) {
            Log::error('crawl.http.get_failed', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Tải nhị phân (MP3) về đường dẫn tuyệt đối trên disk.
     */
    public function downloadToPath(string $url, string $absolutePath): void
    {
        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            $response = Http::withHeaders($this->defaultHeaders())
                ->timeout((int) config('crawl.http_timeout_seconds', 120))
                ->sink($absolutePath)
                ->get($url);

            $this->throttle();

            if (! $response->successful()) {
                @unlink($absolutePath);
                throw new \RuntimeException('HTTP '.$response->status().' khi tải '.$url);
            }
        } catch (Throwable $e) {
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
            Log::error('crawl.http.download_failed', [
                'url' => $url,
                'path' => $absolutePath,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @return array<string, string>
     */
    private function defaultHeaders(): array
    {
        return [
            'User-Agent' => (string) config('crawl.user_agent'),
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'vi-VN,vi;q=0.9,en;q=0.8',
        ];
    }

    private function throttle(): void
    {
        $micro = (int) config('crawl.delay_microseconds', 500_000);
        if ($micro > 0) {
            usleep($micro);
        }
    }
}
