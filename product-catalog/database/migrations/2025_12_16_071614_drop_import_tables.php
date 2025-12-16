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
        // Supprimer les tables dans l'ordre inverse de création pour respecter les contraintes de clés étrangères
        Schema::dropIfExists('import_mapping_templates');
        Schema::dropIfExists('import_logs');
        Schema::dropIfExists('import_created_entities');
        Schema::dropIfExists('import_unmapped_values');
        Schema::dropIfExists('import_value_mappings');
        Schema::dropIfExists('import_field_mappings');
        Schema::dropIfExists('imports');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cette migration ne peut pas être annulée car les migrations originales ont été supprimées
        // Si nécessaire, recréer les tables manuellement
    }
};
