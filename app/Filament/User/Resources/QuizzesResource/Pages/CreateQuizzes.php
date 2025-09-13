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
        // Check database directly for current generation status
        $userId = auth()->id();
        if (!$userId) {
            return __('Create Exam');
        }
        
        $processingQuiz = Quiz::where('user_id', $userId)
            ->where('generation_status', 'processing')
            ->orderBy('created_at', 'desc')
            ->first();
            
        if (!$processingQuiz) {
            return __('Create Exam');
        }
        
        $progressTotal = $processingQuiz->generation_progress_total ?? 0;
        $progressDone = $processingQuiz->generation_progress_done ?? 0;
        
        if ($progressTotal <= 0) {
            return __('Create Exam');
        }
        
        $percentage = round(($progressDone / $progressTotal) * 100);
        return __('Creating exam (:done/:total) :percent%', [
            'done' => $progressDone,
            'total' => $progressTotal,
            'percent' => $percentage,
        ]);
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
        // Default active tab from user presets if available
        $presetTab = getUserSettings('preset_default_tab');
        $activeTab = $presetTab !== null ? (int) $presetTab : getTabType();

        $descriptionFields = [
            Quiz::TEXT_TYPE => $data['quiz_description_text'] ?? null,
            Quiz::SUBJECT_TYPE => $data['quiz_description_sub'] ?? null,
            Quiz::URL_TYPE => $data['quiz_description_url'] ?? null,
            Quiz::UPLOAD_TYPE => null, // Will be processed from file upload
            Quiz::IMAGE_TYPE => null, // Will be processed from image upload
        ];

        $description = $descriptionFields[$activeTab] ?? null;

        // Apply user presets as defaults if provided
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
        ];

        if ($activeTab == Quiz::URL_TYPE && $data['quiz_description_url'] != null) {
            // Inline state only; no popups

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

            // Enforce website token cap per plan
            $plan = app(\App\Services\PlanValidationService::class)->getUsageSummary();
            $userPlan = auth()->user()?->subscriptions()->where('status', \App\Enums\SubscriptionStatus::ACTIVE->value)->orderByDesc('id')->first()?->plan;
            $maxTokens = $userPlan?->max_website_tokens_allowed;
            if ($maxTokens && $maxTokens > 0) {
                $estimated = \App\Services\TokenEstimator::estimateTokens($description ?? '');
                if ($estimated > $maxTokens) {
                    // Truncate to allowed budget
                    $charsAllowed = $maxTokens * 4; // inverse of 4 chars ≈ 1 token
                    $description = mb_substr($description, 0, $charsAllowed, 'UTF-8');
                    \Filament\Notifications\Notification::make()
                        ->warning()
                        ->title(__('Content truncated to fit your plan limit'))
                        ->body(__('Your website content exceeded the allowed size for this plan. We used the first :tokens tokens.', ['tokens' => $maxTokens]))
                        ->send();
                }
            }
            $input['type'] = Quiz::URL_TYPE; // Set type to URL
        }

        if (isset($this->data['file_upload']) && is_array($this->data['file_upload'])) {
            // Process file silently; inline counter will handle UX

            foreach ($this->data['file_upload'] as $file) {
                if ($file instanceof \Illuminate\Http\UploadedFile) {
                    $filePath = $file->store('temp-file', 'public');
                    $fileUrl = Storage::disk('public')->url($filePath);
                    $extension = pathinfo($fileUrl, PATHINFO_EXTENSION);

                    if ($extension === 'pdf') {
                        $description = pdfToText($fileUrl);
                        // Best-effort page count: split on form feed or fallback by heuristics
                        $pages = substr_count($description, "\f");
                        $pages = $pages > 0 ? $pages : null;
                        $userPlan = auth()->user()?->subscriptions()->where('status', \App\Enums\SubscriptionStatus::ACTIVE->value)->orderByDesc('id')->first()?->plan;
                        if ($userPlan && $userPlan->max_pdf_pages_allowed && $userPlan->max_pdf_pages_allowed > 0 && $pages && $pages > $userPlan->max_pdf_pages_allowed) {
                            \Filament\Notifications\Notification::make()->danger()->title(__('This PDF is too large for your current plan. Please upgrade to a higher plan.'))->send();
                            $this->halt();
                        }
                        // Token budget guard as well
                        if ($userPlan && $userPlan->max_website_tokens_allowed) {
                            $estimated = \App\Services\TokenEstimator::estimateTokens($description ?? '');
                            $maxTokens = $userPlan->max_website_tokens_allowed; // reuse same cap for pdf text if set
                            if ($maxTokens > 0 && $estimated > $maxTokens) {
                                \Filament\Notifications\Notification::make()->danger()->title(__('Your file exceeds the allowed limit for this plan. Please upgrade to continue.'))->send();
                                $this->halt();
                            }
                        }
                        $input['type'] = Quiz::UPLOAD_TYPE; // Set type to upload
                    } elseif ($extension === 'docx') {
                        $description = docxToText($fileUrl);
                        $input['type'] = Quiz::UPLOAD_TYPE; // Set type to upload
                    }
                }
            }
        }

        // Process image uploads for OCR (silent)
        if (isset($this->data['image_upload']) && is_array($this->data['image_upload'])) {
            // No popups; keep inline indicator

            $imageProcessingService = new ImageProcessingService();
            $userPlan = auth()->user()?->subscriptions()->where('status', \App\Enums\SubscriptionStatus::ACTIVE->value)->orderByDesc('id')->first()?->plan;
            $maxImages = $userPlan?->max_images_allowed;
            if ($maxImages && $maxImages > 0 && count($this->data['image_upload']) > $maxImages) {
                \Filament\Notifications\Notification::make()->danger()->title(__('Your file exceeds the allowed limit for this plan. Please upgrade to continue.'))->send();
                $this->halt();
            }
            foreach ($this->data['image_upload'] as $file) {
                if ($file instanceof \Illuminate\Http\UploadedFile) {
                    if ($imageProcessingService->validateImageFile($file)) {
                        $extractedText = $imageProcessingService->processUploadedImage($file);
                        if ($extractedText) {
                            $description = $extractedText;
                            $input['type'] = Quiz::IMAGE_TYPE; // Set type to image
                            // Guard token budget for OCR result too
                            if ($userPlan && $userPlan->max_website_tokens_allowed) {
                                $estimated = \App\Services\TokenEstimator::estimateTokens($description ?? '');
                                $maxTokens = $userPlan->max_website_tokens_allowed;
                                if ($maxTokens > 0 && $estimated > $maxTokens) {
                                    \Filament\Notifications\Notification::make()->danger()->title(__('Your file exceeds the allowed limit for this plan. Please upgrade to continue.'))->send();
                                    $this->halt();
                                }
                            }
                            break; // Use first successfully processed image
                        }
                    }
                }
            }
        }

        if (strlen($description) > 10000) {
            $description = substr($description, 0, 10000).'...';
        }

        $quizData = [
            'Title' => $data['title'],
            'Description' => $description,
            'No of Questions' => $data['max_questions'],
            'Difficulty' => Quiz::DIFF_LEVEL[$data['diff_level']],
            'question_type' => Quiz::QUIZ_TYPE[$data['quiz_type']],
            'language' => getAllLanguages()[$data['language']] ?? 'English',
        ];

        // Generate dynamic prompt based on selected question type
        $questionType = $quizData['question_type'];
        $formatInstructions = '';
        $guidelines = '';

        switch ($questionType) {
            case 'Multiple Choices':
                $formatInstructions = <<<FORMAT
    **Format for Multiple Choice Questions:**
    - Structure your JSON with exactly four answer options
    - Mark exactly one option as `is_correct: true`
    - Use the following format:

    [
        {
            "question": "Your question text here",
            "answers": [
                {
                    "title": "Answer Option 1",
                    "is_correct": false
                },
                {
                    "title": "Answer Option 2",
                    "is_correct": true
                },
                {
                    "title": "Answer Option 3",
                    "is_correct": false
                },
                {
                    "title": "Answer Option 4",
                    "is_correct": false
                }
            ],
            "correct_answer_key": "Answer Option 2"
        }
    ]
    FORMAT;
                $guidelines = '- You must generate exactly **' . $data['max_questions'] . '** Multiple Choice questions with exactly four answer options each, with one option marked as `is_correct: true`.';
                break;

            case 'Single Choice':
                $formatInstructions = <<<FORMAT
    **Format for Single Choice Questions:**
    - Structure your JSON with exactly two answer options
    - Mark exactly one option as `is_correct: true`
    - Use the following format:

    [
        {
            "question": "Your question text here",
            "answers": [
                {
                    "title": "Answer Option 1",
                    "is_correct": false
                },
                {
                    "title": "Answer Option 2",
                    "is_correct": true
                }
            ],
            "correct_answer_key": "Answer Option 2"
        }
    ]
    FORMAT;
                $guidelines = '- You must generate exactly **' . $data['max_questions'] . '** Single Choice questions with exactly two answer options each, with one option marked as `is_correct: true`.';
                break;

            case 'Short Answer':
                $formatInstructions = <<<FORMAT
    **Format for Short Answer Questions:**
    - Structure your JSON with one correct answer
    - Use the following format:

    [
        {
            "question": "Your question text here",
            "answers": [
                {
                    "title": "Expected short answer",
                    "is_correct": true
                }
            ],
            "correct_answer_key": "Expected short answer"
        }
    ]
    FORMAT;
                $guidelines = '- You must generate exactly **' . $data['max_questions'] . '** Short Answer questions with one correct answer each.';
                break;

            case 'Long Answer':
                $formatInstructions = <<<FORMAT
    **Format for Long Answer Questions:**
    - Structure your JSON with one detailed correct answer
    - Use the following format:

    [
        {
            "question": "Your question text here",
            "answers": [
                {
                    "title": "Expected detailed answer",
                    "is_correct": true
                }
            ],
            "correct_answer_key": "Expected detailed answer"
        }
    ]
    FORMAT;
                $guidelines = '- You must generate exactly **' . $data['max_questions'] . '** Long Answer questions with one detailed correct answer each.';
                break;

            case 'True/False':
                $formatInstructions = <<<FORMAT
    **Format for True/False Questions:**
    - Structure your JSON with exactly two options: "True" and "False"
    - Mark one option as `is_correct: true`
    - Use the following format:

    [
        {
            "question": "Your question text here",
            "answers": [
                {
                    "title": "True",
                    "is_correct": true
                },
                {
                    "title": "False",
                    "is_correct": false
                }
            ],
            "correct_answer_key": "True"
        }
    ]
    FORMAT;
                $guidelines = '- You must generate exactly **' . $data['max_questions'] . '** True/False questions with exactly two options each: "True" and "False", with one marked as correct.';
                break;

            case 'Fill in the Blank':
                $formatInstructions = <<<FORMAT
    **Format for Fill in the Blank Questions:**
    - Structure your JSON with one correct answer
    - Use underscores (_____) in the question text for the blank
    - Use the following format:

    [
        {
            "question": "Your question text with _____ blank here",
            "answers": [
                {
                    "title": "Correct word/phrase",
                    "is_correct": true
                }
            ],
            "correct_answer_key": "Correct word/phrase"
        }
    ]
    FORMAT;
                $guidelines = '- You must generate exactly **' . $data['max_questions'] . '** Fill in the Blank questions with underscores (_____) in the question text and one correct word/phrase as the answer.';
                break;
        }

        $prompt = <<<PROMPT

    You are an expert in crafting engaging quizzes. Based on the quiz details provided, your task is to meticulously generate questions according to the specified question type. Your output should be exclusively in properly formatted JSON.

    **Quiz Details:**

    - **Title**: {$data['title']}
    - **Description**: {$description}
    - **Number of Questions**: {$data['max_questions']}
    - **Difficulty**: {$quizData['Difficulty']}
    - **Question Type**: {$quizData['question_type']}

    **Instructions:**

    1. **Language Requirement**: Write all quiz questions and answers in {$data['language']}. If the language is Hindi (hi), use proper Devanagari script with correct Hindi characters and grammar.
    2. **CRITICAL - Number of Questions**: You MUST create EXACTLY {$data['max_questions']} questions. Not more, not less. Count them carefully.
    3. **Difficulty Level**: Ensure each question adheres to the specified difficulty level: {$quizData['Difficulty']}.
    4. **Description Alignment**: Ensure that each question is relevant to and reflects key aspects of the provided description.
    5. **Question Type**: ALL questions must be of the type: {$quizData['question_type']}. Do not mix different question types.
    6. **Format**: Follow the format specified below for the selected question type ONLY:

    {$formatInstructions}

    **Guidelines:**
    {$guidelines}
    - The correct_answer_key should match the correct answer's title value.
    - Ensure that each question is diverse and well-crafted, covering various relevant concepts.
    - Do not create questions of any other type - only {$quizData['question_type']} questions.
    - **IMPORTANT**: Before submitting, count your questions to ensure you have created exactly {$data['max_questions']} questions.

    **Final Check**: Your JSON response must contain exactly {$data['max_questions']} question objects in the array.

    Your responses should be formatted impeccably in JSON, capturing the essence of the provided quiz details.

    PROMPT;

        $aiType = getSetting()->ai_type;

        $totalQuestions = (int) $data['max_questions'];
        $quizText = null; // Initialize quizText variable

        if ($aiType == Quiz::GEMINI_AI) {
            $geminiApiKey = getSetting()->gemini_api_key;
            $model = getSetting()->gemini_ai_model;

            if (! $geminiApiKey) {
                Notification::make()
                    ->danger()
                    ->title(__('messages.quiz.set_openai_key_at_env'))
                    ->send();
                $this->halt();
            }

            $geminiResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$geminiApiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
            ]);

            if ($geminiResponse->failed()) {
                Notification::make()
                    ->danger()
                    ->title($geminiResponse->json()['error']['message'])
                    ->send();
                $this->halt();
            }

            $rawText = $geminiResponse->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;
            $quizText = preg_replace('/^```(?:json)?|```$/im', '', $rawText);
        }
        if ($aiType == Quiz::OPEN_AI) {
            $key = getSetting()->open_api_key;
            $openAiKey = (! empty($key)) ? $key : config('services.open_ai.open_api_key');
            $model = getSetting()->open_ai_model;

            if (! $openAiKey) {
                Notification::make()
                    ->danger()
                    ->title(__('messages.quiz.set_openai_key_at_env'))
                    ->send();
                $this->halt();
            }

            try {
                // Dynamic timeout based on question count
                $timeout = $data['max_questions'] > 20 ? 300 : 180; // 5 minutes for large requests
                
                $quizResponse = Http::withToken($openAiKey)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                    ])
                    ->timeout($timeout)
                    ->retry(3, 2000)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $model,
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => $prompt,
                            ],
                        ],
                    ]);
            } catch (\Exception $e) {
                Notification::make()
                    ->danger()
                    ->title(__('API Connection Failed'))
                    ->body(__('Unable to connect to OpenAI API. Please try again or contact support if the issue persists.'))
                    ->send();
                Log::error('OpenAI API connection error: ' . $e->getMessage());
                $this->halt();
            }

            if ($quizResponse->failed()) {
                $error = $quizResponse->json()['error']['message'] ?? 'Unknown error occurred';
                Notification::make()->danger()->title(__('OpenAI Error'))->body($error)->send();
                $this->halt();
            }

            $quizText = $quizResponse['choices'][0]['message']['content'] ?? null;
            
            // AI response received - continue to DB creation phase
            if ($quizText) {
                // keep inline indicator
            }
        }

        // Always dispatch async job for better performance and progress tracking
        try {
            $quiz = Quiz::create($input + [
                'generation_status' => 'processing',
                'generation_progress_total' => $totalQuestions,
                'generation_progress_done' => 0,
            ]);

            \Log::info("Created quiz {$quiz->id} for async processing");

            $model = getSetting()->open_ai_model;
            if (empty($model)) {
                $model = 'gpt-4o-mini';
            }
            
            // Progress will be tracked via database and UI refresh
            
            \App\Jobs\GenerateQuizJob::dispatch(
                quizId: $quiz->id,
                model: $model,
                prompt: $prompt,
                totalQuestions: $totalQuestions,
                batchSize: 25
            );

            \Log::info("Dispatched GenerateQuizJob for quiz {$quiz->id}");

            return $quiz;
        } catch (\Throwable $e) {
            \Log::error("Failed to create quiz or dispatch job: " . $e->getMessage());
            $this->halt();
        }

        // This code is now handled by GenerateQuizJob
        // All quiz creation is now async for better performance and progress tracking
        $this->halt();
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

        $actions = [$create];
        
        // Add progress indicator if generating - check database directly
        $userId = auth()->id();
        $isCurrentlyGenerating = false;
        if ($userId) {
            $isCurrentlyGenerating = Quiz::where('user_id', $userId)
                ->where('generation_status', 'processing')
                ->exists();
        }
        
        if ($isCurrentlyGenerating) {
            $actions[] = Action::make('progress')
                ->label(__('Generating...'))
                ->disabled()
                ->color('info')
                ->icon('heroicon-o-clock')
                ->extraAttributes([
                    'class' => 'animate-pulse',
                ]);
        }
        
        $actions[] = Action::make('cancel')
            ->label(__('messages.common.cancel'))
            ->color('gray')
            ->url(QuizzesResource::getUrl('index'));
            
        return $actions;
    }

    protected function getHeaderActions(): array
    {
        // Exams Remaining button removed as requested
        return [];
    }

    public function mount(): void
    {
        parent::mount();
        
        // Add JavaScript to periodically check progress
        $this->js('
            // Define function in global scope
            window.checkQuizProgress = function() {
                if (window.livewire) {
                    window.livewire.find("' . $this->getId() . '").call("checkProgress");
                }
            };
            
            // Check if there are any processing quizzes
            let hasProcessingQuiz = false;
            try {
                // This will be updated by checkProgress method
                hasProcessingQuiz = ' . (Quiz::where('user_id', auth()->id())->where('generation_status', 'processing')->exists() ? 'true' : 'false') . ';
            } catch (e) {
                hasProcessingQuiz = false;
            }
            
            // Only start interval if there are processing quizzes
            if (hasProcessingQuiz) {
                window.progressInterval = setInterval(window.checkQuizProgress, 3000);
            }
        ');
    }
    
    public function checkProgress(): void
    {
        $userId = auth()->id();
        if (!$userId) return;
        
        // Find any quiz that's currently being processed by this user
        $processingQuiz = Quiz::where('user_id', $userId)
            ->where('generation_status', 'processing')
            ->orderBy('created_at', 'desc')
            ->first();
            
        if ($processingQuiz) {
            // If completed, redirect to edit page
            if ($processingQuiz->generation_status === 'completed') {
                // Stop the interval and redirect
                $this->js('
                    // Clear any existing intervals
                    if (window.progressInterval) {
                        clearInterval(window.progressInterval);
                    }
                    
                    setTimeout(function() {
                        window.location.href = "/user/quizzes/' . $processingQuiz->id . '/edit";
                    }, 1000);
                ');
                return;
            }
        } else {
            // No processing quiz found, stop the interval
            $this->js('
                if (window.progressInterval) {
                    clearInterval(window.progressInterval);
                }
            ');
            return;
        }
        
        // Refresh the page to update the UI
        $this->dispatch('$refresh');
    }
}