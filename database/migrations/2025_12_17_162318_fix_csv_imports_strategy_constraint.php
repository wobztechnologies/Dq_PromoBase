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
            \DB::statement("ALTER TABLE csv_imports DROP CONSTRAINT IF EXISTS csv_imports_strategy_check");
            
            // Recréer la contrainte CHECK avec les bonnes valeurs
            \DB::statement("ALTER TABLE csv_imports ADD CONSTRAINT csv_imports_strategy_check CHECK (strategy IN ('create_update', 'update_only'))");
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
            \DB::statement("ALTER TABLE csv_imports DROP CONSTRAINT IF EXISTS csv_imports_strategy_check");
        }
    }
};
