<?php

use App\Http\Controllers\SongController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('crawled-audio-cms.index');
})->name('crawled-audio-cms.index');

Route::prefix('songs')->group(function () {
    Route::get('/fetch', [SongController::class, 'fetchSongs'])->name('songs.fetch');
    Route::get('/sync/hot', [SongController::class, 'syncSongHot'])->name('songs.sync.hot');
    Route::get('/sync/new', [SongController::class, 'syncSongNew'])->name('songs.sync.new');
    Route::get('/sync/all', [SongController::class, 'syncSongHotAndNew'])->name('songs.sync.all');
});
