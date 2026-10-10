<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Clearance\PendingApprovals;
use Filament\Widgets\Widget;

/**
 * Dashboard count of clearance requests waiting for the signed-in approver.
 */
class ClearanceWaiting extends Widget
{
    protected static ?int $sort = -2;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.clearance-waiting';

    public static function canView(): bool
    {
        return PendingApprovals::canAccess();
    }

    public function count(): int
    {
        return PendingApprovals::waitingCount();
    }
}
