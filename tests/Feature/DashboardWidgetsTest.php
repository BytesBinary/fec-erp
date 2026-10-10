<?php

use App\Filament\Widgets\DashboardStatsOverview;
use App\Filament\Widgets\QuickActions;
use App\Filament\Widgets\RecentExamDuties;
use App\Filament\Widgets\RecentStudents;
use Database\Seeders\Testing\TestDataset as T;

beforeEach(function () {
    seedTestDataset();
});

it('shows the Shield-guarded dashboard widgets to the super admin', function () {
    $this->actingAs(datasetUser(T::SUPER_ADMIN))->get('/')->assertOk();

    foreach ([DashboardStatsOverview::class, QuickActions::class, RecentExamDuties::class, RecentStudents::class] as $widget) {
        expect($widget::canView())->toBeTrue($widget);
    }
});

it('keeps the institution-wide widgets away from students', function () {
    $this->actingAs(datasetUser(T::STUDENT_ELIGIBLE))->get('/')->assertOk();

    foreach ([DashboardStatsOverview::class, QuickActions::class, RecentExamDuties::class, RecentStudents::class] as $widget) {
        expect($widget::canView())->toBeFalse($widget);
    }
});
