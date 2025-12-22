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
            $table->string('meshy_task_id')->nullable()->after('s3_url');
            $table->index('meshy_task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_3d_models', function (Blueprint $table) {
            $table->dropIndex(['meshy_task_id']);
            $table->dropColumn('meshy_task_id');
        });
    }
};
