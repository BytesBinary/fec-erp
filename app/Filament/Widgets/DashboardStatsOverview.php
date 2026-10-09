<?php

namespace App\Filament\Widgets;

use App\Models\Department;
use App\Models\ExamDuty;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Teacher;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsOverview extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected static ?int $sort = -1;

    protected function getStats(): array
    {
        return [
            Stat::make('Students', Student::query()->count())
                ->description('Total enrolled students')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
            Stat::make('Teachers', Teacher::query()->count())
                ->description('Total teaching staff')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('success'),
            Stat::make('Staff', Staff::query()->count())
                ->description('Total non-teaching staff')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('warning'),
            Stat::make('Departments', Department::query()->where('is_active', true)->count())
                ->description('Active departments')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('info'),
            Stat::make('Exam Duties', ExamDuty::query()->count())
                ->description('Total exam duties assigned')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color('danger'),
        ];
    }
}
