<?php

namespace App\Providers;

use App\Domains\BoardMeeting\Imports\BoardMeetingDocumentsImportDefinition;
use App\Domains\Employee\Imports\EmployeeImportDefinition;
use App\Support\Exports\Contract\DocumentComposer;
use App\Support\Exports\DocumentExportService;
use App\Support\Exports\DocumentRendererRegistry;
use App\Support\Exports\ExportService;
use App\Support\Exports\Queued\ExportFileStore;
use App\Support\Exports\Render\DocxRenderer;
use App\Support\Exports\Render\JsonRenderer;
use App\Support\Exports\Render\MarkdownRenderer;
use App\Support\Exports\Render\PdfRenderer;
use App\Support\Exports\Templates\DocxTemplateFiller;
use App\Support\Exports\Templates\JsonTemplateFiller;
use App\Support\Exports\Templates\MarkdownTemplateFiller;
use App\Support\Exports\Templates\TemplateFillerRegistry;
use App\Support\Exports\Writer\CsvWriter;
use App\Support\Exports\Writer\JsonlWriter;
use App\Support\Exports\Writer\TsvWriter;
use App\Support\Exports\Writer\XlsxWriter;
use App\Support\Exports\WriterRegistry;
use App\Support\Imports\DocumentBundleImporterRegistry;
use App\Support\Imports\ImportDefinitionRegistry;
use App\Support\Imports\ImportService;
use App\Support\Imports\Reader\CsvReader;
use App\Support\Imports\Reader\XlsxReader;
use App\Support\Imports\ReaderRegistry;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class ExportKernelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WriterRegistry::class, function (): WriterRegistry {
            $registry = new WriterRegistry;
            $registry->register('csv', new CsvWriter);
            $registry->register('tsv', new TsvWriter);
            $registry->register('jsonl', new JsonlWriter);
            $registry->register('xlsx', new XlsxWriter);

            return $registry;
        });
        $this->app->singleton(ExportService::class);

        $this->app->singleton(DocumentRendererRegistry::class, function (): DocumentRendererRegistry {
            $registry = new DocumentRendererRegistry;
            $registry->register('pdf', new PdfRenderer);
            $registry->register('docx', new DocxRenderer);
            $registry->register('md', new MarkdownRenderer);
            $registry->register('json', new JsonRenderer);

            return $registry;
        });
        $this->app->singleton(DocumentExportService::class);

        $this->app->singleton(TemplateFillerRegistry::class, function ($app): TemplateFillerRegistry {
            $registry = new TemplateFillerRegistry;
            $registry->register($app->make(DocxTemplateFiller::class));
            $registry->register($app->make(MarkdownTemplateFiller::class));
            $registry->register($app->make(JsonTemplateFiller::class));

            return $registry;
        });

        $this->app->singleton(DocumentComposer::class, function ($app): DocumentComposer {
            $renderer = $app->make(DocumentRendererRegistry::class)->get('docx');

            if (! $renderer instanceof DocumentComposer) {
                throw new RuntimeException(sprintf(
                    'The [docx] renderer [%s] cannot compose onto an existing document, '
                    .'so a Word template cannot be used.',
                    $renderer::class,
                ));
            }

            return $renderer;
        });

        $this->app->singleton(ExportFileStore::class, fn (): ExportFileStore => new ExportFileStore(
            disk: (string) config('exports.disk'),
            directory: (string) config('exports.queued.directory'),
            ttlHours: (int) config('exports.queued.ttl_hours'),
        ));

        $this->app->singleton(ReaderRegistry::class, function (): ReaderRegistry {
            $registry = new ReaderRegistry;
            $registry->register('csv', new CsvReader);
            $registry->register('xlsx', new XlsxReader);

            return $registry;
        });
        $this->app->singleton(ImportService::class);

        $this->app->singleton(ImportDefinitionRegistry::class, function (): ImportDefinitionRegistry {
            $registry = new ImportDefinitionRegistry;
            $registry->register('employees', app(EmployeeImportDefinition::class));

            return $registry;
        });

        $this->app->singleton(DocumentBundleImporterRegistry::class, function (): DocumentBundleImporterRegistry {
            $registry = new DocumentBundleImporterRegistry;
            $registry->register('board-meeting-documents', app(BoardMeetingDocumentsImportDefinition::class));

            return $registry;
        });
    }
}
