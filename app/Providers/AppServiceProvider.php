<?php

namespace App\Providers;

use App\Contracts\Auth\AuthVerificationProvider;
use App\Contracts\CriminalBackgroundCheckProvider;
use App\Models\Subscription;
use App\Models\UserVerification;
use App\Observers\SubscriptionObserver;
use App\Observers\UserVerificationObserver;
use App\Services\Auth\Providers\FirebaseAuthVerificationProvider;
use App\Services\BackgroundChecks\SearchbugClient;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CriminalBackgroundCheckProvider::class, SearchbugClient::class);

        // Bind AuthVerificationProvider → FirebaseAuthVerificationProvider.
        // To switch providers (Twilio, Auth0, etc.), change ONLY this binding.
        $this->app->bind(AuthVerificationProvider::class, function ($app) {
            return new FirebaseAuthVerificationProvider(
                $app->make(FirebaseAuth::class)
            );
        });
    }

    public function boot(): void
    {
        Subscription::observe(SubscriptionObserver::class);
        UserVerification::observe(UserVerificationObserver::class);
    }
}
