<?php

namespace App\Services\CsvImport;

use App\Models\CsvImport;

interface ImportHandlerInterface
{
    /**
     * Traiter une ligne du CSV
     */
    public function processRow(CsvImport $import, array $row, int $rowNumber): bool;

    /**
     * Obtenir les valeurs nécessitant un matching
     */
    public function getMatchingValues(array $rows): array;
}
