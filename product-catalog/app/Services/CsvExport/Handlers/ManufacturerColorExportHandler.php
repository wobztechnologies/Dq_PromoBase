<?php

namespace App\Services\CsvExport\Handlers;

use App\Models\PrimaryColor;
use App\Services\CsvExport\ExportHandlerInterface;

class ManufacturerColorExportHandler implements ExportHandlerInterface
{
    public function getHeaders(?string $mode = null): array
    {
        return ['name', 'manufacturer_name', 'hex_code', 'parent_name', 'color_sku_code', 'rgb', 'pantone_c', 'pantone_tcx', 'pms'];
    }

    public function getData(?string $mode = null, array $filters = []): array
    {
        $query = PrimaryColor::whereNotNull('manufacturer_id')
            ->with(['manufacturer', 'parent']);

        // Appliquer les filtres si nécessaire
        if (isset($filters['manufacturer_id'])) {
            $query->where('manufacturer_id', $filters['manufacturer_id']);
        }

        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $filters['parent_id']);
            }
        }

        $colors = $query->get();

        return $colors->map(function ($color) {
            return [
                'name' => $color->name,
                'manufacturer_name' => $color->manufacturer?->name ?? '',
                'hex_code' => $color->hex_code ?? '',
                'parent_name' => $color->parent?->name ?? '',
                'color_sku_code' => $color->color_sku_code ?? '',
                'rgb' => $color->rgb ?? '',
                'pantone_c' => $color->pantone_c ?? '',
                'pantone_tcx' => $color->pantone_tcx ?? '',
                'pms' => $color->pms ?? '',
            ];
        })->toArray();
    }
}
