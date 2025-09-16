<?php

namespace App\Filament\User\Resources\QuizzesResource\Pages;

use App\Models\Quiz;
use App\Models\Answer;
use App\Models\Question;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use fivefilters\Readability\Readability;
use fivefilters\Readability\Configuration;
use App\Filament\User\Resources\QuizzesResource;

class EditQuizzes extends EditRecord
{
    protected static string $resource = QuizzesResource::class;

    public static $tab = Quiz::TEXT_TYPE;

    public function mount(int | string $record): void
    {
        parent::mount($record);
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

    // protected function afterValidate(): void
    // {
    //     $data = $this->form->getState();

    //     if (empty($this->data['file_upload']) && empty($data['quiz_description_text']) && empty($data['quiz_description_sub']) && empty($data['quiz_description_url'])) {
    //         Notification::make()
    //             ->danger()
    //             ->title(__('messages.quiz.quiz_description_required'))
    //             ->send();
    //         $this->halt();
    //     }
    // }


    public function fillForm(): void
    {
        $quizQuestions = Session::get('quizQuestions');
        $editedBaseData = Session::get('editedQuizDataForRegeneration');
        Session::forget('editedQuizDataForRegeneration');
        Session::forget('quizQuestions');

        $quizData = trim($quizQuestions);
        if (stripos($quizData, '```json') === 0) {
            $quizData = preg_replace('/^```json\s*|\s*```$/', '', $quizData);
            $quizData = trim($quizData);
        }

        $questionData = json_decode($quizData, true);

        if ($editedBaseData) {
            $data = $editedBaseData;

            unset($data['questions'], $data['custom_questions']);
        } else {
            $data = $this->record->attributesToArray();
            $data = $this->mutateFormDataBeforeFill($data);
        }

        $data['questions'] = [];

        if (is_array($questionData) && !empty($questionData)) {
            $questionsArray = isset($questionData['questions']) && is_array($questionData['questions'])
                ? $questionData['questions']
                : $questionData;

            foreach ($questionsArray as $question) {
                if (isset($question['question'], $question['answers']) && is_array($question['answers'])) {
                    $answersOption = array_map(function ($answer) {
                        return [
                            'title' => $answer['title'],
                            'is_correct' => $answer['is_correct']
                        ];
                    }, $question['answers']);

                    $correctAnswer = array_keys(array_filter(array_column($answersOption, 'is_correct')));

                    $data['questions'][] = [
                        'title' => $question['question'],
                        'answers' => $answersOption,
                        'is_correct' => $correctAnswer,

                    ];
                }
            }
        }

        if (empty($data['questions']) && !is_array($questionData) && isset($data['id'])) {
            $questions = Question::where('quiz_id', $data['id'])->with('answers')->get();
            foreach ($questions as $question) {
                $answersOption = $question->answers->map(function ($answer) {
                    return [
                        'title' => $answer->title,
                        'is_correct' => $answer->is_correct
                    ];
                })->toArray();

                $correctAnswer = array_keys(array_filter(array_column($answersOption, 'is_correct')));

                $data['questions'][] = [
                    'title' => $question->title,
                    'answers' => $answersOption,
                    'is_correct' => $correctAnswer,
                    'question_id' => $question->id
                ];
            }
        }
        $this->form->fill($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('messages.common.back'))
                ->url($this->getResource()::getUrl('index')),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $data['type'] = getTabType();
        if ($data['type'] == Quiz::TEXT_TYPE) {
            $data['quiz_description'] = $data['quiz_description_text'];
        } elseif ($data['type'] == Quiz::SUBJECT_TYPE) {
            $data['quiz_description'] = $data['quiz_description_sub'];
        } elseif ($data['type'] == Quiz::URL_TYPE) {
            $data['quiz_description'] = $data['quiz_description_url'];
        }
        $questions = array_merge(
            $data['questions'] ?? [],
            $data['custom_questions'] ?? []
        );
        if (!empty($questions)) {
            $updatedQuestionIds = [];

            foreach ($questions as $index => $quizQuestion) {

                $isLongAnswer = ($record->quiz_type === \App\Models\Quiz::LONG_ANSWER);
                if (!$isLongAnswer && (empty($quizQuestion['answers']) || !collect($quizQuestion['answers'])->where('is_correct', true)->count())) {
                    Notification::make()
                        ->danger()
                        ->title('Question #' . ($index + 1) . ' must have at least one correct answer.')
                        ->send();

                    $this->halt();
                }

                if (isset($quizQuestion['question_id'])) {
                    $question = Question::where('quiz_id', $record->id)
                        ->where('id', $quizQuestion['question_id'])
                        ->first();

                    if ($question) {
                        $question->update([
                            'title' => $quizQuestion['title'],
                        ]);
                    } else {
                        $question = Question::create([
                            'quiz_id' => $record->id,
                            'title' => $quizQuestion['title'],
                        ]);
                    }
                } else {
                    $question = Question::create([
                        'quiz_id' => $record->id,
                        'title' => $quizQuestion['title'],
                    ]);
                }
                $updatedQuestionIds[] = $question->id;
                Question::where('quiz_id', $record->id)
                    ->whereNotIn('id', $updatedQuestionIds)
                    ->delete();
                if (!empty($quizQuestion['answers'])) {
                    foreach ($quizQuestion['answers'] as $answer) {
                        $answerRecord = Answer::where('question_id', $question->id)
                            ->where('title', $answer['title'])
                            ->first();

                        if ($answerRecord) {
                            $answerRecord->update([
                                'is_correct' => $answer['is_correct']
                            ]);
                        } else {
                            Answer::create([
                                'question_id' => $question->id,
                                'title' => $answer['title'],
                                'is_correct' => $answer['is_correct']
                            ]);
                        }
                    }
                }
            }
        } else {
            $record->questions()->delete();
        }

        session()->forget('quizQuestions');
        unset($data['questions']);
        unset($data['custom_questions']);
        unset($data['quiz_description_text']);
        unset($data['quiz_description_sub']);
        unset($data['quiz_description_url']);
        unset($data['active_tab']);
        $data['max_questions'] = $record->questions()->count();

        $record->update($data);

        return $record;
    }


    public function getTitle(): string
    {
        return __('messages.quiz.edit_exam');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('messages.quiz.exam_updated_success');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }


    protected function getFormActions(): array
    {
        return [
            parent::getFormActions()[0],
            Action::make('addMoreQuestionsWithAI')
                ->label('Add More Questions with AI')
                ->color('success')
                ->icon('heroicon-o-sparkles')
                ->requiresConfirmation()
                ->modalHeading('Add More Questions with AI')
                ->modalDescription('This will generate additional questions using AI based on your quiz content. How many questions would you like to add?')
                ->disabled(function () {
                    $userPlan = auth()->user()?->subscriptions()->where('status', \App\Enums\SubscriptionStatus::ACTIVE->value)->orderByDesc('id')->first()?->plan;
                    $maxQuestions = $userPlan?->max_questions_per_exam ?? 20;
                    if ($maxQuestions == -1) {
                        $maxQuestions = 50; // Safety cap if unlimited
                    }
                    $currentQuestions = $this->record->questions()->count();
                    $remainingQuestions = $maxQuestions - $currentQuestions;
                    return $remainingQuestions <= 0;
                })
                ->form([
                    \Filament\Forms\Components\TextInput::make('questionCount')
                        ->label('Number of Questions')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(function () {
                            $userPlan = auth()->user()?->subscriptions()->where('status', \App\Enums\SubscriptionStatus::ACTIVE->value)->orderByDesc('id')->first()?->plan;
                            $maxQuestions = $userPlan?->max_questions_per_exam ?? 20;
                            if ($maxQuestions == -1) {
                                $maxQuestions = 50; // Safety cap if unlimited
                            }
                            $currentQuestions = $this->record->questions()->count();
                            $remainingQuestions = $maxQuestions - $currentQuestions;
                            return max(min($remainingQuestions, 10), 1);
                        })
                        ->default(3)
                        ->required()
                        ->helperText(function () {
                            $userPlan = auth()->user()?->subscriptions()->where('status', \App\Enums\SubscriptionStatus::ACTIVE->value)->orderByDesc('id')->first()?->plan;
                            $maxQuestions = $userPlan?->max_questions_per_exam ?? 20;
                            if ($maxQuestions == -1) {
                                $maxQuestions = 50; // Safety cap if unlimited
                            }
                            $currentQuestions = $this->record->questions()->count();
                            $remainingQuestions = $maxQuestions - $currentQuestions;

                            if ($remainingQuestions <= 0) {
                                return "You have reached the maximum questions limit for your plan ({$maxQuestions} questions).";
                            }

                            return "Maximum 10 questions at a time. Plan allows {$maxQuestions} total questions. {$remainingQuestions} remaining.";
                        })
                ])
                ->action(function (array $data) {
                    $this->addMoreQuestionsWithAI($data);
                }),
            Action::make('regenerate')
                ->label(__('messages.common.re_generate'))
                ->color('gray')
                ->action('regenerateQuestions'),

            Action::make('cancel')
                ->label(__('messages.common.cancel'))
                ->color('gray')
                ->url(QuizzesResource::getUrl('index')),

        ];
    }

    public function regenerateQuestions(): void
    {
        $currentFormState = $this->form->getState();
        $currentFormState['type'] = getTabType();
        if ($currentFormState['type'] == Quiz::TEXT_TYPE) {
            $currentFormState['quiz_description'] = $currentFormState['quiz_description_text'];
        } elseif ($currentFormState['type'] == Quiz::SUBJECT_TYPE) {
            $currentFormState['quiz_description'] = $currentFormState['quiz_description_sub'];
        } elseif ($currentFormState['type'] == Quiz::URL_TYPE) {
            $currentFormState['quiz_description'] = $currentFormState['quiz_description_url'];
        }
        Session::put('editedQuizDataForRegeneration', $currentFormState);

        $data = $this->data;
        $description = null;

        if ($data['type'] == Quiz::URL_TYPE && $data['quiz_description_url'] != null) {
            $url = $data['quiz_description_url'];

            $context = stream_context_create([
                "ssl" => [
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                ],
            ]);

            $responseContent = file_get_contents($url, false, $context);
            $readability = new Readability(new Configuration());
            $readability->parse($responseContent);
            $readability->getContent();
            $description = $readability->getExcerpt();
        }

        if (isset($data['quiz_document']) && !empty($data['quiz_document'])) {
            $filePath = $data['quiz_document'];
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if ($extension === 'pdf') {
                $description = pdfToText($filePath);
            } elseif ($extension === 'docx') {
                $description = docxToText($filePath);
            }
        }

        // Handle image processing for OCR
        if (isset($data['image_upload']) && is_array($data['image_upload'])) {
            foreach ($data['image_upload'] as $file) {
                if ($file instanceof \Illuminate\Http\UploadedFile) {
                    $extractedText = imageToText($file->getPathname());
                    if ($extractedText) {
                        $description = $extractedText;
                        break; // Use first successfully processed image
                    }
                }
            }
        }

        if (strlen($description) > 10000) {
            $description = substr($description, 0, 10000) . '...';
        }

        $quizData = [
            'Difficulty' => Quiz::DIFF_LEVEL[$data['diff_level']],
            'question_type' => Quiz::QUIZ_TYPE[$data['quiz_type']],
            'language' => getAllLanguages()[$data['language']] ?? 'English'
        ];

        $prompt = <<<PROMPT

        You are an expert in crafting engaging quizzes. Based on the quiz details provided, your task is to meticulously generate questions according to the specified question type. Your output should be exclusively in properly formatted JSON.

        **Quiz Details:**

        - **Title**: {$data['title']}
        - **Description**: {$description}
        - **Number of Questions**: {$data['max_questions']}
        - **Difficulty**: {$quizData['Difficulty']}
        - **Question Type**: {$quizData['question_type']}

        **Instructions:**

        1. **Language Requirement**: Write all quiz questions and answers in {$data['language']}.
        2. **Number of Questions**: Create exactly {$data['max_questions']} questions.
        3. **Difficulty Level**: Ensure each question adheres to the specified difficulty level: {$quizData['Difficulty']}.
        4. **Description Alignment**: Ensure that each question is relevant to and reflects key aspects of the provided description.
        5. **Question Type**: Follow the format specified below based on the question type:

        **Question Formats:**

        - **Multiple Choice**:
            - Structure your JSON with four answer options. Mark exactly two options as `is_correct: true`. Use the following format:

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
                            "is_correct": true
                        }
                    ],
                    "correct_answer_key": ["Answer Option 2", "Answer Option 4"]
                }
            ]

        - **Single Choice**:
            - Use the following format with exactly two options. Mark one option as `is_correct: true` and the other as `is_correct: false`:

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

        **Guidelines:**
        - You must generate exactly **{$data['max_questions']}** questions.
        - For Multiple Choice questions, ensure that there are exactly four answer options, with two options marked as `is_correct: true`.
        - For Single Choice questions, ensure that there are exactly two answer options, with one option marked as `is_correct: true`.
        - The correct_answer_key should match the correct answer's title value(s) for Multiple Choice and Single Choice questions.
        - Ensure that each question is diverse and well-crafted, covering various relevant concepts.

        Your responses should be formatted impeccably in JSON, capturing the essence of the provided quiz details.

        PROMPT;

        $aiType = getSetting()->ai_type;
        $quizText = null;

        if ($aiType === Quiz::GEMINI_AI) {
            $geminiApiKey = getSetting()->gemini_api_key;
            $model = getSetting()->gemini_ai_model;

            if (!$geminiApiKey) {
                Notification::make()->danger()->title(__('messages.quiz.set_openai_key_at_env'))->send();
                return;
            }

            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$geminiApiKey}", [
                    'contents' => [['parts' => [['text' => $prompt]]]]
                ]);

                if ($response->failed()) {
                    Notification::make()->danger()->title($response->json()['error']['message'])->send();
                    return;
                }

                $rawText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;
                $quizText = preg_replace('/^```(?:json)?|```$/im', '', $rawText);
            } catch (\Exception $exception) {
                Notification::make()->danger()->title($exception->getMessage())->send();
                return;
            }
        }

        if ($aiType === Quiz::OPEN_AI) {
            $key = getSetting()->open_api_key ?? null;
            $openAiKey = ! empty($key) ? $key : config('services.open_ai.open_api_key');
            $model = getSetting()->open_ai_model;

            if (!$openAiKey) {
                Notification::make()->danger()->title(__('messages.quiz.set_openai_key_at_env'))->send();
                return;
            }

            try {
                $quizResponse = Http::withToken($openAiKey)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(90)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $model,
                        'messages' => [['role' => 'user', 'content' => $prompt]]
                    ]);

                if ($quizResponse->failed()) {
                    $error = $quizResponse->json()['error']['message'] ?? 'Unknown error occurred';
                    Notification::make()->danger()->title(__('OpenAI Error'))->body($error)->send();
                    return;
                }

                $quizText = $quizResponse['choices'][0]['message']['content'] ?? null;
            } catch (\Exception $e) {
                Notification::make()->danger()->title(__('API Request Failed'))->body($e->getMessage())->send();
                Log::error('OpenAI API error: ' . $e->getMessage());
                return;
            }
        }

        if ($quizText) {
            Session::put('quizQuestions', $quizText);
            $this->fillForm();
        } else {
            Notification::make()
                ->danger()
                ->title('Quiz generation failed.')
                ->send();
        }
    }

    public function addMoreQuestionsWithAI(array $data): void
    {
        try {
            $questionCount = $data['questionCount'];
            $quiz = $this->record;
            
            // Check plan limits
            $userPlan = auth()->user()?->subscriptions()->where('status', \App\Enums\SubscriptionStatus::ACTIVE->value)->orderByDesc('id')->first()?->plan;
            $maxQuestions = $userPlan?->max_questions_per_exam ?? 20;
            if ($maxQuestions == -1) {
                $maxQuestions = 50; // Safety cap if unlimited
            }
            $currentQuestions = $quiz->questions()->count();
            $remainingQuestions = $maxQuestions - $currentQuestions;
            
            if ($questionCount > $remainingQuestions) {
                Notification::make()
                    ->danger()
                    ->title('Question Limit Exceeded')
                    ->body("You can only add {$remainingQuestions} more questions. Your plan allows {$maxQuestions} total questions.")
                    ->send();
                return;
            }
            
            if ($questionCount > 10) {
                Notification::make()
                    ->danger()
                    ->title('Too Many Questions')
                    ->body('Maximum 10 questions can be added at a time to prevent API failures.')
                    ->send();
                return;
            }
            
            Log::info("Adding {$questionCount} more questions with AI for quiz {$quiz->id}");
            
            // Get AI settings
            $settings = getSetting();
            $openaiKey = $settings->open_api_key ?? null;
            $geminiKey = $settings->gemini_api_key ?? null;
            
            // Clean and validate API keys
            $openaiKey = $this->cleanApiKey($openaiKey);
            $geminiKey = $this->cleanApiKey($geminiKey);
            
            if (empty($openaiKey) && empty($geminiKey)) {
                Notification::make()
                    ->danger()
                    ->title('AI Keys Not Found')
                    ->body('Please configure OpenAI or Gemini API key in settings.')
                    ->send();
                return;
            }
            
            // Get quiz description for context
            $description = $quiz->quiz_description ?? 'General knowledge questions';
            
            // Determine target language and type from quiz and build language-aware prompt
            $languageCode = $quiz->language ?? 'en';
            $languageName = getAllLanguages()[$languageCode] ?? 'English';
            $prompt = $this->buildAdditionalQuestionsPrompt($description, $questionCount, $languageName, $quiz->quiz_type);
            
            Log::info("Generated prompt for additional questions: " . strlen($prompt) . " characters");
            
            // Try OpenAI first, then Gemini
            $questions = null;
            if (!empty($openaiKey)) {
                Log::info("Attempting OpenAI generation for additional questions");
                $questions = $this->generateWithOpenAI($prompt, $openaiKey);
                if (!empty($questions)) {
                    Log::info("OpenAI generation successful for additional questions");
                }
            }
            
            if (empty($questions) && !empty($geminiKey)) {
                Log::info("Attempting Gemini generation for additional questions");
                $questions = $this->generateWithGemini($prompt, $geminiKey);
                if (!empty($questions)) {
                    Log::info("Gemini generation successful for additional questions");
                }
            }
            
            if (empty($questions)) {
                Log::error("Both AI services failed for additional questions");
                Notification::make()
                    ->danger()
                    ->title('AI Generation Failed')
                    ->body('Failed to generate additional questions. Please try again.')
                    ->send();
                return;
            }
            
            // Parse and create questions
            $createdCount = $this->createAdditionalQuestions($quiz, $questions);
            
            if ($createdCount > 0) {
                Notification::make()
                    ->success()
                    ->title('Questions Added Successfully')
                    ->body("Added {$createdCount} new questions to your quiz.")
                    ->send();
                
                // Refresh the form to show new questions
                $this->fillForm();
            } else {
                Notification::make()
                    ->warning()
                    ->title('No Questions Added')
                    ->body('Could not parse questions from AI response. Please try again.')
                    ->send();
            }
            
        } catch (\Exception $e) {
            Log::error("Error adding more questions with AI: " . $e->getMessage());
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('An error occurred while adding questions. Please try again.')
                ->send();
        }
    }

    private function cleanApiKey($apiKey)
    {
        if (empty($apiKey)) {
            return null;
        }
        
        // Remove any extra whitespace
        $apiKey = trim($apiKey);
        
        // Remove any JSON-like formatting that might be stored incorrectly
        if (strpos($apiKey, '{"id":') === 0) {
            // This looks like a JSON object, extract the actual key
            $decoded = json_decode($apiKey, true);
            if (isset($decoded['key'])) {
                $apiKey = $decoded['key'];
            } elseif (isset($decoded['value'])) {
                $apiKey = $decoded['value'];
            } elseif (isset($decoded['open_api_key'])) {
                $apiKey = $decoded['open_api_key'];
            } elseif (isset($decoded['gemini_api_key'])) {
                $apiKey = $decoded['gemini_api_key'];
            }
        }
        
        // Remove quotes if present
        $apiKey = trim($apiKey, '"\'');
        
        // Validate OpenAI key format (should start with sk-)
        if (strpos($apiKey, 'sk-') === 0) {
            return $apiKey;
        }
        
        // Validate Gemini key format (should be a long string)
        if (strlen($apiKey) > 20 && !strpos($apiKey, 'sk-')) {
            return $apiKey;
        }
        
        Log::warning("Invalid API key format detected: " . substr($apiKey, 0, 20) . "...");
        return null;
    }

    private function buildAdditionalQuestionsPrompt($description, $questionCount, $languageName = 'English', $quizType = null)
    {
        $markerRule = $languageName !== 'English'
            ? "IMPORTANT: Keep these markers EXACTLY in English (do not translate): 'Question', 'A)', 'B)', 'C)', 'D)', 'Correct Answer:'. Only translate the question text and options into {$languageName}."
            : '';
        $isTrueFalse = ($quizType === \App\Models\Quiz::TRUE_FALSE);

        if ($isTrueFalse) {
            $true = ($languageName === 'Hindi') ? 'सही' : 'True';
            $false = ($languageName === 'Hindi') ? 'गलत' : 'False';
            return "Create exactly {$questionCount} additional TRUE/FALSE questions in {$languageName} based on: {$description}

REQUIREMENTS:
- Generate EXACTLY {$questionCount} questions in {$languageName}
- Each question must have exactly 2 options: A) {$true} and B) {$false}
- Mark the correct answer clearly in {$languageName}
- Questions should be relevant and educational
- Make sure questions are different from existing ones
- Use this exact format:

{$markerRule}

Question 1 ({$languageName}): [Your question here?]
A) {$true}
B) {$false}
Correct Answer: [A/B/C/D]

Question 2 ({$languageName}): [Your question here?]
A) {$true}
B) {$false}
Correct Answer: [A/B/C/D]

Continue this pattern for all {$questionCount} questions.";
        }

        return "Create exactly {$questionCount} additional multiple choice questions in {$languageName} based on: {$description}

REQUIREMENTS:
- Generate EXACTLY {$questionCount} questions in {$languageName}
- Each question must have exactly 4 options (A, B, C, D)
- Mark the correct answer clearly in {$languageName}
- Questions should be relevant and educational
- Make sure questions are different from existing ones
- Use this exact format:

{$markerRule}

Question 1 ({$languageName}): [Your question here?]
A) [Option 1 in {$languageName}]
B) [Option 2 in {$languageName}] 
C) [Option 3 in {$languageName}]
D) [Option 4 in {$languageName}]
Correct Answer: [A/B/C/D]

Question 2 ({$languageName}): [Your question here?]
A) [Option 1 in {$languageName}]
B) [Option 2 in {$languageName}]
C) [Option 3 in {$languageName}] 
D) [Option 4 in {$languageName}]
Correct Answer: [A/B/C/D]

Continue this pattern for all {$questionCount} questions.";
    }

    private function generateWithOpenAI($prompt, $apiKey)
    {
        $client = new \GuzzleHttp\Client();
        
        $response = $client->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 2000,
                'temperature' => 0.7,
            ],
            'timeout' => 90
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    private function generateWithGemini($prompt, $apiKey)
    {
        $client = new \GuzzleHttp\Client();
        
        $response = $client->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$apiKey}", [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'contents' => [
                    ['parts' => [['text' => $prompt]]]
                ],
                'generationConfig' => [
                    'maxOutputTokens' => 2000,
                    'temperature' => 0.7,
                ]
            ],
            'timeout' => 90
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }

    private function createAdditionalQuestions($quiz, $aiResponse)
    {
        Log::info("Parsing AI response for additional questions");
        
        $lines = explode("\n", $aiResponse);
        $currentQuestion = null;
        $currentOptions = [];
        $correctAnswer = null;
        $questionCount = 0;
        $requiredOptions = ($quiz->quiz_type === \App\Models\Quiz::TRUE_FALSE) ? 2 : (($quiz->quiz_type === \App\Models\Quiz::LONG_ANSWER) ? 0 : 4);

        Log::info("AI response has " . count($lines) . " lines");

        foreach ($lines as $lineNum => $line) {
            $line = $this->normalizeLine(trim($line));
            
            // Skip empty lines
            if (empty($line)) continue;
            
            // Check for question pattern (allow optional language tag)
            if (preg_match('/^(Question|Q|प्रश्न)\s*\d+(?:\s*\([^)]*\))?\s*[:\.)-]/iu', $line)) {
                // Save previous question if exists
                if ($currentQuestion && (($requiredOptions === 0) || count($currentOptions) >= ($requiredOptions === 2 ? 2 : 3))) {
                    $this->saveAdditionalQuestion($quiz, $currentQuestion, $currentOptions, $correctAnswer);
                    $questionCount++;
                    Log::info("Saved additional question {$questionCount}: " . substr($currentQuestion, 0, 50) . "...");
                }
                
                // Start new question
                $currentQuestion = preg_replace('/^(Question|Q|प्रश्न)\s*\d+(?:\s*\([^)]*\))?\s*[:\.]\s*/iu', '', $line);
                $currentOptions = [];
                $correctAnswer = null;
            } 
            // Check for option pattern (A) / A. / A- / A: / A]
            elseif (preg_match('/^[A-D]\s*[\)\.:\-\]]\s*(.+)$/i', $line, $matches)) {
                $opt = trim($matches[1]);
                if ($opt !== '' && count($currentOptions) < 4) {
                    $currentOptions[] = $opt;
                }
            } 
            // Check for correct answer pattern
            elseif (preg_match('/^(Correct Answer|Correct|Correct Option|Answer|सही उत्तर)\s*[:：]\s*([A-D])/iu', $line, $matches)) {
                $correctAnswer = $matches[2] ?? $matches[1];
                if ($currentQuestion && (($requiredOptions === 0) || count($currentOptions) >= ($requiredOptions === 2 ? 2 : 2))) {
                    $this->saveAdditionalQuestion($quiz, $currentQuestion, $currentOptions, $correctAnswer);
                    $questionCount++;
                    $currentQuestion = null;
                    $currentOptions = [];
                    $correctAnswer = null;
                }
            }
        }

        // Save last question
        if ($currentQuestion && (($requiredOptions === 0) || count($currentOptions) >= ($requiredOptions === 2 ? 2 : 3))) {
            $this->saveAdditionalQuestion($quiz, $currentQuestion, $currentOptions, $correctAnswer);
            $questionCount++;
            Log::info("Saved final additional question {$questionCount}: " . substr($currentQuestion, 0, 50) . "...");
        }

        Log::info("Successfully parsed {$questionCount} additional questions from AI response");
        return $questionCount;
    }

    private function saveAdditionalQuestion($quiz, $questionText, $options, $correctAnswer)
    {
        // Normalize options: trim, dedupe, and pad to required count
        $languageCode = $quiz->language ?? 'en';
        $languageName = getAllLanguages()[$languageCode] ?? 'English';
        $labelNone = strtolower($languageName) === 'hindi' ? 'उपर्युक्त में से कोई नहीं' : 'None of the above';
        $labelDontKnow = strtolower($languageName) === 'hindi' ? 'मुझे नहीं पता' : "I don't know";
        $labelTrue = strtolower($languageName) === 'hindi' ? 'सही' : 'True';
        $labelFalse = strtolower($languageName) === 'hindi' ? 'गलत' : 'False';
        $requiredOptions = ($quiz->quiz_type === \App\Models\Quiz::TRUE_FALSE) ? 2 : (($quiz->quiz_type === \App\Models\Quiz::LONG_ANSWER) ? 0 : 4);

        $clean = [];
        foreach ($options as $opt) {
            $t = trim((string)$opt);
            if ($t !== '' && !in_array($t, $clean, true)) {
                $clean[] = $t;
            }
        }
        $options = $clean;
        if ($requiredOptions > 0 && count($options) > $requiredOptions) {
            $options = array_slice($options, 0, $requiredOptions);
        }
        while ($requiredOptions > 0 && count($options) < $requiredOptions) {
            if ($requiredOptions === 2) {
                // True/False padding
                $needed = [$labelTrue, $labelFalse];
                foreach ($needed as $n) {
                    if (count($options) >= $requiredOptions) break;
                    if (!in_array($n, $options, true)) {
                        $options[] = $n;
                    }
                }
                if (count($options) < $requiredOptions) {
                    $options[] = $labelFalse;
                }
                break;
            }
            $fallback = count($options) === 2 ? $labelNone : $labelDontKnow;
            if (!in_array($fallback, $options, true)) {
                $options[] = $fallback;
            } else {
                $options[] = $fallback . ' ' . (count($options)+1);
            }
        }
        $question = $quiz->questions()->create([
            'title' => $questionText,
            'type' => 0, // Multiple choice
        ]);

        $optionLetters = ['A', 'B', 'C', 'D'];
        
        foreach ($options as $index => $option) {
            $isCorrect = false;
            
            // Check if this option matches the correct answer
            if ($correctAnswer && isset($optionLetters[$index]) && $optionLetters[$index] === strtoupper(trim($correctAnswer))) {
                $isCorrect = true;
            }

            $question->answers()->create([
                'title' => $option,
                'is_correct' => $isCorrect,
            ]);
            
            Log::info("Created additional answer option " . ($index + 1) . ": " . substr($option, 0, 30) . "... (Correct: " . ($isCorrect ? 'Yes' : 'No') . ")");
        }
        
        Log::info("Additional question saved: " . substr($questionText, 0, 50) . "... with " . count($options) . " options");
    }

    private function normalizeLine(string $line): string
    {
        if ($line === '') return $line;
        $devToAscii = ['०'=>'0','१'=>'1','२'=>'2','३'=>'3','४'=>'4','५'=>'5','६'=>'6','७'=>'7','८'=>'8','९'=>'9'];
        $line = strtr($line, $devToAscii);
        $line = str_replace(['：','﹕','ᐟ'], ':', $line);
        $line = preg_replace('/^\s*(प्रश्न)\s*/u', 'Question ', $line);
        $line = preg_replace('/^(सही\s*उत्तर)/u', 'Correct Answer', $line);
        $line = preg_replace('/^\s*\(?([A-Da-d])\)?\s*[:\.-\]]\s*/', strtoupper('$1') . ') ', $line);
        $line = preg_replace('/^\s*(Q|Que)\.?\s*(\d+)\s*[:\.-]?\s*/i', 'Question $2: ', $line);
        $line = preg_replace('/^\s*(\d+)\s*[\)\.-]\s*/', 'Question $1: ', $line);
        return $line;
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\User\Resources\QuizzesResource\Widgets\QuestionCountWidget::class,
        ];
    }
}