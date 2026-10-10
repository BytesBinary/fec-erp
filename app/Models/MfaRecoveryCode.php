<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfaRecoveryCode extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'code_hash',
        'used_at',
    ];

    protected $hidden = [
        'code_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }
}
