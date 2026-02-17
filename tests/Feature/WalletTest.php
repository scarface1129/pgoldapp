<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        
        // Create wallet for user
        Wallet::factory()->withBalance(100000)->create([
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_can_view_wallet()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/wallet');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'wallet' => [
                        'id',
                        'balance',
                        'formatted_balance',
                        'currency',
                        'is_active',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'wallet' => [
                        'balance' => 100000,
                        'currency' => 'NGN',
                    ],
                ],
            ]);
    }

    public function test_user_can_deposit_funds()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/wallet/deposit', [
                'amount' => 50000,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'transaction' => [
                        'reference',
                        'type',
                        'amount',
                        'balance_before',
                        'balance_after',
                    ],
                    'wallet' => [
                        'balance',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Deposit successful',
                'data' => [
                    'transaction' => [
                        'type' => 'credit',
                        'amount' => 50000,
                        'balance_before' => 100000,
                        'balance_after' => 150000,
                    ],
                ],
            ]);

        // Verify wallet balance updated
        $this->assertEquals(150000, $this->user->wallet->fresh()->balance);
    }

    public function test_user_cannot_deposit_below_minimum()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/wallet/deposit', [
                'amount' => 50,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_user_can_withdraw_funds()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/wallet/withdraw', [
                'amount' => 30000,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'transaction',
                    'wallet',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Withdrawal successful',
                'data' => [
                    'transaction' => [
                        'type' => 'debit',
                        'amount' => 30000,
                        'balance_before' => 100000,
                        'balance_after' => 70000,
                    ],
                ],
            ]);
    }

    public function test_user_cannot_withdraw_more_than_balance()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/wallet/withdraw', [
                'amount' => 500000,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient balance',
            ]);
    }

    public function test_user_can_view_transactions()
    {
        // Create some transactions first
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/wallet/deposit', ['amount' => 10000]);
        
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/wallet/withdraw', ['amount' => 5000]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/wallet/transactions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'transactions',
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('data.transactions'));
    }

    public function test_user_can_filter_transactions_by_type()
    {
        // Create some transactions
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/wallet/deposit', ['amount' => 10000]);
        
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/wallet/deposit', ['amount' => 20000]);
        
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/wallet/withdraw', ['amount' => 5000]);

        // Filter credits only
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/wallet/transactions?type=credit');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.transactions'));

        // Filter debits only
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/wallet/transactions?type=debit');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.transactions'));
    }
}
