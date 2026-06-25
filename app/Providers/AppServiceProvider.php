<?php

namespace App\Providers;

use App\Contracts\Auth\AuthVerificationProvider;
use App\Services\Auth\Providers\FirebaseAuthVerificationProvider;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
        //
    }
}
