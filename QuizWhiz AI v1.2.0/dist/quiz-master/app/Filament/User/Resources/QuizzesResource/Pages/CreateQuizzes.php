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
    
    // Inline progress state
    public bool $isProcessing = false;
    public int $progressTotal = 0;
    public int $progressCreated = 0;

    protected function getProgressLabel(): string
    {
        if (! $this->isProcessing || $this->progressTotal <= 0) {
            return '';
        }
        $percent = (int) round(($this->progressCreated / max(1, $this->progressTotal)) * 100);
        return __('Creating... :created/:total (:percent%)', [
            'created' => $this->progressCreated,
            'total' => $this->progressTotal,
            'percent' => $percent,
        ]);
    }

    protected static bool $canCreateAnother = false;

    public static $tab = Quiz::TEXT_TYPE;

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
        // Initialize inline progress state (total is set after form data read)
        $this->isProcessing = false; // Do not show progress until job is dispatched
        $this->progressTotal = 0;
        $this->progressCreated = 0;

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
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false || !empty($curlError)) {
                throw new \Exception('Failed to fetch URL content: ' . ($curlError ?: 'Unknown cURL error'));
            }

            if ($httpCode != 200) {
                throw new \Exception('Failed to fetch the URL content. HTTP Code: '.$httpCode);
            }

            try {
                $readability = new Readability(new Configuration);
                $readability->parse($response);
                $readability->getContent();
                $description = $readability->getExcerpt();
                
                if (empty($description)) {
                    throw new \Exception('Unable to extract readable content from the URL');
                }
            } catch (\Exception $e) {
                throw new \Exception('Failed to parse URL content: ' . $e->getMessage());
            }

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

        if ($description && strlen($description) > 10000) {
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

        // Initialize total for inline progress
        $totalQuestions = (int) $data['max_questions'];
        $this->progressTotal = $totalQuestions;

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

        // Always dispatch async job for automatic quiz generation
        if ($totalQuestions > 0) {
            try {
                $quiz = Quiz::create($input + [
                    'generation_status' => 'processing',
                    'generation_progress_total' => $totalQuestions,
                    'generation_progress_done' => 0,
                ]);

                \Log::info("Created quiz {$quiz->id} for automatic async processing");

                $model = getSetting()->open_ai_model;
                if (empty($model)) {
                    $model = 'gpt-4o-mini';
                }
                
                \App\Jobs\GenerateQuizJob::dispatch(
                    quizId: $quiz->id,
                    model: $model,
                    prompt: $prompt,
                    totalQuestions: $totalQuestions,
                    batchSize: $totalQuestions >= 50 ? 10 : 5
                );

                \Log::info("Dispatched GenerateQuizJob for quiz {$quiz->id} with {$totalQuestions} questions");

                // Set UI inline progress and return immediately; page will poll quiz status
                $this->isProcessing = true;
                $this->progressTotal = $totalQuestions;
                $this->progressCreated = 0;
                return $quiz;
            } catch (\Throwable $e) {
                \Log::error("Failed to create quiz or dispatch job: " . $e->getMessage());
                $this->halt();
            }
        }

        if ($quizText) {
            $quizData = trim($quizText);
            if (stripos($quizData, '```json') === 0) {
                $quizData = preg_replace('/^```json\s*|\s*```$/', '', $quizData);
                $quizData = trim($quizData);
            }
            $quizQuestions = json_decode($quizData, true);

            // Validate that we got valid questions
            if (!is_array($quizQuestions) || empty($quizQuestions)) {
                $this->isProcessing = false;
                $this->progressTotal = 0;
                $this->progressCreated = 0;
                $this->halt();
            }

            // Normalize to requested count; allow partials and slice extras
            $requestedQuestions = (int) $data['max_questions'];
            $generatedQuestions = count($quizQuestions);
            if ($generatedQuestions > $requestedQuestions) {
                $quizQuestions = array_slice($quizQuestions, 0, $requestedQuestions);
                $generatedQuestions = count($quizQuestions);
            } elseif ($generatedQuestions < $requestedQuestions) {
                if ($generatedQuestions < 1) {
                    $this->isProcessing = false;
                    $this->progressTotal = 0;
                    $this->progressCreated = 0;
                    $this->halt();
                }
            }

            $quiz = Quiz::create($input);

            $questionsCreated = 0;
            $totalQuestions = count($quizQuestions);
            
            foreach ($quizQuestions as $index => $question) {
                if (isset($question['question'], $question['answers'])) {
                    $questionModel = Question::create([
                        'quiz_id' => $quiz->id,
                        'title' => $question['question'],
                    ]);

                    foreach ($question['answers'] as $answer) {
                        $isCorrect = false;
                        $correctKey = $question['correct_answer_key'];

                        if (is_array($correctKey)) {
                            $isCorrect = in_array($answer['title'], $correctKey);
                        } else {
                            $isCorrect = $answer['title'] === $correctKey;
                        }

                        Answer::create([
                            'question_id' => $questionModel->id,
                            'title' => $answer['title'],
                            'is_correct' => $isCorrect,
                        ]);
                    }
                    $questionsCreated++;
                    // Update inline progress for each question
                    $this->progressCreated = $questionsCreated;
                }
            }

            // Only show success if questions were actually created
            if ($questionsCreated > 0) {
                // Update monthly usage counters (1 exam, N questions)
                try {
                    app(\App\Services\PlanValidationService::class)->updateUsage(1, $questionsCreated);
                } catch (\Throwable $e) {
                    // Silently ignore counter update errors to not block creation
                }

                // Mark complete; Filament will redirect per getRedirectUrl
                $this->isProcessing = false;

                return $quiz;
            } else {
                // Delete the quiz if no questions were created
                $quiz->delete();
                $this->isProcessing = false;
                $this->progressTotal = 0;
                $this->progressCreated = 0;
                $this->halt();
            }
        }

        $this->isProcessing = false;
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
            ->label(__('Create Exam'))
            ->icon('heroicon-o-plus')
            ->disabled(fn () => ($this->isProcessing || (app(\App\Services\PlanValidationService::class)->canCreateExam()['allowed'] ?? true) === false))
            ->extraAttributes([
                'wire:target' => 'create',
                'wire:loading.attr' => 'disabled',
            ]);

        $progress = Action::make('progress')
            ->label(fn () => ($this->getProgressLabel() !== '' ? $this->getProgressLabel() : __('Creating... Please wait')))
            ->disabled()
            ->color('gray')
            ->extraAttributes(fn () => [
                'class' => $this->isProcessing ? '' : 'hidden',
                'wire:loading.class.remove' => 'hidden',
                'wire:loading' => '',
                'wire:target' => 'create',
            ]);

        return [
            $create,
            $progress,
            Action::make('cancel')->label(__('messages.common.cancel'))->color('gray')->url(QuizzesResource::getUrl('index')),
        ];
    }

    protected function getHeaderActions(): array
    {
        $planCheck = app(\App\Services\PlanValidationService::class)->canCreateExam();
        $examsRemaining = isset($planCheck['remaining']) ? $planCheck['remaining'] : 0;
        
        $actions = [];
        $actions[] = Action::make('exams_remaining')
            ->label(__('Exams Remaining: ') . ($examsRemaining === -1 ? __('Unlimited') : $examsRemaining))
            ->color($examsRemaining > 10 ? 'success' : ($examsRemaining > 0 ? 'warning' : 'danger'))
            ->disabled()
            ->icon('heroicon-o-clipboard-document-list');

        // If no remaining exams, show disabled Create + upgrade hint
        if ($examsRemaining === 0) {
            $actions[] = Action::make('limit_reached')
                ->label(__('Monthly exam limit reached'))
                ->color('danger')
                ->disabled();
        }

        return $actions;
    }

    public function mount(): void
    {
        parent::mount();
        
        // Inline progress tracking for Create Exam button
        $this->js('
            console.log("Create page loaded");
            
            function checkProgress() {
                fetch("/api/quiz-progress")
                .then(response => response.json())
                .then(data => {
                    if (data.quiz && data.quiz.status === "processing") {
                        updateCreateButton(data.quiz);
                    } else if (data.quiz && data.quiz.status === "completed") {
                        redirectToEdit(data.quiz.id);
                    } else {
                        resetCreateButton();
                    }
                })
                .catch(error => console.error("API Error:", error));
            }
            
            function updateCreateButton(quiz) {
                var createButton = document.querySelector("button[type=\\"submit\\"]");
                if (createButton) {
                    var progressText = "Creating Exam (" + quiz.progress_done + "/" + quiz.progress_total + ")";
                    createButton.textContent = progressText;
                    createButton.disabled = true;
                    createButton.style.opacity = "0.7";
                }
            }
            
            function resetCreateButton() {
                var createButton = document.querySelector("button[type=\\"submit\\"]");
                if (createButton) {
                    createButton.textContent = "Create Exam";
                    createButton.disabled = false;
                    createButton.style.opacity = "1";
                }
            }
            
            function redirectToEdit(quizId) {
                var createButton = document.querySelector("button[type=\\"submit\\"]");
                if (createButton) {
                    createButton.textContent = "✅ Completed! Redirecting...";
                    createButton.style.background = "#10b981";
                }
                setTimeout(() => { 
                    window.location.href = "/user/quizzes/" + quizId + "/edit"; 
                }, 1500);
            }
            
            // Start checking progress
            setTimeout(checkProgress, 3000);
            setInterval(checkProgress, 3000);
        ');
    }
}
