<?php

namespace MohabMamdouh\Wallet\Providers;

use Illuminate\Support\ServiceProvider;

class WalletServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/wallet.php',
            'wallet'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
            __DIR__ . '/../config/wallet.php' => config_path('wallet.php'),
        ]);

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}