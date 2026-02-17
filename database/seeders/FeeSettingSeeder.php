<?php

namespace Database\Seeders;

use App\Models\FeeSetting;
use Illuminate\Database\Seeder;

class FeeSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'name' => FeeSetting::BUY_FEE,
                'description' => 'Fee charged on cryptocurrency purchases',
                'percentage' => 1.5,
                'minimum_amount' => 1000, // ₦1,000 minimum transaction
                'is_active' => true,
            ],
            [
                'name' => FeeSetting::SELL_FEE,
                'description' => 'Fee charged on cryptocurrency sales',
                'percentage' => 1.5,
                'minimum_amount' => 1000, // ₦1,000 minimum transaction
                'is_active' => true,
            ],
        ];

        foreach ($settings as $setting) {
            FeeSetting::updateOrCreate(
                ['name' => $setting['name']],
                $setting
            );
        }

        $this->command->info('Fee settings seeded successfully.');
    }
}
