<x-filament-widgets::widget>
    <!-- Exams Remaining Banner -->
    @if($examsRemaining !== 0)
    <div class="mb-6 p-6 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 rounded-xl text-white shadow-xl border border-white/20">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="flex-shrink-0 p-3 bg-white/20 rounded-full">
                    <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2 2 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold">{{ __('Exams Remaining This Month') }}</h3>
                    <p class="text-sm opacity-90 mt-1">{{ __('You have') }} <span class="font-bold text-2xl">{{ $examsRemaining }}</span> {{ __('exams remaining in your') }} <span class="font-semibold">{{ isset($subscription) ? $subscription->plan->name : __('Free Plan') }}</span></p>
                </div>
            </div>
            <div class="text-right">
                <div class="text-4xl font-black">{{ $examsRemaining }}</div>
                <div class="text-sm opacity-90 font-medium">{{ __('remaining') }}</div>
            </div>
        </div>
    </div>
    @endif

    <div class=" grid gap-6 md:grid-cols-2 xl:grid-cols-5 custom-grids">

        <div class="flex">
            <div class="admin-dashboard-card flex w-full max-w-full flex-col break-words rounded-lg text-white p-4">
                <div class="flex justify-end">
                    <div class="icon flex items-center justify-center w-12 h-12">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-11 w-11" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6.75 2.994v2.25m10.5-2.25v2.25m-14.252 13.5V7.491a2.25 2.25 0 0 1 2.25-2.25h13.5a2.25 2.25 0 0 1 2.25 2.25v11.251m-18 0a2.25 2.25 0 0 0 2.25 2.25h13.5a2.25 2.25 0 0 0 2.25-2.25m-18 0v-7.5a2.25 2.25 0 0 1 2.25-2.25h13.5a2.25 2.25 0 0 1 2.25 2.25v7.5m-6.75-6h2.25m-9 2.25h4.5m.002-2.25h.005v.006H12v-.006Zm-.001 4.5h.006v.006h-.006v-.005Zm-2.25.001h.005v.006H9.75v-.006Zm-2.25 0h.005v.005h-.006v-.005Zm6.75-2.247h.005v.005h-.005v-.005Zm0 2.247h.006v.006h-.006v-.006Zm2.25-2.248h.006V15H16.5v-.005Z" />
                        </svg>
                    </div>
                </div>
                <div class="py-5 my-5"></div>
                <div class="pt-3">
                    <p class="text-sm font-medium opacity-70">{{ __('messages.dashboard.expiry_date') }}</p>
                    <h4 class="text-3xl font-bold tracking-tight xl:text-2xl">
                        {{ isset($subscription) ? date('d/m/Y', strtotime($subscription->ends_at)) : __('messages.common.n/a') }}
                    </h4>
                    <p class="text-sm opacity-70">
                        <span>{{ __('messages.dashboard.your_current_plan') . ':' }}</span>
                        <span
                            class="font-semibold text-primary">{{ isset($subscription) ? $subscription->plan->name : __('messages.common.n/a') }}</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="flex">
            <div class="admin-dashboard-card flex w-full max-w-full flex-col break-words rounded-lg text-white p-4">
                <div class="flex justify-end">
                    <div class="icon flex items-center justify-center w-12 h-12">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-11 w-11" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z">
                            </path>
                        </svg>
                    </div>
                </div>
                <div class="py-5 my-5"></div>
                <div class="pt-3">
                    <p class="text-sm font-medium opacity-70">{{ __('messages.dashboard.active_quizzes') }}</p>
                    <h4 class="text-3xl font-bold tracking-tight xl:text-2xl">{{ $activeQuizzes }}</h4>
                    <p class="text-sm opacity-70">
                        <span>{{ __('messages.dashboard.total_quizzes') . ':' }}</span>
                        <span>{{ $totalQuizzes }}</span>
                    </p>
                    <p class="text-sm opacity-70 mt-1">
                        <span>{{ __('Exams remaining this month') . ':' }}</span>
                        <span class="font-semibold">{{ $examsRemaining }}</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="flex">
            <div class="admin-dashboard-card flex w-full max-w-full flex-col break-words rounded-xl text-white p-6 bg-black shadow-lg border border-gray-700">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center space-x-3">
                        <div class="p-2 bg-white/20 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2 2 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z">
                                </path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium opacity-90">{{ __('Exams Remaining') }}</p>
                            <p class="text-xs opacity-70">{{ __('This Month') }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-black">{{ $examsRemaining }}</div>
                    </div>
                </div>
                <div class="mt-auto">
                    <div class="flex items-center justify-between">
                        <span class="text-sm opacity-80">{{ __('Plan') }}:</span>
                        <span class="text-sm font-semibold bg-white/20 px-2 py-1 rounded-full">{{ isset($subscription) ? $subscription->plan->name : __('Free Plan') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex">
            <div class="admin-dashboard-card flex w-full max-w-full flex-col break-words rounded-lg text-white p-4">
                <div class="flex justify-end">
                    <div class="icon flex items-center justify-center w-12 h-12">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-11 w-11" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z">
                            </path>
                        </svg>
                    </div>
                </div>
                <div class="py-5 my-5"></div>
                <div class="pt-3">
                    <p class="text-sm font-medium opacity-70">{{ __('messages.dashboard.participants') }}</p>
                    <h4 class="text-3xl font-bold tracking-tight xl:text-2xl">{{ $participants }}</h4>
                    <p class="text-sm opacity-70">
                        <span>{{ $completedPer }}%</span>
                        <span>{{ __('messages.dashboard.participants_completed_quiz') }}</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="flex">
            <div class="admin-dashboard-card flex w-full max-w-full flex-col break-words rounded-lg text-white p-4">
                <div class="flex justify-end">
                    <div class="icon flex items-center justify-center w-12 h-12">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-11 w-11" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>
                <div class="py-5 my-5"></div>
                <div class="pt-3">
                    <p class="text-sm font-medium opacity-70">{{ __('messages.user.balance') }}</p>
                    <h4 class="text-3xl font-bold tracking-tight xl:text-2xl">
                        ${{ number_format(auth()->user()->balance ?? 0, 2) }}
                    </h4>

                    <p class="text-sm opacity-70">
                        <span>{{ __('messages.user.remaining_balance') }}: </span>
                        <span>${{ number_format(auth()->user()->remaining_balance ?? 0, 2) }}</span>
                    </p>
                </div>
            </div>
        </div>

    </div>
</x-filament-widgets::widget>
