<?php

namespace App\Livewire\CsvImport;

use App\Models\CsvImport;
use App\Services\CsvImport\MatchingService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CreateEntityForm extends Component implements HasForms
{
    use InteractsWithForms;

    public string $mappingType;
    public string $sourceValue;
    public string $importId;

    public function mount(string $mappingType, string $sourceValue, string $importId): void
    {
        $this->mappingType = $mappingType;
        $this->sourceValue = $sourceValue;
        $this->importId = $importId;
    }

    public function form(Form $form): Form
    {
        $schema = [
            Forms\Components\TextInput::make('name')
                ->label('Nom')
                ->default($this->sourceValue)
                ->required()
                ->maxLength(255),
        ];

        // Ajouter des champs spécifiques selon le type
        switch($this->mappingType) {
            case 'category':
                $schema[] = Forms\Components\Select::make('parent_id')
                    ->label('Catégorie parente')
                    ->options(\App\Models\Category::pluck('name', 'id'))
                    ->searchable()
                    ->nullable();
                break;
            
            case 'manufacturer_color':
                $schema[] = Forms\Components\Select::make('manufacturer_id')
                    ->label('Fabricant')
                    ->options(\App\Models\Manufacturer::pluck('name', 'id'))
                    ->required()
                    ->searchable();
                $schema[] = Forms\Components\TextInput::make('hex_code')
                    ->label('Code hexadécimal')
                    ->placeholder('#FF0000')
                    ->regex('/^#[0-9A-Fa-f]{6}$/')
                    ->nullable();
                $schema[] = Forms\Components\Select::make('parent_id')
                    ->label('Couleur parente')
                    ->options(\App\Models\PrimaryColor::whereNull('manufacturer_id')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable();
                break;
            
            case 'primary_color':
                $schema[] = Forms\Components\TextInput::make('hex_code')
                    ->label('Code hexadécimal')
                    ->placeholder('#FF0000')
                    ->regex('/^#[0-9A-Fa-f]{6}$/')
                    ->nullable();
                break;
            
            case 'size':
                $schema[] = Forms\Components\TextInput::make('order')
                    ->label('Ordre')
                    ->numeric()
                    ->default(0);
                break;
        }

        return $form->schema($schema);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        
        $import = CsvImport::find($this->importId);
        if (!$import) {
            Notification::make()
                ->title('Erreur')
                ->body('Import non trouvé.')
                ->danger()
                ->send();
            return;
        }

        try {
            DB::beginTransaction();
            
            $entity = match($this->mappingType) {
                'category' => $this->createCategory($data),
                'distributor' => $this->createDistributor($data),
                'manufacturer' => $this->createManufacturer($data),
                'manufacturer_color' => $this->createManufacturerColor($data),
                'size' => $this->createSize($data),
                'primary_color' => $this->createPrimaryColor($data),
                default => throw new \Exception("Type non supporté: {$this->mappingType}"),
            };
            
            // Créer le mapping
            $matchingService = app(MatchingService::class);
            $matchingService->createMapping(
                $this->mappingType,
                $this->sourceValue,
                get_class($entity),
                $entity->id,
                $entity->name,
                $import->created_by
            );
            
            DB::commit();
            
            Notification::make()
                ->title('Entité créée')
                ->body("L'entité '{$entity->name}' a été créée et mappée avec succès.")
                ->success()
                ->send();
            
            $this->dispatch('value-mapped', [
                'mappingType' => $this->mappingType,
                'sourceValue' => $this->sourceValue,
            ]);
            
            $this->dispatch('close-modal', id: 'create-modal-' . $this->mappingType . '-' . md5($this->sourceValue));
            
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()
                ->title('Erreur')
                ->body('Erreur lors de la création: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function createCategory(array $data): \App\Models\Category
    {
        $category = new \App\Models\Category();
        $category->name = $data['name'];
        if (!empty($data['parent_id'])) {
            $category->parent_id = $data['parent_id'];
        }
        $category->save();
        return $category;
    }

    protected function createDistributor(array $data): \App\Models\Distributor
    {
        $distributor = new \App\Models\Distributor();
        $distributor->name = $data['name'];
        $distributor->save();
        return $distributor;
    }

    protected function createManufacturer(array $data): \App\Models\Manufacturer
    {
        $manufacturer = new \App\Models\Manufacturer();
        $manufacturer->name = $data['name'];
        $manufacturer->save();
        return $manufacturer;
    }

    protected function createManufacturerColor(array $data): \App\Models\PrimaryColor
    {
        $color = new \App\Models\PrimaryColor();
        $color->name = $data['name'];
        $color->manufacturer_id = $data['manufacturer_id'];
        if (!empty($data['parent_id'])) {
            $color->parent_id = $data['parent_id'];
        }
        if (!empty($data['hex_code'])) {
            $color->hex_code = $data['hex_code'];
        }
        $color->save();
        return $color;
    }

    protected function createSize(array $data): \App\Models\Size
    {
        $size = new \App\Models\Size();
        $size->name = $data['name'];
        if (isset($data['order'])) {
            $size->order = $data['order'];
        }
        $size->save();
        return $size;
    }

    protected function createPrimaryColor(array $data): \App\Models\PrimaryColor
    {
        $color = new \App\Models\PrimaryColor();
        $color->name = $data['name'];
        if (!empty($data['hex_code'])) {
            $color->hex_code = $data['hex_code'];
        }
        $color->save();
        return $color;
    }

    public function render()
    {
        return view('livewire.csv-import.create-entity-form');
    }
}
