<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One run of the daily check or of a manual catalog sync.
 */
class PortalCheckRun extends Model
{
    /** @use HasFactory<\Database\Factories\PortalCheckRunFactory> */
    use HasFactory;

    protected $fillable = ['kind', 'status', 'exams_total', 'new_exams', 'publications_confirmed', 'message', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
