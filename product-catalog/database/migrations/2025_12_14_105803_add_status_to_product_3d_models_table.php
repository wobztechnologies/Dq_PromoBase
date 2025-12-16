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
        Schema::table('product_3d_models', function (Blueprint $table) {
            $table->string('status')->default('Published')->after('s3_url');
        });
        
        // Mettre à jour les enregistrements existants sans s3_url en "Requested"
        // et ceux avec s3_url en "Published"
        DB::table('product_3d_models')
            ->whereNull('s3_url')
            ->orWhere('s3_url', '')
            ->update(['status' => 'Requested']);
        
        DB::table('product_3d_models')
            ->whereNotNull('s3_url')
            ->where('s3_url', '!=', '')
            ->update(['status' => 'Published']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_3d_models', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
