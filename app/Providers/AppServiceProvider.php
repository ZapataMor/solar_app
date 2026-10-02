<?php

namespace App\Providers;

use App\Domain\Climate\ClimateSourceChain;
use App\Domain\Reference\ReferenceValues;
use App\Infrastructure\Climate\AmbientWeatherClimateSource;
use App\Infrastructure\Climate\LocalStationClimateSource;
use App\Infrastructure\Climate\NasaPowerClimateSource;
use App\Infrastructure\Reference\DatabaseReferenceValues;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
