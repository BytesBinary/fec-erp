<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Authorization\HasAuthorizationScope;
use App\Support\Authorization\ResourceScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model implements HasAuthorizationScope
{
    /** @use HasFactory<\Database\Factories\StaffFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'department_id',
        'designation_id',
        'employee_id',
        'joining_date',
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

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function authorizationScope(): ResourceScope
    {
        return ResourceScope::forOwner($this->user_id)
            ->merge(ResourceScope::forDepartment($this->department_id));
    }

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
        ];
    }
}
