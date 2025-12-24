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
        Schema::table('product_3d_models', function (Blueprint $table) {
            // Origine du modèle 3D: 'uploaded' ou 'Ai - NomDuModele'
            $table->string('origin')->nullable()->after('status');
        });
        
        // Mettre à jour les enregistrements existants: si meshy_task_id est rempli, c'est une génération AI
        \Illuminate\Support\Facades\DB::table('product_3d_models')
            ->whereNotNull('meshy_task_id')
            ->whereNull('origin')
            ->update(['origin' => 'Ai - Legacy']);
        
        // Les autres sont des uploads manuels
        \Illuminate\Support\Facades\DB::table('product_3d_models')
            ->whereNull('origin')
            ->update(['origin' => 'uploaded']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_3d_models', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
