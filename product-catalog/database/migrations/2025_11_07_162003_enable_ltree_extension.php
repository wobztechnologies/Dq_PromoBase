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
        \DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
        \DB::statement('CREATE EXTENSION IF NOT EXISTS "ltree";');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \DB::statement('DROP EXTENSION IF EXISTS "ltree";');
        \DB::statement('DROP EXTENSION IF EXISTS "uuid-ossp";');
    }
};
