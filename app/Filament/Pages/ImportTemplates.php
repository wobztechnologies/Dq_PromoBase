<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ImportTemplates extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';
    protected static ?string $navigationLabel = 'Modèles d\'import';
    protected static ?string $title = 'Modèles d\'import CSV';
    protected static ?string $navigationGroup = 'Gestion des données';
    protected static ?int $navigationSort = 7;
    protected static string $view = 'filament.pages.import-templates';

    /**
     * Liste des types d'import disponibles avec leurs descriptions
     */
    public function getImportTypes(): array
    {
        return [
            [
                'type' => 'product',
                'mode' => null,
                'name' => 'Produits (Standard)',
                'description' => 'Import des produits avec variantes (couleurs, tailles) et images. Sans informations distributeur.',
                'icon' => 'heroicon-o-cube',
                'color' => 'primary',
                'fields' => ['sku', 'name', 'category_name', 'manufacturer_name', 'primary_color_name', 'color_name', 'size_name', 'image_1_url...image_8_url'],
            ],
            [
                'type' => 'product',
                'mode' => 'distributor',
                'name' => 'Produits (Distributeur)',
                'description' => 'Import des produits avec les informations distributeur (SKU distributeur).',
                'icon' => 'heroicon-o-truck',
                'color' => 'info',
                'fields' => ['sku', 'name', 'category_name', 'manufacturer_name', 'primary_color_name', 'color_name', 'size_name', 'distributor_name', 'sku_distributor', 'image_1_url...image_8_url'],
            ],
            [
                'type' => 'category',
                'mode' => null,
                'name' => 'Catégories',
                'description' => 'Import des catégories de produits avec hiérarchie parent/enfant et traductions multilingues (FR, EN, DE, ES, IT, NL, PT, PL).',
                'icon' => 'heroicon-o-folder',
                'color' => 'success',
                'fields' => ['name', 'parent_name', 'name_fr', 'name_en', 'name_de', 'name_es', 'name_it', 'name_nl', 'name_pt', 'name_pl'],
            ],
            [
                'type' => 'manufacturer',
                'mode' => null,
                'name' => 'Fabricants',
                'description' => 'Import des fabricants/marques avec leur logo.',
                'icon' => 'heroicon-o-building-office',
                'color' => 'warning',
                'fields' => ['name', 'logo_url'],
            ],
            [
                'type' => 'distributor',
                'mode' => null,
                'name' => 'Distributeurs',
                'description' => 'Import des distributeurs/fournisseurs avec leur logo.',
                'icon' => 'heroicon-o-building-storefront',
                'color' => 'danger',
                'fields' => ['name', 'logo_url'],
            ],
            [
                'type' => 'manufacturer_color',
                'mode' => null,
                'name' => 'Couleurs fabricant',
                'description' => 'Import des couleurs spécifiques aux fabricants avec codes couleur (Hex, RGB, Pantone).',
                'icon' => 'heroicon-o-swatch',
                'color' => 'gray',
                'fields' => ['name', 'manufacturer_name', 'hex_code', 'parent_name', 'color_sku_code', 'rgb', 'pantone_c', 'pantone_tcx', 'pms'],
            ],
            [
                'type' => 'stock',
                'mode' => null,
                'name' => 'Stocks',
                'description' => 'Mise à jour des quantités en stock par distributeur.',
                'icon' => 'heroicon-o-archive-box',
                'color' => 'info',
                'fields' => ['sku_distributor', 'distributor_name', 'stock'],
            ],
            [
                'type' => 'price',
                'mode' => null,
                'name' => 'Prix',
                'description' => 'Import des prix avec paliers de quantité par distributeur.',
                'icon' => 'heroicon-o-currency-euro',
                'color' => 'success',
                'fields' => ['sku_distributor', 'distributor_name', 'price', 'tier_name', 'min_quantity'],
            ],
        ];
    }
}
