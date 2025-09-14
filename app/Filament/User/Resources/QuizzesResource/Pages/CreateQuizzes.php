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
                            return null;
                        }
                        if ($userPlan && $userPlan->max_website_tokens_allowed) {
                            $estimated = \App\Services\TokenEstimator::estimateTokens($description ?? '');
                            $maxTokens = $userPlan->max_website_tokens_allowed;
                            if ($maxTokens > 0 && $estimated > $maxTokens) {
                                Notification::make()->danger()->title(__('Your file exceeds the allowed limit for this plan. Please upgrade to continue.'))->send();
                                return null;
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
                return null;
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
                                    return null;
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

        // For now, use synchronous processing to avoid Livewire issues
        try {
            $this->generateQuestionsSynchronously($quiz, $data, $totalQuestions);
        } catch (\Exception $e) {
            Log::error("Quiz generation failed: " . $e->getMessage());
            $quiz->update(['generation_status' => 'failed']);
            Notification::make()
                ->danger()
                ->title(__('Quiz Creation Failed'))
                ->body(__('Unable to create quiz. Please try again.'))
                ->send();
        }

        return $quiz;
    }

    private function generateQuestionsSynchronously(Quiz $quiz, array $data, int $totalQuestions): void
    {
        $aiType = getSetting()->ai_type;
        $prompt = $this->buildPrompt($quiz, $data, $totalQuestions);

        if ($aiType == Quiz::OPEN_AI) {
            $questions = $this->generateWithOpenAI($prompt, $totalQuestions);
        } elseif ($aiType == Quiz::GEMINI_AI) {
            $questions = $this->generateWithGemini($prompt, $totalQuestions);
        } else {
            throw new \Exception('Unsupported AI type');
        }

        if (empty($questions)) {
            throw new \Exception('No questions generated');
        }

        // Create questions and answers
        $this->createQuestionsAndAnswers($quiz, $questions);

        // Update quiz status
        $quiz->update([
            'generation_status' => 'completed',
            'generation_progress_total' => $totalQuestions,
            'generation_progress_created' => count($questions),
        ]);

        // Send success notification
        Notification::make()
            ->success()
            ->title(__('Quiz Created Successfully'))
            ->body(__('Your quiz has been created with :count questions.', ['count' => count($questions)]))
            ->send();
    }

    private function buildPrompt(Quiz $quiz, array $data, int $totalQuestions): string
    {
        $questionType = Quiz::QUIZ_TYPE[$quiz->quiz_type];
        $difficulty = Quiz::DIFF_LEVEL[$quiz->diff_level];
        $language = getAllLanguages()[$quiz->language] ?? 'English';

        $formatInstructions = $this->getFormatInstructions($questionType);
        $guidelines = $this->getGuidelines($questionType, $totalQuestions);

        return <<<PROMPT
Generate EXACTLY {$totalQuestions} {$questionType} questions.

Title: {$quiz->title}
Subject: {$quiz->quiz_description}
Difficulty: {$difficulty}
Language: {$language}

Format:
{$formatInstructions}

Guidelines:
{$guidelines}

CRITICAL REQUIREMENTS:
1. Generate EXACTLY {$totalQuestions} questions - NO MORE, NO LESS
2. Count your questions before responding
3. Ensure each question has proper answers
4. Return ONLY the JSON array with exactly {$totalQuestions} question objects

VERIFICATION: Your response must contain exactly {$totalQuestions} question objects in the JSON array.

Return ONLY JSON array with {$totalQuestions} question objects. No explanations, no additional text.
PROMPT;
    }

    private function getFormatInstructions(string $questionType): string
    {
        switch ($questionType) {
            case 'Multiple Choices':
                return <<<FORMAT
[
    {
        "question": "Your question here?",
        "answers": [
            {"title": "Option A", "is_correct": false},
            {"title": "Option B", "is_correct": true},
            {"title": "Option C", "is_correct": false},
            {"title": "Option D", "is_correct": false}
        ]
    }
]
FORMAT;
            case 'True/False':
                return <<<FORMAT
[
    {
        "question": "Your statement here?",
        "answers": [
            {"title": "True", "is_correct": true},
            {"title": "False", "is_correct": false}
        ]
    }
]
FORMAT;
            case 'Fill in the Blank':
                return <<<FORMAT
[
    {
        "question": "Complete this sentence: The capital of France is _____.?",
        "answers": [
            {"title": "Paris", "is_correct": true}
        ]
    }
]
FORMAT;
            default:
                return '';
        }
    }

    private function getGuidelines(string $questionType, int $totalQuestions): string
    {
        switch ($questionType) {
            case 'Multiple Choices':
                return "- You must generate exactly **{$totalQuestions}** Multiple Choice questions with 4 options each, where only one option is correct.";
            case 'True/False':
                return "- You must generate exactly **{$totalQuestions}** True/False questions with only True or False as options.";
            case 'Fill in the Blank':
                return "- You must generate exactly **{$totalQuestions}** Fill in the Blank questions with underscores (_____) in the question text and one correct word/phrase as the answer.";
            default:
                return "- Generate exactly **{$totalQuestions}** questions of the specified type.";
        }
    }

    private function generateWithOpenAI(string $prompt, int $totalQuestions): array
    {
        $openAiKey = getSetting()->open_api_key;
        $model = getSetting()->open_ai_model ?? 'gpt-4o-mini';

        if (!$openAiKey) {
            throw new \Exception('OpenAI API key not configured');
        }

        $maxRetries = 3;
        $questions = [];

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            Log::info("OpenAI attempt {$attempt} for {$totalQuestions} questions");

            try {
                $response = Http::withToken($openAiKey)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(120)
                    ->retry(2, 1000)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'user', 'content' => $prompt]
                        ],
                        'temperature' => 0.7,
                        'max_tokens' => 4000,
                    ]);

                if ($response->failed()) {
                    $error = $response->json()['error']['message'] ?? 'Unknown error';
                    Log::error("OpenAI API error on attempt {$attempt}: {$error}");
                    
                    if ($attempt === $maxRetries) {
                        throw new \Exception("OpenAI API failed after {$maxRetries} attempts: {$error}");
                    }
                    continue;
                }

                $content = $response['choices'][0]['message']['content'] ?? null;
                if (!$content) {
                    Log::warning("Empty response from OpenAI on attempt {$attempt}");
                    continue;
                }

                $questions = $this->parseQuestions($content);
                
                if (count($questions) === $totalQuestions) {
                    Log::info("Successfully generated {$totalQuestions} questions on attempt {$attempt}");
                    break;
                } else {
                    Log::warning("Attempt {$attempt}: Generated " . count($questions) . " questions instead of {$totalQuestions}");
                    
                    if ($attempt < $maxRetries) {
                        $prompt .= "\n\n🚨 RETRY ATTEMPT {$attempt}: You previously failed to generate exactly {$totalQuestions} questions. You MUST generate EXACTLY {$totalQuestions} questions this time. Count them carefully!";
                    }
                }

            } catch (\Exception $e) {
                Log::error("OpenAI attempt {$attempt} failed: " . $e->getMessage());
                
                if ($attempt === $maxRetries) {
                    throw $e;
                }
            }
        }

        if (count($questions) !== $totalQuestions) {
            Log::warning("Final result: Generated " . count($questions) . " questions instead of {$totalQuestions}");
            
            if (count($questions) > $totalQuestions) {
                $questions = array_slice($questions, 0, $totalQuestions);
                Log::info("Trimmed questions to exact count: {$totalQuestions}");
            }
        }

        return $questions;
    }

    private function generateWithGemini(string $prompt, int $totalQuestions): array
    {
        $geminiKey = getSetting()->gemini_api_key;
        $model = getSetting()->gemini_ai_model ?? 'gemini-pro';

        if (!$geminiKey) {
            throw new \Exception('Gemini API key not configured');
        }

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(120)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$geminiKey}", [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ],
                ]);

            if ($response->failed()) {
                $error = $response->json()['error']['message'] ?? 'Unknown error';
                throw new \Exception("Gemini API failed: {$error}");
            }

            $content = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (!$content) {
                throw new \Exception('Empty response from Gemini');
            }

            return $this->parseQuestions($content);

        } catch (\Exception $e) {
            Log::error("Gemini API error: " . $e->getMessage());
            throw $e;
        }
    }

    private function parseQuestions(string $content): array
    {
        // Clean the content
        $content = trim($content);
        if (stripos($content, '```json') === 0) {
            $content = preg_replace('/^```json\s*|\s*```$/', '', $content);
            $content = trim($content);
        }

        $questions = json_decode($content, true);
        
        if (!is_array($questions)) {
            Log::error("Failed to parse questions JSON: " . json_last_error_msg());
            return [];
        }

        return $questions;
    }

    private function createQuestionsAndAnswers(Quiz $quiz, array $questions): void
    {
        $questionsCreated = 0;

        foreach ($questions as $questionData) {
            if (!isset($questionData['question'], $questionData['answers'])) {
                Log::warning("Invalid question data: " . json_encode($questionData));
                continue;
            }

            $question = Question::create([
                'quiz_id' => $quiz->id,
                'title' => $questionData['question'],
            ]);

            foreach ($questionData['answers'] as $answerData) {
                Answer::create([
                    'question_id' => $question->id,
                    'title' => $answerData['title'],
                    'is_correct' => $answerData['is_correct'] ?? false,
                ]);
            }

            $questionsCreated++;
        }

        Log::info("Created {$questionsCreated} questions for quiz {$quiz->id}");
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