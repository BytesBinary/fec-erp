<?php

namespace App\Enums;

/**
 * Canonical role keys used across web, MCP, assistant, fixtures and seeds.
 * Display labels live in lang/en/roles.php.
 */
enum RoleKey: string
{
    case SuperAdmin = 'super_admin';
    case AdminOffice = 'admin_office';
    case HeadOfInstitution = 'head_of_institution';
    case Principal = 'principal';
    case DepartmentHead = 'department_head';
    case HallProvost = 'hall_provost';
    case Librarian = 'librarian';
    case Teacher = 'teacher';
    case Student = 'student';

    public function label(): string
    {
        return __("roles.{$this->value}");
    }

    /**
     * The scope a permission granted through this role is limited to,
     * unless overridden in config('erp.rbac.role_scopes').
     */
    public function defaultScope(): ScopeType
    {
        $configured = config("erp.rbac.role_scopes.{$this->value}");

        if ($configured !== null) {
            return ScopeType::from($configured);
        }

        return ScopeType::Global;
    }

    /**
     * Roles whose scope must be pinned to concrete records via role_scopes rows.
     */
    public function requiresExplicitScope(): bool
    {
        return in_array($this->defaultScope(), [ScopeType::Department, ScopeType::Hall], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
