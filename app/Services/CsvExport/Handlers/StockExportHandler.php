<?php

namespace App\Services\CsvExport\Handlers;

use App\Models\ProductVariantPrice;
use App\Services\CsvExport\ExportHandlerInterface;

class StockExportHandler implements ExportHandlerInterface
{
    public function getHeaders(?string $mode = null): array
    {
        return ['sku_distributor', 'distributor_name', 'stock'];
    }

    public function getData(?string $mode = null, array $filters = []): array
    {
        $query = ProductVariantPrice::with(['distributor']);

        // Appliquer les filtres si nécessaire
        if (isset($filters['distributor_id'])) {
            $query->where('distributor_id', $filters['distributor_id']);
        }

        if (isset($filters['sku_distributor'])) {
            $query->where('sku_distributor', 'like', '%' . $filters['sku_distributor'] . '%');
        }

        $variantPrices = $query->get();

        return $variantPrices->map(function ($variantPrice) {
            return [
                'sku_distributor' => $variantPrice->sku_distributor ?? '',
                'distributor_name' => $variantPrice->distributor?->name ?? '',
                'stock' => $variantPrice->stock ?? 0,
            ];
        })->toArray();
    }
}
