<?php

namespace App\Services\CsvImport\Handlers;

use App\Models\CsvImport;
use App\Models\ProductVariantPrice;
use App\Models\Distributor;
use App\Services\CsvImport\ImportHandlerInterface;
use App\Services\CsvImport\MatchingService;

class StockImportHandler implements ImportHandlerInterface
{
    public function __construct(
        protected MatchingService $matchingService
    ) {}

    public function processRow(CsvImport $import, array $row, int $rowNumber): bool
    {
        try {
            $skuDistributor = $row['sku_distributor'] ?? null;
            $stock = $row['stock'] ?? null;
            $distributorName = $row['distributor_name'] ?? null;
            
            if (!$skuDistributor || $stock === null || !$distributorName) {
                $import->addLog('error', 'Données manquantes (sku_distributor, stock ou distributor_name)', $row, $rowNumber, $skuDistributor);
                return false;
            }
            
            // Trouver le distributeur
            $distributor = Distributor::where('name', $distributorName)->first();
            if (!$distributor) {
                $import->addLog('error', "Distributeur '{$distributorName}' non trouvé", $row, $rowNumber, $skuDistributor);
                return false;
            }
            
            // Trouver la variante de prix par SKU distributeur
            $variantPrice = ProductVariantPrice::where('sku_distributor', $skuDistributor)
                ->where('distributor_id', $distributor->id)
                ->first();
            
            if (!$variantPrice) {
                $import->addLog('error', "Variante de prix non trouvée pour SKU distributeur '{$skuDistributor}'", $row, $rowNumber, $skuDistributor);
                return false;
            }
            
            // Mettre à jour le stock
            $variantPrice->stock = (int) $stock;
            $variantPrice->last_updated_at = now();
            $variantPrice->save();
            
            $import->incrementSuccessful();
            return true;
            
        } catch (\Exception $e) {
            $import->addLog('error', 'Erreur lors du traitement: ' . $e->getMessage(), $row, $rowNumber);
            return false;
        }
    }

    public function getMatchingValues(array $rows): array
    {
        $values = [];
        
        foreach ($rows as $row) {
            if (!empty($row['distributor_name'])) {
                $values['distributor'][] = $row['distributor_name'];
            }
        }
        
        return $values;
    }
}
