<?php

namespace App\Services\CsvImport;

use League\Csv\Reader;
use Illuminate\Support\Facades\DB;

class CsvAnalysisService
{
    /**
     * Séparateurs potentiels pour la détection automatique
     */
    protected const POSSIBLE_DELIMITERS = [',', ';', "\t", '|'];
    
    /**
     * Enclosures potentiels pour la détection automatique
     */
    protected const POSSIBLE_ENCLOSURES = ['"', "'"];
    
    /**
     * Détecter automatiquement le séparateur et l'enclosure utilisés dans un fichier CSV
     * Retourne un tableau avec 'delimiter' et 'enclosure'
     */
    public function detectCsvFormat(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['delimiter' => ',', 'enclosure' => '"']; // Par défaut
        }
        
        // Lire les premières lignes pour analyser
        $lines = [];
        $lineCount = 0;
        while (($line = fgets($handle)) !== false && $lineCount < 5) {
            $lines[] = $line;
            $lineCount++;
        }
        fclose($handle);
        
        if (empty($lines)) {
            return ['delimiter' => ',', 'enclosure' => '"'];
        }
        
        // Détecter le délimiteur
        $delimiter = $this->detectDelimiterFromLines($lines);
        
        // Détecter l'enclosure
        $enclosure = $this->detectEnclosureFromLines($lines);
        
        return [
            'delimiter' => $delimiter,
            'enclosure' => $enclosure,
        ];
    }
    
    /**
     * Détecter automatiquement le séparateur utilisé dans un fichier CSV
     */
    public function detectDelimiter(string $filePath): string
    {
        return $this->detectCsvFormat($filePath)['delimiter'];
    }
    
    /**
     * Détecter automatiquement l'enclosure utilisé dans un fichier CSV
     */
    public function detectEnclosure(string $filePath): string
    {
        return $this->detectCsvFormat($filePath)['enclosure'];
    }
    
    /**
     * Détecter le délimiteur à partir des lignes
     */
    protected function detectDelimiterFromLines(array $lines): string
    {
        $delimiterScores = [];
        
        foreach (self::POSSIBLE_DELIMITERS as $delimiter) {
            $counts = [];
            foreach ($lines as $line) {
                // Compter les occurrences du délimiteur dans chaque ligne
                // En ignorant celles qui sont dans des guillemets
                $counts[] = $this->countDelimiterOutsideQuotes($line, $delimiter);
            }
            
            // Le meilleur délimiteur est celui qui:
            // 1. Apparaît au moins une fois
            // 2. A un nombre cohérent d'occurrences entre les lignes
            if (max($counts) > 0) {
                $avg = array_sum($counts) / count($counts);
                $variance = 0;
                foreach ($counts as $count) {
                    $variance += pow($count - $avg, 2);
                }
                $variance = $variance / count($counts);
                
                // Score = nombre moyen d'occurrences / (1 + variance)
                // Plus le score est élevé, plus le délimiteur est probable
                $delimiterScores[$delimiter] = $avg / (1 + $variance);
            } else {
                $delimiterScores[$delimiter] = 0;
            }
        }
        
        // Retourner le délimiteur avec le meilleur score
        arsort($delimiterScores);
        $bestDelimiter = array_key_first($delimiterScores);
        
        return $bestDelimiter ?: ',';
    }
    
    /**
     * Compter les occurrences d'un délimiteur en dehors des guillemets
     */
    protected function countDelimiterOutsideQuotes(string $line, string $delimiter): int
    {
        $count = 0;
        $inQuotes = false;
        $quoteChar = null;
        $length = strlen($line);
        
        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];
            
            // Gérer les guillemets
            if (($char === '"' || $char === "'") && !$inQuotes) {
                $inQuotes = true;
                $quoteChar = $char;
            } elseif ($char === $quoteChar && $inQuotes) {
                // Vérifier si c'est un guillemet échappé (double guillemet)
                if ($i + 1 < $length && $line[$i + 1] === $quoteChar) {
                    $i++; // Sauter le guillemet échappé
                } else {
                    $inQuotes = false;
                    $quoteChar = null;
                }
            } elseif ($char === $delimiter && !$inQuotes) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Détecter l'enclosure à partir des lignes
     */
    protected function detectEnclosureFromLines(array $lines): string
    {
        $enclosureScores = [];
        
        foreach (self::POSSIBLE_ENCLOSURES as $enclosure) {
            $score = 0;
            foreach ($lines as $line) {
                // Compter les paires d'enclosures (début de champ ou après délimiteur)
                $pattern = '/(?:^|[,;\t|])' . preg_quote($enclosure, '/') . '/';
                $score += preg_match_all($pattern, $line);
            }
            $enclosureScores[$enclosure] = $score;
        }
        
        // Retourner l'enclosure le plus utilisé, ou " par défaut
        arsort($enclosureScores);
        $bestEnclosure = array_key_first($enclosureScores);
        
        // Si aucun enclosure trouvé ou score nul, utiliser " par défaut
        if (!$bestEnclosure || $enclosureScores[$bestEnclosure] === 0) {
            return '"';
        }
        
        return $bestEnclosure;
    }
    
    /**
     * Lire un fichier CSV et retourner les headers et l'aperçu
     * (Étape 1 - juste lire le fichier, pas d'analyse de valeurs)
     */
    public function readFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception('Le fichier CSV n\'existe pas: ' . $filePath);
        }

        // Détecter automatiquement le format CSV (séparateur et enclosure)
        $format = $this->detectCsvFormat($filePath);
        $delimiter = $format['delimiter'];
        $enclosure = $format['enclosure'];
        
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setDelimiter($delimiter);
        $csv->setEnclosure($enclosure);
        
        // Ignorer le BOM UTF-8 si présent
        if (method_exists($csv, 'skipInputBOM')) {
            $csv->skipInputBOM();
        }
        
        $csv->setHeaderOffset(0);
        
        $headers = $csv->getHeader();
        
        // Nettoyer les headers (supprimer BOM et espaces)
        $headers = array_map(function($header) {
            // Supprimer le BOM UTF-8 s'il est présent
            $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
            return trim($header);
        }, $headers);
        
        // Récupérer les records et les convertir en tableau avec indices séquentiels
        $allRecords = [];
        foreach ($csv->getRecords() as $record) {
            // Nettoyer les clés des records aussi
            $cleanRecord = [];
            foreach ($record as $key => $value) {
                $cleanKey = preg_replace('/^\xEF\xBB\xBF/', '', $key);
                $cleanKey = trim($cleanKey);
                $cleanRecord[$cleanKey] = $value;
            }
            $allRecords[] = $cleanRecord;
        }
        
        // Prendre les 10 premières lignes pour l'aperçu
        $preview = array_slice($allRecords, 0, 10);
        
        // S'assurer que preview contient des tableaux simples pour la sérialisation Livewire
        $preview = array_map(function($row) use ($headers) {
            $cleanRow = [];
            foreach ($headers as $header) {
                $cleanRow[$header] = $row[$header] ?? '';
            }
            return $cleanRow;
        }, $preview);
        
        return [
            'headers' => array_values($headers), // Réindexer
            'preview' => array_values($preview), // Réindexer
            'total_rows' => count($allRecords),
            'delimiter' => $delimiter,
            'enclosure' => $enclosure,
        ];
    }

    /**
     * Analyser les valeurs manquantes en utilisant le mapping de colonnes
     * (Étape 2 - après que l'utilisateur a mappé les colonnes)
     */
    public function analyzeWithMapping(string $filePath, string $importType, array $columnMapping): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception('Le fichier CSV n\'existe pas: ' . $filePath);
        }

        // Détecter automatiquement le format CSV (séparateur et enclosure)
        $format = $this->detectCsvFormat($filePath);
        $delimiter = $format['delimiter'];
        $enclosure = $format['enclosure'];
        
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setDelimiter($delimiter);
        $csv->setEnclosure($enclosure);
        $csv->setHeaderOffset(0);
        
        $allRecords = iterator_to_array($csv->getRecords());
        
        // Extraire les valeurs uniques en utilisant le mapping de colonnes
        $uniqueValues = $this->extractUniqueValuesWithMapping($allRecords, $columnMapping);
        
        // Identifier les valeurs manquantes et mappées en DB
        $analysisResult = $this->identifyMissingAndMappedValues($importType, $uniqueValues);
        
        // Extraire le contexte des couleurs fabricant pour le wizard
        $manufacturerColorContext = $uniqueValues['manufacturer_color_context'] ?? [];
        unset($uniqueValues['manufacturer_color_context']);
        unset($uniqueValues['manufacturer_color_pairs']);
        
        return [
            'unique_values' => $uniqueValues,
            'missing_values' => $analysisResult['missing'],
            'mapped_values' => $analysisResult['mapped'],
            'manufacturer_color_context' => $manufacturerColorContext,
            'total_rows' => count($allRecords),
            'delimiter' => $delimiter,
            'enclosure' => $enclosure,
        ];
    }

    /**
     * Extraire les valeurs uniques pour chaque champ mappé
     */
    protected function extractUniqueValuesWithMapping(array $records, array $columnMapping): array
    {
        $uniqueValues = [];
        
        foreach ($columnMapping as $targetField => $csvColumn) {
            if (empty($csvColumn)) {
                continue;
            }
            
            $values = [];
            foreach ($records as $record) {
                $value = $record[$csvColumn] ?? null;
                if ($value !== null && $value !== '' && trim($value) !== '') {
                    $values[] = trim($value);
                }
            }
            
            $uniqueValues[$targetField] = array_values(array_unique($values));
        }
        
        // Extraire les paires fabricant/couleur pour les couleurs fabricant
        if (!empty($columnMapping['color_name']) && !empty($columnMapping['manufacturer_name'])) {
            $manufacturerColorPairs = [];
            $manufacturerColorContext = [];
            
            foreach ($records as $record) {
                $colorName = trim($record[$columnMapping['color_name']] ?? '');
                $manufacturerName = trim($record[$columnMapping['manufacturer_name']] ?? '');
                $primaryColorName = !empty($columnMapping['primary_color_name']) 
                    ? trim($record[$columnMapping['primary_color_name']] ?? '') 
                    : '';
                
                if ($colorName && $manufacturerName) {
                    $key = $manufacturerName . '|' . $colorName;
                    $manufacturerColorPairs[$key] = true;
                    
                    // Stocker le contexte pour permettre la création ultérieure
                    if ($primaryColorName && !isset($manufacturerColorContext[$key])) {
                        $manufacturerColorContext[$key] = [
                            'manufacturer_name' => $manufacturerName,
                            'color_name' => $colorName,
                            'primary_color_name' => $primaryColorName,
                        ];
                    }
                }
            }
            
            $uniqueValues['manufacturer_color_pairs'] = array_keys($manufacturerColorPairs);
            $uniqueValues['manufacturer_color_context'] = $manufacturerColorContext;
        }
        
        return $uniqueValues;
    }

    /**
     * Identifier les valeurs manquantes et mappées en DB selon le type d'import
     * Retourne les valeurs manquantes ET les valeurs automatiquement mappées
     */
    protected function identifyMissingAndMappedValues(string $importType, array $uniqueValues): array
    {
        $missing = [];
        $mapped = [];
        
        switch ($importType) {
            case 'product':
                // Vérifier les catégories (champ mappé: category_name)
                if (!empty($uniqueValues['category_name'])) {
                    $result = $this->findMissingAndMappedInDB(
                        'categories',
                        'name',
                        $uniqueValues['category_name']
                    );
                    if (!empty($result['missing'])) $missing['categories'] = $result['missing'];
                    if (!empty($result['mapped'])) $mapped['categories'] = $result['mapped'];
                }
                
                // Vérifier les fabricants (champ mappé: manufacturer_name)
                if (!empty($uniqueValues['manufacturer_name'])) {
                    $result = $this->findMissingAndMappedInDB(
                        'manufacturers',
                        'name',
                        $uniqueValues['manufacturer_name']
                    );
                    if (!empty($result['missing'])) $missing['manufacturers'] = $result['missing'];
                    if (!empty($result['mapped'])) $mapped['manufacturers'] = $result['mapped'];
                }
                
                // Vérifier les couleurs principales (champ mappé: primary_color_name)
                if (!empty($uniqueValues['primary_color_name'])) {
                    $result = $this->findMissingAndMappedInDB(
                        'primary_colors',
                        'name',
                        $uniqueValues['primary_color_name'],
                        ['parent_id' => null, 'manufacturer_id' => null]
                    );
                    if (!empty($result['missing'])) $missing['primary_colors'] = $result['missing'];
                    if (!empty($result['mapped'])) $mapped['primary_colors'] = $result['mapped'];
                }
                
                // Vérifier les couleurs fabricant avec contexte (manufacturer + color)
                if (!empty($uniqueValues['manufacturer_color_pairs'])) {
                    $missingPairs = [];
                    $mappedPairs = [];
                    foreach ($uniqueValues['manufacturer_color_pairs'] as $pair) {
                        $dbMatch = $this->findManufacturerColorMatch($pair);
                        if ($dbMatch) {
                            $mappedPairs[] = [
                                'csv_value' => $pair,
                                'db_value' => $dbMatch['name'],
                                'db_id' => $dbMatch['id'],
                            ];
                        } else {
                            $missingPairs[] = $pair;
                        }
                    }
                    if (!empty($missingPairs)) $missing['manufacturer_colors'] = $missingPairs;
                    if (!empty($mappedPairs)) $mapped['manufacturer_colors'] = $mappedPairs;
                }
                
                // Vérifier les tailles (champ mappé: size_name)
                if (!empty($uniqueValues['size_name'])) {
                    $result = $this->findMissingAndMappedInDB(
                        'sizes',
                        'name',
                        $uniqueValues['size_name']
                    );
                    if (!empty($result['missing'])) $missing['sizes'] = $result['missing'];
                    if (!empty($result['mapped'])) $mapped['sizes'] = $result['mapped'];
                }
                break;
                
            case 'manufacturer_color':
                // Vérifier les fabricants
                if (!empty($uniqueValues['manufacturer_name'])) {
                    $result = $this->findMissingAndMappedInDB(
                        'manufacturers',
                        'name',
                        $uniqueValues['manufacturer_name']
                    );
                    if (!empty($result['missing'])) $missing['manufacturers'] = $result['missing'];
                    if (!empty($result['mapped'])) $mapped['manufacturers'] = $result['mapped'];
                }
                
                // Vérifier les couleurs principales
                if (!empty($uniqueValues['parent_name'])) {
                    $result = $this->findMissingAndMappedInDB(
                        'primary_colors',
                        'name',
                        array_filter($uniqueValues['parent_name']),
                        ['parent_id' => null, 'manufacturer_id' => null]
                    );
                    if (!empty($result['missing'])) $missing['primary_colors'] = $result['missing'];
                    if (!empty($result['mapped'])) $mapped['primary_colors'] = $result['mapped'];
                }
                break;
                
            case 'category':
                // Vérifier les catégories parentes
                if (!empty($uniqueValues['parent_name'])) {
                    $result = $this->findMissingAndMappedInDB(
                        'categories',
                        'name',
                        array_filter($uniqueValues['parent_name'])
                    );
                    if (!empty($result['missing'])) $missing['parent_categories'] = $result['missing'];
                    if (!empty($result['mapped'])) $mapped['parent_categories'] = $result['mapped'];
                }
                break;
        }
        
        return [
            'missing' => array_filter($missing, fn($arr) => !empty($arr)),
            'mapped' => array_filter($mapped, fn($arr) => !empty($arr)),
        ];
    }

    /**
     * Vérifier si une couleur fabricant existe (format: "manufacturer_name|color_name")
     * Comparaison insensible à la casse
     */
    protected function manufacturerColorExists(string $pair): bool
    {
        return $this->findManufacturerColorMatch($pair) !== null;
    }
    
    /**
     * Trouver une couleur fabricant et retourner ses infos (format: "manufacturer_name|color_name")
     * Comparaison insensible à la casse
     */
    protected function findManufacturerColorMatch(string $pair): ?array
    {
        if (!str_contains($pair, '|')) {
            return null;
        }
        
        [$manufacturerName, $colorName] = explode('|', $pair, 2);
        
        $result = DB::table('primary_colors')
            ->join('manufacturers', 'primary_colors.manufacturer_id', '=', 'manufacturers.id')
            ->whereRaw('LOWER(manufacturers.name) = ?', [mb_strtolower($manufacturerName)])
            ->whereRaw('LOWER(primary_colors.name) = ?', [mb_strtolower($colorName)])
            ->select('primary_colors.id', 'primary_colors.name', 'manufacturers.name as manufacturer_name')
            ->first();
        
        if (!$result) {
            return null;
        }
        
        return [
            'id' => $result->id,
            'name' => $result->name,
            'manufacturer_name' => $result->manufacturer_name,
        ];
    }
    
    /**
     * Trouver les valeurs manquantes ET mappées en DB
     * Comparaison insensible à la casse
     * Retourne les valeurs mappées avec leur correspondance en DB
     */
    protected function findMissingAndMappedInDB(
        string $table,
        string $column,
        array $values,
        array $additionalConditions = []
    ): array {
        if (empty($values)) {
            return ['missing' => [], 'mapped' => []];
        }
        
        // Filtrer les valeurs vides
        $values = array_filter($values, fn($v) => !empty($v) && trim($v) !== '');
        
        if (empty($values)) {
            return ['missing' => [], 'mapped' => []];
        }
        
        $query = DB::table($table)->select('id', $column);
        
        foreach ($additionalConditions as $col => $condition) {
            if (is_array($condition) && isset($condition[0]) && $condition[0] === '!=') {
                $query->whereNotNull($col);
            } elseif ($condition === null) {
                $query->whereNull($col);
            } else {
                $query->where($col, $condition);
            }
        }
        
        // Récupérer toutes les valeurs existantes avec leur ID et casse originale
        $existingRecords = $query->get();
        
        // Créer un mapping lowercase -> record pour les existants
        $existingByLower = [];
        foreach ($existingRecords as $record) {
            $lowerName = mb_strtolower($record->$column);
            $existingByLower[$lowerName] = [
                'id' => $record->id,
                'name' => $record->$column,
            ];
        }
        
        // Séparer les valeurs manquantes et mappées
        $missing = [];
        $mapped = [];
        
        foreach ($values as $value) {
            $lowerValue = mb_strtolower($value);
            if (isset($existingByLower[$lowerValue])) {
                $dbRecord = $existingByLower[$lowerValue];
                // Ajouter toutes les correspondances trouvées
                $mapped[] = [
                    'csv_value' => $value,
                    'db_value' => $dbRecord['name'],
                    'db_id' => $dbRecord['id'],
                    'exact_match' => ($value === $dbRecord['name']), // Indiquer si c'est une correspondance exacte
                ];
            } else {
                $missing[] = $value;
            }
        }
        
        return [
            'missing' => array_values($missing),
            'mapped' => $mapped,
        ];
    }
    
    /**
     * Trouver les valeurs qui n'existent pas en DB (méthode legacy pour compatibilité)
     * Comparaison insensible à la casse
     */
    protected function findMissingInDB(
        string $table,
        string $column,
        array $values,
        array $additionalConditions = []
    ): array {
        $result = $this->findMissingAndMappedInDB($table, $column, $values, $additionalConditions);
        return $result['missing'];
    }

    /**
     * Obtenir les champs requis selon le type d'import
     */
    public function getRequiredFields(string $importType): array
    {
        return match($importType) {
            'product' => ['sku', 'name', 'category_name', 'manufacturer_name'],
            'manufacturer_color' => ['name', 'manufacturer_name'],
            'category' => ['name'],
            'distributor' => ['name'],
            'manufacturer' => ['name'],
            'stock' => ['sku', 'quantity'],
            'price' => ['sku', 'price'],
            default => [],
        };
    }
}
