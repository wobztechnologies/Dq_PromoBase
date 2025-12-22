<?php

namespace App\Services\CsvImport\Handlers;

use App\Models\CsvImport;
use App\Models\Manufacturer;
use App\Services\CsvImport\ImportHandlerInterface;
use App\Services\CsvImport\MatchingService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ManufacturerImportHandler implements ImportHandlerInterface
{
    public function __construct(
        protected MatchingService $matchingService
    ) {}

    public function processRow(CsvImport $import, array $row, int $rowNumber): bool
    {
        try {
            $name = $row['name'] ?? null;
            $logoUrl = $row['logo_url'] ?? null;
            
            if (!$name) {
                $import->addLog('error', 'Nom de fabricant manquant', $row, $rowNumber);
                return false;
            }
            
            // Chercher ou créer le fabricant
            $manufacturer = Manufacturer::where('name', $name)->first();
            
            if (!$manufacturer && $import->strategy === 'create_update') {
                $manufacturer = new Manufacturer();
                $manufacturer->name = $name;
            }
            
            if (!$manufacturer) {
                $import->addLog('error', 'Fabricant non trouvé et création non autorisée', $row, $rowNumber);
                return false;
            }
            
            // Télécharger le logo si URL fournie
            if ($logoUrl && ($import->strategy === 'create_update' || !$manufacturer->logo_s3_url)) {
                try {
                    $logoPath = $this->downloadImage($logoUrl, 'manufacturerslogos', $name);
                    if ($logoPath) {
                        $manufacturer->logo_s3_url = $logoPath;
                    }
                } catch (\Exception $e) {
                    $import->addLog('warning', "Impossible de télécharger le logo: " . $e->getMessage(), $row, $rowNumber);
                }
            }
            
            $manufacturer->save();
            
            // Créer le mapping
            $this->matchingService->createMapping(
                'manufacturer',
                $name,
                Manufacturer::class,
                $manufacturer->id,
                $manufacturer->name,
                $import->created_by
            );
            
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
                $values['manufacturer'][] = $row['name'];
            }
        }
        
        return $values;
    }

    protected function downloadImage(string $url, string $folder, string $name): ?string
    {
        try {
            $contents = file_get_contents($url);
            if ($contents === false) {
                return null;
            }
            
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = Str::slug($name) . '_' . time() . '.' . $extension;
            $path = "imports/{$folder}/{$filename}";
            
            Storage::disk('s3')->put($path, $contents);
            
            return $path;
        } catch (\Exception $e) {
            return null;
        }
    }
}
