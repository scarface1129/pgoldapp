<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CryptoHolding;
use App\Services\CoinGeckoService;
use App\Services\TradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CryptoController extends Controller
{
    private CoinGeckoService $coinGeckoService;
    private TradingService $tradingService;

    public function __construct(CoinGeckoService $coinGeckoService, TradingService $tradingService)
    {
        $this->coinGeckoService = $coinGeckoService;
        $this->tradingService = $tradingService;
    }

    /**
     * Get all supported cryptocurrency prices.
     */
    public function prices(): JsonResponse
    {
        try {
            $prices = $this->coinGeckoService->getAllPrices();

            if (empty($prices)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to fetch prices. Please try again later.',
                ], 503);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'prices' => array_values($prices),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching prices: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get price for a specific cryptocurrency.
     */
    public function price(string $symbol): JsonResponse
    {
        $symbol = strtoupper($symbol);

        if (!CryptoHolding::isSupported($symbol)) {
            return response()->json([
                'success' => false,
                'message' => "Unsupported cryptocurrency: {$symbol}",
            ], 422);
        }

        try {
            $priceData = $this->coinGeckoService->getPrice($symbol);

            if (!$priceData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to fetch price. Please try again later.',
                ], 503);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'price' => $priceData,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching price: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's crypto portfolio.
     */
    public function portfolio(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $portfolio = $this->tradingService->getPortfolio($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'portfolio' => $portfolio,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching portfolio: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's holdings.
     */
    public function holdings(Request $request): JsonResponse
    {
        $user = $request->user();
        $holdings = $user->cryptoHoldings;

        return response()->json([
            'success' => true,
            'data' => [
                'holdings' => $holdings->map(function ($holding) {
                    return [
                        'symbol' => $holding->crypto_symbol,
                        'name' => $holding->crypto_name,
                        'balance' => (float) $holding->balance,
                    ];
                }),
            ],
        ]);
    }
}
