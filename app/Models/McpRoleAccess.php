<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpRoleAccess extends Model
{
    protected $table = 'mcp_role_access';

    protected $primaryKey = 'role_id';

    public $incrementing = false;

    protected $fillable = ['role_id', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
