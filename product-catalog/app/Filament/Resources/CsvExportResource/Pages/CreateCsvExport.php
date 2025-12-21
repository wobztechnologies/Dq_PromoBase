<?php

namespace App\Filament\Resources\CsvExportResource\Pages;

use App\Filament\Resources\CsvExportResource;
use App\Services\CsvExport\CsvExportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\Page;

class CreateCsvExport extends Page
{
    protected static string $resource = CsvExportResource::class;

    protected static string $view = 'filament.resources.csv-export-resource.pages.create-csv-export';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(CsvExportResource::form($form)->getComponents())
            ->statePath('data');
    }

    public function export()
    {
        $data = $this->form->getState();
        $type = $data['type'] ?? null;
        $mode = $data['mode'] ?? null;
        $filters = $data['filters'] ?? [];

        if (!$type) {
            \Filament\Notifications\Notification::make()
                ->title('Erreur')
                ->body('Veuillez sélectionner un type d\'export')
                ->danger()
                ->send();
            return;
        }

        try {
            $exportService = app(CsvExportService::class);
            $csvContent = $exportService->generateExport($type, $mode, $filters);

            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = $mode ? "export_{$type}_{$mode}_{$timestamp}.csv" : "export_{$type}_{$timestamp}.csv";

            return response()->streamDownload(function () use ($csvContent) {
                echo $csvContent;
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Erreur lors de l\'export')
                ->body('Erreur: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('export')
                ->label('Générer et télécharger le CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->submit('export'),
        ];
    }
}
