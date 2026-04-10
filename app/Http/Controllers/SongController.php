<?php

namespace App\Http\Controllers;

use App\Models\Songs;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class SongController extends Controller
{
    protected $getListSongHot = 'https://vipservice.keeng.vn/KeengWSRestful///ws/common/getListSongHot';
    protected $getListSongNew = 'https://vipservice.keeng.vn/KeengWSRestful///ws/common/getListSongNewV4';

    protected int $perPage = 40;

    public function fetchSongs(string $type = 'all'): JsonResponse
    {
        $inserted = 0;
        $updated  = 0;

        $endpoints = [];
        if (in_array($type, ['all', 'hot'])) $endpoints[] = $this->getListSongHot;
        if (in_array($type, ['all', 'new'])) $endpoints[] = $this->getListSongNew;

        foreach ($endpoints as $endpoint) {
            // Xử lý từng trang, insert ngay, không giữ toàn bộ data trong memory
            $this->fetchAndSaveByPage($endpoint, $inserted, $updated);
        }

        return response()->json([
            'message'  => "Inserted: {$inserted}, Updated: {$updated}",
            'inserted' => $inserted,
            'updated'  => $updated,
        ]);
    }

    // -------------------------------------------------------------------------

    private function fetchAndSaveByPage(string $baseUrl, int &$inserted, int &$updated): void
    {
        $page = 1;

        do {
            $response = Http::timeout(15)->get($baseUrl, [
                'page' => $page,
                'num'  => $this->perPage,
            ]);

            if ($response->failed()) {
                Log::warning("Keeng API failed", ['url' => $baseUrl, 'page' => $page]);
                break;
            }

            $body  = $response->json();
            $items = $body['data'] ?? $body['result'] ?? [];

            if (empty($items)) break;

            // Insert ngay trang này rồi giải phóng memory
            $this->saveItems($items, $inserted, $updated);

            // Giải phóng response khỏi memory
            unset($response, $body, $items);

            $page++;

        } while (true); // điều kiện break nằm trong loop
    }

    private function saveItems(array $items, int &$inserted, int &$updated): void
    {
        // Dedup trong trang hiện tại (phòng API trả về trùng trong 1 trang)
        $seen = [];

        foreach ($items as $item) {
            $identify = $item['identify'] ?? null;

            if (!$identify || isset($seen[$identify])) continue;
            $seen[$identify] = true;

            $exists = Songs::where('identify', $identify)->exists();

            Songs::updateOrCreate(
                ['identify' => $identify],
                $this->mapSongData($item)
            );

            $exists ? $updated++ : $inserted++;
        }

        unset($seen);
    }

    private function mapSongData(array $item): array
    {
        $lyric = null;
        if (!empty($item['lyric'])) {
            $lyric = trim(html_entity_decode(strip_tags($item['lyric']), ENT_QUOTES, 'UTF-8'));
        }

        return [
            'identify'    => $item['identify'],
            'slug'        => $item['slug'] ?? null,
            'name'        => $item['name'] ?? null,
            'download_url'=> $item['download_url'] ?? $item['media_url_pre'] ?? null,
            'image_url'   => $item['image'] ?? $item['image310'] ?? null,
            'singer_id'   => $item['singer_id'] ?? null,
            'name_singer' => $item['singer'] ?? null,
            'author_name' => $item['info_extra']['author_name'] ?? null,
            'lyric'       => $lyric,
        ];
    }
}
