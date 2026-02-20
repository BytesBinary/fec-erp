<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Teacher = 'teacher';
    case Staff = 'staff';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            UserRole::Admin => 'Admin',
            UserRole::Teacher => 'Teacher',
            UserRole::Staff => 'Staff',
            UserRole::Student => 'Student',
        };
    }

    public function color(): string
    {
        return match ($this) {
            UserRole::Admin => 'danger',
            UserRole::Teacher => 'warning',
            UserRole::Staff => 'success',
            UserRole::Student => 'info',
        };
    }
}
