<?php

namespace App\Providers;

use App\Domains\Rbac\Policies\DynamicPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Use DynamicPolicy as the default for all models
        Gate::guessPolicyNamesUsing(fn () => DynamicPolicy::class);
    }
}
