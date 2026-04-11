<?php

namespace App\Http\Controllers;

use App\Models\Songs;
use App\Models\Video;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class FetchKengController extends Controller
{
    private int $perPage = 40;

    private function getEndpointConfig(): array
    {
        return [
            'song' => [
                'endpoints' => [
                    'https://vipservice.keeng.vn/KeengWSRestful/ws/common/getListSongHot',
                    'https://vipservice.keeng.vn/KeengWSRestful/ws/common/getListSongNewV4',
                ],
                'model' => Songs::class,
                'map' => 'mapSongData',
            ],
            'video' => [
                'endpoints' => [
                    'https://vipservice.keeng.vn/KeengWSRestful/ws/common/getListVideoHot',
                    'https://vipservice.keeng.vn/KeengWSRestful/ws/common/getListVideoNewV4',
                ],
                'model' => Video::class,
                'map' => 'mapVideoData',
            ],
        ];
    }

    // -------------------------------------------------------------------------

    public function fetchSongs(): JsonResponse
    {
        $config = $this->getEndpointConfig()['song'];

        return $this->fetchMedia($config);
    }

    public function fetchVideos(): JsonResponse
    {
        $config = $this->getEndpointConfig()['video'];

        return $this->fetchMedia($config);
    }

    public function fetchAllKeeng(): JsonResponse
    {
        $songResult = json_decode($this->fetchSongs()->getContent(), true);
        $videoResult = json_decode($this->fetchVideos()->getContent(), true);

        return response()->json([
            'songs' => $songResult,
            'videos' => $videoResult,
        ]);
    }

    // -------------------------------------------------------------------------

    private function fetchMedia(array $config): JsonResponse
    {
        $inserted = 0;
        $updated = 0;

        // Chạy tuần tự qua từng endpoint trong config
        foreach ($config['endpoints'] as $url) {
            $page = 1;

            while (true) {
                $response = Http::timeout(15)->get($url, [
                    'page' => $page,
                    'num' => $this->perPage,
                ]);

                if ($response->failed()) {
                    Log::warning('Keeng API failed', ['url' => $url, 'page' => $page]);
                    break;
                }

                $body = $response->json();
                $items = $body['data'] ?? $body['result'] ?? [];

                if (empty($items)) {
                    break;
                }

                $mappedItems = array_map([$this, $config['map']], $items);
                $this->saveData($mappedItems, $config['model'], $inserted, $updated);

                unset($response, $body, $items, $mappedItems);
                $page++;
            }
        }

        return response()->json([
            'inserted' => $inserted,
            'updated' => $updated,
        ]);
    }

    // -------------------------------------------------------------------------

    private function saveData(array $items, string $model, int &$inserted, int &$updated): void
    {
        $seen = [];

        foreach ($items as $item) {
            $identify = $item['identify'] ?? null;
            if (! $identify || isset($seen[$identify])) {
                continue;
            }
            $seen[$identify] = true;

            $exists = $model::where('identify', $identify)->exists();

            $model::updateOrCreate(
                ['identify' => $identify],
                $item
            );

            $exists ? $updated++ : $inserted++;
        }

        unset($seen);
    }

    // -------------------------------------------------------------------------

    private function mapSongData(array $item): array
    {
        $identify = $item['info_extra']['identify'] ?? $item['identify'] ?? null;
        $lyric = null;
        if (! empty($item['lyric'])) {
            $lyric = trim(html_entity_decode(strip_tags($item['lyric']), ENT_QUOTES, 'UTF-8'));
        }

        return [
            'identify' => $identify,
            'slug' => $item['slug'] ?? null,
            'name' => $item['name'] ?? null,
            'download_url_web' => $item['download_url_web'] ?? null,
            'download_url' => $item['download_url'] ?? null,
            'image_url' => $item['image310'] ?? $item['image'] ?? null,
            'singer_id' => $item['singer_id'] ?? null,
            'name_singer' => $item['singer'] ?? null,
            'album_id' => $item['album_id'] ?? null,
            'author_name' => $item['info_extra']['author_name'] ?? null,
            'lyric' => $lyric,
        ];
    }

    private function mapVideoData(array $item): array
    {
        $identify = $item['info_extra']['identify'] ?? $item['identify'] ?? null;
        $slug = $item['info_extra']['slug'] ?? $item['slug'] ?? null;

        return [
            'identify' => $identify,
            'slug' => $slug,
            'name' => $item['name'] ?? null,
            'download_url_web' => $item['download_url_web'] ?? null,
            'download_url' => $item['download_url'] ?? null,
            'image_url' => $item['image310'] ?? $item['image'] ?? null,
            'singer_id' => $item['singer_id'] ?? null,
            'name_singer' => $item['singer'] ?? null,
            'song_identify' => $item['song_identify'] ?? null,
            'is_active' => $item['is_active'] ?? true,
        ];
    }
}
