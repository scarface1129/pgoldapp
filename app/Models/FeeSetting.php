<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'percentage',
        'minimum_amount',
        'is_active',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Fee setting names.
     */
    public const BUY_FEE = 'buy_fee';
    public const SELL_FEE = 'sell_fee';

    /**
     * Get a fee setting by name.
     */
    public static function getByName(string $name): ?self
    {
        return self::where('name', $name)->where('is_active', true)->first();
    }

    /**
     * Get the buy fee setting.
     */
    public static function getBuyFee(): ?self
    {
        return self::getByName(self::BUY_FEE);
    }

    /**
     * Get the sell fee setting.
     */
    public static function getSellFee(): ?self
    {
        return self::getByName(self::SELL_FEE);
    }

    /**
     * Calculate fee amount based on the subtotal.
     */
    public function calculateFee(float $subtotal): float
    {
        return round($subtotal * ($this->percentage / 100), 2);
    }

    /**
     * Check if the amount meets the minimum requirement.
     */
    public function meetsMinimum(float $amount): bool
    {
        return $amount >= $this->minimum_amount;
    }
}
