<?php

namespace App\Services\CsvExport\Handlers;

use App\Models\ProductVariantPrice;
use App\Models\ProductPriceTier;
use App\Services\CsvExport\ExportHandlerInterface;

class PriceExportHandler implements ExportHandlerInterface
{
    public function getHeaders(?string $mode = null): array
    {
        return ['sku_distributor', 'distributor_name', 'price', 'tier_name', 'min_quantity'];
    }

    public function getData(?string $mode = null, array $filters = []): array
    {
        $query = ProductVariantPrice::with(['distributor', 'priceTiers']);

        // Appliquer les filtres si nécessaire
        if (isset($filters['distributor_id'])) {
            $query->where('distributor_id', $filters['distributor_id']);
        }

        if (isset($filters['sku_distributor'])) {
            $query->where('sku_distributor', 'like', '%' . $filters['sku_distributor'] . '%');
        }

        $variantPrices = $query->get();
        $rows = [];

        foreach ($variantPrices as $variantPrice) {
            $priceTiers = $variantPrice->priceTiers;
            
            if ($priceTiers->isEmpty()) {
                // Si pas de tiers, créer une ligne avec le prix par défaut
                $rows[] = [
                    'sku_distributor' => $variantPrice->sku_distributor ?? '',
                    'distributor_name' => $variantPrice->distributor?->name ?? '',
                    'price' => '',
                    'tier_name' => '',
                    'min_quantity' => '',
                ];
            } else {
                // Créer une ligne pour chaque tier
                foreach ($priceTiers as $tier) {
                    $rows[] = [
                        'sku_distributor' => $variantPrice->sku_distributor ?? '',
                        'distributor_name' => $variantPrice->distributor?->name ?? '',
                        'price' => $tier->unit_price ?? '',
                        'tier_name' => $tier->name ?? '',
                        'min_quantity' => $tier->quantity_min ?? 1,
                    ];
                }
            }
        }

        return $rows;
    }
}
