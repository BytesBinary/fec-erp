<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalProbe extends Model
{
    /** @use HasFactory<\Database\Factories\PortalProbeFactory> */
    use HasFactory;

    protected $fillable = ['portal_exam_id', 'student_id', 'outcome', 'message', 'checked_at'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }
}
