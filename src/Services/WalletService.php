<?php

namespace YourVendor\Wallet\Services;

use MohabMamdouh\Wallet\Models\Wallet;
use MohabMamdouh\Wallet\Models\Transaction;
use MohabMamdouh\Wallet\Models\Hold;
use MohabMamdouh\Wallet\Events\DepositCreated;
use MohabMamdouh\Wallet\Events\WithdrawalCreated;
use MohabMamdouh\Wallet\Events\TransferCreated;
use MohabMamdouh\Wallet\Exceptions\InsufficientBalance;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

class WalletService
{
    public function __construct(private DatabaseManager $db) {}

    private function ensureWallet(Wallet $wallet): Wallet
    {
        if (!$wallet->exists) {
            $wallet->save();
        }
        return $wallet;
    }

    private function generateReference(string $prefix): string
    {
        $generator = config('wallet.reference.generator', 'uuid');
        
        if ($generator === 'uuid') {
            return $prefix . '-' . Str::uuid();
        }
        
        return $prefix . '-' . now()->timestamp;
    }

    public function deposit(Wallet $wallet, float $amount, string $reference = null, array $meta = []): Transaction
    {
        return $this->db->transaction(function () use ($wallet, $amount, $reference, $meta) {
            $wallet = $this->ensureWallet($wallet);

            $transaction = $wallet->transactions()->create([
                'type' => Transaction::TYPE_DEPOSIT,
                'status' => Transaction::STATUS_PENDING,
                'amount' => $amount,
                'reference' => $reference ?? $this->generateReference('DEP'),
                'meta' => $meta,
            ]);

            $wallet->incrementBalance($amount, 'total');

            event(new DepositCreated($transaction));

            return $transaction;
        });
    }

    public function withdraw(Wallet $wallet, float $amount, string $reference = null, array $meta = []): Transaction
    {
        return $this->db->transaction(function () use ($wallet, $amount, $reference, $meta) {
            $wallet = $this->ensureWallet($wallet);

            if ($wallet->getAvailableBalance() < $amount) {
                throw new InsufficientBalance('Insufficient available balance');
            }

            $transaction = $wallet->transactions()->create([
                'type' => Transaction::TYPE_WITHDRAW,
                'status' => Transaction::STATUS_PENDING,
                'amount' => $amount,
                'reference' => $reference ?? $this->generateReference('WTH'),
                'meta' => $meta,
            ]);

            $wallet->decrementBalance($amount, 'available');
            $wallet->decrementBalance($amount, 'total');

            event(new WithdrawalCreated($transaction));

            return $transaction;
        });
    }

    public function forceWithdraw(Wallet $wallet, float $amount, string $reference = null, array $meta = []): Transaction
    {
        return $this->db->transaction(function () use ($wallet, $amount, $reference, $meta) {
            $wallet = $this->ensureWallet($wallet);

            $transaction = $wallet->transactions()->create([
                'type' => Transaction::TYPE_WITHDRAW,
                'status' => Transaction::STATUS_CONFIRMED,
                'amount' => $amount,
                'reference' => $reference ?? $this->generateReference('FWD'),
                'meta' => array_merge($meta, ['force' => true]),
                'confirmed_at' => now(),
            ]);

            $wallet->decrementBalance($amount, 'total');
            
            // Only decrement available if it has enough, otherwise allow negative
            if ($wallet->getAvailableBalance() >= 0) {
                $wallet->decrementBalance($amount, 'available');
            }

            event(new WithdrawalCreated($transaction));

            return $transaction;
        });
    }

    public function transfer(Wallet $from, Wallet $to, float $amount, string $reference = null, array $meta = []): Transaction
    {
        return $this->db->transaction(function () use ($from, $to, $amount, $reference, $meta) {
            $from = $this->ensureWallet($from);
            $to = $this->ensureWallet($to);

            if ($from->getAvailableBalance() < $amount) {
                throw new InsufficientBalance('Insufficient balance for transfer');
            }

            $transaction = $from->transactions()->create([
                'type' => Transaction::TYPE_TRANSFER,
                'status' => Transaction::STATUS_CONFIRMED,
                'amount' => $amount,
                'reference' => $reference ?? $this->generateReference('TRN'),
                'related_wallet_id' => $to->id,
                'meta' => $meta,
                'confirmed_at' => now(),
            ]);

            $from->decrementBalance($amount, 'available');
            $from->decrementBalance($amount, 'total');
            $to->incrementBalance($amount, 'total');
            $to->incrementBalance($amount, 'available');

            event(new TransferCreated($transaction));

            return $transaction;
        });
    }

    public function hold(Wallet $wallet, float $amount, string $reference = null, array $meta = []): Hold
    {
        return $this->db->transaction(function () use ($wallet, $amount, $reference, $meta) {
            $wallet = $this->ensureWallet($wallet);

            if ($wallet->getAvailableBalance() < $amount) {
                throw new InsufficientBalance('Insufficient balance to place hold');
            }

            $hold = $wallet->holds()->create([
                'amount' => $amount,
                'status' => Hold::STATUS_ACTIVE,
                'reference' => $reference ?? 'HLD-' . now()->timestamp,
                'meta' => $meta,
            ]);

            $wallet->decrementBalance($amount, 'available');
            $wallet->incrementBalance($amount, 'held');

            return $hold;
        });
    }

    public function confirmTransaction(Transaction $transaction): Transaction
    {
        return $this->db->transaction(function () use ($transaction) {
            if ($transaction->isConfirmed()) {
                return $transaction;
            }

            $wallet = $transaction->wallet;

            if ($transaction->type === Transaction::TYPE_DEPOSIT) {
                $wallet->incrementBalance($transaction->amount, 'confirmed');
                $wallet->incrementBalance($transaction->amount, 'available');
            }

            $transaction->confirm();

            return $transaction;
        });
    }

    public function failTransaction(Transaction $transaction, string $reason = null): Transaction
    {
        return $this->db->transaction(function () use ($transaction, $reason) {
            $wallet = $transaction->wallet;

            if ($transaction->type === Transaction::TYPE_DEPOSIT) {
                $wallet->decrementBalance($transaction->amount, 'total');
            } elseif ($transaction->type === Transaction::TYPE_WITHDRAW) {
                $wallet->incrementBalance($transaction->amount, 'available');
                $wallet->incrementBalance($transaction->amount, 'total');
            }

            $transaction->fail($reason);

            return $transaction;
        });
    }
}