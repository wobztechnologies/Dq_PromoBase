<?php

namespace App\Services\CsvExport;

use App\Services\CsvExport\Handlers\CategoryExportHandler;
use App\Services\CsvExport\Handlers\DistributorExportHandler;
use App\Services\CsvExport\Handlers\ManufacturerExportHandler;
use App\Services\CsvExport\Handlers\ManufacturerColorExportHandler;
use App\Services\CsvExport\Handlers\StockExportHandler;
use App\Services\CsvExport\Handlers\PriceExportHandler;
use App\Services\CsvExport\Handlers\ProductExportHandler;
use League\Csv\Writer;
use Illuminate\Support\Facades\Storage;

class CsvExportService
{
    /**
     * Obtenir le handler approprié pour un type d'export
     */
    public function getHandler(string $type): ExportHandlerInterface
    {
        return match($type) {
            'category' => app(CategoryExportHandler::class),
            'distributor' => app(DistributorExportHandler::class),
            'manufacturer' => app(ManufacturerExportHandler::class),
            'manufacturer_color' => app(ManufacturerColorExportHandler::class),
            'stock' => app(StockExportHandler::class),
            'price' => app(PriceExportHandler::class),
            'product' => app(ProductExportHandler::class),
            default => throw new \Exception("Type d'export inconnu: {$type}"),
        };
    }

    /**
     * Générer un export CSV
     */
    public function generateExport(string $type, ?string $mode = null, array $filters = []): string
    {
        $handler = $this->getHandler($type);
        $headers = $handler->getHeaders($mode);
        $data = $handler->getData($mode, $filters);

        $csv = Writer::createFromString();
        $csv->insertOne($headers);
        
        foreach ($data as $row) {
            $csv->insertOne($row);
        }

        return $csv->toString();
    }

    /**
     * Sauvegarder un export CSV dans le storage
     */
    public function saveExport(string $type, ?string $mode = null, array $filters = []): string
    {
        $content = $this->generateExport($type, $mode, $filters);
        $timestamp = now()->format('Y-m-d_H-i-s');
        $fileName = $mode ? "export_{$type}_{$mode}_{$timestamp}.csv" : "export_{$type}_{$timestamp}.csv";
        $path = "csv-exports/{$fileName}";

        Storage::disk('local')->put($path, $content);
        return $path;
    }
}
