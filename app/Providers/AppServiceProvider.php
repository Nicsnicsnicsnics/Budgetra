<?php

namespace App\Providers;

use App\Database\ResilientLostConnectionDetector;
use Illuminate\Contracts\Database\LostConnectionDetector as LostConnectionDetectorContract;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Overrides the framework's own binding (DatabaseServiceProvider registers
        // first, so this one wins) to teach Laravel that Supabase's pooled-backend
        // terminations are retryable rather than fatal. See the detector class.
        $this->app->singleton(
            LostConnectionDetectorContract::class,
            ResilientLostConnectionDetector::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Expense::observe(\App\Observers\ExpenseObserver::class);
        \App\Models\Trip::observe(\App\Observers\TripObserver::class);
    }
}
