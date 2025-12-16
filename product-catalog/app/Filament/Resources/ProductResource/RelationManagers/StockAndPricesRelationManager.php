<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Distributor;
use App\Models\ProductVariantPrice;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Filament\Notifications\Notification;
use Filament\Forms;

class StockAndPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'variantPrices';

    protected static ?string $title = 'Stock & Prix';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $product = $this->getOwnerRecord();
        $distributors = Distributor::orderBy('name')->get();
        
        // Construire les colonnes
        $columns = [
            Tables\Columns\TextColumn::make('id')
                ->label('ID Debug')
                ->searchable(false)
                ->sortable(false)
                ->visible(false), // Caché mais utilisé comme clé unique
            Tables\Columns\TextColumn::make('variant_label')
                ->label('Variante')
                ->html()
                ->getStateUsing(function ($record) use ($product) {
                    $parts = [];
                    
                    // Indentation pour les tailles (sous-variantes)
                    $indent = '';
                    if ($record->sizeVariant) {
                        $indent = '<span class="inline-block w-6"></span>'; // Indentation visuelle
                    }
                    
                    // Vérifier si cette couleur a des tailles
                    $colorHasSizes = false;
                    if ($record->colorVariant && !$record->sizeVariant) {
                        $colorHasSizes = \App\Models\ProductSizeVariant::where('product_color_variant_id', $record->colorVariant->id)->exists();
                    }
                    
                    if ($record->colorVariant) {
                        $colorName = $record->colorVariant->primaryColor->full_name ?? $record->colorVariant->primaryColor->name ?? 'Inconnu';
                        
                        if ($record->sizeVariant) {
                            // Affichage hiérarchique : taille sous la couleur
                            $parts[] = '<div class="text-xs text-gray-500 dark:text-gray-400">' . $indent . '└─ 📏 ' . htmlspecialchars($record->sizeVariant->size->name) . '</div>';
                            $parts[] = '<div class="text-xs text-gray-400 dark:text-gray-500 ml-6">' . htmlspecialchars($record->sizeVariant->sku) . '</div>';
                        } else {
                            // Couleur seule - griser si elle a des tailles
                            $textClass = $colorHasSizes ? 'text-gray-400 dark:text-gray-500' : 'text-blue-600 dark:text-blue-400';
                            $parts[] = '<div class="font-medium ' . $textClass . '">🎨 ' . htmlspecialchars($record->colorVariant->sku) . '</div>';
                            $parts[] = '<div class="text-xs text-gray-400 dark:text-gray-500">' . htmlspecialchars($colorName) . '</div>';
                            if ($colorHasSizes) {
                                $parts[] = '<div class="text-xs text-gray-400 dark:text-gray-500 italic">(voir tailles ci-dessous)</div>';
                            }
                        }
                    } else {
                        $parts[] = '<div class="font-medium">📦 Produit seul</div>';
                    }
                    
                    return new HtmlString(implode('', $parts));
                })
                ->extraAttributes(function ($record) {
                    // Ajouter un fond gris clair si c'est une couleur avec des tailles
                    if ($record->colorVariant && !$record->sizeVariant) {
                        $colorHasSizes = \App\Models\ProductSizeVariant::where('product_color_variant_id', $record->colorVariant->id)->exists();
                        if ($colorHasSizes) {
                            return ['class' => 'bg-gray-50 dark:bg-gray-800'];
                        }
                    }
                    return [];
                })
                ->searchable(false)
                ->sortable(false),
        ];
        
        // Ajouter une colonne complète pour chaque distributeur avec stock, prix et action
        foreach ($distributors as $distributor) {
            $columns[] = Tables\Columns\TextColumn::make('distributor_' . $distributor->id)
                ->label($distributor->name)
                ->html()
                ->getStateUsing(function ($record) use ($distributor, $product) {
                    // Vérifier si cette couleur a des tailles et si on est sur la ligne sans taille
                    if ($record->colorVariant && !$record->sizeVariant) {
                        $colorHasSizes = \App\Models\ProductSizeVariant::where('product_color_variant_id', $record->colorVariant->id)->exists();
                        if ($colorHasSizes) {
                            // Ne pas afficher de données pour les couleurs qui ont des tailles
                            return new HtmlString('<div class="text-center text-gray-400 text-sm py-2">-</div>');
                        }
                    }
                    
                    $price = ProductVariantPrice::where('product_id', $product->id)
                        ->where('distributor_id', $distributor->id)
                        ->where('product_color_variant_id', $record->product_color_variant_id)
                        ->where('product_size_variant_id', $record->product_size_variant_id)
                        ->with('priceTiers')
                        ->first();
                    
                    if (!$price) {
                        return new HtmlString('<div class="text-center text-gray-400 text-sm py-2">-</div>');
                    }
                    
                    $html = '<div class="text-center border-l-2 border-gray-300 dark:border-gray-600 pl-2">';
                    
                    // SKU Distributeur
                    if ($price->sku_distributor) {
                        $html .= '<div class="text-xs text-gray-600 dark:text-gray-400 font-mono mb-2 bg-gray-50 dark:bg-gray-700 px-2 py-1 rounded">' 
                               . htmlspecialchars($price->sku_distributor) 
                               . '</div>';
                    }
                    
                    // Stock
                    $html .= '<div class="text-sm font-semibold ' . ($price->stock > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400') . '">Stock: ' . $price->stock . '</div>';
                    
                    // Prix
                    $basePrice = $price->getPriceForQuantity(1);
                    $currency = $price->priceTiers->first()->currency ?? 'EUR';
                    $html .= '<div class="text-sm font-semibold mt-1">Prix: ' . number_format($basePrice, 2, ',', ' ') . ' ' . $currency . '</div>';
                    
                    // Nombre de paliers
                    if ($price->priceTiers->count() > 0) {
                        $html .= '<div class="text-xs text-gray-500 dark:text-gray-400 mt-1">' . $price->priceTiers->count() . ' palier' . ($price->priceTiers->count() > 1 ? 's' : '') . '</div>';
                    }
                    
                    $html .= '</div>';
                    
                    return new HtmlString($html);
                })
                ->action(
                    Tables\Actions\Action::make('view_tiers_' . $distributor->id)
                        ->label('Voir paliers')
                        ->icon('heroicon-o-currency-dollar')
                        ->modalHeading(function ($record) use ($distributor, $product) {
                            if (!$record) return 'Grilles de prix';
                            $price = ProductVariantPrice::where('product_id', $product->id)
                                ->where('distributor_id', $distributor->id)
                                ->where('product_color_variant_id', $record->product_color_variant_id)
                                ->where('product_size_variant_id', $record->product_size_variant_id)
                                ->first();
                            return 'Grilles de prix - ' . ($price ? $price->variant_sku : 'N/A') . ' - ' . $distributor->name;
                        })
                        ->modalContent(function ($record) use ($distributor, $product) {
                            if (!$record) return view('filament.components.price-tiers-table', ['tiers' => collect()]);
                            
                            $price = ProductVariantPrice::where('product_id', $product->id)
                                ->where('distributor_id', $distributor->id)
                                ->where('product_color_variant_id', $record->product_color_variant_id)
                                ->where('product_size_variant_id', $record->product_size_variant_id)
                                ->with(['priceTiers' => fn($q) => $q->orderBy('quantity_min')])
                                ->first();
                            
                            return view('filament.components.price-tiers-table', [
                                'tiers' => $price ? $price->priceTiers : collect(),
                            ]);
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Fermer')
                        ->color('info')
                        ->size('xs')
                        ->link()
                        ->visible(function ($record) use ($distributor, $product) {
                            if (!$record) return false;
                            
                            // Ne pas afficher le bouton pour les couleurs qui ont des tailles
                            if ($record->colorVariant && !$record->sizeVariant) {
                                $colorHasSizes = \App\Models\ProductSizeVariant::where('product_color_variant_id', $record->colorVariant->id)->exists();
                                if ($colorHasSizes) {
                                    return false;
                                }
                            }
                            
                            $price = ProductVariantPrice::where('product_id', $product->id)
                                ->where('distributor_id', $distributor->id)
                                ->where('product_color_variant_id', $record->product_color_variant_id)
                                ->where('product_size_variant_id', $record->product_size_variant_id)
                                ->first();
                            return $price && $price->priceTiers()->count() > 0;
                        })
                )
                ->extraAttributes(function ($record) {
                    // Ajouter un fond gris clair si c'est une couleur avec des tailles
                    if ($record->colorVariant && !$record->sizeVariant) {
                        $colorHasSizes = \App\Models\ProductSizeVariant::where('product_color_variant_id', $record->colorVariant->id)->exists();
                        if ($colorHasSizes) {
                            return ['class' => 'bg-gray-50 dark:bg-gray-800'];
                        }
                    }
                    return [];
                })
                ->searchable(false)
                ->sortable(false);
        }
        
        return $table
            ->columns($columns)
            ->query(function () {
                $product = $this->getOwnerRecord();
                
                // Sous-requête pour obtenir un ID par (color, size) en utilisant array_agg
                $uniqueIds = \DB::table('product_variant_prices')
                    ->selectRaw('(array_agg(id))[1] as id')
                    ->where('product_id', $product->id)
                    ->groupBy('product_color_variant_id', 'product_size_variant_id')
                    ->pluck('id');
                
                // Query principale avec les IDs uniques
                return ProductVariantPrice::query()
                    ->whereIn('id', $uniqueIds)
                    ->with([
                        'colorVariant.primaryColor.parent',
                        'sizeVariant.size',
                        'priceTiers' => fn($q) => $q->orderBy('quantity_min', 'asc')
                    ])
                    ->orderByRaw('
                        CASE 
                            WHEN product_color_variant_id IS NULL THEN 0
                            ELSE 1
                        END,
                        product_color_variant_id,
                        CASE 
                            WHEN product_size_variant_id IS NULL THEN 0
                            ELSE 1
                        END,
                        product_size_variant_id
                    ');
            })
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->emptyStateHeading('Aucune variante de prix')
            ->emptyStateDescription('Configurez les prix et stocks pour les variantes de ce produit.');
    }

    protected function getViewData(): array
    {
        $product = $this->getOwnerRecord();
        return array_merge(parent::getViewData(), [
            'product' => $product,
        ]);
    }
}
