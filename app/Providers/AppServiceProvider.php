<?php

namespace App\Providers;

use App\Domain\Climate\ClimateSourceChain;
use App\Domain\Consumption\ApplianceCatalog;
use App\Domain\Reference\ReferenceValues;
use App\Infrastructure\Climate\AmbientWeatherClimateSource;
use App\Infrastructure\Climate\LocalStationClimateSource;
use App\Infrastructure\Climate\NasaPowerClimateSource;
use App\Infrastructure\Consumption\DatabaseApplianceEntries;
use App\Infrastructure\Reference\DatabaseReferenceValues;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Appliance catalog: the built-in one plus what administrators added (ADR-0017), read once per request.
        $this->app->scoped(ApplianceCatalog::class, fn ($app) => new ApplianceCatalog($app->make(DatabaseApplianceEntries::class)->all()));

        // Reference values (ADR-0015): one instance per request, so a recorded value is seen right away.
        $this->app->scoped(DatabaseReferenceValues::class);
        $this->app->scoped(ReferenceValues::class, fn ($app) => $app->make(DatabaseReferenceValues::class));

        // Climate sources in priority order: highest-quality data first.
        $this->app->singleton(ClimateSourceChain::class, fn ($app) => new ClimateSourceChain(
            $app->make(AmbientWeatherClimateSource::class),
            $app->make(LocalStationClimateSource::class),
            $app->make(NasaPowerClimateSource::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Platform administration (raw data, future users/prices screens).
        Gate::define('administer-platform', fn (User $user): bool => $user->isAdmin());

        // 3D designer: a workbench for the models, only for admins and only with APP_ENV=local.
        Gate::define('design-3d', fn (User $user): bool => $user->isAdmin() && app()->environment('local'));

        // Climate readings are shared by every project, so only admins may trigger a sync.
        Gate::define('sync-climate-data', fn (User $user): bool => $user->isAdmin());

        // `php artisan serve` does not pass TEMP/TMP to the web server, so on Windows SQLite cannot
        // create the temp files large GROUP BY / ORDER BY queries need ("unable to open database file").
        // Keeping SQLite temporaries in memory works no matter how the server was started.
        Event::listen(function (ConnectionEstablished $event): void {
            if ($event->connection->getDriverName() === 'sqlite') {
                $event->connection->statement('PRAGMA temp_store = MEMORY');
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // After any page loads, fetch the chunks app.js imports on demand (the 3D scenes and Three.js),
        // quietly: the first project opened finds its 3D already downloaded (ADR-0012).
        Vite::prefetch(concurrency: 3);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
