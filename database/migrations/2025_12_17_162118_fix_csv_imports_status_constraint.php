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
        // SQLite ne supporte pas ALTER TABLE avec DROP/ADD CONSTRAINT
        // Cette migration est spécifique à PostgreSQL
        $driver = Schema::getConnection()->getDriverName();
        
        if ($driver === 'pgsql') {
            // Supprimer la contrainte CHECK existante si elle existe
            \DB::statement("ALTER TABLE csv_imports DROP CONSTRAINT IF EXISTS csv_imports_status_check");
            
            // Recréer la contrainte CHECK avec les bonnes valeurs
            \DB::statement("ALTER TABLE csv_imports ADD CONSTRAINT csv_imports_status_check CHECK (status IN ('pending_validation', 'validation_failed', 'pending_matching', 'matching_completed', 'processing', 'completed', 'failed'))");
        }
        // Pour SQLite, la validation est gérée côté application
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        
        if ($driver === 'pgsql') {
            // Supprimer la contrainte CHECK
            \DB::statement("ALTER TABLE csv_imports DROP CONSTRAINT IF EXISTS csv_imports_status_check");
        }
    }
};
