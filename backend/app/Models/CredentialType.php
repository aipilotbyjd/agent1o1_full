<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CredentialType extends Model
{
    /** @use HasFactory<\Database\Factories\CredentialTypeFactory> */
    use HasFactory, HasUuid;

    protected $fillable = [
        'type',
        'name',
        'description',
        'icon',
        'color',
        'fields_schema',
        'test_config',
        'oauth_config',
        'is_active',
        'docs_url',
    ];

    protected function casts(): array
    {
        return [
            'fields_schema' => 'array',
            'test_config' => 'array',
            'oauth_config' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
