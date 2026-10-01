<?php

namespace App\Providers;

use App\Domain\Climate\ClimateSourceChain;
use App\Infrastructure\Climate\AmbientWeatherClimateSource;
use App\Infrastructure\Climate\LocalStationClimateSource;
use App\Infrastructure\Climate\NasaPowerClimateSource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
