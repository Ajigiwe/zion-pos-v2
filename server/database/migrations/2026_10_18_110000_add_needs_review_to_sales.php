<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Flags offline sales that were accepted while overselling (PHP-PLAN §3). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('needs_review')->default(false)->after('sale_status');
            $table->string('source')->default('web')->after('needs_review'); // web|offline_pwa|import
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['needs_review', 'source']);
        });
    }
};
