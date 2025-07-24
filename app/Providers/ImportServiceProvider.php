<?php

namespace App\Providers;

use App\Services\Import\DataMapper;
use App\Services\Import\CsvProcessor;
use App\Services\Import\FileResolver;
use Illuminate\Support\ServiceProvider;
use App\Services\Import\CsvImportService;
use App\Services\Import\TaxonomyExtractor;
use App\Repositories\ImportedDataRepository;

class ImportServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(CsvImportService::class, function ($app) {
            return new CsvImportService(
                $app->make(FileResolver::class),
                $app->make(CsvProcessor::class),
                $app->make(DataMapper::class),
                $app->make(TaxonomyExtractor::class),
                $app->make(ImportedDataRepository::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
