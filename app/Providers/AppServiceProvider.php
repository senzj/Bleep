<?php

namespace App\Providers;

use App\Models\Bleep;
use App\Models\Comments;
use App\Policies\BleepPolicy;
use App\Policies\CommentsPolicy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Bleep::class => BleepPolicy::class,
        Comments::class => CommentsPolicy::class,
    ];

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
        //
    }
}
