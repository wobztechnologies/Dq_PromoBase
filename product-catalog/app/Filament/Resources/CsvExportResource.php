<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CsvExportResource\Pages;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Distributor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CsvExportResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    
    protected static ?string $navigationLabel = 'Exports CSV';
    
    protected static ?string $navigationGroup = 'Gestion des données';
    
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Configuration de l\'export')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->required()
                            ->options([
                                'category' => 'Catégories',
                                'distributor' => 'Distributeurs',
                                'manufacturer' => 'Fabricants',
                                'manufacturer_color' => 'Couleurs Fabricant',
                                'stock' => 'Stock',
                                'price' => 'Prix',
                                'product' => 'Produits',
                            ])
                            ->reactive()
                            ->label('Type d\'export'),
                        
                        Forms\Components\Select::make('mode')
                            ->options([
                                'manufacturer' => 'Fabricant',
                                'distributor' => 'Distributeur',
                            ])
                            ->visible(fn ($get) => $get('type') === 'product')
                            ->required(fn ($get) => $get('type') === 'product')
                            ->label('Mode'),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Filtres (optionnels)')
                    ->schema([
                        Forms\Components\Select::make('filters.category_id')
                            ->label('Catégorie')
                            ->options(Category::pluck('name', 'id'))
                            ->visible(fn ($get) => $get('type') === 'product')
                            ->searchable()
                            ->preload(),
                        
                        Forms\Components\Select::make('filters.manufacturer_id')
                            ->label('Fabricant')
                            ->options(Manufacturer::pluck('name', 'id'))
                            ->visible(fn ($get) => in_array($get('type'), ['product', 'manufacturer_color']))
                            ->searchable()
                            ->preload(),
                        
                        Forms\Components\Select::make('filters.distributor_id')
                            ->label('Distributeur')
                            ->options(Distributor::pluck('name', 'id'))
                            ->visible(fn ($get) => in_array($get('type'), ['stock', 'price']))
                            ->searchable()
                            ->preload(),
                        
                        Forms\Components\TextInput::make('filters.sku')
                            ->label('SKU')
                            ->visible(fn ($get) => $get('type') === 'product')
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('filters.sku_distributor')
                            ->label('SKU Distributeur')
                            ->visible(fn ($get) => in_array($get('type'), ['stock', 'price']))
                            ->maxLength(255),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\CreateCsvExport::route('/'),
        ];
    }
}
