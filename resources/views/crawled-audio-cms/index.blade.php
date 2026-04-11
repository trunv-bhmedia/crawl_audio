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
        $filterSongQ = request()->string('song_q')->toString();
        $filterSongHasUrl = request()->string('song_has_url')->toString();
        $filterSongHasLyric = request()->string('song_has_lyric')->toString();
        $filterVideoQ = request()->string('video_q')->toString();
        $filterVideoHasUrl = request()->string('video_has_url')->toString();

        $zipQuerySongs = array_merge(request()->except(['songs_page', 'videos_page']), ['scope' => 'songs']);
        $zipQueryVideos = array_merge(request()->except(['songs_page', 'videos_page']), ['scope' => 'videos']);
    @endphp

    <div class="min-h-screen">
        <div class="w-full px-4 py-6 sm:px-6 lg:px-8 2xl:px-10">
            <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Quản lý dữ liệu crawl</h1>
                    <p class="mt-1 text-xs text-slate-500">
                        Tải qua Laravel (stream). Trình duyệt lưu vào thư mục Downloads mặc định — không chọn thư mục đích b&#7857;ng PHP.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ url('/') }}" class="text-sm font-medium text-blue-600 hover:underline">&#8592; V&#7873; trang ch&#7917;</a>
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
                                href="{{ route('crawled-audio-cms.index', array_merge(request()->query(), ['view' => 'songs'])) }}"
                                class="{{ $activeView === 'songs' ? 'bg-blue-50 text-blue-700 ring-1 ring-blue-100' : 'text-slate-700 hover:bg-slate-50' }} flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium"
                            >
                                <span>Bài hát (songs)</span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ number_format($songTotalFiltered) }}</span>
                            </a>
                            <a
                                href="{{ route('crawled-audio-cms.index', array_merge(request()->query(), ['view' => 'videos'])) }}"
                                class="{{ $activeView === 'videos' ? 'bg-blue-50 text-blue-700 ring-1 ring-blue-100' : 'text-slate-700 hover:bg-slate-50' }} mt-1 flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium"
                            >
                                <span>Video</span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ number_format($videoTotalFiltered) }}</span>
                            </a>
                            
                        </nav>
                    </div>
                </aside>

                <main class="min-w-0 lg:flex-1">
                    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-4 py-4 sm:px-6">
                            <div class="text-sm font-semibold text-slate-900">
                                @if($activeView === 'videos')
                                    Danh sách video
                                @else
                                    Danh sách bài hát
                                @endif
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
                                        <label for="artist_q" class="block text-xs font-medium text-slate-600">T&#7915; kh&#243;a</label>
                                        <input
                                            name="artist_q"
                                            id="artist_q"
                                            value="{{ $filterArtistQ }}"
                                            placeholder="VD: sơn tùng..."
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

                        @elseif ($activeView === 'videos')
                            <form method="get" action="{{ route('crawled-audio-cms.index') }}" class="border-b border-slate-100 px-4 py-4 sm:px-6">
                                <input type="hidden" name="view" value="videos">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <div class="sm:col-span-2">
                                        <label for="video_q" class="block text-xs font-medium text-slate-600">T&#7915; kh&#243;a</label>
                                        <input
                                            name="video_q"
                                            id="video_q"
                                            value="{{ $filterVideoQ }}"
                                            placeholder="Tên video, ca s&#297;..."
                                            class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600"
                                        >
                                    </div>
                                    <div>
                                        <label for="video_has_url" class="block text-xs font-medium text-slate-600">Link tải</label>
                                        <select name="video_has_url" id="video_has_url" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
                                            <option value="" @selected($filterVideoHasUrl === '')>Tất cả</option>
                                            <option value="1" @selected($filterVideoHasUrl === '1')>Có URL</option>
                                            <option value="0" @selected($filterVideoHasUrl === '0')>Thiếu URL</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-4 flex flex-wrap items-center gap-3">
                                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                                        Lọc
                                    </button>
                                    <a href="{{ route('crawled-audio-cms.index', ['view' => 'videos']) }}" class="text-sm font-medium text-slate-600 hover:underline">Reset</a>
                                    @if ($videoWithUrlCount > 0)
                                        <a
                                            href="{{ route('crawled-audio-cms.download-zip', $zipQueryVideos) }}"
                                            class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow hover:bg-slate-800"
                                        >
                                            Tải ZIP (tối đa {{ $zipMaxFiles }} file có link)
                                        </a>
                                    @endif
                                </div>
                                <p class="mt-2 text-xs text-slate-500">
                                    ZIP g&#7891;m bản ghi khớp bộ lọc, có <code class="rounded bg-slate-100 px-1">download_url</code>, tối đa {{ $zipMaxFiles }} file (tránh timeout).
                                </p>
                            </form>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                        <tr>
                                            <th class="px-4 py-3 sm:px-6">Tiêu đề</th>
                                            <th class="px-4 py-3 hidden md:table-cell">Ca s&#297;</th>
                                            <th class="px-4 py-3">Link tải</th>
                                            <th class="px-4 py-3 w-44">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse ($videosPaginator as $row)
                                            @php
                                                $hasUrl = filled(trim((string) $row->download_url));
                                            @endphp
                                            <tr class="hover:bg-slate-50/80">
                                                <td class="px-4 py-3 sm:px-6 max-w-xs">
                                                    <div class="font-medium text-slate-900 line-clamp-2">{{ $row->name ?? '—' }}</div>
                                                    <div class="mt-0.5 text-xs text-slate-500">#{{ $row->id }} @if($row->identify)• {{ $row->identify }}@endif</div>
                                                </td>
                                                <td class="px-4 py-3 text-slate-600 hidden md:table-cell">{{ $row->name_singer ?? '—' }}</td>
                                                <td class="px-4 py-3 max-w-[14rem]">
                                                    @if ($hasUrl)
                                                        <span class="text-green-600 text-xs font-medium">Có</span>
                                                        <div class="mt-1 truncate text-xs text-slate-500" title="{{ $row->download_url }}">{{ $row->download_url }}</div>
                                                    @else
                                                        <span class="text-red-500 text-xs font-medium">Chưa có</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3">
                                                    @if ($hasUrl)
                                                        <a
                                                            href="{{ route('crawled-audio-cms.download', ['type' => 'video', 'id' => $row->id]) }}"
                                                            class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
                                                        >
                                                            Tải video
                                                        </a>
                                                    @else
                                                        <span class="text-xs text-slate-400">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="px-4 py-12 text-center text-slate-500 sm:px-6">
                                                    Chưa có video hoặc không khớp bộ lọc.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="border-t border-slate-100 px-4 py-3 sm:px-6">
                                {{ $videosPaginator->withQueryString()->links() }}
                            </div>
                        @else
                            <form method="get" action="{{ route('crawled-audio-cms.index') }}" class="border-b border-slate-100 px-4 py-4 sm:px-6">
                                <input type="hidden" name="view" value="songs">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div class="sm:col-span-2">
                                        <label for="song_q" class="block text-xs font-medium text-slate-600">T&#7915; kh&#243;a</label>
                                        <input
                                            name="song_q"
                                            id="song_q"
                                            value="{{ $filterSongQ }}"
                                            placeholder="Tên bài, ca s&#297;, nh&#7841;c s&#297;..."
                                            class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600"
                                        >
                                    </div>
                                    <div>
                                        <label for="song_has_url" class="block text-xs font-medium text-slate-600">Link tải</label>
                                        <select name="song_has_url" id="song_has_url" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
                                            <option value="" @selected($filterSongHasUrl === '')>Tất cả</option>
                                            <option value="1" @selected($filterSongHasUrl === '1')>Có URL</option>
                                            <option value="0" @selected($filterSongHasUrl === '0')>Thiếu URL</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="song_has_lyric" class="block text-xs font-medium text-slate-600">Lyric</label>
                                        <select name="song_has_lyric" id="song_has_lyric" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
                                            <option value="" @selected($filterSongHasLyric === '')>Tất cả</option>
                                            <option value="1" @selected($filterSongHasLyric === '1')>Có lyric</option>
                                            <option value="0" @selected($filterSongHasLyric === '0')>Không lyric</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-4 flex flex-wrap items-center gap-3">
                                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                                        Lọc
                                    </button>
                                    <a href="{{ route('crawled-audio-cms.index', ['view' => 'songs']) }}" class="text-sm font-medium text-slate-600 hover:underline">Reset</a>
                                    @if ($songWithUrlCount > 0)
                                        <a
                                            href="{{ route('crawled-audio-cms.download-zip', $zipQuerySongs) }}"
                                            class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow hover:bg-slate-800"
                                        >
                                            Tải ZIP (tối đa {{ $zipMaxFiles }} file có link)
                                        </a>
                                    @endif
                                </div>
                                <p class="mt-2 text-xs text-slate-500">
                                    ZIP g&#7891;m bài khớp bộ lọc, có <code class="rounded bg-slate-100 px-1">download_url</code>, tối đa {{ $zipMaxFiles }} file.
                                </p>
                            </form>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                        <tr>
                                            <th class="px-4 py-3 sm:px-6">Tiêu đề</th>
                                            <th class="px-4 py-3 hidden lg:table-cell">Ca s&#297;</th>
                                            <th class="px-4 py-3 hidden xl:table-cell">Nh&#7841;c s&#297;</th>
                                            <th class="px-4 py-3">Link tải</th>
                                            <th class="px-4 py-3 hidden md:table-cell">Lyric</th>
                                            <th class="px-4 py-3 w-44">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse ($songsPaginator as $row)
                                            @php
                                                $hasUrl = filled(trim((string) $row->download_url));
                                                $hasLyric = filled(trim((string) $row->lyric));
                                            @endphp
                                            <tr class="hover:bg-slate-50/80">
                                                <td class="px-4 py-3 sm:px-6 max-w-xs">
                                                    <div class="font-medium text-slate-900 line-clamp-2">{{ $row->name ?? '—' }}</div>
                                                    <div class="mt-0.5 text-xs text-slate-500">#{{ $row->id }} @if($row->identify)• {{ $row->identify }}@endif</div>
                                                </td>
                                                <td class="px-4 py-3 text-slate-600 hidden lg:table-cell">{{ $row->name_singer ?? '—' }}</td>
                                                <td class="px-4 py-3 text-slate-600 hidden xl:table-cell">{{ $row->author_name ?? '—' }}</td>
                                                <td class="px-4 py-3 max-w-[14rem]">
                                                    @if ($hasUrl)
                                                        <span class="text-green-600 text-xs font-medium">Có</span>
                                                        <div class="mt-1 truncate text-xs text-slate-500" title="{{ $row->download_url }}">{{ $row->download_url }}</div>
                                                    @else
                                                        <span class="text-red-500 text-xs font-medium">Chưa có</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 hidden md:table-cell">
                                                    @if ($hasLyric)
                                                        <span class="text-green-600 text-xs font-medium">Có</span>
                                                    @else
                                                        <span class="text-slate-400 text-xs">Không</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3">
                                                    @if ($hasUrl)
                                                        <a
                                                            href="{{ route('crawled-audio-cms.download', ['type' => 'song', 'id' => $row->id]) }}"
                                                            class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
                                                        >
                                                            Tải audio
                                                        </a>
                                                    @else
                                                        <span class="text-xs text-slate-400">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="px-4 py-12 text-center text-slate-500 sm:px-6">
                                                    Chưa có bài hát hoặc không khớp bộ lọc.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="border-t border-slate-100 px-4 py-3 sm:px-6">
                                {{ $songsPaginator->withQueryString()->links() }}
                            </div>
                        @endif
                    </div>
                </main>
            </div>
        </div>
    </div>
</body>
</html>
