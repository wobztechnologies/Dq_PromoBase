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
        Schema::create('csv_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->enum('type', [
                'category',
                'distributor',
                'manufacturer',
                'manufacturer_color',
                'stock',
                'price',
                'product'
            ]);
            $table->enum('mode', ['manufacturer', 'distributor'])->nullable(); // Pour les produits uniquement
            $table->enum('strategy', ['create_update', 'update_only'])->default('create_update');
            $table->string('file_path'); // Chemin du fichier CSV uploadé
            $table->string('s3_archive_path')->nullable(); // Chemin dans S3 après archivage
            $table->string('report_path')->nullable(); // Chemin du rapport TXT dans S3
            $table->enum('status', [
                'pending_validation',
                'validation_failed',
                'pending_matching',
                'matching_completed',
                'processing',
                'completed',
                'failed'
            ])->default('pending_validation');
            $table->json('validation_errors')->nullable(); // Erreurs de validation
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('successful_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('csv_imports');
    }
};
