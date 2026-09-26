<?php

namespace App\Providers;

use App\Contracts\Authorization;
use App\Contracts\DocumentAuthorization;
use App\Contracts\External\AttendanceGateway;
use App\Contracts\External\InboxGateway;
use App\Domains\Authorization\Policies\DynamicPolicy;
use App\Domains\Authorization\Services\AuthorizationService;
use App\Domains\Cv\Repositories\CvRepository;
use App\Domains\Cv\Repositories\CvRepositoryInterface;
use App\Domains\Document\Repositories\DocumentRepository;
use App\Domains\Document\Repositories\DocumentRepositoryInterface;
use App\Domains\Employee\Imports\EmployeeImportDefinition;
use App\Domains\Questionnaire\Repositories\QuestionnaireRepository;
use App\Domains\Questionnaire\Repositories\QuestionnaireRepositoryInterface;
use App\Domains\Reporting\Reports\UnifiedInbox\SenderIdentityResolver;
use App\Domains\Reporting\Reports\UnifiedInbox\Sources\GatewayInboxSource;
use App\Domains\Reporting\Reports\UnifiedInbox\Sources\InboxSourceRegistry;
use App\Domains\Settings\Repositories\FileSettingsRepository;
use App\Domains\Settings\Repositories\SettingsRepositoryInterface;
use App\Domains\Settings\Services\SettingsService;
use App\Integrations\Automation\AutomationInboxMapper;
use App\Integrations\Automation\FakeAutomationInboxGateway;
use App\Integrations\Bizagi\BizagiInboxMapper;
use App\Integrations\Bizagi\FakeBizagiInboxGateway;
use App\Integrations\Rahkaran\FakeRahkaranAttendanceGateway;
use App\Integrations\Rahkaran\FakeRahkaranInboxGateway;
use App\Integrations\Rahkaran\RahkaranAttendanceMapper;
use App\Integrations\Rahkaran\RahkaranInboxMapper;
use App\Services\DocumentAuthorizationService;
use App\Support\Exports\ExportService;
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

        // Unified inbox sources (V2 §63): each integration contributes a
        // named source by implementing InboxGateway; the report-level adapter
        // wraps it with key/label. Adding a system = adapter + registration.
        $this->app->bind(AutomationInboxMapper::class);
        $this->app->bind(BizagiInboxMapper::class);
        $this->app->bind(RahkaranInboxMapper::class);

        // Sender identity resolution: external username → HR Hub user.
        $this->app->bind(SenderIdentityResolver::class);

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
