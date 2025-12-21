<?php

namespace App\Services\CsvImport;

use App\Models\CsvImport;

class CsvRowMapperService
{
    /**
     * Appliquer le mapping de colonnes à une ligne CSV
     */
    public function mapRow(array $csvRow, CsvImport $import): array
    {
        $columnMapping = $import->column_mapping ?? [];
        
        if (empty($columnMapping)) {
            // Pas de mapping, retourner la ligne telle quelle
            return $csvRow;
        }
        
        $mappedRow = [];
        
        foreach ($columnMapping as $targetField => $sourceColumn) {
            if ($sourceColumn && isset($csvRow[$sourceColumn])) {
                $mappedRow[$targetField] = $csvRow[$sourceColumn];
            } else {
                $mappedRow[$targetField] = null;
            }
        }
        
        return $mappedRow;
    }

    /**
     * Appliquer les mappings de valeurs à une ligne
     */
    public function applyValueMappings(array $row, CsvImport $import): array
    {
        $valueMappings = $import->value_mappings ?? [];
        
        if (empty($valueMappings)) {
            return $row;
        }
        
        foreach ($valueMappings as $mapping) {
            $type = $mapping['type'] ?? null;
            $sourceValue = $mapping['source_value'] ?? null;
            $action = $mapping['action'] ?? null;
            
            if (!$type || !$sourceValue) {
                continue;
            }
            
            // Trouver le champ correspondant dans la ligne
            $fieldName = $this->getFieldNameForMappingType($type);
            
            if (!$fieldName || !isset($row[$fieldName])) {
                continue;
            }
            
            // Si la valeur correspond, appliquer le mapping
            if ($row[$fieldName] === $sourceValue) {
                if ($action === 'map' && isset($mapping['target_id'])) {
                    // Remplacer par la valeur mappée (on garde le nom pour l'instant)
                    // Le handler devra utiliser le matching service pour trouver l'ID réel
                    $row[$fieldName . '_mapped_id'] = $mapping['target_id'];
                } elseif ($action === 'create' && isset($mapping['new_value'])) {
                    // Utiliser la nouvelle valeur créée
                    $row[$fieldName] = $mapping['new_value'];
                }
            }
        }
        
        return $row;
    }

    /**
     * Obtenir le nom du champ CSV correspondant à un type de mapping
     */
    protected function getFieldNameForMappingType(string $mappingType): ?string
    {
        return match($mappingType) {
            'categories' => 'category_name',
            'manufacturers' => 'manufacturer_name',
            'primary_colors' => 'primary_color_name',
            'manufacturer_colors' => 'color_name',
            'sizes' => 'size_name',
            default => null,
        };
    }
}


