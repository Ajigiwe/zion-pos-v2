<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable trail of who did what (§32). Append-only — no updates, no deletes.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'entity_type', 'entity_id', 'details', 'device_id'];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Record one entry and return it. */
    public static function record(
        string $action,
        ?int $userId,
        ?string $entityType = null,
        int|string|null $entityId = null,
        ?string $details = null,
        ?string $deviceId = null,
    ): self {
        return static::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => (string) $entityId,
            'details' => $details,
            'device_id' => $deviceId,
            'created_at' => now(),
        ]);
    }
}
