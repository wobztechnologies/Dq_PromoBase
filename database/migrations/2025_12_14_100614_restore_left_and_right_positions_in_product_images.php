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
     * Cette migration restaure Left et Right comme positions valides.
     * Note: Les données existantes qui étaient Left/Right ont été converties en Side
     * par la migration précédente (2025_11_08_200000_update_position_to_side_in_product_images).
     * On ne peut pas restaurer automatiquement les positions originales car l'information
     * a été perdue, mais Left et Right sont maintenant à nouveau disponibles pour les 
     * nouvelles images et peuvent être utilisées pour reclassifier manuellement si nécessaire.
     */
    public function up(): void
    {
        // Mettre à jour le commentaire de la colonne pour refléter toutes les positions possibles
        // Cela documente que Left et Right sont maintenant des valeurs valides
        try {
            DB::statement("
                COMMENT ON COLUMN product_images.position IS 'Position de l''image: Back, Bottom, Front, PartZoom, Side, Top, Left, Right'
            ");
        } catch (\Exception $e) {
            // Si PostgreSQL n'est pas disponible ou si la syntaxe ne fonctionne pas,
            // on continue sans erreur car le commentaire est optionnel
            // Les valeurs Left et Right sont déjà supportées par le code
        }
        
        // Note: On ne modifie pas les données existantes car on ne peut pas savoir
        // si une image Side était à l'origine Left, Right, ou vraiment Side.
        // Les utilisateurs pourront reclassifier manuellement via l'interface Filament si nécessaire.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Le commentaire reste le même car Left et Right doivent rester valides
        // même en cas de rollback
        try {
            DB::statement("
                COMMENT ON COLUMN product_images.position IS 'Position de l''image: Back, Bottom, Front, PartZoom, Side, Top, Left, Right'
            ");
        } catch (\Exception $e) {
            // Ignorer les erreurs
        }
    }
};
