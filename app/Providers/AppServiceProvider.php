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

        // Suppress modulepreload for JS entry points: the <script type="module"> tag is emitted
        // immediately after in <head>, so the preload hint provides no measurable benefit.
        // Without this, Chrome fires a "preloaded but not used" warning because the waterfall
        // prefetch (triggered on window.load) creates network pressure that delays module
        // consumption past Chrome's preload timeout.
        Vite::usePreloadTagAttributes(function (string $src, string $url, array $chunk, ?array $manifest): array|bool {
            if (($chunk['isEntry'] ?? false) && ! str_ends_with($url, '.css')) {
                return false;
            }

            return [];
        });

        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);

        // Super admins bypass all policy checks; null user = unauthenticated, skip
        Gate::before(fn (?User $user, string $ability) => $user?->is_super_admin ? true : null);
    }
}
