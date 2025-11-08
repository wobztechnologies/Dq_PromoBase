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
        // Mettre à jour les positions existantes : Left, Right, Lateral Left, Lateral Right → Side
        DB::table('product_images')
            ->whereIn('position', ['Left', 'Right', 'Lateral Left', 'Lateral Right'])
            ->update(['position' => 'Side']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: On ne peut pas vraiment restaurer les positions originales
        // car on ne sait pas si c'était Left, Right, Lateral Left ou Lateral Right
        // On laisse donc les données telles quelles
    }
};


