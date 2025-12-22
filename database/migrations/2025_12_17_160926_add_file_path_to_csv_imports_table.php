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
            if (!Schema::hasColumn('csv_imports', 'file_path')) {
                $table->string('file_path')->after('strategy');
            }
            if (!Schema::hasColumn('csv_imports', 's3_archive_path')) {
                $table->string('s3_archive_path')->nullable()->after('file_path');
            }
            if (!Schema::hasColumn('csv_imports', 'report_path')) {
                $table->string('report_path')->nullable()->after('s3_archive_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('csv_imports', function (Blueprint $table) {
            if (Schema::hasColumn('csv_imports', 'file_path')) {
                $table->dropColumn('file_path');
            }
            if (Schema::hasColumn('csv_imports', 's3_archive_path')) {
                $table->dropColumn('s3_archive_path');
            }
            if (Schema::hasColumn('csv_imports', 'report_path')) {
                $table->dropColumn('report_path');
            }
        });
    }
};
