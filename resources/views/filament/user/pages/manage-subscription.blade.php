<section class="flex flex-col gap-y-8 py-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="">
            <h1 class="fi-header-heading text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
                {{ __('messages.subscription.manage_subscription') }}
            </h1>
        </div>
        <div class="fi-ac gap-3 flex flex-wrap items-center justify-start shrink-0">
            <a href="{{ route('filament.user.pages.upgrade-subscription') }}"
                style="--c-400:var(--primary-400);--c-500:var(--primary-500);--c-600:var(--primary-600);"
                class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-color-custom fi-btn-color-primary fi-color-primary fi-size-md fi-btn-size-md gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm bg-custom-600 text-white hover:bg-custom-500 focus-visible:ring-custom-500/50 dark:bg-custom-500 dark:hover:bg-custom-400 dark:focus-visible:ring-custom-400/50 fi-ac-action fi-ac-btn-action">
                <span class="fi-btn-label">{{ __('messages.subscription.upgrade_plan') }}</span>
            </a>
        </div>
    </div>
    
    {{-- Current Plan Information --}}
    @php
        $activeSubscription = \App\Models\Subscription::where('user_id', auth()->id())
            ->where('status', \App\Enums\SubscriptionStatus::ACTIVE->value)
            ->where('ends_at', '>', \Carbon\Carbon::now())
            ->with('plan')
            ->orderByDesc('id')
            ->first();
    @endphp
    
    @if($activeSubscription)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Current Plan Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-blue-900 dark:text-blue-100">Current Plan</p>
                            <p class="text-lg font-semibold text-blue-600 dark:text-blue-400">{{ $activeSubscription->plan->name }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-900 dark:text-green-100">Plan Price</p>
                            <p class="text-lg font-semibold text-green-600 dark:text-green-400">
                                @php
                                    $currencySymbol = $activeSubscription->plan->currency->symbol ?? '₹';
                                    $amount = $activeSubscription->plan->plan_amount;
                                    echo getCurrencyPosition() ? $currencySymbol . ' ' . number_format($amount, 2) : number_format($amount, 2) . ' ' . $currencySymbol;
                                @endphp
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-purple-900 dark:text-purple-100">Max Questions Per Exam</p>
                            <p class="text-lg font-semibold text-purple-600 dark:text-purple-400">
                                @php
                                    $maxQuestions = $activeSubscription->plan->max_questions_per_exam;
                                    echo $maxQuestions == -1 ? 'Unlimited' : $maxQuestions;
                                @endphp
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-orange-900 dark:text-orange-100">Exams Per Month</p>
                            <p class="text-lg font-semibold text-orange-600 dark:text-orange-400">
                                @php
                                    $maxExams = $activeSubscription->plan->exams_per_month;
                                    echo $maxExams == -1 ? 'Unlimited' : $maxExams;
                                @endphp
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-indigo-900 dark:text-indigo-100">Max Questions Per Month</p>
                            <p class="text-lg font-semibold text-indigo-600 dark:text-indigo-400">
                                @php
                                    $maxQuestionsPerMonth = $activeSubscription->plan->max_questions_per_month;
                                    echo $maxQuestionsPerMonth == -1 ? 'Unlimited' : $maxQuestionsPerMonth;
                                @endphp
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 text-gray-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            Expires on: {{ \Carbon\Carbon::parse($activeSubscription->ends_at)->format('d/m/Y') }}
                        </span>
                    </div>
                    <div class="flex items-center">
                        <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm text-green-600 dark:text-green-400 font-medium">
                            @php
                                $daysRemaining = \Carbon\Carbon::now()->diffInDays($activeSubscription->ends_at, false);
                                echo $daysRemaining > 0 ? "Expires in {$daysRemaining} days" : 'Expired';
                            @endphp
                        </span>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-700 p-6 mb-6">
            <div class="flex items-center">
                <svg class="h-6 w-6 text-yellow-600 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                <div>
                    <h3 class="text-lg font-medium text-yellow-800 dark:text-yellow-200">No Active Plan</h3>
                    <p class="text-sm text-yellow-700 dark:text-yellow-300">You don't have an active subscription. Please upgrade your plan to access all features.</p>
                </div>
            </div>
        </div>
    @endif
    
    <div>
        {{ $this->table }}
    </div>
</section>
