<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Create a wallet for a user.
     */
    public function createWallet(User $user, string $currency = 'NGN'): Wallet
    {
        return Wallet::create([
            'user_id' => $user->id,
            'currency' => $currency,
            'balance' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * Get or create the user's Naira wallet.
     */
    public function getOrCreateWallet(User $user): Wallet
    {
        $wallet = $user->wallet;

        if (!$wallet) {
            $wallet = $this->createWallet($user);
        }

        return $wallet;
    }

    /**
     * Deposit funds to wallet.
     */
    public function deposit(User $user, float $amount, string $description = 'Deposit', array $metadata = []): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $description, $metadata) {
            $wallet = $this->getOrCreateWallet($user);
            $balanceBefore = $wallet->balance;
            
            $wallet->credit($amount);
            
            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'reference' => WalletTransaction::generateReference(),
                'type' => 'credit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'description' => $description,
                'source' => 'deposit',
                'sourceable_type' => null,
                'sourceable_id' => null,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Withdraw funds from wallet.
     */
    public function withdraw(User $user, float $amount, string $description = 'Withdrawal', array $metadata = []): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $description, $metadata) {
            $wallet = $this->getOrCreateWallet($user);
            
            if (!$wallet->hasSufficientBalance($amount)) {
                throw new \Exception('Insufficient balance');
            }

            $balanceBefore = $wallet->balance;
            $wallet->debit($amount);
            
            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'reference' => WalletTransaction::generateReference(),
                'type' => 'debit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'description' => $description,
                'source' => 'withdrawal',
                'sourceable_type' => null,
                'sourceable_id' => null,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Debit wallet for a trade (buying crypto).
     */
    public function debitForTrade(User $user, float $amount, $trade, string $description = null): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $trade, $description) {
            $wallet = $this->getOrCreateWallet($user);
            
            if (!$wallet->hasSufficientBalance($amount)) {
                throw new \Exception('Insufficient balance');
            }

            $balanceBefore = $wallet->balance;
            $wallet->debit($amount);
            
            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'reference' => WalletTransaction::generateReference(),
                'type' => 'debit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'description' => $description ?? "Buy {$trade->crypto_symbol}",
                'source' => 'trade_buy',
                'sourceable_type' => get_class($trade),
                'sourceable_id' => $trade->id,
                'metadata' => [
                    'trade_reference' => $trade->reference,
                    'crypto_symbol' => $trade->crypto_symbol,
                    'crypto_amount' => $trade->crypto_amount,
                ],
            ]);
        });
    }

    /**
     * Credit wallet for a trade (selling crypto).
     */
    public function creditForTrade(User $user, float $amount, $trade, string $description = null): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $trade, $description) {
            $wallet = $this->getOrCreateWallet($user);
            $balanceBefore = $wallet->balance;
            
            $wallet->credit($amount);
            
            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'reference' => WalletTransaction::generateReference(),
                'type' => 'credit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'description' => $description ?? "Sell {$trade->crypto_symbol}",
                'source' => 'trade_sell',
                'sourceable_type' => get_class($trade),
                'sourceable_id' => $trade->id,
                'metadata' => [
                    'trade_reference' => $trade->reference,
                    'crypto_symbol' => $trade->crypto_symbol,
                    'crypto_amount' => $trade->crypto_amount,
                ],
            ]);
        });
    }

    /**
     * Get wallet balance.
     */
    public function getBalance(User $user): float
    {
        $wallet = $this->getOrCreateWallet($user);
        return (float) $wallet->balance;
    }

    /**
     * Check if user has sufficient balance.
     */
    public function hasSufficientBalance(User $user, float $amount): bool
    {
        $wallet = $this->getOrCreateWallet($user);
        return $wallet->hasSufficientBalance($amount);
    }
}
