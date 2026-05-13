<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TriggerFieldValue extends Model
{
    /** @use HasFactory<\Database\Factories\TriggerFieldValueFactory> */
    use HasFactory;

    protected $fillable = [
        'trigger_id',
        'trigger_type_field_id',
        'value',
    ];

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(Trigger::class, 'trigger_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(TriggerTypeField::class, 'trigger_type_field_id');
    }
}
