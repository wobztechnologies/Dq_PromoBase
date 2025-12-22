<?php

use App\Http\Controllers\Webhook\MeshyWebhookController;
use App\Http\Controllers\CsvImportTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Health check endpoint for container monitoring
Route::get('/health', function () {
    return response('OK', 200)->header('Content-Type', 'text/plain');
});

// Route webhook Meshy (CSRF désactivé pour les webhooks externes)
Route::post('/webhooks/meshy', [MeshyWebhookController::class, 'handle'])
    ->middleware('web')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Route pour récupérer les grilles de prix d'une variante
Route::get('/admin/product-variant-prices/{id}/price-tiers', function ($id) {
    $variantPrice = \App\Models\ProductVariantPrice::with('priceTiers')->find($id);
    
    if (!$variantPrice) {
        return response()->json(['error' => 'Variante de prix non trouvée'], 404);
    }
    
    return response()->json([
        'variant_sku' => $variantPrice->variant_sku,
        'tiers' => $variantPrice->priceTiers->map(function ($tier) {
            return [
                'quantity_min' => $tier->quantity_min,
                'quantity_max' => $tier->quantity_max,
                'unit_price' => $tier->unit_price,
                'currency' => $tier->currency,
            ];
        }),
    ]);
})->middleware(['web', 'auth']);

// Route pour télécharger les modèles CSV d'import
Route::get('/csv-import/template/{type}', [CsvImportTemplateController::class, 'download'])
    ->name('csv-import.template')
    ->middleware(['web', 'auth']);
