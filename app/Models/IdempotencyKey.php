<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $fillable = ['user_id', 'tool', 'key', 'request_hash', 'response'];

    protected function casts(): array
    {
        return ['response' => 'array'];
    }
}
