<?php

namespace Tests\Feature;

use App\Models\CsvImport;
use App\Models\User;
use App\Services\CsvImport\CsvImportService;
use App\Services\CsvImport\CsvValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Créer un utilisateur de test
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_can_create_csv_import(): void
    {
        Storage::fake('local');
        
        $file = UploadedFile::fake()->create('test.csv', 100);
        $filePath = $file->store('csv-imports', 'local');
        
        $import = CsvImport::create([
            'name' => 'Test Import',
            'type' => 'category',
            'strategy' => 'create_update',
            'file_path' => storage_path('app/' . $filePath),
            'status' => 'pending_validation',
            'created_by' => $this->user->id,
        ]);
        
        $this->assertDatabaseHas('csv_imports', [
            'id' => $import->id,
            'name' => 'Test Import',
            'type' => 'category',
        ]);
    }

    public function test_validator_validates_category_csv(): void
    {
        $validator = app(CsvValidator::class);
        
        $headers = ['name', 'parent_name'];
        $data = [
            ['name' => 'Test Category', 'parent_name' => 'Parent'],
            ['name' => '', 'parent_name' => 'Parent'], // Erreur: name manquant
        ];
        
        $errors = $validator->validate('category', $data, $headers);
        
        $this->assertNotEmpty($errors);
        $this->assertCount(1, $errors); // Une erreur pour la ligne avec name manquant
    }

    public function test_validator_validates_headers(): void
    {
        $validator = app(CsvValidator::class);
        
        $headers = ['name']; // OK pour category
        $errors = $validator->validateHeaders('category', $headers);
        $this->assertEmpty($errors);
        
        $headers = []; // Manque 'name'
        $errors = $validator->validateHeaders('category', $headers);
        $this->assertNotEmpty($errors);
    }

    public function test_matching_service_finds_existing_mapping(): void
    {
        $matchingService = app(\App\Services\CsvImport\MatchingService::class);
        
        // Créer une catégorie
        $category = \App\Models\Category::create(['name' => 'Test Category']);
        
        // Créer un mapping
        \App\Models\CsvImportMapping::create([
            'source_value' => 'Test',
            'target_type' => \App\Models\Category::class,
            'target_id' => $category->id,
            'target_name' => 'Test Category',
            'mapping_type' => 'category',
            'created_by' => $this->user->id,
        ]);
        
        // Chercher le mapping
        $mapping = $matchingService->findOrCreateMapping('category', 'Test', $this->user->id);
        
        $this->assertNotNull($mapping);
        $this->assertEquals($category->id, $mapping['id']);
    }

    public function test_csv_import_service_validates_csv(): void
    {
        Storage::fake('local');
        
        // Créer un fichier CSV valide
        $csvContent = "name,parent_name\nTest Category,Parent";
        $filePath = storage_path('app/csv-imports/test.csv');
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        file_put_contents($filePath, $csvContent);
        
        $import = CsvImport::create([
            'name' => 'Test Import',
            'type' => 'category',
            'strategy' => 'create_update',
            'file_path' => $filePath,
            'status' => 'pending_validation',
            'created_by' => $this->user->id,
        ]);
        
        $service = app(CsvImportService::class);
        $result = $service->validate($import);
        
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
        
        // Nettoyer
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
