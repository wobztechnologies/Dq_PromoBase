<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PostgreSQL requiert des ALTER COLUMN spécifiques
        DB::statement('ALTER TABLE csv_import_mappings ALTER COLUMN csv_import_id DROP NOT NULL');
        DB::statement('ALTER TABLE csv_import_mappings ALTER COLUMN entity_type DROP NOT NULL');
        DB::statement('ALTER TABLE csv_import_mappings ALTER COLUMN is_created DROP NOT NULL');
        DB::statement('ALTER TABLE csv_import_mappings ALTER COLUMN target_id DROP NOT NULL');
        DB::statement('ALTER TABLE csv_import_mappings ALTER COLUMN source_value DROP NOT NULL');
        
        // Ajouter une valeur par défaut pour is_created
        DB::statement('ALTER TABLE csv_import_mappings ALTER COLUMN is_created SET DEFAULT false');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: ne pas rétablir NOT NULL car cela pourrait échouer si des valeurs NULL existent
    }
};
