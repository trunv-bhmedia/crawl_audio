<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrawledTrack extends Model
{
    public const SOURCE_KEENG = 'keeng';

    public const SOURCE_IMUZIK = 'imuzik';

    protected $fillable = [
        'source',
        'external_id',
        'title',
        'canonical_url',
        'url_hash',
        'performers',
        'composers',
        'mp3_source_url',
        'mp3_storage_path',
        'lyric',
        'raw_payload',
        'crawled_at',
    ];

    protected $casts = [
        'performers' => 'array',
        'composers' => 'array',
        'raw_payload' => 'array',
        'crawled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (CrawledTrack $track) {
            if ($track->canonical_url !== null && $track->canonical_url !== '') {
                $track->url_hash = hash('sha256', $track->source.'|'.$track->canonical_url);
            }
        });
    }
}
