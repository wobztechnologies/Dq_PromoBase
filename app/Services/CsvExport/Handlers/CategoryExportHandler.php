<?php

namespace App\Services\CsvExport\Handlers;

use App\Models\Category;
use App\Services\CsvExport\ExportHandlerInterface;

class CategoryExportHandler implements ExportHandlerInterface
{
    public function getHeaders(?string $mode = null): array
    {
        return ['name', 'parent_name'];
    }

    public function getData(?string $mode = null, array $filters = []): array
    {
        $query = Category::query();

        // Appliquer les filtres si nécessaire
        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $filters['parent_id']);
            }
        }

        $categories = $query->with('parent')->get();

        return $categories->map(function ($category) {
            return [
                'name' => $category->name,
                'parent_name' => $category->parent?->name ?? '',
            ];
        })->toArray();
    }
}
