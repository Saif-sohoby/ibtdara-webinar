<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
// use App\Models\Notification;
use Illuminate\Notifications\DatabaseNotification as Notification;
use App\Observers\NotificationObserver;

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
        Notification::observe(NotificationObserver::class);
    }
}
