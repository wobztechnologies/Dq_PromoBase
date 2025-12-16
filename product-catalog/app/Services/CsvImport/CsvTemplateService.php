<?php

namespace App\Services\CsvImport;

use League\Csv\Writer;
use Illuminate\Support\Facades\Storage;

class CsvTemplateService
{
    /**
     * Générer un modèle CSV pour un type d'import
     */
    public function generateTemplate(string $type, ?string $mode = null): string
    {
        $headers = $this->getHeadersForType($type, $mode);
        $exampleRow = $this->getExampleRowForType($type, $mode);

        $csv = Writer::createFromString();
        $csv->insertOne($headers);
        $csv->insertOne($exampleRow);

        return $csv->toString();
    }

    /**
     * Obtenir les en-têtes pour un type d'import
     */
    protected function getHeadersForType(string $type, ?string $mode = null): array
    {
        return match($type) {
            'category' => ['name', 'parent_name'],
            'distributor' => ['name', 'logo_url'],
            'manufacturer' => ['name', 'logo_url'],
            'manufacturer_color' => ['name', 'manufacturer_name', 'hex_code'],
            'stock' => ['sku', 'quantity', 'distributor_name', 'distributor_sku'],
            'price' => ['sku', 'price', 'distributor_name', 'distributor_sku', 'min_quantity', 'max_quantity', 'currency'],
            'product' => $this->getProductHeaders($mode),
            default => [],
        };
    }

    /**
     * Obtenir les en-têtes pour les produits
     */
    protected function getProductHeaders(?string $mode): array
    {
        $baseHeaders = [
            'sku',
            'name',
            'category_name',
            'manufacturer_name',
            'color_name',
            'size_name',
            'primary_color_name',
        ];

        // Ajouter les champs distributeur si mode distributeur
        if ($mode === 'distributor') {
            $baseHeaders = array_merge($baseHeaders, [
                'distributor_name',
                'distributor_sku',
            ]);
        }

        // Ajouter les champs d'images (jusqu'à 8)
        for ($i = 1; $i <= 8; $i++) {
            $baseHeaders[] = "image_{$i}_url";
        }

        return $baseHeaders;
    }

    /**
     * Obtenir un exemple de ligne pour un type d'import
     */
    protected function getExampleRowForType(string $type, ?string $mode = null): array
    {
        return match($type) {
            'category' => ['Chaussures', ''],
            'distributor' => ['Mon Distributeur', 'https://example.com/logo.jpg'],
            'manufacturer' => ['Mon Fabricant', 'https://example.com/logo.jpg'],
            'manufacturer_color' => ['Rouge', 'Mon Fabricant', '#FF0000'],
            'stock' => ['PROD-001', '100', 'Mon Distributeur', 'DIST-SKU-001'],
            'price' => ['PROD-001', '29.99', 'Mon Distributeur', 'DIST-SKU-001', '1', '10', 'EUR'],
            'product' => $this->getProductExampleRow($mode),
            default => [],
        };
    }

    /**
     * Obtenir un exemple de ligne pour les produits
     */
    protected function getProductExampleRow(?string $mode): array
    {
        $baseRow = [
            'PROD-001',
            'Nom du produit',
            'Chaussures',
            'Mon Fabricant',
            'Rouge',
            '42',
            '',
        ];

        if ($mode === 'distributor') {
            $baseRow = array_merge($baseRow, [
                'Mon Distributeur',
                'DIST-SKU-001',
            ]);
        }

        // Ajouter des exemples d'URLs d'images
        for ($i = 1; $i <= 8; $i++) {
            $baseRow[] = $i === 1 ? 'https://example.com/image' . $i . '.jpg' : '';
        }

        return $baseRow;
    }

    /**
     * Sauvegarder un modèle CSV dans le storage public
     */
    public function saveTemplate(string $type, ?string $mode = null): string
    {
        $content = $this->generateTemplate($type, $mode);
        $fileName = $mode ? "template_{$type}_{$mode}.csv" : "template_{$type}.csv";
        $path = "csv-templates/{$fileName}";

        Storage::disk('public')->put($path, $content);
        return Storage::disk('public')->url($path);
    }
}
