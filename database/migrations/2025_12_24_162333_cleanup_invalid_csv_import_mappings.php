<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Supprime les mappings CSV invalides (sans target_name ou target_id)
     */
    public function up(): void
    {
        DB::table('csv_import_mappings')
            ->whereNull('target_name')
            ->orWhereNull('target_id')
            ->orWhere('target_name', '')
            ->orWhere('target_id', '')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Impossible de restaurer les données supprimées
    }
};
