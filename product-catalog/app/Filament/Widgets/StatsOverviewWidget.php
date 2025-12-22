<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\PrimaryColor;
use App\Models\Product;
use App\Models\ProductColorVariant;
use App\Models\ProductImage;
use App\Models\ProductModel3D;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $models3DPublished = ProductModel3D::where('status', 'Published')->count();
        $models3DTotal = ProductModel3D::count();

        return [
            Stat::make('Fabricants', Manufacturer::count())
                ->description('Fabricants enregistrés')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('primary'),

            Stat::make('Couleurs principales', PrimaryColor::roots()->count())
                ->description('Couleurs de base')
                ->descriptionIcon('heroicon-m-paint-brush')
                ->color('success'),

            Stat::make('Couleurs fabricant', PrimaryColor::subColors()->count())
                ->description('Couleurs des fabricants')
                ->descriptionIcon('heroicon-m-swatch')
                ->color('info'),

            Stat::make('Produits', Product::count())
                ->description(ProductColorVariant::count() . ' variantes couleur')
                ->descriptionIcon('heroicon-m-cube')
                ->color('warning'),

            Stat::make('Images', ProductImage::count())
                ->description('Images de produits')
                ->descriptionIcon('heroicon-m-photo')
                ->color('gray'),

            Stat::make('Modèles 3D', $models3DPublished . ' / ' . $models3DTotal)
                ->description('Publiés / Total')
                ->descriptionIcon('heroicon-m-cube-transparent')
                ->color('danger'),

            Stat::make('Catégories', Category::count())
                ->description('Catégories de produits')
                ->descriptionIcon('heroicon-m-folder')
                ->color('primary'),
        ];
    }
}

