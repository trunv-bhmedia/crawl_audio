<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nghỉ giữa các request (microseconds)
    |--------------------------------------------------------------------------
    */
    'delay_microseconds' => (int) env('CRAWL_DELAY_MS', 500) * 1000,

    'user_agent' => env(
        'CRAWL_USER_AGENT',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ),

    'http_timeout_seconds' => (int) env('CRAWL_HTTP_TIMEOUT', 60),

    'keeng' => [
        'base_url' => rtrim(env('CRAWL_KEENG_BASE_URL', 'https://keeng.vn'), '/'),
        'entry_url' => env('CRAWL_KEENG_ENTRY_URL', 'https://keeng.vn/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Giới hạn an toàn khi duyệt link từ một trang listing
    |--------------------------------------------------------------------------
    */
    'max_links_per_page' => (int) env('CRAWL_MAX_LINKS_PER_PAGE', 100),

];
