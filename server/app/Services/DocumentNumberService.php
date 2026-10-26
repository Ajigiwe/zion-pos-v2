<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Sequential document numbers (SALE-000123 / BAR-REF-000001), stored as
 * counters in the settings table — ports core/numbering/document_numbers.dart
 * and extends it for multi-shop: every NON-main shop keeps its own counter
 * series per document type, so receipts read SHOPCODE-SALE-0000xx.
 *
 * The MAIN shop deliberately reuses the original global counters
 * (counter.SALE etc.) so its pre-multi-shop numbering continues without a
 * visible jump. Safe under concurrency: the counter row is read and bumped
 * inside a pessimistic lock.
 */
class DocumentNumberService
{
    public function next(string $prefix, ?string $shopCode = null): string
    {
        $shopCode ??= current_shop()?->code;

        // main shop (or console jobs): original global series, unchanged
        if ($shopCode === null || $shopCode === 'MAIN') {
            return $this->bump('counter.'.$prefix, fn (int $n) => sprintf('%s-%06d', $prefix, $n));
        }

        // named shops: own series per document type
        return $this->bump(
            'counter.shop.'.$shopCode.'.'.$prefix,
            fn (int $n) => sprintf('%s-%s-%06d', $shopCode, $prefix, $n),
        );
    }

    private function bump(string $key, \Closure $format): string
    {
        // Nested transactions render as savepoints, safe inside a sale tx.
        return DB::transaction(function () use ($key, $format) {
            $current = Setting::query()->where('key', $key)->lockForUpdate()->value('value');
            $next = ((int) $current) + 1;

            Setting::query()->updateOrInsert(
                ['key' => $key],
                ['value' => (string) $next, 'updated_at' => now()],
            );

            return $format($next);
        });
    }
}
