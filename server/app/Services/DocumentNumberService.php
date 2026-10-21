<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Sequential document numbers (SALE-000123), stored as counters in the
 * settings table — ports core/numbering/document_numbers.dart. Safe under
 * concurrency: the counter row is read and bumped inside a pessimistic lock.
 */
class DocumentNumberService
{
    public function next(string $prefix): string
    {
        // Nested transactions render as savepoints, safe inside a sale tx.
        return DB::transaction(function () use ($prefix) {
            $key = 'counter.'.$prefix;

            $current = Setting::query()->where('key', $key)->lockForUpdate()->value('value');
            $next = ((int) $current) + 1;

            Setting::query()->updateOrInsert(
                ['key' => $key],
                ['value' => (string) $next, 'updated_at' => now()],
            );

            return sprintf('%s-%06d', $prefix, $next);
        });
    }
}
