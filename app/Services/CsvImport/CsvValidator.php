<?php

namespace App\Services\CsvImport;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CsvValidator
{
    /**
     * Valider un CSV selon son type
     */
    public function validate(string $type, array $data, array $headers): array
    {
        $errors = [];
        
        foreach ($data as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2; // +2 car ligne 1 = headers, ligne 2 = première donnée
            
            try {
                $validator = Validator::make($row, $this->getRules($type, $headers));
                
                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $error) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'field' => $validator->errors()->keys()[0] ?? 'unknown',
                            'message' => $error,
                            'data' => $row,
                        ];
                    }
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'general',
                    'message' => 'Erreur de validation: ' . $e->getMessage(),
                    'data' => $row,
                ];
            }
        }
        
        return $errors;
    }

    /**
     * Obtenir les règles de validation selon le type d'import
     */
    protected function getRules(string $type, array $headers): array
    {
        return match($type) {
            'category' => $this->getCategoryRules($headers),
            'distributor' => $this->getDistributorRules($headers),
            'manufacturer' => $this->getManufacturerRules($headers),
            'manufacturer_color' => $this->getManufacturerColorRules($headers),
            'stock' => $this->getStockRules($headers),
            'price' => $this->getPriceRules($headers),
            'product' => $this->getProductRules($headers),
            default => [],
        };
    }

    /**
     * Règles pour les catégories
     */
    protected function getCategoryRules(array $headers): array
    {
        $rules = [];
        
        if (in_array('name', $headers)) {
            $rules['name'] = 'required|string|max:255';
        }
        
        if (in_array('parent_name', $headers)) {
            $rules['parent_name'] = 'nullable|string|max:255';
        }
        
        return $rules;
    }

    /**
     * Règles pour les distributeurs
     */
    protected function getDistributorRules(array $headers): array
    {
        $rules = [];
        
        if (in_array('name', $headers)) {
            $rules['name'] = 'required|string|max:255';
        }
        
        if (in_array('logo_url', $headers)) {
            $rules['logo_url'] = 'nullable|url';
        }
        
        return $rules;
    }

    /**
     * Règles pour les fabricants
     */
    protected function getManufacturerRules(array $headers): array
    {
        $rules = [];
        
        if (in_array('name', $headers)) {
            $rules['name'] = 'required|string|max:255';
        }
        
        if (in_array('logo_url', $headers)) {
            $rules['logo_url'] = 'nullable|url';
        }
        
        return $rules;
    }

    /**
     * Règles pour les couleurs fabricant
     */
    protected function getManufacturerColorRules(array $headers): array
    {
        $rules = [];
        
        if (in_array('name', $headers)) {
            $rules['name'] = 'required|string|max:255';
        }
        
        if (in_array('manufacturer_name', $headers)) {
            $rules['manufacturer_name'] = 'required|string|max:255';
        }
        
        if (in_array('hex_code', $headers)) {
            $rules['hex_code'] = 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/';
        }
        
        if (in_array('parent_name', $headers)) {
            $rules['parent_name'] = 'nullable|string|max:255';
        }
        
        if (in_array('color_sku_code', $headers)) {
            $rules['color_sku_code'] = 'nullable|string|max:255';
        }
        
        if (in_array('rgb', $headers)) {
            $rules['rgb'] = 'nullable|string|max:255';
        }
        
        if (in_array('pantone_c', $headers)) {
            $rules['pantone_c'] = 'nullable|string|max:255';
        }
        
        if (in_array('pantone_tcx', $headers)) {
            $rules['pantone_tcx'] = 'nullable|string|max:255';
        }
        
        if (in_array('pms', $headers)) {
            $rules['pms'] = 'nullable|string|max:255';
        }
        
        return $rules;
    }

    /**
     * Règles pour le stock
     */
    protected function getStockRules(array $headers): array
    {
        $rules = [];
        
        if (in_array('sku_distributor', $headers)) {
            $rules['sku_distributor'] = 'required|string|max:255';
        }
        
        if (in_array('stock', $headers)) {
            $rules['stock'] = 'required|integer|min:0';
        }
        
        if (in_array('distributor_name', $headers)) {
            $rules['distributor_name'] = 'required|string|max:255';
        }
        
        return $rules;
    }

    /**
     * Règles pour les prix
     */
    protected function getPriceRules(array $headers): array
    {
        $rules = [];
        
        if (in_array('sku_distributor', $headers)) {
            $rules['sku_distributor'] = 'required|string|max:255';
        }
        
        if (in_array('distributor_name', $headers)) {
            $rules['distributor_name'] = 'required|string|max:255';
        }
        
        if (in_array('price', $headers)) {
            $rules['price'] = 'required|numeric|min:0';
        }
        
        if (in_array('tier_name', $headers)) {
            $rules['tier_name'] = 'nullable|string|max:255';
        }
        
        if (in_array('min_quantity', $headers)) {
            $rules['min_quantity'] = 'nullable|integer|min:1';
        }
        
        return $rules;
    }

    /**
     * Règles pour les produits
     */
    protected function getProductRules(array $headers): array
    {
        $rules = [];
        
        if (in_array('sku', $headers)) {
            $rules['sku'] = 'required|string|max:255';
        }
        
        if (in_array('name', $headers)) {
            $rules['name'] = 'required|string|max:255';
        }
        
        if (in_array('category_name', $headers)) {
            $rules['category_name'] = 'required|string|max:255';
        }
        
        if (in_array('manufacturer_name', $headers)) {
            $rules['manufacturer_name'] = 'required|string|max:255';
        }
        
        // Images (jusqu'à 8)
        for ($i = 1; $i <= 8; $i++) {
            $imageKey = "image_{$i}_url";
            if (in_array($imageKey, $headers)) {
                $rules[$imageKey] = 'nullable|url';
            }
        }
        
        // Variantes de couleur
        if (in_array('color_name', $headers)) {
            $rules['color_name'] = 'nullable|string|max:255';
        }
        
        if (in_array('primary_color_name', $headers)) {
            $rules['primary_color_name'] = 'nullable|string|max:255';
        }
        
        // Variantes de taille
        if (in_array('size_name', $headers)) {
            $rules['size_name'] = 'nullable|string|max:255';
        }
        
        // SKU distributeur (requis seulement en mode distributeur)
        if (in_array('sku_distributor', $headers)) {
            $rules['sku_distributor'] = 'nullable|string|max:255';
        }
        
        if (in_array('distributor_name', $headers)) {
            $rules['distributor_name'] = 'nullable|string|max:255';
        }
        
        return $rules;
    }

    /**
     * Valider les en-têtes du CSV
     */
    public function validateHeaders(string $type, array $headers): array
    {
        $requiredHeaders = $this->getRequiredHeaders($type);
        $errors = [];
        
        foreach ($requiredHeaders as $required) {
            if (!in_array($required, $headers)) {
                $errors[] = "Colonne manquante: {$required}";
            }
        }
        
        return $errors;
    }

    /**
     * Obtenir les en-têtes requis selon le type
     */
    protected function getRequiredHeaders(string $type): array
    {
        return match($type) {
            'category' => ['name'],
            'distributor' => ['name'],
            'manufacturer' => ['name'],
            'manufacturer_color' => ['name', 'manufacturer_name'],
            'stock' => ['sku_distributor', 'stock', 'distributor_name'],
            'price' => ['sku_distributor', 'distributor_name', 'price'],
            'product' => ['sku', 'name', 'category_name', 'manufacturer_name'],
            default => [],
        };
    }
}
