<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Policies\EventPolicy;
use App\Policies\OrganizationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);

        // Super admins bypass all policy checks; null user = unauthenticated, skip
        Gate::before(fn (?User $user, string $ability) => $user?->is_super_admin ? true : null);
    }
}
