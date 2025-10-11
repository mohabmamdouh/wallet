<?php

namespace MohabMamdouh\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $table = 'wallet_transactions';
    protected $guarded = [];
    protected $casts = [
        'amount' => 'decimal:8',
        'meta' => 'array',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    const TYPE_DEPOSIT = 'deposit';
    const TYPE_WITHDRAW = 'withdraw';
    const TYPE_TRANSFER = 'transfer';
    const TYPE_REFUND = 'refund';

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function confirm(): self
    {
        $this->update(['status' => self::STATUS_CONFIRMED, 'confirmed_at' => now()]);
        return $this;
    }

    public function fail(string $reason = null): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);
        return $this;
    }

    public function cancel(): self
    {
        $this->update(['status' => self::STATUS_CANCELLED, 'cancelled_at' => now()]);
        return $this;
    }
}