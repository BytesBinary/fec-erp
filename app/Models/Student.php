<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Authorization\HasAuthorizationScope;
use App\Support\Authorization\ResourceScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model implements HasAuthorizationScope
{
    /** @use HasFactory<\Database\Factories\StudentFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'department_id',
        'program_id',
        'batch_id',
        'roll_number',
        'registration_number',
        'current_semester',
        'phone',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function authorizationScope(): ResourceScope
    {
        return ResourceScope::forOwner($this->user_id)
            ->merge(ResourceScope::forDepartment($this->department_id));
    }

    protected function casts(): array
    {
        return [
            'current_semester' => 'integer',
        ];
    }
}
