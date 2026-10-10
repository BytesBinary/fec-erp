<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClearancePrint extends Model
{
    protected $fillable = ['clearance_request_id', 'printed_by', 'printed_at', 'is_duplicate', 'format'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ClearanceRequest::class, 'clearance_request_id');
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    protected function casts(): array
    {
        return ['printed_at' => 'datetime', 'is_duplicate' => 'boolean'];
    }
}
