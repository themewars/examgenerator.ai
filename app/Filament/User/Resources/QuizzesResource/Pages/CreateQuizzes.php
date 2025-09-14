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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            $userId = Auth::id();
            $activeTab = getTabType();

            // Handle description from different sources
            $description = '';
            if (isset($data['quiz_description_text']) && !empty($data['quiz_description_text'])) {
                $description = $data['quiz_description_text'];
            } elseif (isset($data['quiz_description_sub']) && !empty($data['quiz_description_sub'])) {
                $description = $data['quiz_description_sub'];
            } elseif (isset($data['quiz_description_url']) && !empty($data['quiz_description_url'])) {
                $description = $data['quiz_description_url'];
            }

            // Remove form fields that don't exist in database
            unset($data['quiz_description_text']);
            unset($data['quiz_description_sub']);
            unset($data['quiz_description_url']);

            // Set required fields
            $data['user_id'] = $userId;
            $data['quiz_description'] = $description;
            $data['type'] = $activeTab;
            $data['status'] = 1;
            $data['unique_code'] = generateUniqueCode();
            $data['generation_status'] = 'completed';
            $data['generation_progress_total'] = $data['max_questions'] ?? 10;
            $data['generation_progress_done'] = $data['max_questions'] ?? 10;

            return $data;
        } catch (\Exception $e) {
            // If any error occurs, return original data
            return $data;
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