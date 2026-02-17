<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CryptoHolding extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'crypto_symbol',
        'crypto_name',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:8',
    ];

    /**
     * Supported cryptocurrencies with their CoinGecko IDs.
     */
    public const SUPPORTED_CRYPTOS = [
        'BTC' => [
            'id' => 'bitcoin',
            'name' => 'Bitcoin',
            'symbol' => 'BTC',
        ],
        'ETH' => [
            'id' => 'ethereum',
            'name' => 'Ethereum',
            'symbol' => 'ETH',
        ],
        'USDT' => [
            'id' => 'tether',
            'name' => 'Tether',
            'symbol' => 'USDT',
        ],
    ];

    /**
     * Get the user that owns the holding.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Add to the balance.
     */
    public function addBalance(float $amount): float
    {
        $this->balance += $amount;
        $this->save();
        
        return $this->balance;
    }

    /**
     * Subtract from the balance.
     */
    public function subtractBalance(float $amount): float
    {
        if ($this->balance < $amount) {
            throw new \Exception('Insufficient crypto balance');
        }
        
        $this->balance -= $amount;
        $this->save();
        
        return $this->balance;
    }

    /**
     * Check if has sufficient balance.
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * Get CoinGecko ID for a symbol.
     */
    public static function getCoinGeckoId(string $symbol): ?string
    {
        return self::SUPPORTED_CRYPTOS[strtoupper($symbol)]['id'] ?? null;
    }

    /**
     * Check if a crypto symbol is supported.
     */
    public static function isSupported(string $symbol): bool
    {
        return array_key_exists(strtoupper($symbol), self::SUPPORTED_CRYPTOS);
    }

    /**
     * Get crypto info by symbol.
     */
    public static function getCryptoInfo(string $symbol): ?array
    {
        return self::SUPPORTED_CRYPTOS[strtoupper($symbol)] ?? null;
    }
}
