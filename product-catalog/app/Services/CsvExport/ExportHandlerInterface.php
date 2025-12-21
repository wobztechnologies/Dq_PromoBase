<?php

namespace App\Services\CsvExport;

interface ExportHandlerInterface
{
    /**
     * Obtenir les en-têtes CSV pour ce type d'export
     */
    public function getHeaders(?string $mode = null): array;

    /**
     * Obtenir les données à exporter
     */
    public function getData(?string $mode = null, array $filters = []): array;
}
