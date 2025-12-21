<?php

namespace App\Services\CsvImport\Handlers;

use App\Models\CsvImport;
use App\Models\Category;
use App\Services\CsvImport\ImportHandlerInterface;
use App\Services\CsvImport\MatchingService;
use Illuminate\Support\Str;

class CategoryImportHandler implements ImportHandlerInterface
{
    /**
     * Langues supportées pour les traductions
     */
    protected const SUPPORTED_LOCALES = ['fr', 'en', 'de', 'es', 'it', 'nl', 'pt', 'pl'];

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
            
            // Gérer les traductions
            $translations = $this->extractTranslations($row);
            if (!empty($translations)) {
                // Fusionner avec les traductions existantes
                $existingTranslations = $category->translations ?? [];
                $category->translations = array_merge($existingTranslations, $translations);
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
            
            return true;
            
        } catch (\Exception $e) {
            $import->addLog('error', 'Erreur lors du traitement: ' . $e->getMessage(), $row, $rowNumber);
            return false;
        }
    }

    /**
     * Extraire les traductions depuis la ligne CSV
     */
    protected function extractTranslations(array $row): array
    {
        $translations = [];
        
        foreach (self::SUPPORTED_LOCALES as $locale) {
            $key = "name_{$locale}";
            if (!empty($row[$key])) {
                $translations[$locale] = trim($row[$key]);
            }
        }
        
        return $translations;
    }

    /**
     * Obtenir la liste des langues supportées
     */
    public static function getSupportedLocales(): array
    {
        return self::SUPPORTED_LOCALES;
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
