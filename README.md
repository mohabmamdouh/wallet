# 🪙 Wallet - Advanced Laravel Wallet System

A powerful, production-ready wallet system for Laravel designed for financial platforms, marketplaces, fintech applications, and e-commerce systems. Built with atomic transactions, comprehensive audit trails, and extensible architecture.

---

## 📋 Table of Contents

- [Features](#-features)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Core Concepts](#-core-concepts)
- [Usage Guide](#-usage-guide)
- [API Reference](#-api-reference)
- [Advanced Examples](#-advanced-examples)
- [Error Handling](#-error-handling)
- [Testing](#-testing)

---

## ✨ Features

### Core Features
✅ **Multi-Wallet System** - Multiple wallets per user with custom slugs and names  
✅ **Balance Types** - Track total, available, held, and confirmed balances independently  
✅ **Deposit System** - Pending deposits with confirmation workflow  
✅ **Withdrawal System** - Standard and force withdrawals with balance validation  
✅ **Transfers** - Instant peer-to-peer transfers between wallets  
✅ **Holds/Locks** - Hold funds temporarily with release/capture functionality  
✅ **Transaction Ledger** - Immutable, fully auditable transaction history  
✅ **Reference Generation** - UUID or timestamp-based unique references  
✅ **Metadata Storage** - Attach custom data to wallets and transactions  

### Technical Features
✅ **Atomic Operations** - Database transactions ensure consistency  
✅ **Status Management** - Pending, confirmed, failed, cancelled workflows  
✅ **Event Driven** - Events fired for every financial action  
✅ **Scalable** - Indexed database schema for high-performance queries  
✅ **Type Safe** - Full type hints and strict mode compatible  
✅ **Polymorphic** - Works with any Eloquent model (Users, Organizations, etc.)  
✅ **Error Handling** - Custom exceptions for better error management  
✅ **UUID Support** - Default UUID references with fallback to timestamps  

---

## 📦 Installation

### Step 1: Install Package via Composer

```bash
composer require mohabmamdouh/wallet
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --provider="MohabMamdouh\Wallet\Providers\WalletServiceProvider"
```

### Step 3: Run Migrations

```bash
php artisan migrate
```

### Step 4: Add Trait to Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use MohabMamdouh\Wallet\Traits\HasWallet;

class User extends Model
{
    use HasWallet;
}
```

---

## ⚙️ Configuration

Edit `config/wallet.php`:

```php
return [
    // Decimal precision for balance calculations
    'decimal_places' => 8,
    
    // Default currency for wallets
    'default_currency' => 'USD',
    
    // Default wallet configuration
    'default_wallet' => [
        'slug' => 'main',
        'name' => 'Main Wallet',
        'initial_balance' => 0,
        'auto_create' => true,  // Auto-create on model creation
    ],

    // Reference generation method
    'reference' => [
        'generator' => 'uuid',  // 'uuid' or 'timestamp'
    ],
];
```

---

## 💡 Core Concepts

### Wallet Slugs
Wallets are identified by unique slugs within each owner. Use descriptive, URL-friendly slugs:

```
main              → Default wallet
savings           → Savings account
escrow-order-123  → Order-specific escrow
business          → Business wallet
rewards           → Reward points wallet
```

### Balance Types

| Balance Type | Description | Use Case |
|---|---|---|
| **total_balance** | Sum of all balances | Overall funds |
| **available_balance** | Amount user can spend | Normal transactions |
| **held_balance** | Temporarily locked funds | Order holds, disputes |
| **confirmed_balance** | Verified deposits | Revenue recognition |

### Transaction Types

| Type | Status | Description |
|---|---|---|
| **deposit** | pending/confirmed | Adding funds to wallet |
| **withdraw** | pending/confirmed | Removing funds (validated) |
| **transfer** | confirmed | Moving funds between wallets |
| **refund** | confirmed | Money returned to user |

### Transaction States

```
DEPOSIT FLOW:
pending → confirm() → confirmed

WITHDRAWAL FLOW:
pending → confirm() → confirmed
       ↘ fail() → failed

TRANSFER FLOW:
created → confirmed (atomic, no pending)
```

---

## 🚀 Usage Guide

### Basic Setup

```php
use App\Models\User;

$user = User::find(1);

// Get or create default wallet (auto-created on user creation)
$wallet = $user->wallet('main');

// Or explicitly create
$wallet = $user->getOrCreateWallet('main', 'Main Wallet');
```

### Deposits

```php
use MohabMamdouh\Wallet\Services\WalletService;

$service = app(WalletService::class);

// Basic deposit (pending)
$txn = $service->deposit($wallet, 100);

// Deposit with metadata
$txn = $service->deposit($wallet, 500, null, [
    'source' => 'stripe',
    'payment_id' => 'pi_123',
]);

// Confirm deposit
$txn->confirm();
$wallet->refresh();
echo $wallet->balance(); // 500

// Fail deposit
$txn->fail('Payment declined');
```

### Withdrawals

```php
// Standard withdrawal (requires available balance)
$txn = $service->withdraw($wallet, 50);

// Force withdrawal (no balance check, can go negative)
$txn = $service->forceWithdraw($wallet, 100, null, [
    'reason' => 'admin_action',
]);

// Check withdrawal status
if ($txn->isConfirmed()) {
    echo "Withdrawal successful";
}
```

### Transfers

```php
$user1 = User::find(1);
$user2 = User::find(2);

$wallet1 = $user1->wallet('main');
$wallet2 = $user2->wallet('main');

// Transfer between users (atomic, instantly confirmed)
$txn = $service->transfer($wallet1, $wallet2, 100, null, [
    'order_id' => 123,
]);

echo "Transfer ID: " . $txn->reference;
```

### Holds/Locks

```php
// Place hold on funds (e.g., for pending order)
$hold = $service->hold($wallet, 1000, null, [
    'order_id' => 123,
]);

echo $wallet->held_balance;      // 1000
echo $wallet->available_balance; // (reduced by 1000)

// Later: Release hold (refund)
$hold->release();

// Or: Capture hold (confirm debit)
$hold->capture();
```

### Balance Queries

```php
$wallet = $user->wallet('main');

// Quick balance
$balance = $wallet->balance();  // Available balance

// All balance types
$wallet->totalBalance();        // Total funds
$wallet->availableBalance();    // Spendable funds
$wallet->heldBalance();         // Held/locked funds
$wallet->confirmedBalance();    // Confirmed deposits

// Direct attribute access
$wallet->total_balance;         // Decimal value
$wallet->available_balance;
$wallet->held_balance;
$wallet->confirmed_balance;
```

### Multi-Wallet Operations

```php
// Create multiple wallets
$user->getOrCreateWallet('main', 'Main Account');
$user->getOrCreateWallet('savings', 'Savings Account');
$user->getOrCreateWallet('business', 'Business Account');

// Get all wallets
$wallets = $user->getAllWallets();

// Total across all wallets
$total = $user->getTotalBalanceAcrossWallets();

// Transfer between own wallets
$main = $user->wallet('main');
$savings = $user->wallet('savings');
$service->transfer($main, $savings, 500);
```

### Metadata Management

```php
$wallet = $user->wallet('main');

// Set metadata
$wallet->setMeta('account_type', 'premium');
$wallet->setMeta('kyc_verified', true);
$wallet->save();

// Get metadata
$accountType = $wallet->getMeta('account_type');
$verified = $wallet->getMeta('kyc_verified', false);
```

---

## 📚 API Reference

### WalletService

```php
class WalletService {
    
    // Deposit funds (pending)
    public function deposit(
        Wallet $wallet,
        float $amount,
        string $reference = null,
        array $meta = []
    ): Transaction
    
    // Withdraw funds (pending, requires balance)
    public function withdraw(
        Wallet $wallet,
        float $amount,
        string $reference = null,
        array $meta = []
    ): Transaction
    
    // Force withdraw (no balance check)
    public function forceWithdraw(
        Wallet $wallet,
        float $amount,
        string $reference = null,
        array $meta = []
    ): Transaction
    
    // Transfer between wallets (confirmed)
    public function transfer(
        Wallet $from,
        Wallet $to,
        float $amount,
        string $reference = null,
        array $meta = []
    ): Transaction
    
    // Hold funds temporarily
    public function hold(
        Wallet $wallet,
        float $amount,
        string $reference = null,
        array $meta = []
    ): Hold
    
    // Confirm transaction
    public function confirmTransaction(
        Transaction $transaction
    ): Transaction
    
    // Fail transaction with reason
    public function failTransaction(
        Transaction $transaction,
        string $reason = null
    ): Transaction
}
```

### Wallet Model Methods

```php
// Balance getters
$wallet->balance(): float              // Available balance
$wallet->totalBalance(): float         // Total balance
$wallet->availableBalance(): float     // Available balance
$wallet->heldBalance(): float          // Held balance
$wallet->confirmedBalance(): float     // Confirmed balance

// Relations
$wallet->transactions()                // All transactions
$wallet->holds()                       // All holds
$wallet->owner()                       // Owner model

// Metadata
$wallet->getMeta(string $key, mixed $default): mixed
$wallet->setMeta(string $key, mixed $value): self
```

### Transaction Model Methods

```php
// Status checks
$transaction->isPending(): bool
$transaction->isConfirmed(): bool
$transaction->isFailed(): bool

// Status changes
$transaction->confirm(): self
$transaction->fail(string $reason): self
$transaction->cancel(): self

// Properties
$transaction->type              // deposit|withdraw|transfer|refund
$transaction->status            // pending|confirmed|failed|cancelled
$transaction->amount            // Decimal
$transaction->reference         // UUID or timestamp
$transaction->meta              // JSON metadata
```

### Hold Model Methods

```php
// Status checks
$hold->isActive(): bool

// Release or capture
$hold->release(): self          // Unlock funds
$hold->capture(): self          // Confirm debit

// Properties
$hold->amount                   // Amount held
$hold->status                   // active|released|captured
$hold->reference                // Unique ID
```

---

## 🎯 Advanced Examples

### E-Commerce Order Flow

```php
use MohabMamdouh\Wallet\Services\WalletService;

class OrderController
{
    public function __construct(private WalletService $walletService) {}
    
    public function checkout(Order $order, User $user)
    {
        // Get user's main wallet
        $wallet = $user->wallet('main');
        
        // Check balance
        if ($wallet->balance() < $order->total) {
            throw new InsufficientBalance('Insufficient funds');
        }
        
        // Create escrow wallet for order
        $escrow = $user->getOrCreateWallet(
            'order-' . $order->id,
            'Order #' . $order->id
        );
        
        // Hold funds in escrow
        $hold = $this->walletService->hold($wallet, $order->total);
        
        // Process payment
        $payment = processPaymentGateway($order);
        
        if ($payment->successful) {
            // Capture hold
            $hold->capture();
            
            // Transfer to merchant
            $merchant = $order->merchant;
            $this->walletService->transfer(
                $wallet,
                $merchant->wallet('main'),
                $order->total,
                'order-' . $order->id,
                ['order_id' => $order->id]
            );
            
            $order->status = 'confirmed';
            $order->save();
        } else {
            // Release hold
            $hold->release();
            
            throw new PaymentException('Payment failed');
        }
    }
}
```

### Referral Reward System

```php
class ReferralService
{
    public function __construct(private WalletService $walletService) {}
    
    public function rewardReferrer(User $referrer, User $referred, float $amount)
    {
        // Get rewards wallet
        $wallet = $referrer->getOrCreateWallet(
            'rewards',
            'Referral Rewards'
        );
        
        // Create deposit transaction (pending approval)
        $txn = $this->walletService->deposit($wallet, $amount, null, [
            'type' => 'referral',
            'referred_user_id' => $referred->id,
        ]);
        
        // After manual verification
        $txn->confirm();
    }
}
```

### Subscription Billing

```php
class SubscriptionManager
{
    public function __construct(private WalletService $walletService) {}
    
    public function chargeSubscription(Subscription $subscription)
    {
        $user = $subscription->user;
        $wallet = $user->wallet('main');
        
        // Attempt withdrawal
        try {
            $txn = $this->walletService->withdraw(
                $wallet,
                $subscription->amount,
                'sub-' . $subscription->id,
                ['subscription_id' => $subscription->id]
            );
            
            $txn->confirm();
            $subscription->last_charged_at = now();
            $subscription->save();
            
        } catch (InsufficientBalance $e) {
            // Retry with force withdraw (debt)
            $txn = $this->walletService->forceWithdraw(
                $wallet,
                $subscription->amount
            );
            
            notifyUserAboutDebt($user, $subscription->amount);
        }
    }
}
```

### Transaction Reporting

```php
class ReportingService
{
    public function userTransactionSummary(User $user, $days = 30)
    {
        $since = now()->subDays($days);
        
        $wallets = $user->getAllWallets();
        
        $summary = [];
        
        foreach ($wallets as $wallet) {
            $deposits = $wallet->transactions()
                ->where('type', 'deposit')
                ->where('status', 'confirmed')
                ->where('created_at', '>=', $since)
                ->sum('amount');
            
            $withdrawals = $wallet->transactions()
                ->where('type', 'withdraw')
                ->where('status', 'confirmed')
                ->where('created_at', '>=', $since)
                ->sum('amount');
            
            $transfers = $wallet->transactions()
                ->where('type', 'transfer')
                ->where('created_at', '>=', $since)
                ->sum('amount');
            
            $summary[$wallet->slug] = [
                'wallet_name' => $wallet->name,
                'current_balance' => $wallet->balance(),
                'deposits' => $deposits,
                'withdrawals' => $withdrawals,
                'transfers_out' => $transfers,
                'net_change' => $deposits - $withdrawals,
            ];
        }
        
        return $summary;
    }
}
```

---

## ⚠️ Error Handling

```php
use MohabMamdouh\Wallet\Exceptions\InsufficientBalance;

try {
    $txn = $service->withdraw($wallet, 1000);
} catch (InsufficientBalance $e) {
    return response()->json([
        'error' => 'Insufficient balance',
        'required' => 1000,
        'available' => $wallet->balance(),
    ], 422);
} catch (Exception $e) {
    return response()->json([
        'error' => $e->getMessage(),
    ], 500);
}
```

---

## 🧪 Testing

```php
use MohabMamdouh\Wallet\Services\WalletService;
use Tests\TestCase;

class WalletTest extends TestCase
{
    private WalletService $service;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WalletService::class);
    }
    
    public function test_deposit()
    {
        $user = User::factory()->create();
        $wallet = $user->wallet('main');
        
        $txn = $this->service->deposit($wallet, 100);
        
        $this->assertTrue($txn->isPending());
        $this->assertEquals(100, $wallet->refresh()->total_balance);
        
        $txn->confirm();
        
        $this->assertTrue($txn->isConfirmed());
        $this->assertEquals(100, $wallet->refresh()->available_balance);
    }
    
    public function test_insufficient_balance_withdrawal()
    {
        $user = User::factory()->create();
        $wallet = $user->wallet('main');
        
        $this->expectException(InsufficientBalance::class);
        
        $this->service->withdraw($wallet, 100);
    }
    
    public function test_transfer_between_wallets()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $wallet1 = $user1->wallet('main');
        $wallet2 = $user2->wallet('main');
        
        $this->service->deposit($wallet1, 100)->confirm();
        
        $this->service->transfer($wallet1, $wallet2, 50);
        
        $this->assertEquals(50, $wallet1->refresh()->available_balance);
        $this->assertEquals(50, $wallet2->refresh()->available_balance);
    }
}
```

---

## 📖 Database Schema

### Wallets Table
```
id, owner_type, owner_id, slug, name, currency, is_active,
total_balance, available_balance, held_balance, confirmed_balance,
meta, created_at, updated_at
```

### Wallet Transactions Table
```
id, wallet_id, type, status, amount, reference, related_wallet_id,
meta, confirmed_at, failed_at, cancelled_at, failure_reason, created_at, updated_at
```

### Wallet Holds Table
```
id, wallet_id, amount, status, reference, meta,
released_at, captured_at, created_at, updated_at
```

---

## 📝 License

MIT License - See LICENSE file for details

## 🤝 Support

For issues, questions, or contributions, please visit the repository.

**Happy coding! 🚀**