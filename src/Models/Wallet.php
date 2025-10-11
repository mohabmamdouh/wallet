<?php

namespace MohabMamdouh\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MohabMamdouh\Wallet\Events\DepositCreated;
use MohabMamdouh\Wallet\Events\WithdrawalCreated;
use MohabMamdouh\Wallet\Events\TransferCreated;
use MohabMamdouh\Wallet\Exceptions\InsufficientBalance;
use Illuminate\Support\Str;

class Wallet extends Model
{
    protected $table = 'wallets';
    protected $guarded = [];
    protected $casts = [
        'meta' => 'array',
        'total_balance' => 'decimal:8',
        'available_balance' => 'decimal:8',
        'held_balance' => 'decimal:8',
        'confirmed_balance' => 'decimal:8',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(Hold::class);
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Balance getters
    public function getTotalBalance(): float
    {
        return (float) $this->total_balance;
    }

    public function getAvailableBalance(): float
    {
        return (float) $this->available_balance;
    }

    public function getHeldBalance(): float
    {
        return (float) $this->held_balance;
    }

    public function getConfirmedBalance(): float
    {
        return (float) $this->confirmed_balance;
    }

    public function balance(): float
    {
        return $this->getAvailableBalance();
    }

    public function totalBalance(): float
    {
        return $this->getTotalBalance();
    }

    public function availableBalance(): float
    {
        return $this->getAvailableBalance();
    }

    public function heldBalance(): float
    {
        return $this->getHeldBalance();
    }

    public function confirmedBalance(): float
    {
        return $this->getConfirmedBalance();
    }

    // Balance updates
    public function incrementBalance(float $amount, string $type = 'total'): void
    {
        $this->increment($type . '_balance', $amount);
        $this->refresh();
    }

    public function decrementBalance(float $amount, string $type = 'available'): void
    {
        $this->decrement($type . '_balance', $amount);
        $this->refresh();
    }

    // Metadata
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return data_get($this->meta, $key, $default);
    }

    public function setMeta(string $key, mixed $value): self
    {
        $this->meta = array_merge($this->meta ?? [], [$key => $value]);
        return $this;
    }

    // Transaction methods
    private function generateReference(string $prefix): string
    {
        $generator = config('wallet.reference.generator', 'uuid');
        
        if ($generator === 'uuid') {
            return $prefix . '-' . Str::uuid();
        }
        
        return $prefix . '-' . now()->timestamp;
    }

    public function deposit(float $amount, string $reference = null, array $meta = []): Transaction
    {
        return \DB::transaction(function () use ($amount, $reference, $meta) {
            $transaction = $this->transactions()->create([
                'type' => Transaction::TYPE_DEPOSIT,
                'status' => Transaction::STATUS_PENDING,
                'amount' => $amount,
                'reference' => $reference ?? $this->generateReference('DEP'),
                'meta' => $meta,
            ]);

            $this->incrementBalance($amount, 'total');

            event(new DepositCreated($transaction));

            return $transaction;
        });
    }

    public function withdraw(float $amount, string $reference = null, array $meta = []): Transaction
    {
        return \DB::transaction(function () use ($amount, $reference, $meta) {
            if ($this->getAvailableBalance() < $amount) {
                throw new InsufficientBalance('Insufficient available balance');
            }

            $transaction = $this->transactions()->create([
                'type' => Transaction::TYPE_WITHDRAW,
                'status' => Transaction::STATUS_PENDING,
                'amount' => $amount,
                'reference' => $reference ?? $this->generateReference('WTH'),
                'meta' => $meta,
            ]);

            $this->decrementBalance($amount, 'available');
            $this->decrementBalance($amount, 'total');

            event(new WithdrawalCreated($transaction));

            return $transaction;
        });
    }

    public function forceWithdraw(float $amount, string $reference = null, array $meta = []): Transaction
    {
        return \DB::transaction(function () use ($amount, $reference, $meta) {
            $transaction = $this->transactions()->create([
                'type' => Transaction::TYPE_WITHDRAW,
                'status' => Transaction::STATUS_CONFIRMED,
                'amount' => $amount,
                'reference' => $reference ?? $this->generateReference('FWD'),
                'meta' => array_merge($meta, ['force' => true]),
                'confirmed_at' => now(),
            ]);

            $this->decrementBalance($amount, 'total');
            
            if ($this->getAvailableBalance() >= $amount) {
                $this->decrementBalance($amount, 'available');
            } else {
                $this->update(['available_balance' => 0]);
            }

            event(new WithdrawalCreated($transaction));

            return $transaction;
        });
    }

    public function transfer(Wallet $to, float $amount, string $reference = null, array $meta = []): Transaction
    {
        return \DB::transaction(function () use ($to, $amount, $reference, $meta) {
            if ($this->getAvailableBalance() < $amount) {
                throw new InsufficientBalance('Insufficient balance for transfer');
            }

            $transaction = $this->transactions()->create([
                'type' => Transaction::TYPE_TRANSFER,
                'status' => Transaction::STATUS_CONFIRMED,
                'amount' => $amount,
                'reference' => $reference ?? $this->generateReference('TRN'),
                'related_wallet_id' => $to->id,
                'meta' => $meta,
                'confirmed_at' => now(),
            ]);

            $this->decrementBalance($amount, 'available');
            $this->decrementBalance($amount, 'total');
            $to->incrementBalance($amount, 'total');
            $to->incrementBalance($amount, 'available');

            event(new TransferCreated($transaction));

            return $transaction;
        });
    }

    public function hold(float $amount, string $reference = null, array $meta = []): Hold
    {
        return \DB::transaction(function () use ($amount, $reference, $meta) {
            if ($this->getAvailableBalance() < $amount) {
                throw new InsufficientBalance('Insufficient balance to place hold');
            }

            $hold = $this->holds()->create([
                'amount' => $amount,
                'status' => Hold::STATUS_ACTIVE,
                'reference' => $reference ?? $this->generateReference('HLD'),
                'meta' => $meta,
            ]);

            $this->decrementBalance($amount, 'available');
            $this->incrementBalance($amount, 'held');

            return $hold;
        });
    }

    public function confirmTransaction(Transaction $transaction): Transaction
    {
        return \DB::transaction(function () use ($transaction) {
            if ($transaction->isConfirmed()) {
                return $transaction;
            }

            if ($transaction->type === Transaction::TYPE_DEPOSIT) {
                $this->incrementBalance($transaction->amount, 'confirmed');
                $this->incrementBalance($transaction->amount, 'available');
            }

            $transaction->confirm();

            return $transaction;
        });
    }

    public function failTransaction(Transaction $transaction, string $reason = null): Transaction
    {
        return \DB::transaction(function () use ($transaction, $reason) {
            if ($transaction->type === Transaction::TYPE_DEPOSIT) {
                $this->decrementBalance($transaction->amount, 'total');
            } elseif ($transaction->type === Transaction::TYPE_WITHDRAW) {
                $this->incrementBalance($transaction->amount, 'available');
                $this->incrementBalance($transaction->amount, 'total');
            }

            $transaction->fail($reason);

            return $transaction;
        });
    }
}
