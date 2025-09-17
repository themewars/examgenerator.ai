<x-filament-widgets::widget>
    @if($isCreating || $currentStep === 'Exam created successfully!' || $currentStep === 'Error occurred')
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" 
         x-data="{ show: @entangle('isCreating') || '{{ $currentStep }}' === 'Exam created successfully!' || '{{ $currentStep }}' === 'Error occurred' }"
         x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <div class="bg-white rounded-xl shadow-2xl p-8 max-w-md w-full mx-4" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            
            <!-- Header -->
            <div class="text-center mb-6">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full mb-4"
                     :class="'{{ $currentStep }}' === 'Exam created successfully!' ? 'bg-green-100' : ('{{ $currentStep }}' === 'Error occurred' ? 'bg-red-100' : 'bg-blue-100')">
                    
                    @if($currentStep === 'Exam created successfully!')
                        <svg class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    @elseif($currentStep === 'Error occurred')
                        <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    @else
                        <svg class="h-8 w-8 text-blue-600 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    @endif
                </div>
                
                <h3 class="text-lg font-semibold text-gray-900 mb-2">
                    @if($currentStep === 'Exam created successfully!')
                        {{ __('Exam Created Successfully!') }}
                    @elseif($currentStep === 'Error occurred')
                        {{ __('Creation Failed') }}
                    @else
                        {{ __('Creating Exam...') }}
                    @endif
                </h3>
            </div>

            <!-- Progress Bar -->
            @if($isCreating && $totalQuestions > 0)
            <div class="mb-6">
                <div class="flex justify-between text-sm text-gray-600 mb-2">
                    <span>{{ $currentStep }}</span>
                    <span>{{ $questionsGenerated }}/{{ $totalQuestions }}</span>
                </div>
                
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="bg-blue-600 h-3 rounded-full transition-all duration-500 ease-out" 
                         style="width: {{ $this->getProgressPercentage() }}%"></div>
                </div>
                
                <div class="text-center text-sm text-gray-600 mt-2">
                    {{ $progressMessage }}
                </div>
            </div>
            @elseif($currentStep === 'Exam created successfully!')
            <div class="text-center text-green-600 mb-6">
                <p class="text-lg font-medium">{{ $progressMessage }}</p>
                <p class="text-sm mt-2">{{ __('Redirecting to exam editor...') }}</p>
            </div>
            @elseif($currentStep === 'Error occurred')
            <div class="text-center text-red-600 mb-6">
                <p class="text-lg font-medium">{{ $progressMessage }}</p>
            </div>
            @else
            <div class="text-center text-gray-600 mb-6">
                <p class="text-lg font-medium">{{ $currentStep }}</p>
                <p class="text-sm mt-2">{{ $progressMessage }}</p>
            </div>
            @endif

            <!-- Action Buttons -->
            @if($currentStep === 'Error occurred')
            <div class="flex justify-center space-x-3">
                <button type="button" 
                        @click="$wire.dispatch('close-progress-modal')"
                        class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                    {{ __('Close') }}
                </button>
            </div>
            @endif
        </div>
    </div>
    @endif
</x-filament-widgets::widget>

<script>
document.addEventListener('livewire:init', () => {
    Livewire.on('exam-creation-started', () => {
        console.log('Exam creation started');
    });
    
    Livewire.on('exam-progress-updated', () => {
        console.log('Progress updated');
    });
    
    Livewire.on('exam-creation-completed', (event) => {
        console.log('Exam creation completed', event);
    });
    
    Livewire.on('exam-creation-error', () => {
        console.log('Exam creation error');
    });
});
</script>
