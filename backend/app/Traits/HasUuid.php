<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasUuid
{
    public function initializeHasUuid(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    public static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
