<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Authorization\HasAuthorizationScope;
use App\Support\Authorization\ResourceScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A residential hall (hostel). Exam rooms are {@see ExamHall}.
 */
class Hall extends Model implements HasAuthorizationScope
{
    /** @use HasFactory<\Database\Factories\HallFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'gender',
        'capacity',
        'is_active',
    ];

    public function authorizationScope(): ResourceScope
    {
        return ResourceScope::forHall($this->id);
    }

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
