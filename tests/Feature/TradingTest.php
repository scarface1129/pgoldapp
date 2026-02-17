<?php

namespace Tests\Feature;

use App\Models\CryptoHolding;
use App\Models\FeeSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CoinGeckoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TradingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        
        // Create wallet with sufficient balance
        Wallet::factory()->withBalance(1000000)->create([
            'user_id' => $this->user->id,
        ]);

        // Create fee settings
        FeeSetting::create([
            'name' => FeeSetting::BUY_FEE,
            'description' => 'Buy fee',
            'percentage' => 1.5,
            'minimum_amount' => 1000,
            'is_active' => true,
        ]);

        FeeSetting::create([
            'name' => FeeSetting::SELL_FEE,
            'description' => 'Sell fee',
            'percentage' => 1.5,
            'minimum_amount' => 1000,
            'is_active' => true,
        ]);
    }

    public function test_user_can_get_supported_cryptocurrencies()
    {
        $response = $this->getJson('/api/v1/crypto/supported');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'supported_cryptocurrencies',
                ],
            ]);

        $cryptos = $response->json('data.supported_cryptocurrencies');
        $this->assertCount(3, $cryptos);
        
        $symbols = collect($cryptos)->pluck('symbol')->toArray();
        $this->assertContains('BTC', $symbols);
        $this->assertContains('ETH', $symbols);
        $this->assertContains('USDT', $symbols);
    }

    public function test_user_can_buy_crypto()
    {
        // Mock CoinGecko service
        $mockCoinGecko = Mockery::mock(CoinGeckoService::class);
        $mockCoinGecko->shouldReceive('getPrice')
            ->with('BTC')
            ->andReturn([
                'symbol' => 'BTC',
                'coin_id' => 'bitcoin',
                'price_ngn' => 50000000, // ₦50 million per BTC
                'last_updated_at' => time(),
                'fetched_at' => now()->toIso8601String(),
            ]);

        $this->app->instance(CoinGeckoService::class, $mockCoinGecko);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/trade/buy', [
                'symbol' => 'BTC',
                'amount' => 100000, // ₦100,000
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'trade' => [
                        'reference',
                        'type',
                        'crypto_symbol',
                        'crypto_amount',
                        'rate',
                        'subtotal',
                        'fee_percentage',
                        'fee_amount',
                        'total_amount',
                        'status',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Buy order completed successfully',
                'data' => [
                    'trade' => [
                        'type' => 'buy',
                        'crypto_symbol' => 'BTC',
                        'status' => 'completed',
                    ],
                ],
            ]);

        // Verify wallet was debited
        $wallet = $this->user->wallet->fresh();
        $this->assertEquals(900000, $wallet->balance);

        // Verify crypto was credited
        $holding = $this->user->getCryptoHolding('BTC');
        $this->assertNotNull($holding);
        $this->assertGreaterThan(0, $holding->balance);
    }

    public function test_user_cannot_buy_with_insufficient_balance()
    {
        // Mock CoinGecko service
        $mockCoinGecko = Mockery::mock(CoinGeckoService::class);
        $mockCoinGecko->shouldReceive('getPrice')
            ->with('BTC')
            ->andReturn([
                'symbol' => 'BTC',
                'coin_id' => 'bitcoin',
                'price_ngn' => 50000000,
                'last_updated_at' => time(),
                'fetched_at' => now()->toIso8601String(),
            ]);

        $this->app->instance(CoinGeckoService::class, $mockCoinGecko);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/trade/buy', [
                'symbol' => 'BTC',
                'amount' => 5000000, // More than wallet balance
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient wallet balance',
            ]);
    }

    public function test_user_cannot_buy_below_minimum_amount()
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/trade/buy', [
                'symbol' => 'BTC',
                'amount' => 500, // Below ₦1,000 minimum
            ]);

        $response->assertStatus(422);
    }

    public function test_user_can_sell_crypto()
    {
        // Create crypto holding
        CryptoHolding::create([
            'user_id' => $this->user->id,
            'crypto_symbol' => 'BTC',
            'crypto_name' => 'Bitcoin',
            'balance' => 0.01,
        ]);

        // Mock CoinGecko service
        $mockCoinGecko = Mockery::mock(CoinGeckoService::class);
        $mockCoinGecko->shouldReceive('getPrice')
            ->with('BTC')
            ->andReturn([
                'symbol' => 'BTC',
                'coin_id' => 'bitcoin',
                'price_ngn' => 50000000,
                'last_updated_at' => time(),
                'fetched_at' => now()->toIso8601String(),
            ]);

        $this->app->instance(CoinGeckoService::class, $mockCoinGecko);

        $initialBalance = $this->user->wallet->balance;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/trade/sell', [
                'symbol' => 'BTC',
                'amount' => 0.005,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Sell order completed successfully',
                'data' => [
                    'trade' => [
                        'type' => 'sell',
                        'crypto_symbol' => 'BTC',
                        'status' => 'completed',
                    ],
                ],
            ]);

        // Verify wallet was credited
        $wallet = $this->user->wallet->fresh();
        $this->assertGreaterThan($initialBalance, $wallet->balance);

        // Verify crypto balance decreased
        $holding = $this->user->getCryptoHolding('BTC');
        $this->assertEquals(0.005, $holding->balance);
    }

    public function test_user_cannot_sell_more_than_holding()
    {
        // Create crypto holding with small balance
        CryptoHolding::create([
            'user_id' => $this->user->id,
            'crypto_symbol' => 'BTC',
            'crypto_name' => 'Bitcoin',
            'balance' => 0.001,
        ]);

        // Mock CoinGecko service
        $mockCoinGecko = Mockery::mock(CoinGeckoService::class);
        $mockCoinGecko->shouldReceive('getPrice')
            ->with('BTC')
            ->andReturn([
                'symbol' => 'BTC',
                'coin_id' => 'bitcoin',
                'price_ngn' => 50000000,
                'last_updated_at' => time(),
                'fetched_at' => now()->toIso8601String(),
            ]);

        $this->app->instance(CoinGeckoService::class, $mockCoinGecko);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/trade/sell', [
                'symbol' => 'BTC',
                'amount' => 1.0, // More than holding
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient BTC balance',
            ]);
    }

    public function test_user_can_get_trade_quote()
    {
        // Mock CoinGecko service
        $mockCoinGecko = Mockery::mock(CoinGeckoService::class);
        $mockCoinGecko->shouldReceive('getPrice')
            ->with('ETH')
            ->andReturn([
                'symbol' => 'ETH',
                'coin_id' => 'ethereum',
                'price_ngn' => 3000000, // ₦3 million per ETH
                'last_updated_at' => time(),
                'fetched_at' => now()->toIso8601String(),
            ]);

        $this->app->instance(CoinGeckoService::class, $mockCoinGecko);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/trade/quote', [
                'type' => 'buy',
                'symbol' => 'ETH',
                'amount' => 100000,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'quote' => [
                        'type',
                        'crypto_symbol',
                        'ngn_amount',
                        'fee_percentage',
                        'fee_amount',
                        'crypto_amount',
                        'rate',
                    ],
                    'note',
                ],
            ]);
    }

    public function test_user_can_view_trade_history()
    {
        // Create some trades by mocking and executing
        $mockCoinGecko = Mockery::mock(CoinGeckoService::class);
        $mockCoinGecko->shouldReceive('getPrice')
            ->andReturn([
                'symbol' => 'BTC',
                'coin_id' => 'bitcoin',
                'price_ngn' => 50000000,
                'last_updated_at' => time(),
                'fetched_at' => now()->toIso8601String(),
            ]);

        $this->app->instance(CoinGeckoService::class, $mockCoinGecko);

        // Execute a buy trade
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/trade/buy', [
                'symbol' => 'BTC',
                'amount' => 50000,
            ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/trade/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'trades',
                    'pagination',
                ],
            ]);

        $this->assertCount(1, $response->json('data.trades'));
    }

    public function test_user_can_filter_trade_history()
    {
        // Create some trades
        $mockCoinGecko = Mockery::mock(CoinGeckoService::class);
        $mockCoinGecko->shouldReceive('getPrice')
            ->andReturn([
                'symbol' => 'BTC',
                'coin_id' => 'bitcoin',
                'price_ngn' => 50000000,
                'last_updated_at' => time(),
                'fetched_at' => now()->toIso8601String(),
            ]);

        $this->app->instance(CoinGeckoService::class, $mockCoinGecko);

        // Execute buy trades
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/trade/buy', ['symbol' => 'BTC', 'amount' => 50000]);
        
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/trade/buy', ['symbol' => 'BTC', 'amount' => 30000]);

        // Filter by type
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/trade/history?type=buy');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.trades'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
