<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Songs extends Model
{
    use HasFactory;
    protected $table = 'songs';
    protected $fillable = [
        'identify',
        'slug',
        'name',
        'download_url',
        'image_url',
        'singer_id',
        'name_singer',
        'author_name',
        'lyric',
    ];
    
}
