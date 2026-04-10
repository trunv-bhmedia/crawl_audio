<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý dữ liệu crawl</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    @php
        $activeView = request()->string('view')->toString() ?: 'songs'; // songs|artists

        $sourceOptions = [
            '' => 'Tất cả',
            'keeng' => 'Keeng',
            'imuzik' => 'iMuzik',
        ];

        $mockSongs = [
            [
                'source' => 'keeng',
                'title' => 'Em của ngày hôm qua',
                'performers' => ['Sơn Tùng M-TP'],
                'composers' => ['Sơn Tùng M-TP'],
                'canonical_url' => 'https://keeng.vn/bai-hat/em-cua-ngay-hom-qua.html',
                'mp3_source_url' => 'https://cdn.example.com/audio/em-cua-ngay-hom-qua.mp3',
                'lyric' => true,
                'mp3_storage_path' => null,
            ],
            [
                'source' => 'imuzik',
                'title' => 'Nơi này có anh',
                'performers' => ['Sơn Tùng M-TP'],
                'composers' => ['Sơn Tùng M-TP'],
                'canonical_url' => 'https://imuzik.vn/bai-hat/noi-nay-co-anh.html',
                'mp3_source_url' => null,
                'lyric' => false,
                'mp3_storage_path' => 'tracks/noi-nay-co-anh.mp3',
            ],
            [
                'source' => 'keeng',
                'title' => 'Chúng ta của hiện tại',
                'performers' => ['Sơn Tùng M-TP'],
                'composers' => ['Sơn Tùng M-TP'],
                'canonical_url' => 'https://keeng.vn/bai-hat/chung-ta-cua-hien-tai.html',
                'mp3_source_url' => 'https://cdn.example.com/audio/chung-ta-cua-hien-tai.mp3',
                'lyric' => true,
                'mp3_storage_path' => 'tracks/chung-ta-cua-hien-tai.mp3',
            ],
        ];

        $mockArtists = [
            [
                'name' => 'Sơn Tùng M-TP',
                'sources' => ['keeng', 'imuzik'],
                'tracks_count' => 128,
                'latest_crawl_at' => '2026-04-10 09:30',
            ],
            [
                'name' => 'Mỹ Tâm',
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

        // Nếu sau này controller vẫn truyền $tracks (paginate/collection), mình convert sang shape chung.
        $songs = collect(isset($tracks) ? $tracks : $mockSongs)->map(function ($row) {
            if (is_array($row)) {
                return $row;
            }

            return [
                'source' => (string) data_get($row, 'source', ''),
                'title' => (string) data_get($row, 'title', ''),
                'performers' => (array) data_get($row, 'performers', []),
                'composers' => (array) data_get($row, 'composers', []),
                'canonical_url' => (string) data_get($row, 'canonical_url', ''),
                'mp3_source_url' => data_get($row, 'mp3_source_url'),
                'lyric' => filled(data_get($row, 'lyric')),
                'mp3_storage_path' => data_get($row, 'mp3_storage_path'),
            ];
        });

        $filterSongSource = request()->string('song_source')->toString();
        $filterSongQ = request()->string('song_q')->toString();
        $filterSongHasUrl = request()->string('song_has_url')->toString(); // '', '1', '0'
        $filterSongHasFile = request()->string('song_has_file')->toString(); // '', '1', '0'

        $filteredSongs = $songs
            ->when($filterSongSource !== '', fn ($c) => $c->filter(fn ($s) => ($s['source'] ?? '') === $filterSongSource))
            ->when($filterSongQ !== '', function ($c) use ($filterSongQ) {
                $q = mb_strtolower($filterSongQ);
                return $c->filter(function ($s) use ($q) {
                    $haystack = mb_strtolower(trim(($s['title'] ?? '') . ' ' . implode(' ', (array) ($s['performers'] ?? []))));
                    return $haystack !== '' && str_contains($haystack, $q);
                });
            })
            ->when($filterSongHasUrl !== '', function ($c) use ($filterSongHasUrl) {
                $want = $filterSongHasUrl === '1';
                return $c->filter(fn ($s) => filled($s['mp3_source_url'] ?? null) === $want);
            })
            ->when($filterSongHasFile !== '', function ($c) use ($filterSongHasFile) {
                $want = $filterSongHasFile === '1';
                return $c->filter(fn ($s) => filled($s['mp3_storage_path'] ?? null) === $want);
            })
            ->values();

        $artists = collect($mockArtists);
        $filterArtistSource = request()->string('artist_source')->toString();
        $filterArtistQ = request()->string('artist_q')->toString();
        $filterArtistMinTracks = (int) (request()->string('artist_min_tracks')->toString() ?: 0);

        $filteredArtists = $artists
            ->when($filterArtistSource !== '', fn ($c) => $c->filter(fn ($a) => in_array($filterArtistSource, (array) ($a['sources'] ?? []), true)))
            ->when($filterArtistQ !== '', function ($c) use ($filterArtistQ) {
                $q = mb_strtolower($filterArtistQ);
                return $c->filter(fn ($a) => str_contains(mb_strtolower((string) ($a['name'] ?? '')), $q));
            })
            ->when($filterArtistMinTracks > 0, fn ($c) => $c->filter(fn ($a) => (int) ($a['tracks_count'] ?? 0) >= $filterArtistMinTracks))
            ->values();
    @endphp

    <div class="min-h-screen">
        <div class="w-full px-4 py-6 sm:px-6 lg:px-8 2xl:px-10">
            <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Quản lý dữ liệu crawl</h1>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ url('/') }}" class="text-sm font-medium text-blue-600 hover:underline">← Về trang chủ</a>
                </div>
            </header>

        @if (session('error'))
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                {{ session('error') }}
            </div>
        @endif
        @if (session('success'))
            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700" role="alert">
                {{ session('success') }}
            </div>
        @endif

            <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
                <aside class="lg:w-[320px] lg:flex-none">
                    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-4 py-4">
                            <div class="text-sm font-semibold text-slate-900">Menu</div>
                        </div>
                        <nav class="p-2">
                            <a
                                href="{{ route('crawled-audio-cms.index', array_filter(['view' => 'songs'])) }}"
                                class="{{ $activeView === 'songs' ? 'bg-blue-50 text-blue-700 ring-1 ring-blue-100' : 'text-slate-700 hover:bg-slate-50' }} flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium"
                            >
                                <span>Danh sách bài hát</span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $filteredSongs->count() }}</span>
                            </a>
                            <a
                                href="{{ route('crawled-audio-cms.index', array_filter(['view' => 'artists'])) }}"
                                class="{{ $activeView === 'artists' ? 'bg-blue-50 text-blue-700 ring-1 ring-blue-100' : 'text-slate-700 hover:bg-slate-50' }} mt-1 flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium"
                            >
                                <span>Danh sách nghệ sĩ/ca sĩ</span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $filteredArtists->count() }}</span>
                            </a>
                        </nav>
                    </div>
                </aside>

                <main class="min-w-0 lg:flex-1">
                    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-4 py-4 sm:px-6">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">
                                        {{ $activeView === 'artists' ? 'Danh sách nghệ sĩ/ca sĩ' : 'Danh sách bài hát' }}
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500">
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if ($activeView === 'artists')
                            <form method="get" action="{{ route('crawled-audio-cms.index') }}" class="border-b border-slate-100 px-4 py-4 sm:px-6">
                                <input type="hidden" name="view" value="artists">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <label for="artist_source" class="block text-xs font-medium text-slate-600">Site</label>
                                        <select name="artist_source" id="artist_source" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
                                            @foreach ($sourceOptions as $value => $label)
                                                <option value="{{ $value }}" @selected($filterArtistSource === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="sm:col-span-1 lg:col-span-2">
                                        <label for="artist_q" class="block text-xs font-medium text-slate-600">Từ khóa</label>
                                        <input
                                            name="artist_q"
                                            id="artist_q"
                                            value="{{ $filterArtistQ }}"
                                            placeholder="VD: sơn tùng, mỹ tâm..."
                                            class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600"
                                        >
                                    </div>
                                    <div>
                                        <label for="artist_min_tracks" class="block text-xs font-medium text-slate-600">Tối thiểu bài</label>
                                        <input
                                            name="artist_min_tracks"
                                            id="artist_min_tracks"
                                            value="{{ $filterArtistMinTracks ?: '' }}"
                                            inputmode="numeric"
                                            placeholder="VD: 50"
                                            class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600"
                                        >
                                    </div>
                                </div>
                                <div class="mt-4 flex flex-wrap items-center gap-3">
                                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                                        Lọc
                                    </button>
                                    <a href="{{ route('crawled-audio-cms.index', ['view' => 'artists']) }}" class="text-sm font-medium text-slate-600 hover:underline">Reset</a>
                                </div>
                            </form>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                        <tr>
                                            <th class="px-4 py-3 sm:px-6">Nghệ sĩ</th>
                                            <th class="px-4 py-3">Site</th>
                                            <th class="px-4 py-3">Số bài</th>
                                            <th class="px-4 py-3">Crawl gần nhất</th>
                                            <th class="px-4 py-3 w-40">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse ($filteredArtists as $artist)
                                            <tr class="hover:bg-slate-50/80">
                                                <td class="px-4 py-3 sm:px-6">
                                                    <div class="font-medium text-slate-900">{{ $artist['name'] }}</div>
                                                    <div class="mt-0.5 text-xs text-slate-500">Trang chi tiết (mock) • Sẽ liên kết sau</div>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="flex flex-wrap gap-1.5">
                                                        @foreach ((array) ($artist['sources'] ?? []) as $src)
                                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ $sourceOptions[$src] ?? $src }}</span>
                                                        @endforeach
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 font-medium text-slate-800">{{ number_format((int) ($artist['tracks_count'] ?? 0)) }}</td>
                                                <td class="px-4 py-3 text-slate-600">{{ (string) ($artist['latest_crawl_at'] ?? '—') }}</td>
                                                <td class="px-4 py-3">
                                                    <button type="button" class="w-full rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-1">
                                                        Xem bài hát
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-4 py-12 text-center text-slate-500 sm:px-6">
                                                    Không có nghệ sĩ phù hợp bộ lọc.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <form method="get" action="{{ route('crawled-audio-cms.index') }}" class="border-b border-slate-100 px-4 py-4 sm:px-6">
                                <input type="hidden" name="view" value="songs">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <label for="song_source" class="block text-xs font-medium text-slate-600">Site</label>
                                        <select name="song_source" id="song_source" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
                                            @foreach ($sourceOptions as $value => $label)
                                                <option value="{{ $value }}" @selected($filterSongSource === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="sm:col-span-1 lg:col-span-2">
                                        <label for="song_q" class="block text-xs font-medium text-slate-600">Từ khóa</label>
                                        <input
                                            name="song_q"
                                            id="song_q"
                                            value="{{ $filterSongQ }}"
                                            placeholder="VD: tên bài, ca sĩ..."
                                            class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600"
                                        >
                                    </div>
                                    <div>
                                        <label for="song_has_url" class="block text-xs font-medium text-slate-600">URL audio</label>
                                        <select name="song_has_url" id="song_has_url" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
                                            <option value="" @selected($filterSongHasUrl === '')>Tất cả</option>
                                            <option value="1" @selected($filterSongHasUrl === '1')>Có URL</option>
                                            <option value="0" @selected($filterSongHasUrl === '0')>Thiếu URL</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="song_has_file" class="block text-xs font-medium text-slate-600">File local</label>
                                        <select name="song_has_file" id="song_has_file" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
                                            <option value="" @selected($filterSongHasFile === '')>Tất cả</option>
                                            <option value="1" @selected($filterSongHasFile === '1')>Đã lưu</option>
                                            <option value="0" @selected($filterSongHasFile === '0')>Chưa lưu</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-4 flex flex-wrap items-center gap-3">
                                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                                        Lọc
                                    </button>
                                    <a href="{{ route('crawled-audio-cms.index', ['view' => 'songs']) }}" class="text-sm font-medium text-slate-600 hover:underline">Reset</a>
                                </div>
                            </form>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                        <tr>
                                            <th class="px-4 py-3 sm:px-6">Site</th>
                                            <th class="px-4 py-3">Tiêu đề</th>
                                            <th class="px-4 py-3 hidden lg:table-cell">Trình bày</th>
                                            <th class="px-4 py-3 hidden xl:table-cell">Nhạc sĩ</th>
                                            <th class="px-4 py-3">URL audio</th>
                                            <th class="px-4 py-3 hidden md:table-cell">Lyric</th>
                                            <th class="px-4 py-3">File local</th>
                                            <th class="px-4 py-3 w-48">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse ($filteredSongs as $song)
                                            @php
                                                $hasUrl = filled($song['mp3_source_url'] ?? null);
                                                $hasFile = filled($song['mp3_storage_path'] ?? null);
                                                $performers = implode(', ', (array) ($song['performers'] ?? []));
                                                $composers = implode(', ', (array) ($song['composers'] ?? []));
                                                $canonicalUrl = (string) ($song['canonical_url'] ?? '');
                                            @endphp
                                            <tr class="hover:bg-slate-50/80">
                                                <td class="px-4 py-3 font-medium text-slate-800 sm:px-6">{{ $sourceOptions[$song['source'] ?? ''] ?? ($song['source'] ?? '—') }}</td>
                                                <td class="px-4 py-3 max-w-xs">
                                                    <div class="font-medium text-slate-900 line-clamp-2">{{ $song['title'] ?? '—' }}</div>
                                                    @if ($canonicalUrl !== '')
                                                        <a href="{{ $canonicalUrl }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-block text-xs text-blue-600 hover:underline">Mở trang gốc</a>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-slate-600 hidden lg:table-cell max-w-[10rem] line-clamp-3">{{ $performers !== '' ? $performers : '—' }}</td>
                                                <td class="px-4 py-3 text-slate-600 hidden xl:table-cell max-w-[10rem] line-clamp-3">{{ $composers !== '' ? $composers : '—' }}</td>
                                                <td class="px-4 py-3 max-w-[14rem]">
                                                    @if ($hasUrl)
                                                        <span class="text-green-600 text-xs font-medium">Có link</span>
                                                        <div class="mt-1 truncate text-xs text-slate-500" title="{{ $song['mp3_source_url'] }}">{{ $song['mp3_source_url'] }}</div>
                                                    @else
                                                        <span class="text-red-500 text-xs font-medium">Chưa có URL</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 hidden md:table-cell text-slate-600">
                                                    @if (!empty($song['lyric']))
                                                        <span class="text-green-600 text-xs font-medium">Có</span>
                                                    @else
                                                        <span class="text-slate-400">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3">
                                                    @if ($hasFile)
                                                        <span class="text-green-600 text-xs font-medium">Đã lưu</span>
                                                    @else
                                                        <span class="text-slate-500 text-xs">Chưa tải</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="flex flex-col gap-2">
                                                        <button type="button" class="w-full rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-1">
                                                            Tải về máy (mock)
                                                        </button>
                                                        @if (!$hasUrl)
                                                            <span class="text-xs text-slate-400">Không thể tải (thiếu URL)</span>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="px-4 py-12 text-center text-slate-500 sm:px-6">
                                                    Không có bài hát phù hợp bộ lọc.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </main>
            </div>
        </div>
    </div>
</body>
</html>
