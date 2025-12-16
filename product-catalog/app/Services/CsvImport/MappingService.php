<?php

namespace App\Services\CsvImport;

use App\Models\CsvImport;
use App\Models\CsvImportMapping;
use App\Models\Category;
use App\Models\Distributor;
use App\Models\Manufacturer;
use App\Models\PrimaryColor;
use App\Models\Size;
use Illuminate\Support\Str;

class MappingService
{
    /**
     * Trouver ou créer un mapping pour une valeur source
     */
    public function findOrCreateMapping(
        CsvImport $import,
        string $entityType,
        string $sourceValue,
        ?string $targetId = null,
        ?string $targetName = null,
        bool $isCreated = false
    ): CsvImportMapping {
        // Chercher un mapping existant pour cette valeur source
        $existingMapping = CsvImportMapping::findExisting($entityType, $sourceValue);
        
        if ($existingMapping && $targetId === null) {
            // Réutiliser le mapping existant
            $targetId = $existingMapping->target_id;
            $targetName = $existingMapping->target_name;
        }

        return CsvImportMapping::create([
            'csv_import_id' => $import->id,
            'entity_type' => $entityType,
            'source_value' => $sourceValue,
            'target_id' => $targetId,
            'target_name' => $targetName,
            'is_created' => $isCreated,
        ]);
    }

    /**
     * Trouver une catégorie par nom (avec hiérarchie)
     */
    public function findCategoryByName(string $name, ?string $parentName = null): ?Category
    {
        $query = Category::where('name', $name);
        
        if ($parentName) {
            $parent = Category::where('name', $parentName)->first();
            if ($parent) {
                $query->where('parent_id', $parent->id);
            }
        }
        
        return $query->first();
    }

    /**
     * Trouver un distributeur par nom
     */
    public function findDistributorByName(string $name): ?Distributor
    {
        return Distributor::where('name', $name)->first();
    }

    /**
     * Trouver un fabricant par nom
     */
    public function findManufacturerByName(string $name): ?Manufacturer
    {
        return Manufacturer::where('name', $name)->first();
    }

    /**
     * Trouver une couleur fabricant par nom
     */
    public function findManufacturerColorByName(string $name, string $manufacturerName): ?PrimaryColor
    {
        $manufacturer = $this->findManufacturerByName($manufacturerName);
        if (!$manufacturer) {
            return null;
        }

        return PrimaryColor::where('name', $name)
            ->where('manufacturer_id', $manufacturer->id)
            ->first();
    }

    /**
     * Trouver une taille par nom
     */
    public function findSizeByName(string $name): ?Size
    {
        return Size::where('name', $name)->first();
    }

    /**
     * Trouver une couleur principale par nom
     */
    public function findPrimaryColorByName(string $name): ?PrimaryColor
    {
        return PrimaryColor::where('name', $name)
            ->whereNull('manufacturer_id')
            ->first();
    }

    /**
     * Obtenir les suggestions de matching pour une valeur
     */
    public function getSuggestions(string $entityType, string $sourceValue, ?string $manufacturerName = null): array
    {
        return match($entityType) {
            'category' => $this->getCategorySuggestions($sourceValue),
            'distributor' => $this->getDistributorSuggestions($sourceValue),
            'manufacturer' => $this->getManufacturerSuggestions($sourceValue),
            'manufacturer_color' => $this->getManufacturerColorSuggestions($sourceValue, $manufacturerName),
            'size' => $this->getSizeSuggestions($sourceValue),
            'primary_color' => $this->getPrimaryColorSuggestions($sourceValue),
            default => [],
        };
    }

    protected function getCategorySuggestions(string $sourceValue): array
    {
        return Category::where('name', 'ILIKE', "%{$sourceValue}%")
            ->limit(10)
            ->get()
            ->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'path' => $cat->path,
            ])
            ->toArray();
    }

    protected function getDistributorSuggestions(string $sourceValue): array
    {
        return Distributor::where('name', 'ILIKE', "%{$sourceValue}%")
            ->limit(10)
            ->get()
            ->map(fn($dist) => [
                'id' => $dist->id,
                'name' => $dist->name,
            ])
            ->toArray();
    }

    protected function getManufacturerSuggestions(string $sourceValue): array
    {
        return Manufacturer::where('name', 'ILIKE', "%{$sourceValue}%")
            ->limit(10)
            ->get()
            ->map(fn($man) => [
                'id' => $man->id,
                'name' => $man->name,
            ])
            ->toArray();
    }

    protected function getManufacturerColorSuggestions(string $sourceValue, ?string $manufacturerName): array
    {
        $query = PrimaryColor::where('name', 'ILIKE', "%{$sourceValue}%");
        
        if ($manufacturerName) {
            $manufacturer = $this->findManufacturerByName($manufacturerName);
            if ($manufacturer) {
                $query->where('manufacturer_id', $manufacturer->id);
            }
        }
        
        return $query->limit(10)
            ->get()
            ->map(fn($color) => [
                'id' => $color->id,
                'name' => $color->name,
                'manufacturer' => $color->manufacturer?->name,
            ])
            ->toArray();
    }

    protected function getSizeSuggestions(string $sourceValue): array
    {
        return Size::where('name', 'ILIKE', "%{$sourceValue}%")
            ->limit(10)
            ->get()
            ->map(fn($size) => [
                'id' => $size->id,
                'name' => $size->name,
            ])
            ->toArray();
    }

    protected function getPrimaryColorSuggestions(string $sourceValue): array
    {
        return PrimaryColor::where('name', 'ILIKE', "%{$sourceValue}%")
            ->whereNull('manufacturer_id')
            ->limit(10)
            ->get()
            ->map(fn($color) => [
                'id' => $color->id,
                'name' => $color->name,
                'hex_code' => $color->hex_code,
            ])
            ->toArray();
    }
}
