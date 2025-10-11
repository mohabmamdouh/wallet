<?php
namespace MohabMamdouh\Wallet\Traits;

use MohabMamdouh\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasWallet
{
    public static function bootHasWallet()
    {
        static::created(function ($model) {
            if (config('wallet.default_wallet.auto_create')) {
                $model->createDefaultWallet();
            }
        });
    }

    public function wallets(): MorphMany
    {
        return $this->morphMany(Wallet::class, 'owner');
    }

    public function wallet(string $type = 'main'): ?Wallet
    {
        return $this->wallets()->where('type', $type)->first();
    }

    public function getOrCreateWallet(string $type = 'main', array $meta = [], float $initialBalance = null): Wallet
    {
        $initialBalance = $initialBalance ?? 0;

        $wallet = $this->wallets()->firstOrCreate(
            ['type' => $type],
            [
                'meta' => $meta,
                'is_active' => true,
                'total_balance' => $initialBalance,
                'available_balance' => $initialBalance,
                'confirmed_balance' => $initialBalance,
            ]
        );

        return $wallet;
    }

    public function createDefaultWallet(float $initialBalance = null): Wallet
    {
        $type = config('wallet.default_wallet.type', 'main');
        $initialBalance = $initialBalance ?? config('wallet.default_wallet.initial_balance', 0);

        return $this->getOrCreateWallet($type, [], $initialBalance);
    }

    public function getWallet(string $type = 'main'): ?Wallet
    {
        return $this->wallet($type);
    }

    public function getAllWallets()
    {
        return $this->wallets()->active()->get();
    }

    public function getTotalBalanceAcrossWallets(): float
    {
        return $this->wallets()->active()->sum('total_balance');
    }
}