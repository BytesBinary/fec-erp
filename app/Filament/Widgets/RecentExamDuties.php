<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ExamDuties\ExamDutyResource;
use App\Models\ExamDuty;
use App\Models\ExamType;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentExamDuties extends TableWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = [
        'md' => 2,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent Exam Duties')
            ->query(fn (): Builder => ExamDuty::query()->latest()->limit(5))
            ->columns([
                TextColumn::make('exam_name')
                    ->label('Exam Name'),
                TextColumn::make('exam_type_id')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(function (string $state): string {
                        $ids = explode(',', str_replace(['[', ']', '"'], '', $state));

                        return collect($ids)
                            ->map(fn (string $id): string => ExamType::find($id)?->type ?? $id)
                            ->implode(', ');
                    })
                    ->label('Exam Type'),
                TextColumn::make('department')
                    ->label('Department'),
                TextColumn::make('exam_year')
                    ->badge()
                    ->color('secondary')
                    ->label('Year'),
            ])
            ->recordUrl(fn (ExamDuty $record): string => ExamDutyResource::getUrl('view', ['record' => $record]))
            ->paginated(false);
    }
}
