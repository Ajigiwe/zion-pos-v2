<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Default categories from the Flutter app's Drift seed
 * (client/lib/core/database/app_database.dart §9) so both apps stay in parity.
 */
class CategorySeeder extends Seeder
{
    private const DEFAULTS = [
        'Keyboards', 'Guitars', 'Bass Guitars', 'Drums', 'Percussion',
        'Microphones', 'Speakers', 'Amplifiers', 'Mixers', 'Effects',
        'Cables', 'Accessories', 'Music Stands',
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
