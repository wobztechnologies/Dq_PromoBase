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
        // PostgreSQL: modifier la contrainte CHECK pour le champ mode
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Supprimer l'ancienne contrainte
            DB::statement('ALTER TABLE csv_imports DROP CONSTRAINT IF EXISTS csv_imports_mode_check');
            
            // Ajouter la nouvelle contrainte avec les valeurs supplémentaires
            DB::statement("ALTER TABLE csv_imports ADD CONSTRAINT csv_imports_mode_check CHECK (mode IN ('manufacturer', 'distributor', 'full', 'simple'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Remettre l'ancienne contrainte
            DB::statement('ALTER TABLE csv_imports DROP CONSTRAINT IF EXISTS csv_imports_mode_check');
            DB::statement("ALTER TABLE csv_imports ADD CONSTRAINT csv_imports_mode_check CHECK (mode IN ('manufacturer', 'distributor'))");
        }
    }
};
