<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Authorization\HasAuthorizationScope;
use App\Support\Authorization\ResourceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryLoan extends Model implements HasAuthorizationScope
{
    use Auditable;

    protected $fillable = ['student_id', 'book_title', 'accession_no', 'issued_on', 'due_on', 'returned_on', 'fine_amount', 'fine_settled_at'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @param  Builder<LibraryLoan>  $query
     * @return Builder<LibraryLoan>
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereNull('returned_on');
    }

    public function hasUnpaidFine(): bool
    {
        return $this->fine_amount > 0 && $this->fine_settled_at === null;
    }

    public function authorizationScope(): ResourceScope
    {
        return ResourceScope::forOwner($this->student?->user_id)->merge(ResourceScope::forDepartment($this->student?->department_id));
    }

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'due_on' => 'date', 'returned_on' => 'date', 'fine_amount' => 'float', 'fine_settled_at' => 'datetime'];
    }
}
