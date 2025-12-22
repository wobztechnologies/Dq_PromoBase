<?php

namespace App\Services\CsvExport\Handlers;

use App\Models\Manufacturer;
use App\Services\CsvExport\ExportHandlerInterface;
use Illuminate\Support\Facades\Storage;

class ManufacturerExportHandler implements ExportHandlerInterface
{
    public function getHeaders(?string $mode = null): array
    {
        return ['name', 'logo_url'];
    }

    public function getData(?string $mode = null, array $filters = []): array
    {
        $query = Manufacturer::query();

        // Appliquer les filtres si nécessaire
        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        $manufacturers = $query->get();

        return $manufacturers->map(function ($manufacturer) {
            $logoUrl = '';
            if ($manufacturer->logo_s3_url) {
                try {
                    $logoUrl = Storage::disk('s3')->temporaryUrl($manufacturer->logo_s3_url, now()->addHours(24));
                } catch (\Exception $e) {
                    $logoUrl = Storage::disk('s3')->url($manufacturer->logo_s3_url);
                }
            }

            return [
                'name' => $manufacturer->name,
                'logo_url' => $logoUrl,
            ];
        })->toArray();
    }
}
