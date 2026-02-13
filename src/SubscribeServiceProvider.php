<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe;

use App\Support\AdminNavigation;
use Illuminate\Support\ServiceProvider;

final class SubscribeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/subscribe.php', 'subscribe');

        $this->app->singleton(SubscribeManager::class, fn ($app) => new SubscribeManager($app));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/subscribe.php' => config_path('subscribe.php'),
        ], 'subscribe-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'subscribe-migrations');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if ($this->isAdminEnabled()) {
            $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'subscribe');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/subscribe'),
        ], 'subscribe-views');

        $this->publishes([
            __DIR__.'/../skills/subscribe-development' => base_path('.claude/skills/subscribe-development'),
        ], 'subscribe-skills');

        $this->publishes([
            __DIR__.'/../skills/subscribe-development' => base_path('.github/skills/subscribe-development'),
        ], 'subscribe-skills-github');

        $this->registerAdminNavigation();
    }

    /**
     * Check if admin routes should be enabled via DB setting or config fallback.
     */
    private function isAdminEnabled(): bool
    {
        if (class_exists(\LaravelPlus\GlobalSettings\Models\Setting::class)) {
            try {
                $dbValue = \LaravelPlus\GlobalSettings\Models\Setting::get('package.subscribers.enabled');

                if ($dbValue !== null) {
                    return in_array($dbValue, ['1', 'true', true, 1], true);
                }
            } catch (\Throwable) {
                // Table may not exist yet during migrations
            }
        }

        return (bool) config('subscribe.admin.enabled', true);
    }

    /**
     * Register admin sidebar navigation items.
     */
    protected function registerAdminNavigation(): void
    {
        $this->callAfterResolving(AdminNavigation::class, function (AdminNavigation $nav): void {
            $prefix = config('subscribe.admin.prefix', 'admin/subscribers');

            $nav->register('subscribers', 'Subscribers', 'Mail', [
                ['title' => 'Dashboard', 'href' => "/{$prefix}", 'icon' => 'LayoutDashboard'],
                ['title' => 'Subscribers', 'href' => "/{$prefix}/subscribers", 'icon' => 'UserCheck'],
                ['title' => 'Lists', 'href' => "/{$prefix}/lists", 'icon' => 'List'],
            ], 20);
        });
    }
}
