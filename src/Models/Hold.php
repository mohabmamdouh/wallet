<?php

namespace MohabMamdouh\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hold extends Model
{
    protected $table = 'wallet_holds';
    protected $guarded = [];
    protected $casts = [
        'amount' => 'decimal:8',
        'meta' => 'array',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_RELEASED = 'released';
    const STATUS_CAPTURED = 'captured';

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function release(): self
    {
        $this->wallet->incrementBalance($this->amount, 'held');
        $this->wallet->decrementBalance($this->amount, 'held');
        $this->update(['status' => self::STATUS_RELEASED, 'released_at' => now()]);
        return $this;
    }

    public function capture(): self
    {
        $this->wallet->decrementBalance($this->amount, 'held');
        $this->update(['status' => self::STATUS_CAPTURED, 'captured_at' => now()]);
        return $this;
    }
}
