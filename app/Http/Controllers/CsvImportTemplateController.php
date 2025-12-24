<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use League\Csv\Writer;

class CsvImportTemplateController extends Controller
{
    public function download(Request $request, string $type): Response
    {
        $mode = $request->query('mode');
        $headers = $this->getHeadersForType($type, $mode);
        
        $csv = Writer::createFromString();
        $csv->insertOne($headers);
        
        // Ajouter une ligne d'exemple
        $example = $this->getExampleForType($type, $mode);
        if ($example) {
            $csv->insertOne($example);
        }
        
        $filename = $mode 
            ? "modele_import_{$type}_{$mode}.csv" 
            : "modele_import_{$type}.csv";
        
        return response($csv->toString(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Langues supportées pour les traductions
     */
    protected const SUPPORTED_LOCALES = ['fr', 'en', 'de', 'es', 'it', 'nl', 'pt', 'pl'];

    protected function getHeadersForType(string $type, ?string $mode = null): array
    {
        return match($type) {
            'category' => $this->getCategoryHeaders(),
            'distributor' => ['name', 'logo_url'],
            'manufacturer' => ['name', 'logo_url'],
            'manufacturer_color' => [
                'name', 
                'manufacturer_name', 
                'hex_code', 
                'parent_name', 
                'color_sku_code', 
                'rgb', 
                'pantone_c', 
                'pantone_tcx', 
                'pms'
            ],
            'stock' => ['sku_distributor', 'distributor_name', 'stock'],
            'price' => ['sku_distributor', 'distributor_name', 'price', 'tier_name', 'min_quantity'],
            'product' => $this->getProductHeaders($mode),
            default => [],
        };
    }

    protected function getCategoryHeaders(): array
    {
        $headers = ['name', 'parent_name'];
        
        // Ajouter les colonnes de traduction pour chaque langue
        foreach (self::SUPPORTED_LOCALES as $locale) {
            $headers[] = "name_{$locale}";
        }
        
        return $headers;
    }

    protected function getCategoryExample(): array
    {
        // Exemple avec traductions
        return [
            'Chaussures',           // name
            'Vêtements',            // parent_name
            'Chaussures',           // name_fr
            'Shoes',                // name_en
            'Schuhe',               // name_de
            'Zapatos',              // name_es
            'Scarpe',               // name_it
            'Schoenen',             // name_nl
            'Sapatos',              // name_pt
            'Buty',                 // name_pl
        ];
    }

    protected function getProductHeaders(?string $mode): array
    {
        $headers = [
            'sku',
            'name',
            'category_name',
            'manufacturer_name',
            'primary_color_name',
            'color_name',
            'variant_sku',
            'size_name',
            'size_sku',
        ];

        // Mode distributeur : ajouter les champs distributeur
        if ($mode === 'distributor') {
            $headers[] = 'distributor_name';
            $headers[] = 'sku_distributor';
        }

        // Ajouter les URLs d'images (8 max)
        for ($i = 1; $i <= 8; $i++) {
            $headers[] = "image_{$i}_url";
        }

        return $headers;
    }

    protected function getExampleForType(string $type, ?string $mode = null): ?array
    {
        return match($type) {
            'category' => $this->getCategoryExample(),
            'distributor' => ['Mon Distributeur', 'https://example.com/logo.png'],
            'manufacturer' => ['Mon Fabricant', 'https://example.com/logo.png'],
            'manufacturer_color' => [
                'Ash Heather', 
                'Kariban', 
                '#EBE7DE', 
                'Gris', 
                'ASH', 
                '235,231,222', 
                '', 
                '', 
                ''
            ],
            'stock' => ['DIST-SKU-001', 'Mon Distributeur', '100'],
            'price' => ['DIST-SKU-001', 'Mon Distributeur', '29.99', 'Standard', '1'],
            'product' => $this->getProductExample($mode),
            default => null,
        };
    }

    protected function getProductExample(?string $mode): array
    {
        $example = [
            'PROD-001',                    // sku
            'T-Shirt Coton Bio',           // name
            'Vêtements',                   // category_name
            'Kariban',                     // manufacturer_name
            'Gris',                        // primary_color_name
            'Ash Heather',                 // color_name
            'PROD-001-ASH',                // variant_sku (optionnel)
            'XL',                          // size_name
            'PROD-001-ASH-XL',             // size_sku (optionnel)
        ];

        if ($mode === 'distributor') {
            $example[] = 'Mon Distributeur';
            $example[] = 'DIST-SKU-001';
        }

        // Images d'exemple
        $example[] = 'https://example.com/image1.jpg';
        for ($i = 2; $i <= 8; $i++) {
            $example[] = '';
        }

        return $example;
    }
}
