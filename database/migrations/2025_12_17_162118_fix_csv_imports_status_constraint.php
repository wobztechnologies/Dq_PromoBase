<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Supprimer la contrainte CHECK existante si elle existe
        \DB::statement("ALTER TABLE csv_imports DROP CONSTRAINT IF EXISTS csv_imports_status_check");
        
        // Recréer la contrainte CHECK avec les bonnes valeurs
        \DB::statement("ALTER TABLE csv_imports ADD CONSTRAINT csv_imports_status_check CHECK (status IN ('pending_validation', 'validation_failed', 'pending_matching', 'matching_completed', 'processing', 'completed', 'failed'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer la contrainte CHECK
        \DB::statement("ALTER TABLE csv_imports DROP CONSTRAINT IF EXISTS csv_imports_status_check");
    }
};
