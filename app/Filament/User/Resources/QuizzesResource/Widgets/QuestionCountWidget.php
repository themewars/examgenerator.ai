<?php

namespace App\Filament\User\Resources\QuizzesResource\Widgets;

use App\Models\Quiz;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class QuestionCountWidget extends BaseWidget
{
    public ?Quiz $record = null;

    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        $totalQuestions = $this->record->questions()->count();
        
        return [
            Stat::make('Total Questions in this Exam', $totalQuestions)
                ->description('Questions added to this quiz')
                ->descriptionIcon('heroicon-m-question-mark-circle')
                ->color('success')
                ->icon('heroicon-o-document-text'),
        ];
    }

    protected function getColumns(): int
    {
        return 1;
    }
}
