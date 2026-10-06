<?php

namespace App\Providers;

use App\Contracts\Authorization;
use App\Contracts\BoardMeeting\RegistrySync;
use App\Contracts\DocumentAuthorization;
use App\Contracts\External\AttendanceGateway;
use App\Contracts\External\FarzinGateway;
use App\Contracts\External\InboxGateway;
use App\Domains\Authorization\Policies\DynamicPolicy;
use App\Domains\Authorization\Services\AuthorizationService;
use App\Domains\Cv\Repositories\CvRepository;
use App\Domains\Cv\Repositories\CvRepositoryInterface;
use App\Domains\Document\Repositories\DocumentRepository;
use App\Domains\Document\Repositories\DocumentRepositoryInterface;
use App\Domains\Employee\Imports\EmployeeImportDefinition;
use App\Domains\Ingest\IngestRegistry;
use App\Domains\Questionnaire\Repositories\QuestionnaireRepository;
use App\Domains\Questionnaire\Repositories\QuestionnaireRepositoryInterface;
use App\Domains\Reporting\Reports\UnifiedInbox\SenderIdentityResolver;
use App\Domains\Reporting\Reports\UnifiedInbox\Sources\GatewayInboxSource;
use App\Domains\Reporting\Reports\UnifiedInbox\Sources\InboxSourceRegistry;
use App\Domains\Settings\Repositories\DatabaseSystemSettingsRepository;
use App\Domains\Settings\Repositories\FileSettingsRepository;
use App\Domains\Settings\Repositories\SettingsRepositoryInterface;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use App\Domains\Settings\Services\SettingsService;
use App\Integrations\Automation\AutomationInboxMapper;
use App\Integrations\Automation\FakeAutomationInboxGateway;
use App\Integrations\Bizagi\BizagiInboxMapper;
use App\Integrations\Bizagi\FakeBizagiInboxGateway;
use App\Integrations\Farzin\BoardMeeting\FarzinRegistryImport;
use App\Integrations\Farzin\FakeFarzinSource;
use App\Integrations\Farzin\FarzinLetterNormalizer;
use App\Integrations\Farzin\FarzinSessionNormalizer;
use App\Integrations\Farzin\SqlsrvFarzinSource;
use App\Integrations\Rahkaran\FakeRahkaranAttendanceGateway;
use App\Integrations\Rahkaran\FakeRahkaranInboxGateway;
use App\Integrations\Rahkaran\RahkaranAttendanceMapper;
use App\Integrations\Rahkaran\RahkaranInboxMapper;
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
use RuntimeException;

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
            throw new RuntimeException(
                'No real Rahkaran driver is implemented yet; set RAHKARAN_DRIVER=fake.',
            );
        });

        // Unified inbox sources (V2 §63): each integration contributes a
        // named source by implementing InboxGateway; the report-level adapter
        // wraps it with key/label. Adding a system = adapter + registration.
        $this->app->bind(AutomationInboxMapper::class);
        $this->app->bind(BizagiInboxMapper::class);
        $this->app->bind(RahkaranInboxMapper::class);

        // Sender identity resolution: external username → HR Hub user.
        $this->app->bind(SenderIdentityResolver::class);

        // The Farzin source behind the board-meeting registry sync: a
        // driver change is config, never an edit to the domain.
        $this->app->bind(FarzinGateway::class, function (): FarzinGateway {
            return match ((string) config('integrations.farzin.driver')) {
                'fake' => new FakeFarzinSource,
                'sqlsrv' => new SqlsrvFarzinSource,
                default => throw new RuntimeException(
                    'Unknown FARZIN_DRIVER; supported: fake, sqlsrv.',
                ),
            };
        });

        // The registry sync capability: the domain publishes the port,
        // the integration that owns a source binds it. Adding a second
        // source is a change here, not in App\Domains\BoardMeeting.
        $this->app->bind(RegistrySync::class, FarzinRegistryImport::class);

        // Ingest normalizers (sync pipeline, generic records layer): one
        // per entity type; adding a synced entity = class + registration.
        $this->app->singleton(IngestRegistry::class, function (): IngestRegistry {
            $registry = new IngestRegistry;

            $registry->register(
                'farzin',
                'Entity_A4letter',
                $this->app->make(FarzinLetterNormalizer::class),
            );
            $registry->register(
                'farzin',
                'Entity_heyatModire',
                $this->app->make(FarzinSessionNormalizer::class),
            );

            return $registry;
        });

        $this->app->singleton(InboxSourceRegistry::class, function (): InboxSourceRegistry {
            $registry = new InboxSourceRegistry;

            $registry->register(new GatewayInboxSource(
                'automation',
                'اتوماسیون اداری',
                new FakeAutomationInboxGateway($this->app->make(AutomationInboxMapper::class)),
            ));

            $registry->register(new GatewayInboxSource(
                'rahkaran',
                'راهکاران',
                new FakeRahkaranInboxGateway($this->app->make(RahkaranInboxMapper::class)),
            ));

            $registry->register(new GatewayInboxSource(
                'bizagi',
                'بیزاجی',
                new FakeBizagiInboxGateway($this->app->make(BizagiInboxMapper::class)),
            ));

            return $registry;
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

        // The typed, table-backed store. Separate from the file-backed one above:
        // this one gives a key a shape and keeps a private half in its own
        // column, which the flat branding store has no concept of.
        $this->app->singleton(
            SystemSettingsRepositoryInterface::class,
            DatabaseSystemSettingsRepository::class,
        );

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
