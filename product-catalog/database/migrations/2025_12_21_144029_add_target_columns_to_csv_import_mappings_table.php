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
        Schema::table('csv_import_mappings', function (Blueprint $table) {
            if (!Schema::hasColumn('csv_import_mappings', 'target_type')) {
                $table->string('target_type')->nullable()->after('target_id');
            }
            if (!Schema::hasColumn('csv_import_mappings', 'target_name')) {
                $table->string('target_name')->nullable()->after('target_type');
            }
            if (!Schema::hasColumn('csv_import_mappings', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('target_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('csv_import_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('csv_import_mappings', 'target_type')) {
                $table->dropColumn('target_type');
            }
            if (Schema::hasColumn('csv_import_mappings', 'target_name')) {
                $table->dropColumn('target_name');
            }
            if (Schema::hasColumn('csv_import_mappings', 'created_by')) {
                $table->dropColumn('created_by');
            }
        });
    }
};
