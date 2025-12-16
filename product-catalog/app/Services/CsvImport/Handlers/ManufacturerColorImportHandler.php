<?php

namespace App\Services\CsvImport\Handlers;

use App\Models\CsvImport;
use App\Models\PrimaryColor;
use App\Models\Manufacturer;
use App\Services\CsvImport\ImportHandlerInterface;
use App\Services\CsvImport\MatchingService;

class ManufacturerColorImportHandler implements ImportHandlerInterface
{
    public function __construct(
        protected MatchingService $matchingService
    ) {}

    public function processRow(CsvImport $import, array $row, int $rowNumber): bool
    {
        try {
            $name = $row['name'] ?? null;
            $manufacturerName = $row['manufacturer_name'] ?? null;
            $hexCode = $row['hex_code'] ?? null;
            $parentName = $row['parent_name'] ?? null;
            
            if (!$name || !$manufacturerName) {
                $import->addLog('error', 'Nom de couleur ou fabricant manquant', $row, $rowNumber);
                return false;
            }
            
            // Trouver le fabricant
            $manufacturer = Manufacturer::where('name', $manufacturerName)->first();
            if (!$manufacturer) {
                $import->addLog('error', "Fabricant '{$manufacturerName}' non trouvé", $row, $rowNumber);
                return false;
            }
            
            // Chercher ou créer la couleur
            $color = PrimaryColor::where('name', $name)
                ->where('manufacturer_id', $manufacturer->id)
                ->first();
            
            if (!$color && $import->strategy === 'create_update') {
                $color = new PrimaryColor();
                $color->name = $name;
                $color->manufacturer_id = $manufacturer->id;
            }
            
            if (!$color) {
                $import->addLog('error', 'Couleur non trouvée et création non autorisée', $row, $rowNumber);
                return false;
            }
            
            // Gérer le parent si spécifié
            if ($parentName) {
                $parent = PrimaryColor::where('name', $parentName)
                    ->whereNull('manufacturer_id')
                    ->first();
                if ($parent) {
                    $color->parent_id = $parent->id;
                } else {
                    $import->addLog('warning', "Couleur parente '{$parentName}' non trouvée", $row, $rowNumber);
                }
            }
            
            if ($hexCode) {
                $color->hex_code = $hexCode;
            }
            
            $color->save();
            
            // Créer le mapping
            $this->matchingService->createMapping(
                'manufacturer_color',
                $name,
                PrimaryColor::class,
                $color->id,
                $color->name,
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
                $values['manufacturer_color'][] = $row['name'];
            }
            if (!empty($row['manufacturer_name'])) {
                $values['manufacturer'][] = $row['manufacturer_name'];
            }
            if (!empty($row['parent_name'])) {
                $values['primary_color'][] = $row['parent_name'];
            }
        }
        
        return $values;
    }
}
