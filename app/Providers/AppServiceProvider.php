<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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

        // Requêtes N+1 : charger une relation à la volée sur un modèle issu d'une liste (penser à
        // with() / load()) fait échouer les tests ; en local, c'est seulement journalisé (une démo
        // ne doit pas planter) ; en production, rien ne change.
        Model::preventLazyLoading(! $this->app->isProduction());
        if (! $this->app->runningUnitTests()) {
            Model::handleLazyLoadingViolationUsing(fn (Model $model, string $relation) => logger()->warning('Requête N+1 : '.$model::class."::{$relation}"));
        }
    }
}
