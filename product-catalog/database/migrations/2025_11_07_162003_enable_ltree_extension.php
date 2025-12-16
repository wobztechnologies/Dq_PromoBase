<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Cette migration active les extensions PostgreSQL nécessaires :
     * - uuid-ossp : pour générer des UUIDs
     * - ltree : pour gérer les hiérarchies de catégories avec le type ltree
     * 
     * IMPORTANT : Cette migration ne s'exécute QUE sur PostgreSQL.
     * Pour les tests avec SQLite, elle est ignorée automatiquement.
     */
    public function up(): void
    {
        // Vérifier que nous sommes sur PostgreSQL
        if (DB::getDriverName() !== 'pgsql') {
            // Sur SQLite (tests) ou MySQL, on skip cette migration
            // Le path sera stocké comme une simple chaîne de caractères
            return;
        }
        
        // Activer l'extension uuid-ossp pour générer des UUIDs
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
        
        // Activer l'extension ltree pour les hiérarchies
        // ltree permet de stocker des chemins hiérarchiques (ex: "1.2.3")
        // et d'effectuer des requêtes efficaces comme "trouver tous les descendants"
        DB::statement('CREATE EXTENSION IF NOT EXISTS "ltree";');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Vérifier que nous sommes sur PostgreSQL
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        
        DB::statement('DROP EXTENSION IF EXISTS "ltree";');
        DB::statement('DROP EXTENSION IF EXISTS "uuid-ossp";');
    }
};
