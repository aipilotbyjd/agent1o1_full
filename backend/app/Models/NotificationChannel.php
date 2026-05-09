<?php

namespace App\Models;

use App\Enums\NotificationChannelType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationChannel extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'channel',
        'label',
        'config',
        'is_active',
    ];

    protected $hidden = ['config'];

    protected $casts = [
        'channel'   => NotificationChannelType::class,
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the decrypted config as an array.
     *
     * @return array<string, mixed>
     */
    public function getDecryptedConfig(): array
    {
        try {
            return json_decode(decrypt($this->config), true) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Encrypt and set the config from an array.
     *
     * @param  array<string, mixed>  $config
     */
    public function setConfigFromArray(array $config): void
    {
        $this->config = encrypt(json_encode($config));
    }

    /**
     * Scope to active channels of a specific type for a user.
     */
    public function scopeActiveOfType(
        \Illuminate\Database\Eloquent\Builder $query,
        string $userId,
        NotificationChannelType $type,
    ): \Illuminate\Database\Eloquent\Builder {
        return $query
            ->where('user_id', $userId)
            ->where('channel', $type->value)
            ->where('is_active', true);
    }
}
