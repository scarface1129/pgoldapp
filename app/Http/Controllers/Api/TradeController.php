<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CryptoHolding;
use App\Models\Trade;
use App\Services\TradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    private TradingService $tradingService;

    public function __construct(TradingService $tradingService)
    {
        $this->tradingService = $tradingService;
    }

    /**
     * Buy cryptocurrency with Naira.
     */
    public function buy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'in:BTC,ETH,USDT,btc,eth,usdt'],
            'amount_in_naira' => ['required', 'numeric', 'min:1000'],
        ]);

        $user = $request->user();

        try {
            $trade = $this->tradingService->buyCrypto(
                $user,
                $validated['symbol'],
                $validated['amount_in_naira']
            );

            return response()->json([
                'success' => true,
                'message' => 'Buy order completed successfully',
                'data' => [
                    'trade' => $this->formatTrade($trade),
                ],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Sell cryptocurrency for Naira.
     */
    public function sell(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'in:BTC,ETH,USDT,btc,eth,usdt'],
            'amount' => ['required', 'numeric', 'min:0.00000001'],
        ]);

        $user = $request->user();

        try {
            $trade = $this->tradingService->sellCrypto(
                $user,
                $validated['symbol'],
                $validated['amount']
            );

            return response()->json([
                'success' => true,
                'message' => 'Sell order completed successfully',
                'data' => [
                    'trade' => $this->formatTrade($trade),
                ],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get trade quote without executing.
     */
    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:buy,sell'],
            'symbol' => ['required', 'string', 'in:BTC,ETH,USDT,btc,eth,usdt'],
            'amount' => ['required', 'numeric', 'min:0.00000001'],
        ]);

        try {
            $quote = $this->tradingService->getQuote(
                $validated['type'],
                $validated['symbol'],
                $validated['amount']
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'quote' => $quote,
                    'note' => 'This is an estimate. Actual rates may vary at time of execution.',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get trade history with filtering and pagination.
     */
    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:buy,sell'],
            'symbol' => ['nullable', 'string', 'in:BTC,ETH,USDT,btc,eth,usdt'],
            'status' => ['nullable', 'in:pending,completed,failed,cancelled'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();

        $query = Trade::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (!empty($validated['symbol'])) {
            $query->where('crypto_symbol', strtoupper($validated['symbol']));
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        $perPage = $validated['per_page'] ?? 15;
        $trades = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'trades' => $trades->map(fn($trade) => $this->formatTrade($trade)),
                'pagination' => [
                    'current_page' => $trades->currentPage(),
                    'last_page' => $trades->lastPage(),
                    'per_page' => $trades->perPage(),
                    'total' => $trades->total(),
                ],
            ],
        ]);
    }

    /**
     * Get a specific trade by reference.
     */
    public function show(Request $request, string $reference): JsonResponse
    {
        $user = $request->user();

        $trade = Trade::where('user_id', $user->id)
            ->where('reference', $reference)
            ->first();

        if (!$trade) {
            return response()->json([
                'success' => false,
                'message' => 'Trade not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'trade' => $this->formatTrade($trade),
            ],
        ]);
    }

    /**
     * Get supported cryptocurrencies.
     */
    public function supportedCryptos(): JsonResponse
    {
        $cryptos = collect(CryptoHolding::SUPPORTED_CRYPTOS)->map(function ($crypto, $symbol) {
            return [
                'symbol' => $symbol,
                'name' => $crypto['name'],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'supported_cryptocurrencies' => $cryptos,
            ],
        ]);
    }

    /**
     * Format trade for response.
     */
    private function formatTrade(Trade $trade): array
    {
        return [
            'reference' => $trade->reference,
            'type' => $trade->type,
            'crypto_symbol' => $trade->crypto_symbol,
            'crypto_amount' => (float) $trade->crypto_amount,
            'rate' => (float) $trade->rate,
            'subtotal' => (float) $trade->subtotal,
            'fee_percentage' => (float) $trade->fee_percentage,
            'fee_amount' => (float) $trade->fee_amount,
            'total_amount' => (float) $trade->total_amount,
            'status' => $trade->status,
            'created_at' => $trade->created_at,
            'formatted' => [
                'rate' => '₦' . number_format($trade->rate, 2),
                'subtotal' => '₦' . number_format($trade->subtotal, 2),
                'fee_amount' => '₦' . number_format($trade->fee_amount, 2),
                'total_amount' => '₦' . number_format($trade->total_amount, 2),
            ],
        ];
    }
}
