<?php

namespace App\Jobs;

use App\Models\CsvImport;
use App\Services\CsvImport\CsvImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCsvImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public CsvImport $csvImport
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CsvImportService $importService): void
    {
        try {
            $importService->process($this->csvImport);
        } catch (\Exception $e) {
            Log::error('CSV import job failed', [
                'import_id' => $this->csvImport->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->csvImport->fail();
            throw $e;
        }
    }
}
