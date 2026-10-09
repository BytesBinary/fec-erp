<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ExamDuties\ExamDutyResource;
use App\Filament\Resources\Routine\RoutineResource;
use App\Filament\Resources\Staff\StaffResource;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\Teachers\TeacherResource;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

class QuickActions extends Widget
{
    use HasWidgetShield;

    protected string $view = 'filament.widgets.quick-actions';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    /** @return array<int, array{label: string, url: string, icon: string}> */
    public function getActions(): array
    {
        return [
            [
                'label' => 'Add Student',
                'url' => StudentResource::getUrl('create'),
                'icon' => 'heroicon-o-user-plus',
            ],
            [
                'label' => 'Add Teacher',
                'url' => TeacherResource::getUrl('create'),
                'icon' => 'heroicon-o-academic-cap',
            ],
            [
                'label' => 'Add Staff',
                'url' => StaffResource::getUrl('create'),
                'icon' => 'heroicon-o-briefcase',
            ],
            [
                'label' => 'New Exam Duty',
                'url' => ExamDutyResource::getUrl('create'),
                'icon' => 'heroicon-o-clipboard-document-check',
            ],
            [
                'label' => 'Manage Routine',
                'url' => RoutineResource::getUrl('index'),
                'icon' => 'heroicon-o-calendar-days',
            ],
        ];
    }
}
