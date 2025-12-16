<?php

namespace App\Filament\Resources\ProductVariantPriceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PriceTiersRelationManager extends RelationManager
{
    protected static string $relationship = 'priceTiers';

    protected static ?string $title = 'Grilles de prix';

    protected static ?string $modelLabel = 'Palier de prix';

    protected static ?string $pluralModelLabel = 'Grilles de prix';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('quantity_min')
                    ->label('Quantité minimum')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->minValue(1)
                    ->helperText('Quantité minimum pour ce palier de prix'),
                Forms\Components\TextInput::make('quantity_max')
                    ->label('Quantité maximum')
                    ->numeric()
                    ->nullable()
                    ->minValue(1)
                    ->helperText('Quantité maximum pour ce palier (laisser vide pour pas de limite)')
                    ->rules([
                        fn (Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                            if ($value !== null && $get('quantity_min') !== null && $value < $get('quantity_min')) {
                                $fail('La quantité maximum doit être supérieure ou égale à la quantité minimum.');
                            }
                        },
                    ]),
                Forms\Components\TextInput::make('unit_price')
                    ->label('Prix unitaire')
                    ->numeric()
                    ->required()
                    ->prefix('€')
                    ->step(0.01)
                    ->minValue(0)
                    ->helperText('Prix unitaire pour ce palier de quantité'),
                Forms\Components\Select::make('currency')
                    ->label('Devise')
                    ->options([
                        'EUR' => 'EUR (€)',
                        'USD' => 'USD ($)',
                        'GBP' => 'GBP (£)',
                    ])
                    ->default('EUR')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('quantity_min')
            ->columns([
                Tables\Columns\TextColumn::make('quantity_min')
                    ->label('Quantité min')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity_max')
                    ->label('Quantité max')
                    ->numeric()
                    ->default('∞')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Prix unitaire')
                    ->money(fn ($record) => $record->currency ?? 'EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency')
                    ->label('Devise')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('quantity_min', 'asc');
    }
}
