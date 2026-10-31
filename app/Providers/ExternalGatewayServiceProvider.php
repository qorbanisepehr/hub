<?php

namespace App\Providers;

use App\Contracts\BoardMeeting\RegistrySync;
use App\Contracts\External\AttendanceGateway;
use App\Contracts\External\FarzinGateway;
use App\Domains\Ingest\IngestRegistry;
use App\Domains\Reporting\Reports\UnifiedInbox\SenderIdentityResolver;
use App\Domains\Reporting\Reports\UnifiedInbox\Sources\GatewayInboxSource;
use App\Domains\Reporting\Reports\UnifiedInbox\Sources\InboxSourceRegistry;
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
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class ExternalGatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AttendanceGateway::class, function ($app): AttendanceGateway {
            if (config('integrations.rahkaran.driver') === 'fake') {
                return new FakeRahkaranAttendanceGateway($app->make(RahkaranAttendanceMapper::class));
            }

            throw new RuntimeException(
                'No real Rahkaran driver is implemented yet; set RAHKARAN_DRIVER=fake.',
            );
        });

        $this->app->bind(AutomationInboxMapper::class);
        $this->app->bind(BizagiInboxMapper::class);
        $this->app->bind(RahkaranInboxMapper::class);
        $this->app->bind(RahkaranAttendanceMapper::class);
        $this->app->bind(SenderIdentityResolver::class);

        $this->app->bind(FarzinGateway::class, function (): FarzinGateway {
            return match ((string) config('integrations.farzin.driver')) {
                'fake' => new FakeFarzinSource,
                'sqlsrv' => new SqlsrvFarzinSource,
                default => throw new RuntimeException(
                    'Unknown FARZIN_DRIVER; supported: fake, sqlsrv.',
                ),
            };
        });

        $this->app->bind(RegistrySync::class, FarzinRegistryImport::class);

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
    }
}
