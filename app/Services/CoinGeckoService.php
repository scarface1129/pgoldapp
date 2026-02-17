<?php

namespace App\Services;

use App\Models\CryptoHolding;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CoinGeckoService
{
    private string $baseUrl;
    private int $cacheTtl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.coingecko.base_url', 'https://api.coingecko.com/api/v3');
        $this->cacheTtl = config('services.coingecko.cache_ttl', 60); // 60 seconds cache
        $this->timeout = config('services.coingecko.timeout', 10); // 10 seconds timeout
    }

    /**
     * Get the current price of a cryptocurrency in NGN.
     */
    public function getPrice(string $symbol): ?array
    {
        $coinId = CryptoHolding::getCoinGeckoId($symbol);
        
        if (!$coinId) {
            Log::warning("Unsupported crypto symbol: {$symbol}");
            return null;
        }

        $cacheKey = "coingecko_price_{$coinId}_ngn";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($coinId, $symbol) {
            try {
                $response = Http::timeout($this->timeout)
                    ->get("{$this->baseUrl}/simple/price", [
                        'ids' => $coinId,
                        'vs_currencies' => 'ngn',
                        'include_last_updated_at' => 'true',
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (isset($data[$coinId]['ngn'])) {
                        return [
                            'symbol' => strtoupper($symbol),
                            'coin_id' => $coinId,
                            'price_ngn' => $data[$coinId]['ngn'],
                            'last_updated_at' => $data[$coinId]['last_updated_at'] ?? now()->timestamp,
                            'fetched_at' => now()->toIso8601String(),
                        ];
                    }
                }

                Log::error("Failed to fetch price for {$coinId}", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            } catch (\Exception $e) {
                Log::error("CoinGecko API error: {$e->getMessage()}");
                return null;
            }
        });
    }

    /**
     * Get prices for all supported cryptocurrencies.
     */
    public function getAllPrices(): array
    {
        $cacheKey = 'coingecko_all_prices_ngn';

        return Cache::remember($cacheKey, $this->cacheTtl, function () {
            try {
                $coinIds = collect(CryptoHolding::SUPPORTED_CRYPTOS)
                    ->pluck('id')
                    ->implode(',');

                $response = Http::timeout($this->timeout)
                    ->get("{$this->baseUrl}/simple/price", [
                        'ids' => $coinIds,
                        'vs_currencies' => 'ngn',
                        'include_last_updated_at' => 'true',
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $prices = [];

                    foreach (CryptoHolding::SUPPORTED_CRYPTOS as $symbol => $crypto) {
                        $coinId = $crypto['id'];
                        if (isset($data[$coinId]['ngn'])) {
                            $prices[$symbol] = [
                                'symbol' => $symbol,
                                'name' => $crypto['name'],
                                'coin_id' => $coinId,
                                'price_ngn' => $data[$coinId]['ngn'],
                                'last_updated_at' => $data[$coinId]['last_updated_at'] ?? now()->timestamp,
                                'fetched_at' => now()->toIso8601String(),
                            ];
                        }
                    }

                    return $prices;
                }

                Log::error("Failed to fetch all prices", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error("CoinGecko API error: {$e->getMessage()}");
                return [];
            }
        });
    }

    /**
     * Get the rate for a specific crypto in NGN.
     * Returns the amount of NGN for 1 unit of crypto.
     */
    public function getRate(string $symbol): ?float
    {
        $priceData = $this->getPrice($symbol);
        
        return $priceData['price_ngn'] ?? null;
    }

    /**
     * Calculate how much crypto you can buy with a given NGN amount.
     */
    public function calculateCryptoAmount(string $symbol, float $ngnAmount): ?float
    {
        $rate = $this->getRate($symbol);
        
        if (!$rate || $rate <= 0) {
            return null;
        }

        return $ngnAmount / $rate;
    }

    /**
     * Calculate how much NGN you get for a given crypto amount.
     */
    public function calculateNgnAmount(string $symbol, float $cryptoAmount): ?float
    {
        $rate = $this->getRate($symbol);
        
        if (!$rate) {
            return null;
        }

        return $cryptoAmount * $rate;
    }

    /**
     * Clear the price cache.
     */
    public function clearCache(): void
    {
        Cache::forget('coingecko_all_prices_ngn');
        
        foreach (CryptoHolding::SUPPORTED_CRYPTOS as $crypto) {
            Cache::forget("coingecko_price_{$crypto['id']}_ngn");
        }
    }

    /**
     * Check if the API is available.
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/ping");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
