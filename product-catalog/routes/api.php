<?php

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('products', ProductController::class);
    Route::get('search', [SearchController::class, 'index']);
    Route::post('upload/presigned-url', [\App\Http\Controllers\Api\UploadController::class, 'presignedUrl']);
});

