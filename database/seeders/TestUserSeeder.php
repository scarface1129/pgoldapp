<?php

namespace Database\Seeders;

use App\Models\CryptoHolding;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test user
        $user = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        // Create wallet with initial balance for testing
        Wallet::updateOrCreate(
            ['user_id' => $user->id, 'currency' => 'NGN'],
            [
                'balance' => 500000, // ₦500,000 initial balance
                'is_active' => true,
            ]
        );

        // Create some initial crypto holdings
        $cryptos = [
            ['symbol' => 'BTC', 'name' => 'Bitcoin', 'balance' => 0.001],
            ['symbol' => 'ETH', 'name' => 'Ethereum', 'balance' => 0.05],
            ['symbol' => 'USDT', 'name' => 'Tether', 'balance' => 100],
        ];

        foreach ($cryptos as $crypto) {
            CryptoHolding::updateOrCreate(
                ['user_id' => $user->id, 'crypto_symbol' => $crypto['symbol']],
                [
                    'crypto_name' => $crypto['name'],
                    'balance' => $crypto['balance'],
                ]
            );
        }

        // Create a second test user without initial balance
        $user2 = User::updateOrCreate(
            ['email' => 'test2@example.com'],
            [
                'name' => 'Test User 2',
                'email' => 'test2@example.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        Wallet::updateOrCreate(
            ['user_id' => $user2->id, 'currency' => 'NGN'],
            [
                'balance' => 0,
                'is_active' => true,
            ]
        );

        $this->command->info('Test users seeded successfully.');
        $this->command->info('User 1: test@example.com / password123 (with ₦500,000 balance)');
        $this->command->info('User 2: test2@example.com / password123 (with ₦0 balance)');
    }
}
