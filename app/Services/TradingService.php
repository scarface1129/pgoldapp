<?php

namespace App\Services;

use App\Models\CryptoHolding;
use App\Models\FeeSetting;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TradingService
{
    private CoinGeckoService $coinGeckoService;
    private WalletService $walletService;

    public function __construct(CoinGeckoService $coinGeckoService, WalletService $walletService)
    {
        $this->coinGeckoService = $coinGeckoService;
        $this->walletService = $walletService;
    }

    /**
     * Buy cryptocurrency with Naira.
     * 
     * @param User $user
     * @param string $symbol Crypto symbol (BTC, ETH, USDT)
     * @param float $ngnAmount Amount in NGN to spend
     * @return Trade
     */
    public function buyCrypto(User $user, string $symbol, float $ngnAmount): Trade
    {
        $symbol = strtoupper($symbol);

        // Validate crypto symbol
        if (!CryptoHolding::isSupported($symbol)) {
            throw new \InvalidArgumentException("Unsupported cryptocurrency: {$symbol}");
        }

        // Get buy fee settings
        $feeSetting = FeeSetting::getBuyFee();
        if (!$feeSetting) {
            throw new \Exception('Buy fee configuration not found');
        }

        // Check minimum transaction amount
        if (!$feeSetting->meetsMinimum($ngnAmount)) {
            throw new \InvalidArgumentException(
                "Minimum transaction amount is ₦" . number_format($feeSetting->minimum_amount, 2)
            );
        }

        // Check wallet balance
        if (!$this->walletService->hasSufficientBalance($user, $ngnAmount)) {
            throw new \Exception('Insufficient wallet balance');
        }

        // Get current rate from CoinGecko
        $priceData = $this->coinGeckoService->getPrice($symbol);
        if (!$priceData) {
            throw new \Exception('Unable to fetch current exchange rate. Please try again later.');
        }

        $rate = $priceData['price_ngn'];

        return DB::transaction(function () use ($user, $symbol, $ngnAmount, $rate, $feeSetting, $priceData) {
            // Calculate amounts
            // For buying: user pays ngnAmount, fee is deducted from it, rest goes to crypto
            $feeAmount = $feeSetting->calculateFee($ngnAmount);
            $amountAfterFee = $ngnAmount - $feeAmount; // Amount used to buy crypto
            $cryptoAmount = $amountAfterFee / $rate;

            // Create the trade record
            $trade = Trade::create([
                'user_id' => $user->id,
                'reference' => Trade::generateReference(),
                'type' => 'buy',
                'crypto_symbol' => $symbol,
                'crypto_amount' => $cryptoAmount,
                'rate' => $rate,
                'subtotal' => $amountAfterFee,
                'fee_percentage' => $feeSetting->percentage,
                'fee_amount' => $feeAmount,
                'total_amount' => $ngnAmount, // Total amount debited from wallet
                'status' => 'pending',
                'rate_data' => $priceData,
            ]);

            try {
                // Debit wallet
                $this->walletService->debitForTrade($user, $ngnAmount, $trade);

                // Credit crypto holding
                $cryptoHolding = $user->getOrCreateCryptoHolding($symbol);
                $cryptoHolding->addBalance($cryptoAmount);

                // Mark trade as completed
                $trade->markAsCompleted();

                Log::info("Buy trade completed", [
                    'user_id' => $user->id,
                    'trade_ref' => $trade->reference,
                    'symbol' => $symbol,
                    'crypto_amount' => $cryptoAmount,
                    'ngn_amount' => $ngnAmount,
                ]);

                return $trade->fresh();
            } catch (\Exception $e) {
                $trade->markAsFailed($e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Sell cryptocurrency for Naira.
     * 
     * @param User $user
     * @param string $symbol Crypto symbol (BTC, ETH, USDT)
     * @param float $cryptoAmount Amount of crypto to sell
     * @return Trade
     */
    public function sellCrypto(User $user, string $symbol, float $cryptoAmount): Trade
    {
        $symbol = strtoupper($symbol);

        // Validate crypto symbol
        if (!CryptoHolding::isSupported($symbol)) {
            throw new \InvalidArgumentException("Unsupported cryptocurrency: {$symbol}");
        }

        // Get sell fee settings
        $feeSetting = FeeSetting::getSellFee();
        if (!$feeSetting) {
            throw new \Exception('Sell fee configuration not found');
        }

        // Check crypto holding balance
        $cryptoHolding = $user->getCryptoHolding($symbol);
        if (!$cryptoHolding || !$cryptoHolding->hasSufficientBalance($cryptoAmount)) {
            throw new \Exception("Insufficient {$symbol} balance");
        }

        // Get current rate from CoinGecko
        $priceData = $this->coinGeckoService->getPrice($symbol);
        if (!$priceData) {
            throw new \Exception('Unable to fetch current exchange rate. Please try again later.');
        }

        $rate = $priceData['price_ngn'];
        $subtotal = $cryptoAmount * $rate;

        // Check minimum transaction amount (based on NGN value)
        if (!$feeSetting->meetsMinimum($subtotal)) {
            throw new \InvalidArgumentException(
                "Minimum transaction value is ₦" . number_format($feeSetting->minimum_amount, 2)
            );
        }

        return DB::transaction(function () use ($user, $symbol, $cryptoAmount, $rate, $subtotal, $feeSetting, $priceData, $cryptoHolding) {
            // Calculate amounts
            // For selling: user sells crypto, gets NGN minus fee
            $feeAmount = $feeSetting->calculateFee($subtotal);
            $amountAfterFee = $subtotal - $feeAmount; // Amount credited to wallet

            // Create the trade record
            $trade = Trade::create([
                'user_id' => $user->id,
                'reference' => Trade::generateReference(),
                'type' => 'sell',
                'crypto_symbol' => $symbol,
                'crypto_amount' => $cryptoAmount,
                'rate' => $rate,
                'subtotal' => $subtotal,
                'fee_percentage' => $feeSetting->percentage,
                'fee_amount' => $feeAmount,
                'total_amount' => $amountAfterFee, // Total amount credited to wallet
                'status' => 'pending',
                'rate_data' => $priceData,
            ]);

            try {
                // Debit crypto holding
                $cryptoHolding->subtractBalance($cryptoAmount);

                // Credit wallet
                $this->walletService->creditForTrade($user, $amountAfterFee, $trade);

                // Mark trade as completed
                $trade->markAsCompleted();

                Log::info("Sell trade completed", [
                    'user_id' => $user->id,
                    'trade_ref' => $trade->reference,
                    'symbol' => $symbol,
                    'crypto_amount' => $cryptoAmount,
                    'ngn_amount' => $amountAfterFee,
                ]);

                return $trade->fresh();
            } catch (\Exception $e) {
                $trade->markAsFailed($e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Get trade quote (preview) without executing.
     */
    public function getQuote(string $type, string $symbol, float $amount): array
    {
        $symbol = strtoupper($symbol);
        $type = strtolower($type);

        if (!CryptoHolding::isSupported($symbol)) {
            throw new \InvalidArgumentException("Unsupported cryptocurrency: {$symbol}");
        }

        $feeSetting = $type === 'buy' ? FeeSetting::getBuyFee() : FeeSetting::getSellFee();
        if (!$feeSetting) {
            throw new \Exception('Fee configuration not found');
        }

        $priceData = $this->coinGeckoService->getPrice($symbol);
        if (!$priceData) {
            throw new \Exception('Unable to fetch current exchange rate');
        }

        $rate = $priceData['price_ngn'];

        if ($type === 'buy') {
            // amount is in NGN
            $ngnAmount = $amount;
            $feeAmount = $feeSetting->calculateFee($ngnAmount);
            $amountAfterFee = $ngnAmount - $feeAmount;
            $cryptoAmount = $amountAfterFee / $rate;

            return [
                'type' => 'buy',
                'crypto_symbol' => $symbol,
                'ngn_amount' => $ngnAmount,
                'fee_percentage' => $feeSetting->percentage,
                'fee_amount' => round($feeAmount, 2),
                'crypto_amount' => $cryptoAmount,
                'rate' => $rate,
                'minimum_amount' => $feeSetting->minimum_amount,
                'rate_data' => $priceData,
            ];
        } else {
            // amount is in crypto
            $cryptoAmount = $amount;
            $subtotal = $cryptoAmount * $rate;
            $feeAmount = $feeSetting->calculateFee($subtotal);
            $amountAfterFee = $subtotal - $feeAmount;

            return [
                'type' => 'sell',
                'crypto_symbol' => $symbol,
                'crypto_amount' => $cryptoAmount,
                'fee_percentage' => $feeSetting->percentage,
                'fee_amount' => round($feeAmount, 2),
                'ngn_amount' => round($amountAfterFee, 2),
                'subtotal' => round($subtotal, 2),
                'rate' => $rate,
                'minimum_amount' => $feeSetting->minimum_amount,
                'rate_data' => $priceData,
            ];
        }
    }

    /**
     * Get user's crypto portfolio.
     */
    public function getPortfolio(User $user): array
    {
        $holdings = $user->cryptoHoldings;
        $prices = $this->coinGeckoService->getAllPrices();
        
        $portfolio = [];
        $totalValue = 0;

        foreach ($holdings as $holding) {
            $price = $prices[$holding->crypto_symbol]['price_ngn'] ?? 0;
            $value = $holding->balance * $price;
            $totalValue += $value;

            $portfolio[] = [
                'symbol' => $holding->crypto_symbol,
                'name' => $holding->crypto_name,
                'balance' => (float) $holding->balance,
                'current_price_ngn' => $price,
                'value_ngn' => round($value, 2),
            ];
        }

        return [
            'holdings' => $portfolio,
            'total_value_ngn' => round($totalValue, 2),
        ];
    }
}
