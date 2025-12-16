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
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
        
        // Ajouter la colonne path selon le type de base de données
        if (DB::getDriverName() === 'pgsql') {
            // Sur PostgreSQL : utiliser le type ltree natif pour des performances optimales
            // ltree permet des requêtes comme "trouver tous les descendants" très efficaces
            DB::statement('ALTER TABLE categories ADD COLUMN path ltree;');
            DB::statement('CREATE INDEX idx_path ON categories USING GIST (path);');
        } else {
            // Sur SQLite (tests) ou MySQL : utiliser une simple colonne string
            // Le path sera stocké comme chaîne (ex: "1.2.3") mais sans les fonctionnalités ltree
            Schema::table('categories', function (Blueprint $table) {
                $table->string('path')->nullable()->after('name');
            });
            Schema::table('categories', function (Blueprint $table) {
                $table->index('path');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
