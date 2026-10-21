<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Store profile keys the receipt printer uses — same key names as the
 * Flutter app's Settings table ('store.name', 'store.address', 'store.phone').
 */
class SettingsSeeder extends Seeder
{
    private const DEFAULTS = [
        'store.name' => 'Zion Musical Centre',
        'store.address' => '',
        'store.phone' => '',
        'receipt.footer' => 'Thank you for shopping with us!',
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value, 'updated_at' => now()]);
        }
    }
}
