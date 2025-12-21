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
        Schema::table('csv_import_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('csv_import_logs', 'data')) {
                $table->json('data')->nullable()->after('message');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('csv_import_logs', function (Blueprint $table) {
            if (Schema::hasColumn('csv_import_logs', 'data')) {
                $table->dropColumn('data');
            }
        });
    }
};
