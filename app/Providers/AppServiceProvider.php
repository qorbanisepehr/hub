<?php

namespace App\Providers;

use App\Domains\Document\Repositories\DocumentRepository;
use App\Domains\Document\Repositories\DocumentRepositoryInterface;
use App\Domains\Rbac\Policies\DynamicPolicy;
use App\Domains\Recruitment\Repositories\QuestionnaireRepository;
use App\Domains\Recruitment\Repositories\QuestionnaireRepositoryInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::guessPolicyNamesUsing(fn () => DynamicPolicy::class);

        $this->app->bind(QuestionnaireRepositoryInterface::class, QuestionnaireRepository::class);
        $this->app->bind(DocumentRepositoryInterface::class, DocumentRepository::class);
    }
}
