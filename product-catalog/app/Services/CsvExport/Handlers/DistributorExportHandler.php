<?php

namespace App\Services\CsvExport\Handlers;

use App\Models\Distributor;
use App\Services\CsvExport\ExportHandlerInterface;
use Illuminate\Support\Facades\Storage;

class DistributorExportHandler implements ExportHandlerInterface
{
    public function getHeaders(?string $mode = null): array
    {
        return ['name', 'logo_url'];
    }

    public function getData(?string $mode = null, array $filters = []): array
    {
        $query = Distributor::query();

        // Appliquer les filtres si nécessaire
        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        $distributors = $query->get();

        return $distributors->map(function ($distributor) {
            $logoUrl = '';
            if ($distributor->logo_s3_url) {
                try {
                    $logoUrl = Storage::disk('s3')->temporaryUrl($distributor->logo_s3_url, now()->addHours(24));
                } catch (\Exception $e) {
                    $logoUrl = Storage::disk('s3')->url($distributor->logo_s3_url);
                }
            }

            return [
                'name' => $distributor->name,
                'logo_url' => $logoUrl,
            ];
        })->toArray();
    }
}
