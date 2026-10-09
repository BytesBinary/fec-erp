<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Authorization\HasAuthorizationScope;
use App\Support\Authorization\ResourceScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A degree program offered by a department (e.g. B.Sc. in CSE).
 */
class Program extends Model implements HasAuthorizationScope
{
    /** @use HasFactory<\Database\Factories\ProgramFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'department_id',
        'name',
        'code',
        'required_credits',
        'total_semesters',
        'is_active',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function authorizationScope(): ResourceScope
    {
        return ResourceScope::forDepartment($this->department_id);
    }

    protected function casts(): array
    {
        return [
            'required_credits' => 'float',
            'total_semesters' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
