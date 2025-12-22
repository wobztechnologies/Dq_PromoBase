<?php

namespace App\Services\CsvImport;

use App\Models\CsvImportMapping;
use App\Models\Category;
use App\Models\Distributor;
use App\Models\Manufacturer;
use App\Models\PrimaryColor;
use App\Models\Size;
use Illuminate\Support\Facades\DB;

class MatchingService
{
    /**
     * Trouver ou créer un mapping pour une valeur
     */
    public function findOrCreateMapping(
        string $mappingType,
        string $sourceValue,
        ?int $userId = null
    ): ?array {
        // Chercher un mapping existant
        $existing = CsvImportMapping::findExisting($mappingType, $sourceValue);
        
        if ($existing) {
            return [
                'id' => $existing->target_id,
                'type' => $existing->target_type,
                'name' => $existing->target_name,
                'mapped' => true,
            ];
        }
        
        // Chercher dans la base de données
        $entity = $this->findEntity($mappingType, $sourceValue);
        
        if ($entity) {
            // Créer le mapping pour réutilisation future
            CsvImportMapping::createOrGet(
                $mappingType,
                $sourceValue,
                get_class($entity),
                $entity->id,
                $this->getEntityName($entity),
                $userId
            );
            
            return [
                'id' => $entity->id,
                'type' => get_class($entity),
                'name' => $this->getEntityName($entity),
                'mapped' => true,
            ];
        }
        
        return null;
    }

    /**
     * Trouver une entité dans la base de données
     */
    protected function findEntity(string $mappingType, string $sourceValue): ?object
    {
        return match($mappingType) {
            'category' => Category::where('name', $sourceValue)->first(),
            'distributor' => Distributor::where('name', $sourceValue)->first(),
            'manufacturer' => Manufacturer::where('name', $sourceValue)->first(),
            'manufacturer_color' => PrimaryColor::where('name', $sourceValue)
                ->whereNotNull('manufacturer_id')
                ->first(),
            'size' => Size::where('name', $sourceValue)->first(),
            'primary_color' => PrimaryColor::where('name', $sourceValue)
                ->whereNull('manufacturer_id')
                ->first(),
            default => null,
        };
    }

    /**
     * Obtenir le nom d'une entité
     */
    protected function getEntityName(object $entity): string
    {
        return $entity->name ?? $entity->id;
    }

    /**
     * Créer un mapping après création d'entité
     */
    public function createMapping(
        string $mappingType,
        string $sourceValue,
        string $targetType,
        string $targetId,
        ?string $targetName = null,
        ?int $userId = null
    ): CsvImportMapping {
        return CsvImportMapping::createOrGet(
            $mappingType,
            $sourceValue,
            $targetType,
            $targetId,
            $targetName,
            $userId
        );
    }

    /**
     * Obtenir toutes les valeurs non mappées pour un type
     */
    public function getUnmappedValues(string $mappingType, array $sourceValues): array
    {
        $mappedValues = CsvImportMapping::where('mapping_type', $mappingType)
            ->whereIn('source_value', $sourceValues)
            ->pluck('source_value')
            ->toArray();
        
        return array_diff($sourceValues, $mappedValues);
    }

    /**
     * Obtenir les suggestions de matching (fuzzy matching)
     */
    public function getSuggestions(string $mappingType, string $sourceValue, int $limit = 5): array
    {
        $entities = match($mappingType) {
            'category' => Category::all(),
            'distributor' => Distributor::all(),
            'manufacturer' => Manufacturer::all(),
            'manufacturer_color' => PrimaryColor::whereNotNull('manufacturer_id')->get(),
            'size' => Size::all(),
            'primary_color' => PrimaryColor::whereNull('manufacturer_id')->get(),
            default => collect(),
        };
        
        $suggestions = [];
        foreach ($entities as $entity) {
            $similarity = $this->calculateSimilarity($sourceValue, $entity->name);
            if ($similarity > 0.5) {
                $suggestions[] = [
                    'id' => $entity->id,
                    'name' => $entity->name,
                    'similarity' => $similarity,
                ];
            }
        }
        
        usort($suggestions, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
        
        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Calculer la similarité entre deux chaînes (Levenshtein)
     */
    protected function calculateSimilarity(string $str1, string $str2): float
    {
        $str1 = mb_strtolower($str1);
        $str2 = mb_strtolower($str2);
        
        $maxLen = max(mb_strlen($str1), mb_strlen($str2));
        if ($maxLen === 0) {
            return 1.0;
        }
        
        $distance = levenshtein($str1, $str2);
        return 1 - ($distance / $maxLen);
    }
}
