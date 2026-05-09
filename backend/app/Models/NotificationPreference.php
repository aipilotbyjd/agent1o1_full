<?php

namespace App\Models;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'type',
        'channels',
        'enabled',
    ];

    protected $casts = [
        'type'     => NotificationType::class,
        'channels' => 'array',
        'enabled'  => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the resolved channel driver names for passing to Notification::via().
     *
     * @return list<string>
     */
    public function resolvedDrivers(): array
    {
        if (! $this->enabled) {
            return [];
        }

        return collect($this->channels ?? [])
            ->map(fn (string $ch) => NotificationChannelType::tryFrom($ch)?->driver())
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Build the default preference for a given user and notification type.
     */
    public static function buildDefault(User $user, NotificationType $type): self
    {
        $pref = new self;
        $pref->user_id = $user->id;
        $pref->type = $type;
        $pref->channels = $type->defaultChannels();
        $pref->enabled = true;

        return $pref;
    }
}
