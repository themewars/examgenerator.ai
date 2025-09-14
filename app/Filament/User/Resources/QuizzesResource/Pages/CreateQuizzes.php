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
        $userId = Auth::id();
        $presetTab = getUserSettings('preset_default_tab');
        $activeTab = $presetTab !== null ? (int) $presetTab : getTabType();

        $descriptionFields = [
            Quiz::TEXT_TYPE => $data['quiz_description_text'] ?? null,
            Quiz::SUBJECT_TYPE => $data['quiz_description_sub'] ?? null,
            Quiz::URL_TYPE => $data['quiz_description_url'] ?? null,
            Quiz::UPLOAD_TYPE => null,
            Quiz::IMAGE_TYPE => null,
        ];

        $description = $descriptionFields[$activeTab] ?? null;

        // Apply user presets
        $presetLanguage = getUserSettings('preset_language');
        $presetDifficulty = getUserSettings('preset_difficulty');
        $presetQuestionType = getUserSettings('preset_question_type');
        $presetQuestionCount = getUserSettings('preset_question_count');

        $input = [
            'user_id' => $userId,
            'title' => $data['title'],
            'category_id' => $data['category_id'],
            'quiz_description' => $description,
            'type' => $activeTab,
            'status' => 1,
            'quiz_type' => $data['quiz_type'] ?? ($presetQuestionType ?? 0),
            'max_questions' => $data['max_questions'] ?? ($presetQuestionCount ?? 0),
            'diff_level' => $data['diff_level'] ?? ($presetDifficulty ?? 0),
            'unique_code' => generateUniqueCode(),
            'language' => $data['language'] ?? ($presetLanguage ?? 'en'),
            'time_configuration' => $data['time_configuration'] ?? 0,
            'time' => $data['time'] ?? 0,
            'time_type' => $data['time_type'] ?? null,
            'quiz_expiry_date' => $data['quiz_expiry_date'] ?? null,
            'generation_status' => 'processing',
            'generation_progress_total' => 0,
            'generation_progress_created' => 0,
        ];

        // Handle URL content extraction
        if ($activeTab == Quiz::URL_TYPE && $data['quiz_description_url'] != null) {
            $url = $data['quiz_description_url'];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode != 200) {
                throw new \Exception('Failed to fetch the URL content. HTTP Code: '.$httpCode);
            }

            $readability = new Readability(new Configuration);
            $readability->parse($response);
            $readability->getContent();
            $description = $readability->getExcerpt();
            $input['quiz_description'] = $description;

            // Enforce website token cap per plan
            $plan = app(\App\Services\PlanValidationService::class)->getUsageSummary();
            $userPlan = auth()->user()?->subscriptions()->where('status', \App\Enums\SubscriptionStatus::ACTIVE->value)->orderByDesc('id')->first()?->plan;
            $maxTokens = $userPlan?->max_website_tokens_allowed;
            if ($maxTokens && $maxTokens > 0) {
                $estimated = \App\Services\TokenEstimator::estimateTokens($description ?? '');
                if ($estimated > $maxTokens) {
                    $charsAllowed = $maxTokens * 4;
                    $description = mb_substr($description, 0, $charsAllowed, 'UTF-8');
                    Notification::make()
                        ->warning()
                        ->title(__('Content truncated to fit your plan limit'))
                        ->body(__('Your website content exceeded the allowed size for this plan. We used the first :tokens tokens.', ['tokens' => $maxTokens]))
                        ->send();
                }
            }
            $input['type'] = Quiz::URL_TYPE;
        }

        // Handle file uploads
        if (isset($data['file_upload']) && is_array($data['file_upload'])) {
            foreach ($data['file_upload'] as $file) {
                if ($file instanceof \Illuminate\Http\UploadedFile) {
                    $filePath = $file->store('temp-file', 'public');
                    $fileUrl = Storage::disk('public')->url($filePath);
                    $extension = pathinfo($fileUrl, PATHINFO_EXTENSION);

                    if ($extension === 'pdf') {
                        $description = pdfToText($fileUrl);
                        $input['quiz_description'] = $description;
                        $pages = substr_count($description, "\f");
                        $pages = $pages > 0 ? $pages : null;
                        $userPlan = auth()->user()?->subscriptions()->where('status', \App\Enums\SubscriptionStatus::ACTIVE->value)->orderByDesc('id')->first()?->plan;
                        if ($userPlan && $userPlan->max_pdf_pages_allowed && $userPlan->max_pdf_pages_allowed > 0 && $pages && $pages > $userPlan->max_pdf_pages_allowed) {
                            Notification::make()->danger()->title(__('This PDF is too large for your current plan. Please upgrade to a higher plan.'))->send();
                            $this->halt();
                        }
                        if ($userPlan && $userPlan->max_website_tokens_allowed) {
                            $estimated = \App\Services\TokenEstimator::estimateTokens($description ?? '');
                            $maxTokens = $userPlan->max_website_tokens_allowed;
                            if ($maxTokens > 0 && $estimated > $maxTokens) {
                                Notification::make()->danger()->title(__('Your file exceeds the allowed limit for this plan. Please upgrade to continue.'))->send();
                                $this->halt();
                            }
                        }
                        $input['type'] = Quiz::UPLOAD_TYPE;
                    } elseif ($extension === 'docx') {
                        $description = docxToText($fileUrl);
                        $input['quiz_description'] = $description;
                        $input['type'] = Quiz::UPLOAD_TYPE;
                    }
                }
            }
        }

        // Handle image uploads
        if (isset($data['image_upload']) && is_array($data['image_upload'])) {
            $imageProcessingService = new ImageProcessingService();
            $userPlan = auth()->user()?->subscriptions()->where('status', \App\Enums\SubscriptionStatus::ACTIVE->value)->orderByDesc('id')->first()?->plan;
            $maxImages = $userPlan?->max_images_allowed;
            if ($maxImages && $maxImages > 0 && count($data['image_upload']) > $maxImages) {
                Notification::make()->danger()->title(__('Your file exceeds the allowed limit for this plan. Please upgrade to continue.'))->send();
                $this->halt();
            }
            foreach ($data['image_upload'] as $file) {
                if ($file instanceof \Illuminate\Http\UploadedFile) {
                    if ($imageProcessingService->validateImageFile($file)) {
                        $extractedText = $imageProcessingService->processUploadedImage($file);
                        if ($extractedText) {
                            $description = $extractedText;
                            $input['quiz_description'] = $description;
                            $input['type'] = Quiz::IMAGE_TYPE;
                            if ($userPlan && $userPlan->max_website_tokens_allowed) {
                                $estimated = \App\Services\TokenEstimator::estimateTokens($description ?? '');
                                $maxTokens = $userPlan->max_website_tokens_allowed;
                                if ($maxTokens > 0 && $estimated > $maxTokens) {
                                    Notification::make()->danger()->title(__('Your file exceeds the allowed limit for this plan. Please upgrade to continue.'))->send();
                                    $this->halt();
                                }
                            }
                            break;
                        }
                    }
                }
            }
        }

        if (strlen($description) > 10000) {
            $description = substr($description, 0, 10000).'...';
            $input['quiz_description'] = $description;
        }

        $totalQuestions = (int) $data['max_questions'];

        // Create quiz record first
        $quiz = Quiz::create($input);

        // Show initial notification
                Notification::make()
            ->info()
            ->title(__('Exam Creation Started'))
            ->body(__('Your exam is being created. You will be notified when it\'s ready.'))
                    ->send();

        // Dispatch job for async processing
        \App\Jobs\GenerateQuizJob::dispatch($quiz->id, $data, $totalQuestions)
            ->onQueue('quiz-generation')
            ->delay(now()->addSeconds(2));

        // Show progress notification for large exams
        if ($totalQuestions >= 20) {
                Notification::make()
                ->info()
                ->title(__('Large Exam Processing'))
                ->body(__('Your exam has :count questions and will take some time to generate. You will be notified when ready.', ['count' => $totalQuestions]))
                    ->send();
        }

                return $quiz;
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