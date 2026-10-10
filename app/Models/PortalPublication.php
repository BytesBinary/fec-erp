<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "The portal published results for this exam": recorded once per exam,
 * independent of the individual student pulls.
 */
class PortalPublication extends Model
{
    /** @use HasFactory<\Database\Factories\PortalPublicationFactory> */
    use HasFactory;

    protected $fillable = [
        'portal_exam_id',
        'program_id',
        'status',
        'mode',
        'detected_at',
        'confirmed_at',
        'last_checked_at',
        'next_check_at',
        'check_count',
        'students_total',
        'notes',
    ];

    public function pulls(): HasMany
    {
        return $this->hasMany(ResultPull::class, 'portal_publication_id');
    }

    public function probes(): HasMany
    {
        return $this->hasMany(PortalProbe::class, 'portal_exam_id', 'portal_exam_id');
    }

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'next_check_at' => 'datetime',
        ];
    }
}
