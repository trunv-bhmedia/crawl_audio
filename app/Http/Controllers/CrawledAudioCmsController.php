<?php

namespace App\Http\Controllers;

use App\Models\Songs;
use App\Models\Video;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class CrawledAudioCmsController extends Controller
{
    private int $perPage = 25;

    /** Tối đa số file trong một lần tải ZIP (tránh timeout). */
    private int $zipMaxFiles = 80;

    public function index(Request $request): View
    {
        $activeView = $request->string('view')->toString() ?: 'songs';

        $songsQuery = Songs::query()->orderByDesc('id');
        $this->applySongFilters($songsQuery, $request);

        $videosQuery = Video::query()->orderByDesc('id');
        $this->applyVideoFilters($videosQuery, $request);

        $songsPaginator = (clone $songsQuery)->paginate($this->perPage, ['*'], 'songs_page')->withQueryString();
        $videosPaginator = (clone $videosQuery)->paginate($this->perPage, ['*'], 'videos_page')->withQueryString();

        $songTotalFiltered = (clone $songsQuery)->count();
        $videoTotalFiltered = (clone $videosQuery)->count();

        $sourceOptions = [
            '' => 'Tất cả',
            'keeng' => 'Keeng',
            'imuzik' => 'iMuzik',
        ];

        $mockArtists = [
            [
                'name' => 'Sơn Tùng M-TP',
                'sources' => ['keeng', 'imuzik'],
                'tracks_count' => 128,
                'latest_crawl_at' => '2026-04-10 09:30',
            ],
            [
                'name' => 'My Tam',
                'sources' => ['keeng'],
                'tracks_count' => 64,
                'latest_crawl_at' => '2026-04-09 21:10',
            ],
            [
                'name' => 'Đen Vâu',
                'sources' => ['imuzik'],
                'tracks_count' => 52,
                'latest_crawl_at' => '2026-04-08 18:45',
            ],
        ];

        $filterArtistSource = $request->string('artist_source')->toString();
        $filterArtistQ = $request->string('artist_q')->toString();
        $filterArtistMinTracks = (int) ($request->string('artist_min_tracks')->toString() ?: 0);

        $filteredArtists = collect($mockArtists)
            ->when($filterArtistSource !== '', fn (Collection $c) => $c->filter(fn (array $a) => in_array($filterArtistSource, (array) ($a['sources'] ?? []), true)))
            ->when($filterArtistQ !== '', function (Collection $c) use ($filterArtistQ) {
                $q = mb_strtolower($filterArtistQ);

                return $c->filter(fn (array $a) => str_contains(mb_strtolower((string) ($a['name'] ?? '')), $q));
            })
            ->when($filterArtistMinTracks > 0, fn (Collection $c) => $c->filter(fn (array $a) => (int) ($a['tracks_count'] ?? 0) >= $filterArtistMinTracks))
            ->values();

        $songWithUrlCount = (clone $songsQuery)->whereNotNull('download_url')->where('download_url', '!=', '')->count();
        $videoWithUrlCount = (clone $videosQuery)->whereNotNull('download_url')->where('download_url', '!=', '')->count();

        return view('crawled-audio-cms.index', [
            'activeView' => $activeView,
            'sourceOptions' => $sourceOptions,
            'songsPaginator' => $songsPaginator,
            'videosPaginator' => $videosPaginator,
            'songTotalFiltered' => $songTotalFiltered,
            'videoTotalFiltered' => $videoTotalFiltered,
            'songWithUrlCount' => $songWithUrlCount,
            'videoWithUrlCount' => $videoWithUrlCount,
            'filteredArtists' => $filteredArtists,
            'filterArtistSource' => $filterArtistSource,
            'filterArtistQ' => $filterArtistQ,
            'filterArtistMinTracks' => $filterArtistMinTracks,
            'zipMaxFiles' => $this->zipMaxFiles,
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'type' => 'required|in:song,video',
            'id' => 'required|integer',
        ]);

        $model = match ($data['type']) {
            'song' => Songs::query()->find($data['id']),
            'video' => Video::query()->find($data['id']),
        };

        $url = $model?->download_url;
        if (! $model || $url === null || trim((string) $url) === '') {
            abort(404, 'Không tìm thấy bản ghi hoặc chưa có link tải.');
        }

        $filename = $this->buildSafeFilename(
            (string) ($model->name ?? 'download'),
            (string) $url,
            $data['type']
        );

        return response()->streamDownload(function () use ($url) {
            $client = new Client([
                'timeout' => 0,
                'http_errors' => false,
                'verify' => true,
            ]);
            $res = $client->get($url, ['stream' => true]);
            if ($res->getStatusCode() >= 400) {
                return;
            }
            $body = $res->getBody();
            while (! $body->eof()) {
                echo $body->read(8192);
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, $filename, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    public function downloadZip(Request $request): BinaryFileResponse|RedirectResponse
    {
        $request->validate([
            'scope' => 'required|in:songs,videos',
        ]);

        $scope = $request->string('scope')->toString();

        if ($scope === 'songs') {
            $query = Songs::query()->orderBy('id');
            $this->applySongFilters($query, $request);
            $kind = 'song';
        } else {
            $query = Video::query()->orderBy('id');
            $this->applyVideoFilters($query, $request);
            $kind = 'video';
        }

        $rows = $query
            ->whereNotNull('download_url')
            ->where('download_url', '!=', '')
            ->limit($this->zipMaxFiles)
            ->get();

        if ($rows->isEmpty()) {
            return redirect()
                ->route('crawled-audio-cms.index', array_merge($request->except(['scope']), [
                    'view' => $scope === 'songs' ? 'songs' : 'videos',
                ]))
                ->with('error', 'Không có bản ghi nào có link tải (trong giới hạn bộ lọc).');
        }

        $zipBasename = 'crawl_'.$scope.'_'.date('Ymd_His');
        $zipPath = tempnam(sys_get_temp_dir(), 'ca_zip_');
        if ($zipPath === false) {
            abort(500, 'Không tạo được file tạm.');
        }
        unlink($zipPath);
        $zipPath .= '.zip';

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Không tạo được file ZIP.');
        }

        $client = new Client([
            'timeout' => 120,
            'http_errors' => false,
            'verify' => true,
        ]);

        $tempFiles = [];
        $usedNames = [];
        $added = 0;

        foreach ($rows as $model) {
            $url = trim((string) $model->download_url);
            if ($url === '') {
                continue;
            }

            $filename = $this->buildSafeFilename(
                (string) ($model->name ?? 'file'),
                $url,
                $kind
            );

            $uniqueName = $filename;
            $n = 1;
            while (isset($usedNames[$uniqueName])) {
                $base = pathinfo($filename, PATHINFO_FILENAME);
                $ext = pathinfo($filename, PATHINFO_EXTENSION) ?: ($kind === 'video' ? 'mp4' : 'mp3');
                $uniqueName = $base.'-'.$n.'.'.$ext;
                $n++;
            }
            $usedNames[$uniqueName] = true;

            $tmp = tempnam(sys_get_temp_dir(), 'dl_');
            if ($tmp === false) {
                continue;
            }

            $res = $client->get($url, ['sink' => $tmp]);
            if ($res->getStatusCode() >= 400 || ! is_file($tmp) || filesize($tmp) === 0) {
                @unlink($tmp);

                continue;
            }

            if (! $zip->addFile($tmp, $uniqueName)) {
                @unlink($tmp);

                continue;
            }

            $tempFiles[] = $tmp;
            $added++;
        }

        $zip->close();

        foreach ($tempFiles as $t) {
            @unlink($t);
        }

        if ($added === 0 || ! is_file($zipPath) || filesize($zipPath) === 0) {
            @unlink($zipPath);

            return redirect()
                ->route('crawled-audio-cms.index', array_merge($request->except(['scope']), [
                    'view' => $scope === 'songs' ? 'songs' : 'videos',
                ]))
                ->with('error', 'Khong tai duoc noi dung tu URL (kiem tra link hoac thu tai tung file).');
        }

        return response()->download($zipPath, $zipBasename.'.zip')->deleteFileAfterSend(true);
    }

    private function applySongFilters(Builder $songsQuery, Request $request): void
    {
        if ($q = trim($request->string('song_q')->toString())) {
            $songsQuery->where(function (Builder $sub) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $sub->where('name', 'like', $like)
                    ->orWhere('name_singer', 'like', $like)
                    ->orWhere('author_name', 'like', $like);
            });
        }

        match ($request->string('song_has_url')->toString()) {
            '1' => $songsQuery->whereNotNull('download_url')->where('download_url', '!=', ''),
            '0' => $songsQuery->where(function (Builder $sub) {
                $sub->whereNull('download_url')->orWhere('download_url', '');
            }),
            default => null,
        };

        match ($request->string('song_has_lyric')->toString()) {
            '1' => $songsQuery->whereNotNull('lyric')->whereRaw('TRIM(lyric) != ?', ['']),
            '0' => $songsQuery->where(function (Builder $sub) {
                $sub->whereNull('lyric')->orWhereRaw('TRIM(lyric) = ?', ['']);
            }),
            default => null,
        };
    }

    private function applyVideoFilters(Builder $videosQuery, Request $request): void
    {
        if ($q = trim($request->string('video_q')->toString())) {
            $videosQuery->where(function (Builder $sub) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $sub->where('name', 'like', $like)
                    ->orWhere('name_singer', 'like', $like);
            });
        }

        match ($request->string('video_has_url')->toString()) {
            '1' => $videosQuery->whereNotNull('download_url')->where('download_url', '!=', ''),
            '0' => $videosQuery->where(function (Builder $sub) {
                $sub->whereNull('download_url')->orWhere('download_url', '');
            }),
            default => null,
        };
    }

    private function buildSafeFilename(string $title, string $downloadUrl, string $type): string
    {
        $base = Str::slug($title) ?: 'file';
        $path = parse_url($downloadUrl, PHP_URL_PATH) ?: '';
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $ext = $ext ? Str::lower(preg_replace('/[^a-zA-Z0-9]/', '', $ext) ?? '') : '';
        if ($ext === '') {
            $ext = $type === 'video' ? 'mp4' : 'mp3';
        }
        if (strlen($ext) > 8) {
            $ext = $type === 'video' ? 'mp4' : 'mp3';
        }

        return $base.'.'.$ext;
    }
}
