<?php

namespace App\Models;

use App\Enums\DesignationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Designation extends Model
{
    /** @use HasFactory<\Database\Factories\DesignationFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'short_name',
        'type',
        'is_active',
    ];

    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    protected function casts(): array
    {
        return [
            'type' => DesignationType::class,
            'is_active' => 'boolean',
        ];
    }
}
