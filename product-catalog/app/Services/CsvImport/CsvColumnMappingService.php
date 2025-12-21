<?php

namespace App\Services\CsvImport;

class CsvColumnMappingService
{
    /**
     * Langues supportées pour les traductions
     */
    protected const SUPPORTED_LOCALES = [
        'fr' => 'Français',
        'en' => 'English',
        'de' => 'Deutsch',
        'es' => 'Español',
        'it' => 'Italiano',
        'nl' => 'Nederlands',
        'pt' => 'Português',
        'pl' => 'Polski',
    ];

    /**
     * Obtenir les champs attendus pour un type d'import
     */
    public function getExpectedFields(string $importType, ?string $mode = null): array
    {
        $baseFields = match($importType) {
            'product' => [
                'sku' => ['required' => true, 'label' => 'SKU'],
                'name' => ['required' => true, 'label' => 'Nom'],
                'category_name' => ['required' => true, 'label' => 'Catégorie'],
                'manufacturer_name' => ['required' => true, 'label' => 'Fabricant'],
                'primary_color_name' => ['required' => false, 'label' => 'Couleur principale'],
                'color_name' => ['required' => false, 'label' => 'Couleur fabricant'],
                'size_name' => ['required' => false, 'label' => 'Taille'],
            ],
            'manufacturer_color' => [
                'name' => ['required' => true, 'label' => 'Nom'],
                'manufacturer_name' => ['required' => true, 'label' => 'Fabricant'],
                'hex_code' => ['required' => false, 'label' => 'Hex Color'],
                'parent_name' => ['required' => false, 'label' => 'Couleur principale'],
                'color_sku_code' => ['required' => false, 'label' => 'Color SKU code'],
                'rgb' => ['required' => false, 'label' => 'RGB'],
                'pantone_c' => ['required' => false, 'label' => 'Pantone C'],
                'pantone_tcx' => ['required' => false, 'label' => 'Pantone TCX'],
                'pms' => ['required' => false, 'label' => 'PMS'],
            ],
            'category' => $this->getCategoryFields(),
            'distributor' => [
                'name' => ['required' => true, 'label' => 'Nom'],
                'logo_url' => ['required' => false, 'label' => 'URL du logo'],
            ],
            'manufacturer' => [
                'name' => ['required' => true, 'label' => 'Nom'],
                'logo_url' => ['required' => false, 'label' => 'URL du logo'],
            ],
            'stock' => [
                'sku' => ['required' => true, 'label' => 'SKU'],
                'quantity' => ['required' => true, 'label' => 'Quantité'],
                'distributor_name' => ['required' => false, 'label' => 'Distributeur'],
                'distributor_sku' => ['required' => false, 'label' => 'SKU distributeur'],
            ],
            'price' => [
                'sku' => ['required' => true, 'label' => 'SKU'],
                'price' => ['required' => true, 'label' => 'Prix'],
                'distributor_name' => ['required' => false, 'label' => 'Distributeur'],
                'distributor_sku' => ['required' => false, 'label' => 'SKU distributeur'],
                'min_quantity' => ['required' => false, 'label' => 'Quantité min'],
                'max_quantity' => ['required' => false, 'label' => 'Quantité max'],
                'currency' => ['required' => false, 'label' => 'Devise'],
            ],
            default => [],
        };

        // Ajouter les champs d'images pour les produits
        if ($importType === 'product') {
            for ($i = 1; $i <= 8; $i++) {
                $baseFields["image_{$i}_url"] = [
                    'required' => false,
                    'label' => "Image {$i} URL"
                ];
            }
        }

        return $baseFields;
    }

    /**
     * Obtenir les champs pour les catégories (avec traductions)
     */
    protected function getCategoryFields(): array
    {
        $fields = [
            'name' => ['required' => true, 'label' => 'Nom'],
            'parent_name' => ['required' => false, 'label' => 'Catégorie parente'],
        ];

        // Ajouter les champs de traduction pour chaque langue
        foreach (self::SUPPORTED_LOCALES as $code => $label) {
            $fields["name_{$code}"] = [
                'required' => false,
                'label' => "Nom ({$label})"
            ];
        }

        return $fields;
    }

    /**
     * Suggérer un mapping automatique entre les colonnes CSV et les champs attendus
     */
    public function suggestMapping(array $csvHeaders, array $expectedFields): array
    {
        $mapping = [];
        
        foreach ($expectedFields as $field => $config) {
            // Chercher une correspondance exacte
            if (in_array($field, $csvHeaders)) {
                $mapping[$field] = $field;
                continue;
            }
            
            // Chercher une correspondance par label
            $label = $config['label'];
            foreach ($csvHeaders as $header) {
                if (mb_strtolower($header) === mb_strtolower($label)) {
                    $mapping[$field] = $header;
                    break;
                }
            }
            
            // Si pas de correspondance, laisser vide
            if (!isset($mapping[$field])) {
                $mapping[$field] = null;
            }
        }
        
        return $mapping;
    }

    /**
     * Valider le mapping
     */
    public function validateMapping(array $mapping, array $expectedFields): array
    {
        $errors = [];
        
        foreach ($expectedFields as $field => $config) {
            if ($config['required'] && empty($mapping[$field])) {
                $errors[] = "Le champ requis '{$config['label']}' n'est pas mappé.";
            }
        }
        
        return $errors;
    }
}


