<?php

use App\Http\Controllers\CrawledAudioCmsController;
use App\Http\Controllers\FetchKengController;
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

Route::get('/', [CrawledAudioCmsController::class, 'index'])->name('crawled-audio-cms.index');
Route::get('/crawl-cms/download', [CrawledAudioCmsController::class, 'download'])->name('crawled-audio-cms.download');
Route::get('/crawl-cms/download-zip', [CrawledAudioCmsController::class, 'downloadZip'])->name('crawled-audio-cms.download-zip');

Route::get('/test', [FetchKengController::class, 'fetchSongs']);
