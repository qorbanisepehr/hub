<?php

namespace App\Providers;

use App\Contracts\Authorization;
use App\Contracts\DocumentAuthorization;
use App\Domains\Authorization\Policies\DynamicPolicy;
use App\Domains\Authorization\Services\AuthorizationService;
use App\Domains\Cv\Repositories\CvRepository;
use App\Domains\Cv\Repositories\CvRepositoryInterface;
use App\Domains\Document\Repositories\DocumentRepository;
use App\Domains\Document\Repositories\DocumentRepositoryInterface;
use App\Domains\Employee\Imports\EmployeeImportDefinition;
use App\Domains\Questionnaire\Repositories\QuestionnaireRepository;
use App\Domains\Questionnaire\Repositories\QuestionnaireRepositoryInterface;
use App\Domains\Settings\Repositories\FileSettingsRepository;
use App\Domains\Settings\Repositories\SettingsRepositoryInterface;
use App\Domains\Settings\Services\SettingsService;
use App\Services\DocumentAuthorizationService;
use App\Support\Exports\DocumentExportService;
use App\Support\Exports\DocumentRendererRegistry;
use App\Support\Exports\ExportService;
use App\Support\Exports\Render\DocxRenderer;
use App\Support\Exports\Render\PdfRenderer;
use App\Support\Exports\Writer\CsvWriter;
use App\Support\Exports\Writer\JsonlWriter;
use App\Support\Exports\Writer\TsvWriter;
use App\Support\Exports\Writer\XlsxWriter;
use App\Support\Exports\WriterRegistry;
use App\Support\Imports\ImportDefinitionRegistry;
use App\Support\Imports\ImportService;
use App\Support\Imports\Reader\CsvReader;
use App\Support\Imports\Reader\XlsxReader;
use App\Support\Imports\ReaderRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::guessPolicyNamesUsing(fn () => DynamicPolicy::class);

        $this->app->bind(QuestionnaireRepositoryInterface::class, QuestionnaireRepository::class);
        $this->app->bind(CvRepositoryInterface::class, CvRepository::class);
        $this->app->bind(DocumentRepositoryInterface::class, DocumentRepository::class);
        $this->app->bind(DocumentAuthorization::class, DocumentAuthorizationService::class);
        $this->app->singleton(Authorization::class, AuthorizationService::class);

        $this->app->singleton(SettingsRepositoryInterface::class, FileSettingsRepository::class);
        $this->app->singleton(SettingsService::class);

        // Export kernel: formats are registered here (OCP — a new format is a
        // registration, never an edit to ExportService).
        $this->app->singleton(WriterRegistry::class, function (): WriterRegistry {
            $registry = new WriterRegistry;
            $registry->register('csv', new CsvWriter);
            $registry->register('tsv', new TsvWriter);
            $registry->register('jsonl', new JsonlWriter);
            $registry->register('xlsx', new XlsxWriter);

            return $registry;
        });
        $this->app->singleton(ExportService::class);

        // Document renderers (PDF/Word): the in-memory half of the export
        // kernel, registered as a sibling of the tabular WriterRegistry so
        // a new document format is one line here, never an edit to the
        // services (same OCP as the writers).
        $this->app->singleton(DocumentRendererRegistry::class, function (): DocumentRendererRegistry {
            $registry = new DocumentRendererRegistry;
            $registry->register('pdf', new PdfRenderer);
            $registry->register('docx', new DocxRenderer);

            return $registry;
        });
        $this->app->singleton(DocumentExportService::class);

        // Import kernel: readers are registered here (OCP — a new format is
        // a registration, never an edit to ImportService). Mirrors the
        // export kernel's registry.
        $this->app->singleton(ReaderRegistry::class, function (): ReaderRegistry {
            $registry = new ReaderRegistry;
            $registry->register('csv', new CsvReader);
            $registry->register('xlsx', new XlsxReader);

            return $registry;
        });
        $this->app->singleton(ImportService::class);

        // Import definitions: entities are registered here (OCP — a new
        // importable entity is a registration, never an edit to the
        // controller or kernel).
        $this->app->singleton(ImportDefinitionRegistry::class, function (): ImportDefinitionRegistry {
            $registry = new ImportDefinitionRegistry;
            $registry->register('employees', app(EmployeeImportDefinition::class));

            return $registry;
        });
    }
}
