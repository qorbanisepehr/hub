<?php

namespace App\Providers;

use App\Contracts\DocumentAuthorization;
use App\Domains\Cv\Repositories\CvRepository;
use App\Domains\Cv\Repositories\CvRepositoryInterface;
use App\Domains\Document\Repositories\DocumentRepository;
use App\Domains\Document\Repositories\DocumentRepositoryInterface;
use App\Domains\Questionnaire\Repositories\QuestionnaireRepository;
use App\Domains\Questionnaire\Repositories\QuestionnaireRepositoryInterface;
use App\Domains\Rbac\Policies\DynamicPolicy;
use App\Domains\Settings\Repositories\FileSettingsRepository;
use App\Domains\Settings\Repositories\SettingsRepositoryInterface;
use App\Domains\Settings\Services\SettingsService;
use App\Services\DocumentAuthorizationService;
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

        $this->app->singleton(SettingsRepositoryInterface::class, FileSettingsRepository::class);
        $this->app->singleton(SettingsService::class);
    }
}
