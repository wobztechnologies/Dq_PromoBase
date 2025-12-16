<?php

namespace App\Services\CsvImport\Handlers;

use App\Models\CsvImport;
use App\Models\Category;
use App\Services\CsvImport\ImportHandlerInterface;
use App\Services\CsvImport\MatchingService;
use Illuminate\Support\Str;

class CategoryImportHandler implements ImportHandlerInterface
{
    public function __construct(
        protected MatchingService $matchingService
    ) {}

    public function processRow(CsvImport $import, array $row, int $rowNumber): bool
    {
        try {
            $name = $row['name'] ?? null;
            $parentName = $row['parent_name'] ?? null;
            
            if (!$name) {
                $import->addLog('error', 'Nom de catégorie manquant', $row, $rowNumber);
                return false;
            }
            
            // Chercher ou créer la catégorie
            $category = Category::where('name', $name)->first();
            
            if (!$category && $import->strategy === 'create_update') {
                $category = new Category();
                $category->name = $name;
            }
            
            if (!$category) {
                $import->addLog('error', 'Catégorie non trouvée et création non autorisée', $row, $rowNumber);
                return false;
            }
            
            // Gérer le parent si spécifié
            if ($parentName) {
                $parent = Category::where('name', $parentName)->first();
                if ($parent) {
                    $category->parent_id = $parent->id;
                } else {
                    $import->addLog('warning', "Catégorie parente '{$parentName}' non trouvée", $row, $rowNumber);
                }
            }
            
            $category->save();
            
            // Créer le mapping
            $this->matchingService->createMapping(
                'category',
                $name,
                Category::class,
                $category->id,
                $category->name,
                $import->created_by
            );
            
            $import->incrementSuccessful();
            return true;
            
        } catch (\Exception $e) {
            $import->addLog('error', 'Erreur lors du traitement: ' . $e->getMessage(), $row, $rowNumber);
            return false;
        }
    }

    public function getMatchingValues(array $rows): array
    {
        $values = [];
        
        foreach ($rows as $row) {
            if (!empty($row['name'])) {
                $values['category'][] = $row['name'];
            }
            if (!empty($row['parent_name'])) {
                $values['category'][] = $row['parent_name'];
            }
        }
        
        return $values;
    }
}
