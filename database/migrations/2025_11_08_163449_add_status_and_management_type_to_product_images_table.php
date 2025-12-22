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
        Schema::table('product_images', function (Blueprint $table) {
            $table->enum('management_type', ['ai_managed', 'user_managed'])->default('ai_managed')->after('product_only');
            $table->enum('status', ['waitML', 'userDefined', 'MLcompleted'])->default('waitML')->after('management_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn(['management_type', 'status']);
        });
    }
};
