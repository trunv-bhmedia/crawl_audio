<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;
    protected $table = 'videos';
    protected $fillable = [
        'identify',
        'slug',
        'name',
        'download_url',
        'singer_id',
        'name_singer',
        'image',
    ];
    
}
