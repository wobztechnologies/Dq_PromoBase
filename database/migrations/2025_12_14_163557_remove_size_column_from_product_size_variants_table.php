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
            // Supprimer l'ancienne colonne 'size' (texte) car nous utilisons maintenant 'size_id' (relation)
            $table->dropColumn('size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_size_variants', function (Blueprint $table) {
            // Recréer la colonne 'size' si nécessaire pour rollback
            $table->string('size')->nullable();
        });
    }
};
