<?php

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\External\AuthController as ExternalAuthController;
use App\Http\Controllers\Api\External\ProductController as ExternalProductController;
use App\Http\Controllers\Api\External\PrimaryColorController as ExternalPrimaryColorController;
use App\Http\Controllers\Api\External\ManufacturerColorController as ExternalManufacturerColorController;
use App\Http\Controllers\Api\External\ManufacturerController as ExternalManufacturerController;
use App\Http\Controllers\Api\External\CategoryController as ExternalCategoryController;
use App\Http\Controllers\Api\External\Request3DController as ExternalRequest3DController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (Sanctum - Internal)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('products', ProductController::class);
    Route::get('search', [SearchController::class, 'index']);
    Route::post('upload/presigned-url', [\App\Http\Controllers\Api\UploadController::class, 'presignedUrl']);
});

/*
|--------------------------------------------------------------------------
| External API Routes (JWT Authentication)
|--------------------------------------------------------------------------
|
| Ces routes sont destinées à l'intégration avec des outils externes.
| Elles utilisent l'authentification JWT.
|
*/
Route::prefix('ext')->group(function () {
    // Routes publiques (authentification)
    Route::post('login', [ExternalAuthController::class, 'login']);
    
    // Routes protégées par JWT
    Route::middleware(['auth:api', 'api.update.last.used'])->group(function () {
        // Authentification
        Route::post('refresh', [ExternalAuthController::class, 'refresh']);
        Route::post('logout', [ExternalAuthController::class, 'logout']);
        Route::get('me', [ExternalAuthController::class, 'me']);
        
        // Produits
        Route::get('products', [ExternalProductController::class, 'index']);
        Route::get('products/search', [ExternalProductController::class, 'search']);
        
        // Couleurs
        Route::get('primarycolors', [ExternalPrimaryColorController::class, 'index']);
        Route::get('manufacturerscolors', [ExternalManufacturerColorController::class, 'index']);
        
        // Fabricants
        Route::get('manufacturers', [ExternalManufacturerController::class, 'index']);
        
        // Catégories
        Route::get('categories', [ExternalCategoryController::class, 'index']);
        
        // Modèles 3D
        Route::get('prerequest3d', [ExternalRequest3DController::class, 'prerequest']);
        Route::post('request3d', [ExternalRequest3DController::class, 'request']);
    });
});
