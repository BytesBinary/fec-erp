<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpSetting extends Model
{
    protected $fillable = ['global_enabled', 'max_integrations_per_user', 'allow_never_expire'];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['global_enabled' => true, 'max_integrations_per_user' => (int) config('mcp_access.max_integrations', 5), 'allow_never_expire' => false]);
    }

    protected function casts(): array
    {
        return ['global_enabled' => 'boolean', 'max_integrations_per_user' => 'integer', 'allow_never_expire' => 'boolean'];
    }
}
