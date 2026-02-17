<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    private WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Get wallet balance and details.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $this->walletService->getOrCreateWallet($user);

        return response()->json([
            'success' => true,
            'data' => [
                'wallet' => [
                    'id' => $wallet->id,
                    'balance' => (float) $wallet->balance,
                    'formatted_balance' => '₦' . number_format($wallet->balance, 2),
                    'currency' => $wallet->currency,
                    'is_active' => $wallet->is_active,
                ],
            ],
        ]);
    }

    /**
     * Deposit funds to wallet (for testing purposes).
     */
    public function deposit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:100', 'max:10000000'],
        ]);

        $user = $request->user();

        try {
            $transaction = $this->walletService->deposit(
                $user,
                $validated['amount'],
                'Manual deposit',
                ['source' => 'manual_deposit']
            );

            $wallet = $this->walletService->getOrCreateWallet($user);

            return response()->json([
                'success' => true,
                'message' => 'Deposit successful',
                'data' => [
                    'transaction' => [
                        'reference' => $transaction->reference,
                        'type' => $transaction->type,
                        'amount' => (float) $transaction->amount,
                        'balance_before' => (float) $transaction->balance_before,
                        'balance_after' => (float) $transaction->balance_after,
                        'description' => $transaction->description,
                        'created_at' => $transaction->created_at,
                    ],
                    'wallet' => [
                        'balance' => (float) $wallet->balance,
                        'formatted_balance' => '₦' . number_format($wallet->balance, 2),
                    ],
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Withdraw funds from wallet (for testing purposes).
     */
    public function withdraw(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:100', 'max:10000000'],
        ]);

        $user = $request->user();

        try {
            $transaction = $this->walletService->withdraw(
                $user,
                $validated['amount'],
                'Manual withdrawal',
                ['destination' => 'bank_transfer']
            );

            $wallet = $this->walletService->getOrCreateWallet($user);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal successful',
                'data' => [
                    'transaction' => [
                        'reference' => $transaction->reference,
                        'type' => $transaction->type,
                        'amount' => (float) $transaction->amount,
                        'balance_before' => (float) $transaction->balance_before,
                        'balance_after' => (float) $transaction->balance_after,
                        'description' => $transaction->description,
                        'created_at' => $transaction->created_at,
                    ],
                    'wallet' => [
                        'balance' => (float) $wallet->balance,
                        'formatted_balance' => '₦' . number_format($wallet->balance, 2),
                    ],
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
     * Get wallet transactions with filtering and pagination.
     */
    public function transactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:credit,debit'],
            'source' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $wallet = $this->walletService->getOrCreateWallet($user);

        $query = WalletTransaction::where('wallet_id', $wallet->id)
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (!empty($validated['source'])) {
            $query->where('source', $validated['source']);
        }

        if (!empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        $perPage = $validated['per_page'] ?? 15;
        $transactions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'reference' => $transaction->reference,
                        'type' => $transaction->type,
                        'amount' => (float) $transaction->amount,
                        'balance_before' => (float) $transaction->balance_before,
                        'balance_after' => (float) $transaction->balance_after,
                        'description' => $transaction->description,
                        'source' => $transaction->source,
                        'created_at' => $transaction->created_at,
                    ];
                }),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ],
            ],
        ]);
    }
}
