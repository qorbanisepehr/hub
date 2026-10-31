<?php

namespace App\Providers;

use App\Contracts\Authorization;
use App\Contracts\DocumentAuthorization;
use App\Domains\Authorization\Services\AuthorizationService;
use App\Domains\Cv\Repositories\CvRepository;
use App\Domains\Cv\Repositories\CvRepositoryInterface;
use App\Domains\Document\Repositories\DocumentRepository;
use App\Domains\Document\Repositories\DocumentRepositoryInterface;
use App\Domains\Questionnaire\Repositories\QuestionnaireRepository;
use App\Domains\Questionnaire\Repositories\QuestionnaireRepositoryInterface;
use App\Domains\Settings\Repositories\DatabaseSystemSettingsRepository;
use App\Domains\Settings\Repositories\FileSettingsRepository;
use App\Domains\Settings\Repositories\SettingsRepositoryInterface;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use App\Domains\Settings\Services\SettingsService;
use App\Services\DocumentAuthorizationService;
use App\Support\Sms\Contracts\SmsSender;
use App\Support\Sms\Senders\LogSmsSender;
use Illuminate\Support\ServiceProvider;

class CoreRepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(QuestionnaireRepositoryInterface::class, QuestionnaireRepository::class);
        $this->app->bind(CvRepositoryInterface::class, CvRepository::class);
        $this->app->bind(DocumentRepositoryInterface::class, DocumentRepository::class);
        $this->app->bind(DocumentAuthorization::class, DocumentAuthorizationService::class);
        $this->app->singleton(Authorization::class, AuthorizationService::class);

        $this->app->bind(SmsSender::class, function (): SmsSender {
            $driver = config('sms.default', 'log');
            $class = config("sms.drivers.{$driver}", LogSmsSender::class);

            return new $class;
        });

        $this->app->singleton(SettingsRepositoryInterface::class, FileSettingsRepository::class);

        $this->app->singleton(
            SystemSettingsRepositoryInterface::class,
            DatabaseSystemSettingsRepository::class,
        );

        $this->app->singleton(SettingsService::class);
    }
}
