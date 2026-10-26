<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-shop spine (P1): the shops table + staff assignment pivot, seeded
 * with the main store. Every existing user gets assigned to it so today's
 * staff keep seeing exactly what they saw before the migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 16)->unique(); // MAIN, BAR, KOF…
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_shop', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'shop_id']);
        });

        $mainName = DB::table('settings')->where('key', 'store.name')->value('value');

        $now = now();
        $shopId = (int) DB::table('shops')->insertGetId([
            'name' => trim((string) $mainName) !== '' ? $mainName.' (Main)' : 'Main Shop',
            'code' => 'MAIN',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // every existing staff member belongs to the main shop
        $ts = $now->format('Y-m-d H:i:s');
        DB::table('user_shop')->insertUsing(
            ['user_id', 'shop_id', 'created_at', 'updated_at'],
            DB::table('users')->selectRaw('id, ?, ?, ?', [$shopId, $ts, $ts]),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('user_shop');
        Schema::dropIfExists('shops');
    }
};
