<?php

namespace App\Models;

use App\Enums\ThemePreset;
use App\Models\Concerns\Auditable;
use App\Support\Authorization\HasAuthorizationScope;
use App\Support\Authorization\ResourceScope;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAuthorizationScope
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'theme',
        'theme_primary_color',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active !== false && $this->roles()->exists();
    }

    /**
     * Scopes pinning this user's roles to departments, halls or courses.
     */
    public function roleScopes(): HasMany
    {
        return $this->hasMany(RoleScope::class);
    }

    public function authorizationScope(): ResourceScope
    {
        $departmentId = $this->student?->department_id ?? $this->teacher?->department_id ?? $this->staff?->department_id;

        return ResourceScope::forOwner($this->id)->merge(ResourceScope::forDepartment($departmentId));
    }

    public function mfa(): HasOne
    {
        return $this->hasOne(UserMfa::class);
    }

    public function loginSessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->mfa?->isEnabled() === true;
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'theme' => ThemePreset::class,
        ];
    }
}
