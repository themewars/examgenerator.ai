<?php

namespace App\Filament\User\Widgets;

use Filament\Widgets\Widget;

class ExamCreationProgress extends Widget
{
    protected static string $view = 'filament.user.widgets.exam-creation-progress';

    protected int | string | array $columnSpan = 'full';

    // Widget disabled - no longer needed
    public function render()
    {
        return view('filament.user.widgets.exam-creation-progress');
    }
}