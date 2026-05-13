<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TriggerCategory extends Model
{
    /** @use HasFactory<\Database\Factories\TriggerCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'icon',
        'category_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function triggerTypes(): HasMany
    {
        return $this->hasMany(TriggerType::class, 'category_id');
    }
}
