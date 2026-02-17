<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Trade extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reference',
        'type',
        'crypto_symbol',
        'crypto_amount',
        'rate',
        'subtotal',
        'fee_percentage',
        'fee_amount',
        'total_amount',
        'status',
        'rate_data',
        'failure_reason',
    ];

    protected $casts = [
        'crypto_amount' => 'decimal:8',
        'rate' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'fee_percentage' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'rate_data' => 'array',
    ];

    /**
     * Get the user that owns the trade.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get wallet transactions related to this trade.
     */
    public function walletTransactions(): MorphMany
    {
        return $this->morphMany(WalletTransaction::class, 'sourceable');
    }

    /**
     * Scope for buy trades.
     */
    public function scopeBuys($query)
    {
        return $query->where('type', 'buy');
    }

    /**
     * Scope for sell trades.
     */
    public function scopeSells($query)
    {
        return $query->where('type', 'sell');
    }

    /**
     * Scope for completed trades.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for pending trades.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Generate a unique reference.
     */
    public static function generateReference(): string
    {
        return 'TRD-' . strtoupper(uniqid()) . '-' . time();
    }

    /**
     * Mark trade as completed.
     */
    public function markAsCompleted(): void
    {
        $this->status = 'completed';
        $this->save();
    }

    /**
     * Mark trade as failed.
     */
    public function markAsFailed(string $reason): void
    {
        $this->status = 'failed';
        $this->failure_reason = $reason;
        $this->save();
    }

    /**
     * Check if trade is a buy.
     */
    public function isBuy(): bool
    {
        return $this->type === 'buy';
    }

    /**
     * Check if trade is a sell.
     */
    public function isSell(): bool
    {
        return $this->type === 'sell';
    }
}
