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
        Schema::table('product_size_variants', function (Blueprint $table) {
            // Ajouter size_id et supprimer le champ size texte
            $table->foreignUuid('size_id')
                ->nullable()
                ->after('product_color_variant_id')
                ->constrained('sizes')
                ->onDelete('restrict');
            
            // Migrer les données existantes si nécessaire (on garde size temporairement)
            // On supprimera size dans une migration ultérieure après migration des données
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_size_variants', function (Blueprint $table) {
            $table->dropForeign(['size_id']);
            $table->dropColumn('size_id');
        });
    }
};
