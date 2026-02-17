<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's Naira wallet.
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class)->where('currency', 'NGN');
    }

    /**
     * Get all wallets for the user.
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    /**
     * Get the user's crypto holdings.
     */
    public function cryptoHoldings(): HasMany
    {
        return $this->hasMany(CryptoHolding::class);
    }

    /**
     * Get a specific crypto holding by symbol.
     */
    public function getCryptoHolding(string $symbol): ?CryptoHolding
    {
        return $this->cryptoHoldings()->where('crypto_symbol', strtoupper($symbol))->first();
    }

    /**
     * Get or create a crypto holding.
     */
    public function getOrCreateCryptoHolding(string $symbol): CryptoHolding
    {
        $cryptoInfo = CryptoHolding::getCryptoInfo($symbol);
        
        return $this->cryptoHoldings()->firstOrCreate(
            ['crypto_symbol' => strtoupper($symbol)],
            ['crypto_name' => $cryptoInfo['name'] ?? $symbol]
        );
    }

    /**
     * Get all trades for the user.
     */
    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    /**
     * Get all wallet transactions for the user.
     */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
