<?php

namespace App\Filament\User\Resources\QuizzesResource\Pages;

use App\Filament\User\Resources\QuizzesResource;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Quiz;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\Readability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use App\Services\ImageProcessingService;

class CreateQuizzes extends CreateRecord
{
    protected static string $resource = QuizzesResource::class;
    
    protected static bool $canCreateAnother = false;

    public static $tab = Quiz::TEXT_TYPE;

    protected function getProgressLabel(): string
    {
        return __('Create Exam');
    }

    public function currentActiveTab()
    {
        $pre = URL::previous();
        parse_str(parse_url($pre)['query'] ?? '', $queryParams);
        $tab = $queryParams['tab'] ?? null;
        $tabType = [
            '-subject-tab' => Quiz::SUBJECT_TYPE,
            '-text-tab' => Quiz::TEXT_TYPE,
            '-url-tab' => Quiz::URL_TYPE,
            '-upload-tab' => Quiz::UPLOAD_TYPE,
            '-image-tab' => Quiz::IMAGE_TYPE,
        ];

        return $tabType[$tab] ?? Quiz::TEXT_TYPE;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $userId = Auth::id();
            $activeTab = getTabType();

            $description = $data['quiz_description_text'] ?? $data['quiz_description_sub'] ?? $data['quiz_description_url'] ?? '';

            $input = [
                'user_id' => $userId,
                'title' => $data['title'],
                'category_id' => $data['category_id'],
                'quiz_description' => $description,
                'type' => $activeTab,
                'status' => 1,
                'quiz_type' => $data['quiz_type'] ?? 0,
                'max_questions' => $data['max_questions'] ?? 10,
                'diff_level' => $data['diff_level'] ?? 0,
                'unique_code' => generateUniqueCode(),
                'language' => $data['language'] ?? 'en',
                'time_configuration' => $data['time_configuration'] ?? 0,
                'time' => $data['time'] ?? 0,
                'time_type' => $data['time_type'] ?? null,
                'quiz_expiry_date' => $data['quiz_expiry_date'] ?? null,
                'generation_status' => 'completed',
                'generation_progress_total' => $data['max_questions'] ?? 10,
                'generation_progress_created' => $data['max_questions'] ?? 10,
            ];

            // Create quiz record
            $quiz = Quiz::create($input);

            // Show success notification
            Notification::make()
                ->success()
                ->title(__('Quiz Created Successfully'))
                ->body(__('Your quiz has been created successfully.'))
                ->send();

            return $quiz;

        } catch (\Exception $e) {
            Log::error("Quiz creation error: " . $e->getMessage());
            
            Notification::make()
                ->danger()
                ->title(__('Quiz Creation Failed'))
                ->body(__('Unable to create quiz. Please try again.'))
                ->send();
                
            throw $e;
        }
    }


    public function getTitle(): string
    {
        return __('messages.quiz.create_exam');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('messages.quiz.exam_created_success');
    }

    protected function getRedirectUrl(): string
    {
        $recordId = $this->record->id ?? null;
        return $recordId ? $this->getResource()::getUrl('edit', ['record' => $recordId]) : $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        $create = parent::getFormActions()[0]
            ->label(fn () => $this->getProgressLabel())
            ->icon('heroicon-o-plus')
            ->disabled(fn () => ((app(\App\Services\PlanValidationService::class)->canCreateExam()['allowed'] ?? true) === false))
            ->extraAttributes([
                'wire:target' => 'create',
                'wire:loading.attr' => 'disabled',
            ]);

        return [$create];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function mount(): void
    {
        parent::mount();
    }

    public function checkProgress(): void
    {
        $this->dispatch('$refresh');
    }
}