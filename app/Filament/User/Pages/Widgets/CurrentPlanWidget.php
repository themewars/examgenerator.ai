<?php

namespace App\Filament\User\Pages\Widgets;

use App\Models\Subscription;
use App\Enums\SubscriptionStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class CurrentPlanWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $activeSubscription = Subscription::where('user_id', auth()->id())
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->where('ends_at', '>', Carbon::now())
            ->with('plan')
            ->orderByDesc('id')
            ->first();

        if (!$activeSubscription) {
            return [
                Stat::make('Current Plan', 'No Active Plan')
                    ->description('You don\'t have an active subscription')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('warning')
                    ->icon('heroicon-o-x-circle'),
            ];
        }

        $plan = $activeSubscription->plan;
        $daysRemaining = Carbon::now()->diffInDays($activeSubscription->ends_at, false);
        
        return [
            Stat::make('Current Plan', $plan->name)
                ->description($daysRemaining > 0 ? "Expires in {$daysRemaining} days" : 'Expired')
                ->descriptionIcon($daysRemaining > 0 ? 'heroicon-m-calendar-days' : 'heroicon-m-exclamation-triangle')
                ->color($daysRemaining > 0 ? 'success' : 'danger')
                ->icon('heroicon-o-shield-check'),

            Stat::make('Plan Price', $this->formatCurrency($plan->plan_amount, $plan->currency->symbol ?? '$'))
                ->description($plan->plan_duration . ' ' . $plan->plan_duration_type)
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Max Questions per Exam', $plan->max_questions_per_exam ?? 'Unlimited')
                ->description('Questions allowed per quiz')
                ->descriptionIcon('heroicon-m-question-mark-circle')
                ->color('primary')
                ->icon('heroicon-o-document-text'),

            Stat::make('Max Exams', $plan->max_exams ?? 'Unlimited')
                ->description('Total exams allowed')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('success')
                ->icon('heroicon-o-clipboard-document-list'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }

    private function formatCurrency($amount, $symbol = '$')
    {
        return getCurrencyPosition() ? $symbol . ' ' . number_format($amount, 2) : number_format($amount, 2) . ' ' . $symbol;
    }
}
