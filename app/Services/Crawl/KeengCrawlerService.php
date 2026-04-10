<?php

namespace App\Services\Crawl;

use App\Models\CrawledTrack;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * Crawl keeng.vn: khung xử lý + parser HTML cơ bản.
 * Keeng có thể là SPA — sau khi khảo sát Network/API, bổ sung extractor JSON/API tại đây.
 */
class KeengCrawlerService
{
    public function __construct(
        private readonly CrawlHttpClient $http,
    ) {}

    /**
     * Thu thập link nội bộ keeng.vn từ HTML (trang listing / shell).
     *
     * @return list<string>
     */
    public function collectInternalLinks(string $html, string $pageUrl): array
    {
        $baseHost = parse_url((string) config('crawl.keeng.base_url'), PHP_URL_HOST);
        if (! is_string($baseHost) || $baseHost === '') {
            $baseHost = 'keeng.vn';
        }

        $crawler = new Crawler($html);
        $out = [];

        $crawler->filter('a[href]')->each(function (Crawler $node) use (&$out, $baseHost, $pageUrl) {
            $href = $node->attr('href');
            if (! is_string($href)) {
                return;
            }
            $absolute = $this->absolutizeKeengUrl($href, $pageUrl);
            if ($absolute === null) {
                return;
            }
            $host = parse_url($absolute, PHP_URL_HOST);
            if ($host !== $baseHost) {
                return;
            }
            // Bỏ qua asset / API thô nếu nhận diện được
            if (preg_match('/\.(js|mjs|css|png|jpg|jpeg|gif|webp|svg|ico|woff2?)(\?|$)/i', $absolute)) {
                return;
            }
            $out[$absolute] = true;
        });

        return array_keys($out);
    }

    /**
     * Parse một trang chi tiết: meta, JSON-LD, regex MP3 trong HTML.
     * Các selector chi tiết (ca sĩ, nhạc sĩ, lyric) cần điều chỉnh sau khi khảo sát DOM thực tế.
     *
     * @return array{
     *   title: string,
     *   canonical_url: string,
     *   external_id: ?string,
     *   performers: list<string>,
     *   composers: list<string>,
     *   mp3_source_url: ?string,
     *   lyric: ?string,
     *   raw_payload: array
     * }
     */
    public function parseTrackPage(string $html, string $pageUrl): array
    {
        $crawler = new Crawler($html);

        $title = $this->firstMetaContent($crawler, 'property', 'og:title')
            ?? $this->firstMetaContent($crawler, 'name', 'twitter:title')
            ?? $this->firstText($crawler, 'title');

        $canonical = $this->firstMetaContent($crawler, 'property', 'og:url')
            ?? $this->firstLinkHref($crawler, 'link[rel="canonical"]')
            ?? $pageUrl;

        $performers = [];
        $composers = [];
        $lyric = $this->tryExtractLyric($crawler, $html);

        $ld = $this->extractJsonLd($crawler);
        if ($ld !== []) {
            $this->mergeSchemaOrgAudio($ld, $performers, $composers, $title);
        }

        $mp3 = $this->findMp3UrlInHtml($html);
        $externalId = $this->guessExternalIdFromUrl($canonical);

        $fallbackTitle = basename((string) (parse_url($canonical, PHP_URL_PATH) ?: '')) ?: $canonical;

        return [
            'title' => ($title !== null && $title !== '') ? trim($title) : $fallbackTitle,
            'canonical_url' => $canonical,
            'external_id' => $externalId,
            'performers' => array_values(array_unique(array_filter($performers))),
            'composers' => array_values(array_unique(array_filter($composers))),
            'mp3_source_url' => $mp3,
            'lyric' => $lyric,
            'raw_payload' => [
                'json_ld_snippets' => $ld,
                'parsed_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Tải MP3 về disk (disk crawled_audio), trả về đường dẫn relative trong disk.
     */
    public function downloadMp3(string $sourceUrl, string $filenameBase): ?string
    {
        $safe = Str::slug(Str::limit($filenameBase, 80, ''), '_');
        if ($safe === '') {
            $safe = 'track';
        }
        $subdir = 'keeng/'.substr(hash('sha256', $sourceUrl), 0, 16);
        $relative = $subdir.'/'.$safe.'.mp3';
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('crawled_audio');
        $full = $disk->path($relative);

        try {
            $this->http->downloadToPath($sourceUrl, $full);

            return $relative;
        } catch (Throwable $e) {
            Log::warning('keeng.mp3.download_skipped', [
                'url' => $sourceUrl,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function upsertTrack(array $parsed, bool $withMp3Download): CrawledTrack
    {
        $mp3Path = null;
        $mp3Url = $parsed['mp3_source_url'] ?? null;
        if ($withMp3Download && is_string($mp3Url) && $mp3Url !== '') {
            $mp3Path = $this->downloadMp3($mp3Url, (string) $parsed['title']);
        }

        return CrawledTrack::updateOrCreate(
            [
                'source' => CrawledTrack::SOURCE_KEENG,
                'url_hash' => hash('sha256', CrawledTrack::SOURCE_KEENG.'|'.$parsed['canonical_url']),
            ],
            [
                'external_id' => $parsed['external_id'],
                'title' => $parsed['title'],
                'canonical_url' => $parsed['canonical_url'],
                'performers' => $parsed['performers'],
                'composers' => $parsed['composers'],
                'mp3_source_url' => $mp3Url,
                'mp3_storage_path' => $mp3Path,
                'lyric' => $parsed['lyric'],
                'raw_payload' => $parsed['raw_payload'],
                'crawled_at' => now(),
            ]
        );
    }

    /**
     * Crawl một URL: GET HTML → parse → lưu DB; tùy chọn tải MP3.
     */
    public function crawlSingleUrl(string $url, bool $downloadMp3): CrawledTrack
    {
        $html = $this->http->get($url);
        $parsed = $this->parseTrackPage($html, $url);

        return $this->upsertTrack($parsed, $downloadMp3);
    }

    private function absolutizeKeengUrl(string $href, string $baseUrl): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, 'javascript:') || str_starts_with($href, '#')) {
            return null;
        }

        $base = rtrim((string) config('crawl.keeng.base_url'), '/');

        if (str_starts_with($href, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';

            return $scheme.':'.$href;
        }

        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        if (str_starts_with($href, '/')) {
            return $base.$href;
        }

        $path = parse_url($baseUrl, PHP_URL_PATH) ?: '/';
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
        if ($dir === '' || $dir === '.') {
            $dir = '';
        }

        return $base.($dir !== '' ? $dir.'/' : '/').$href;
    }

    private function firstMetaContent(Crawler $crawler, string $attr, string $value): ?string
    {
        $node = $crawler->filter("meta[{$attr}=\"{$value}\"]")->first();
        if ($node->count() === 0) {
            return null;
        }
        $c = $node->attr('content');

        return is_string($c) && $c !== '' ? $c : null;
    }

    private function firstLinkHref(Crawler $crawler, string $selector): ?string
    {
        $node = $crawler->filter($selector)->first();
        if ($node->count() === 0) {
            return null;
        }
        $h = $node->attr('href');

        return is_string($h) && $h !== '' ? $h : null;
    }

    private function firstText(Crawler $crawler, string $selector): ?string
    {
        $node = $crawler->filter($selector)->first();
        if ($node->count() === 0) {
            return null;
        }
        $t = trim($node->text(''));

        return $t !== '' ? $t : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractJsonLd(Crawler $crawler): array
    {
        $out = [];
        $crawler->filter('script[type="application/ld+json"]')->each(function (Crawler $n) use (&$out) {
            $raw = trim($n->text(''));
            if ($raw === '') {
                return;
            }
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $out[] = $decoded;
                }
            } catch (\JsonException) {
            }
        });

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  list<string>  $performers
     * @param  list<string>  $composers
     */
    private function mergeSchemaOrgAudio(array $blocks, array &$performers, array &$composers, ?string &$title): void
    {
        foreach ($blocks as $block) {
            foreach ($this->jsonLdFlattenGraph($block) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $type = $item['@type'] ?? null;
                if ($type === 'MusicRecording' || $type === 'Song') {
                    if (empty($title) && isset($item['name']) && is_string($item['name'])) {
                        $title = $item['name'];
                    }
                    $by = $item['byArtist'] ?? null;
                    if (is_array($by)) {
                        $name = $by['name'] ?? null;
                        if (is_string($name) && $name !== '') {
                            $performers[] = $name;
                        }
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $doc
     * @return list<mixed>
     */
    private function jsonLdFlattenGraph(array $doc): array
    {
        if (isset($doc['@graph']) && is_array($doc['@graph'])) {
            return array_values($doc['@graph']);
        }

        return [$doc];
    }

    private function findMp3UrlInHtml(string $html): ?string
    {
        if (preg_match('#https?://[^\s"\'<>]+\.mp3(\?[^\s"\'<>]*)?#i', $html, $m)) {
            return $m[0];
        }

        return null;
    }

    private function guessExternalIdFromUrl(string $url): ?string
    {
        if (preg_match('#/(\d+)(?:/|$|\?)#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Khung trích lyric — cần chỉnh selector theo DOM Keeng thực tế.
     */
    private function tryExtractLyric(Crawler $crawler, string $html): ?string
    {
        $selectors = [
            '[data-lyric]',
            '.lyric',
            '#lyric',
            '.song-lyric',
        ];
        foreach ($selectors as $sel) {
            try {
                $n = $crawler->filter($sel)->first();
                if ($n->count() > 0) {
                    $t = trim($n->text(''));
                    if ($t !== '') {
                        return $t;
                    }
                }
            } catch (\Exception) {
                // selector không hợp lệ hoặc không khớp — bỏ qua
            }
        }

        unset($html);

        return null;
    }
}
