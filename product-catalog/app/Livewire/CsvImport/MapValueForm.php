<?php

namespace App\Livewire\CsvImport;

use App\Models\CsvImport;
use App\Services\CsvImport\MatchingService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Livewire\Component;

class MapValueForm extends Component implements HasForms
{
    use InteractsWithForms;

    public string $mappingType;
    public string $sourceValue;
    public string $importId;
    
    public ?string $targetId = null;

    public function mount(string $mappingType, string $sourceValue, string $importId): void
    {
        $this->mappingType = $mappingType;
        $this->sourceValue = $sourceValue;
        $this->importId = $importId;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('targetId')
                    ->label('Valeur existante')
                    ->options($this->getOptions())
                    ->searchable()
                    ->required()
                    ->live(),
            ]);
    }

    protected function getOptions(): array
    {
        $matchingService = app(MatchingService::class);
        $suggestions = $matchingService->getSuggestions($this->mappingType, $this->sourceValue, 20);
        
        $options = [];
        foreach ($suggestions as $suggestion) {
            $options[$suggestion['id']] = $suggestion['name'] . ' (' . round($suggestion['similarity'] * 100) . '%)';
        }
        
        // Ajouter toutes les autres valeurs disponibles
        $allEntities = $this->getAllEntities();
        foreach ($allEntities as $id => $name) {
            if (!isset($options[$id])) {
                $options[$id] = $name;
            }
        }
        
        return $options;
    }

    protected function getAllEntities(): array
    {
        return match($this->mappingType) {
            'category' => \App\Models\Category::pluck('name', 'id')->toArray(),
            'distributor' => \App\Models\Distributor::pluck('name', 'id')->toArray(),
            'manufacturer' => \App\Models\Manufacturer::pluck('name', 'id')->toArray(),
            'manufacturer_color' => \App\Models\PrimaryColor::whereNotNull('manufacturer_id')->pluck('name', 'id')->toArray(),
            'size' => \App\Models\Size::pluck('name', 'id')->toArray(),
            'primary_color' => \App\Models\PrimaryColor::whereNull('manufacturer_id')->pluck('name', 'id')->toArray(),
            default => [],
        };
    }

    public function save(): void
    {
        $data = $this->form->getState();
        
        if (empty($data['targetId'])) {
            Notification::make()
                ->title('Erreur')
                ->body('Veuillez sélectionner une valeur.')
                ->danger()
                ->send();
            return;
        }
        
        $import = CsvImport::find($this->importId);
        if (!$import) {
            Notification::make()
                ->title('Erreur')
                ->body('Import non trouvé.')
                ->danger()
                ->send();
            return;
        }
        
        $matchingService = app(MatchingService::class);
        $matchingService->createMapping(
            $this->mappingType,
            $this->sourceValue,
            $this->getTargetType(),
            $data['targetId'],
            null,
            $import->created_by
        );
        
        Notification::make()
            ->title('Mapping créé')
            ->body("La valeur '{$this->sourceValue}' a été mappée avec succès.")
            ->success()
            ->send();
        
        $this->dispatch('value-mapped', [
            'mappingType' => $this->mappingType,
            'sourceValue' => $this->sourceValue,
        ]);
        
        $this->dispatch('close-modal', id: 'map-modal-' . $this->mappingType . '-' . md5($this->sourceValue));
    }

    protected function getTargetType(): string
    {
        return match($this->mappingType) {
            'category' => \App\Models\Category::class,
            'distributor' => \App\Models\Distributor::class,
            'manufacturer' => \App\Models\Manufacturer::class,
            'manufacturer_color', 'primary_color' => \App\Models\PrimaryColor::class,
            'size' => \App\Models\Size::class,
            default => throw new \Exception("Type non supporté: {$this->mappingType}"),
        };
    }

    public function render()
    {
        return view('livewire.csv-import.map-value-form');
    }
}
