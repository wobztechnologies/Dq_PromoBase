<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use League\Csv\Writer;

class CsvImportTemplateController extends Controller
{
    public function download(string $type): Response
    {
        $headers = $this->getHeadersForType($type);
        
        $csv = Writer::createFromString();
        $csv->insertOne($headers);
        
        // Ajouter une ligne d'exemple
        $example = $this->getExampleForType($type);
        if ($example) {
            $csv->insertOne($example);
        }
        
        $filename = "modele_import_{$type}_" . date('Y-m-d') . ".csv";
        
        return response($csv->toString(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    protected function getHeadersForType(string $type): array
    {
        return match($type) {
            'category' => ['name', 'parent_name'],
            'distributor' => ['name', 'logo_url'],
            'manufacturer' => ['name', 'logo_url'],
            'manufacturer_color' => ['name', 'manufacturer_name', 'hex_code', 'parent_name'],
            'stock' => ['sku_distributor', 'distributor_name', 'stock'],
            'price' => ['sku_distributor', 'distributor_name', 'price', 'tier_name', 'min_quantity'],
            'product' => [
                'sku',
                'name',
                'category_name',
                'manufacturer_name',
                'color_name',
                'size_name',
                'sku_distributor',
                'distributor_name',
                'image_1_url',
                'image_2_url',
                'image_3_url',
                'image_4_url',
                'image_5_url',
                'image_6_url',
                'image_7_url',
                'image_8_url',
            ],
            default => [],
        };
    }

    protected function getExampleForType(string $type): ?array
    {
        return match($type) {
            'category' => ['Chaussures', 'Vêtements'],
            'distributor' => ['Mon Distributeur', 'https://example.com/logo.png'],
            'manufacturer' => ['Mon Fabricant', 'https://example.com/logo.png'],
            'manufacturer_color' => ['Rouge', 'Mon Fabricant', '#FF0000', 'Rouge Principal'],
            'stock' => ['SKU-123', 'Mon Distributeur', '100'],
            'price' => ['SKU-123', 'Mon Distributeur', '29.99', 'Standard', '1'],
            'product' => [
                'PROD-001',
                'Produit exemple',
                'Chaussures',
                'Mon Fabricant',
                'Rouge',
                '42',
                'DIST-SKU-001',
                'Mon Distributeur',
                'https://example.com/image1.jpg',
                'https://example.com/image2.jpg',
                '',
                '',
                '',
                '',
                '',
                '',
            ],
            default => null,
        };
    }
}
