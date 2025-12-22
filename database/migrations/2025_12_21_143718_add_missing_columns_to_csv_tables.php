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
        // Ajouter les colonnes manquantes à csv_import_logs
        Schema::table('csv_import_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('csv_import_logs', 'sku')) {
                $table->string('sku')->nullable()->after('row_number');
            }
            if (!Schema::hasColumn('csv_import_logs', 'row_number')) {
                $table->integer('row_number')->nullable()->after('data');
            }
        });
        
        // Ajouter les colonnes manquantes à csv_import_mappings
        Schema::table('csv_import_mappings', function (Blueprint $table) {
            if (!Schema::hasColumn('csv_import_mappings', 'mapping_type')) {
                $table->string('mapping_type')->nullable()->after('id');
            }
            if (!Schema::hasColumn('csv_import_mappings', 'source_value')) {
                $table->string('source_value')->nullable()->after('mapping_type');
            }
            if (!Schema::hasColumn('csv_import_mappings', 'target_id')) {
                $table->uuid('target_id')->nullable()->after('source_value');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('csv_import_logs', function (Blueprint $table) {
            if (Schema::hasColumn('csv_import_logs', 'sku')) {
                $table->dropColumn('sku');
            }
            if (Schema::hasColumn('csv_import_logs', 'row_number')) {
                $table->dropColumn('row_number');
            }
        });
        
        Schema::table('csv_import_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('csv_import_mappings', 'mapping_type')) {
                $table->dropColumn('mapping_type');
            }
            if (Schema::hasColumn('csv_import_mappings', 'source_value')) {
                $table->dropColumn('source_value');
            }
            if (Schema::hasColumn('csv_import_mappings', 'target_id')) {
                $table->dropColumn('target_id');
            }
        });
    }
};
