<?php

namespace App\Providers;

use App\Contracts\Authorization;
use App\Contracts\DocumentAuthorization;
use App\Contracts\External\AttendanceGateway;
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
use App\Integrations\Rahkaran\FakeRahkaranAttendanceGateway;
use App\Integrations\Rahkaran\RahkaranAttendanceMapper;
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
use App\Support\Sms\Contracts\SmsSender;
use App\Support\Sms\Senders\LogSmsSender;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::guessPolicyNamesUsing(fn () => DynamicPolicy::class);

        // External integrations (V2 §65): the capability gateway is bound to
        // the configured driver — a new source is a binding change, never an
        // edit to Reporting.
        $this->app->bind(AttendanceGateway::class, function ($app): AttendanceGateway {
            if (config('integrations.rahkaran.driver') === 'fake') {
                return new FakeRahkaranAttendanceGateway($app->make(RahkaranAttendanceMapper::class));
            }

            // Real drivers arrive with actual connection details (SQL Server,
            // HTTP, ...); until then fake is the only configured option.
            throw new \RuntimeException(
                'No real Rahkaran driver is implemented yet; set RAHKARAN_DRIVER=fake.',
            );
        });

        $this->app->bind(QuestionnaireRepositoryInterface::class, QuestionnaireRepository::class);
        $this->app->bind(CvRepositoryInterface::class, CvRepository::class);
        $this->app->bind(DocumentRepositoryInterface::class, DocumentRepository::class);
        $this->app->bind(DocumentAuthorization::class, DocumentAuthorizationService::class);
        $this->app->singleton(Authorization::class, AuthorizationService::class);

        // SMS delivery: resolves the driver configured in config/sms.php. The
        // log driver is the default until a real gateway is bought; swapping
        // drivers never touches the callers (OtpService, and later Notification).
        $this->app->bind(SmsSender::class, function (): SmsSender {
            $driver = config('sms.default', 'log');
            $class = config("sms.drivers.{$driver}", LogSmsSender::class);

            return new $class;
        });

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
