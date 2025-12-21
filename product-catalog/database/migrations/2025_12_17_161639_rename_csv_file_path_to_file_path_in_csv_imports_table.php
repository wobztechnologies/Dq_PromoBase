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
        Schema::table('csv_imports', function (Blueprint $table) {
            // Copier les données de csv_file_path vers file_path si csv_file_path existe et file_path est vide
            if (Schema::hasColumn('csv_imports', 'csv_file_path')) {
                \DB::statement('UPDATE csv_imports SET file_path = csv_file_path WHERE file_path IS NULL AND csv_file_path IS NOT NULL');
                // Supprimer la colonne csv_file_path
                $table->dropColumn('csv_file_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('csv_imports', function (Blueprint $table) {
            // Recréer csv_file_path si nécessaire
            if (!Schema::hasColumn('csv_imports', 'csv_file_path')) {
                $table->string('csv_file_path')->nullable()->after('strategy');
                \DB::statement('UPDATE csv_imports SET csv_file_path = file_path');
            }
        });
    }
};
