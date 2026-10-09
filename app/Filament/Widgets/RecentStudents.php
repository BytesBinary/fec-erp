<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Students\StudentResource;
use App\Models\Student;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentStudents extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = [
        'md' => 2,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recently Added Students')
            ->query(fn (): Builder => Student::query()->latest()->limit(5))
            ->columns([
                TextColumn::make('roll_number')
                    ->label('Roll No.'),
                TextColumn::make('user.name')
                    ->label('Name'),
                TextColumn::make('department.name')
                    ->label('Department'),
                TextColumn::make('batch.batch_number')
                    ->label('Batch')
                    ->prefix('Batch '),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->since(),
            ])
            ->recordUrl(fn (Student $record): string => StudentResource::getUrl('edit', ['record' => $record]))
            ->paginated(false);
    }
}
