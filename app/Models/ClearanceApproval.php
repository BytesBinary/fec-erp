<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClearanceApproval extends Model
{
    protected $fillable = [
        'clearance_request_id', 'stage_id', 'decision', 'approver_user_id', 'approver_name', 'approver_designation',
        'signature_snapshot_path', 'signature_sha256', 'remarks', 'ip', 'decided_at', 'prev_hash', 'hash', 'superseded',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ClearanceRequest::class, 'clearance_request_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ClearanceStage::class, 'stage_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'superseded' => 'boolean'];
    }
}
