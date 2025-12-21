<?php

namespace App\Services\CsvImport\Handlers;

use App\Models\CsvImport;
use App\Models\ProductVariantPrice;
use App\Models\ProductPriceTier;
use App\Models\Distributor;
use App\Services\CsvImport\ImportHandlerInterface;
use App\Services\CsvImport\MatchingService;

class PriceImportHandler implements ImportHandlerInterface
{
    public function __construct(
        protected MatchingService $matchingService
    ) {}

    public function processRow(CsvImport $import, array $row, int $rowNumber): bool
    {
        try {
            $skuDistributor = $row['sku_distributor'] ?? null;
            $distributorName = $row['distributor_name'] ?? null;
            $price = $row['price'] ?? null;
            $tierName = $row['tier_name'] ?? null;
            $minQuantity = $row['min_quantity'] ?? 1;
            
            if (!$skuDistributor || !$distributorName || $price === null) {
                $import->addLog('error', 'Données manquantes (sku_distributor, distributor_name ou price)', $row, $rowNumber, $skuDistributor);
                return false;
            }
            
            // Trouver le distributeur
            $distributor = Distributor::where('name', $distributorName)->first();
            if (!$distributor) {
                $import->addLog('error', "Distributeur '{$distributorName}' non trouvé", $row, $rowNumber, $skuDistributor);
                return false;
            }
            
            // Trouver la variante de prix
            $variantPrice = ProductVariantPrice::where('sku_distributor', $skuDistributor)
                ->where('distributor_id', $distributor->id)
                ->first();
            
            if (!$variantPrice) {
                $import->addLog('error', "Variante de prix non trouvée pour SKU distributeur '{$skuDistributor}'", $row, $rowNumber, $skuDistributor);
                return false;
            }
            
            // Si un tier est spécifié, créer ou mettre à jour le tier
            if ($tierName) {
                $tier = ProductPriceTier::where('product_variant_price_id', $variantPrice->id)
                    ->where('quantity_min', (int) $minQuantity)
                    ->first();
                
                if (!$tier) {
                    $tier = new ProductPriceTier();
                    $tier->product_variant_price_id = $variantPrice->id;
                    $tier->quantity_min = (int) $minQuantity;
                    $tier->quantity_max = null; // Pas de limite max par défaut
                }
                
                $tier->unit_price = (float) $price;
                $tier->currency = 'EUR'; // Par défaut, peut être configuré
                $tier->save();
            } else {
                // Créer un tier par défaut avec quantité minimale de 1
                $tier = ProductPriceTier::where('product_variant_price_id', $variantPrice->id)
                    ->where('quantity_min', 1)
                    ->first();
                
                if (!$tier) {
                    $tier = new ProductPriceTier();
                    $tier->product_variant_price_id = $variantPrice->id;
                    $tier->quantity_min = 1;
                    $tier->quantity_max = null;
                }
                
                $tier->unit_price = (float) $price;
                $tier->currency = 'EUR';
                $tier->save();
            }
            
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
