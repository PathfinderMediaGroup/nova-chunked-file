<?php

declare(strict_types=1);

use PathfinderMediaGroup\NovaChunkedFile\Http\Controllers\ChunkUploadController;
use Illuminate\Support\Facades\Route;

Route::post('/chunk', ChunkUploadController::class);
