<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Remplace toutes les occurrences de "Side" par "Left" dans la colonne position
     * de la table product_images.
     */
    public function up(): void
    {
        $updated = DB::table('product_images')
            ->where('position', 'Side')
            ->update(['position' => 'Left']);
        
        // Logger le nombre d'enregistrements mis à jour
        \Log::info("Migration: {$updated} image(s) mise(s) à jour de 'Side' vers 'Left'");
    }

    /**
     * Reverse the migrations.
     * 
     * Note: On ne peut pas vraiment restaurer car on ne sait pas quelles images
     * étaient vraiment "Side" vs celles qui étaient "Left" ou "Right" avant.
     * On les remet toutes en "Side" pour être sûr.
     */
    public function down(): void
    {
        // Remettre en "Side" toutes les images qui sont maintenant "Left"
        // mais qui étaient peut-être "Side" avant
        // Note: Cette opération n'est pas parfaite car on perd l'information
        DB::table('product_images')
            ->where('position', 'Left')
            ->update(['position' => 'Side']);
    }
};
