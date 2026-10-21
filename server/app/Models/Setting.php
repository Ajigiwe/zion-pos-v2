<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key/value store (store.* profile fields, receipt footer, tax label…).
 * New settings need no schema change. Keys are cached per-request by
 * Setting::get().
 */
class Setting extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'updated_at'];

    /** Read one key, with a compile-time ui default fallback. */
    public static function get(string $key, ?string $default = null): ?string
    {
        static $cache = [];

        if (! array_key_exists($key, $cache)) {
            $cache[$key] = static::find($key)?->value;
        }

        return $cache[$key] ?? $default;
    }

    public static function put(string $key, string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'updated_at' => now()]);
    }
}
