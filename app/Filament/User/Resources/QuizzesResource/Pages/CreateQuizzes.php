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
use App\Services\PlanValidationService;

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

    private function getActiveTab()
    {
        try {
            // Try to get from URL first
            $pre = URL::previous();
            if ($pre) {
                parse_str(parse_url($pre)['query'] ?? '', $queryParams);
                $tab = $queryParams['tab'] ?? null;
                $tabType = [
                    '-subject-tab' => Quiz::SUBJECT_TYPE,
                    '-text-tab' => Quiz::TEXT_TYPE,
                    '-url-tab' => Quiz::URL_TYPE,
                    '-upload-tab' => Quiz::UPLOAD_TYPE,
                    '-image-tab' => Quiz::IMAGE_TYPE,
                ];
                
                if (isset($tabType[$tab])) {
                    return $tabType[$tab];
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to get tab from URL: " . $e->getMessage());
        }
        
        // Fallback to default
        return Quiz::TEXT_TYPE;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            Log::info("Starting quiz creation with data: " . json_encode(array_keys($data)));
            Log::info("Full data: " . json_encode($data));
            
            $userId = Auth::id();
            if (!$userId) {
                Log::error("User not authenticated");
                throw new \Exception('User not authenticated');
            }
            
            $activeTab = $this->getActiveTab();
            Log::info("Active tab determined: " . $activeTab);
            
            // Validate required fields early
            if (empty($data['title'])) {
                Log::error("Title is missing in form data");
                throw new \Exception('Quiz title is required');
            }
            if (empty($data['category_id'])) {
                Log::error("Category is missing in form data");
                throw new \Exception('Quiz category is required');
            }

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
            $data['generation_status'] = 'processing';
            $data['generation_progress_total'] = $data['max_questions'] ?? 10;
            $data['generation_progress_done'] = 0;
            
            // Set default values for missing fields
            if (empty($data['max_questions']) || $data['max_questions'] < 1) {
                Log::warning("Max questions not set, using default: 10");
                $data['max_questions'] = 10; // Default fallback
            }
            if (empty($data['diff_level'])) {
                Log::warning("Difficulty level not set, using default: 0");
                $data['diff_level'] = 0; // Default to Basic
            }
            if (empty($data['quiz_type'])) {
                Log::warning("Quiz type not set, using default: 0");
                $data['quiz_type'] = 0; // Default to Multiple Choice
            }
            if (empty($data['language'])) {
                Log::warning("Language not set, using default: en");
                $data['language'] = 'en'; // Default to English
            }
            
            Log::info("Quiz data validated successfully");

            // Enforce plan limit for monthly exams BEFORE creating the record
            try {
                $planValidation = (new PlanValidationService(Auth::user()))->canCreateExam();
                if (isset($planValidation['allowed']) && $planValidation['allowed'] === false) {
                    $limit = $planValidation['limit'] ?? 0;
                    $used = $planValidation['used'] ?? 0;
                    $remaining = $planValidation['remaining'] ?? 0;
                    $message = $planValidation['message'] ?? 'Plan limit reached';

                    Notification::make()
                        ->danger()
                        ->title('Plan limit reached')
                        ->body($message . ". Limit: {$limit}, Used: {$used}, Remaining: {$remaining}.")
                        ->send();

                    $this->halt();
                }
            } catch (\Throwable $e) {
                Log::warning('Plan validation check failed: ' . $e->getMessage());
            }

            // Create quiz record
            Log::info("Creating quiz record with data: " . json_encode($data));
            $quiz = Quiz::create($data);
            Log::info("Quiz created successfully with ID: " . $quiz->id);

            // Generate real questions using AI
            Log::info("Starting AI question generation");
            $this->generateRealQuestions($quiz, $description, $data['max_questions'] ?? 10);

            Log::info("Quiz creation completed successfully");
            return $quiz;

        } catch (\Exception $e) {
            Log::error("Quiz creation error: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            Notification::make()
                ->danger()
                ->title(__('Quiz Creation Failed'))
                ->body(__('Unable to create quiz. Please try again.'))
                ->send();
                
            throw $e;
        }
    }

    private function generateRealQuestions($quiz, $description, $maxQuestions)
    {
        try {
            Log::info("Starting real question generation for quiz {$quiz->id}");
            
            // Get AI settings
            $settings = getSetting();
            $openaiKey = $settings->open_api_key ?? null;
            $geminiKey = $settings->gemini_api_key ?? null;
            
            // Clean and validate API keys
            $openaiKey = $this->cleanApiKey($openaiKey);
            $geminiKey = $this->cleanApiKey($geminiKey);
            
            Log::info("AI Keys - OpenAI: " . (!empty($openaiKey) ? 'Present (' . strlen($openaiKey) . ' chars)' : 'Missing') . ", Gemini: " . (!empty($geminiKey) ? 'Present (' . strlen($geminiKey) . ' chars)' : 'Missing'));
            
            if (empty($openaiKey) && empty($geminiKey)) {
                Log::error("No valid AI keys found - creating sample questions");
                $this->createSampleQuestions($quiz, $maxQuestions);
                return;
            }

            // Build optimized prompt
            $prompt = $this->buildOptimizedPrompt($description, $maxQuestions);
            Log::info("Generated prompt length: " . strlen($prompt));
            
            // Try OpenAI first, then Gemini
            $questions = null;
            if (!empty($openaiKey)) {
                Log::info("Attempting OpenAI generation");
                $questions = $this->generateWithOpenAI($prompt, $openaiKey);
                if (!empty($questions)) {
                    Log::info("OpenAI generation successful");
                }
            }
            
            if (empty($questions) && !empty($geminiKey)) {
                Log::info("Attempting Gemini generation");
                $questions = $this->generateWithGemini($prompt, $geminiKey);
                if (!empty($questions)) {
                    Log::info("Gemini generation successful");
                }
            }

            if (empty($questions)) {
                Log::error("Both AI services failed");
                throw new \Exception('AI generation failed. Please try again.');
            }

            Log::info("AI response length: " . strlen($questions));

            // Parse and create questions
            $questionCount = $this->createQuestionsAndAnswers($quiz, $questions);
            
            if ($questionCount == 0) {
                Log::error("No questions parsed from AI response");
                throw new \Exception('Failed to parse questions from AI response.');
            }
            
            Log::info("Successfully created {$questionCount} questions for quiz {$quiz->id}");

            // Update quiz status
            $quiz->update([
                'generation_status' => 'completed',
                'generation_progress_done' => $questionCount
            ]);

            Notification::make()
                ->success()
                ->title(__('Quiz Created Successfully'))
                ->body(__('Your quiz has been created with ' . $questionCount . ' questions.'))
                ->send();

        } catch (\Exception $e) {
            Log::error("Real question generation failed: " . $e->getMessage());
            
            $quiz->update([
                'generation_status' => 'failed',
                'generation_error' => $e->getMessage()
            ]);

            Notification::make()
                ->warning()
                ->title(__('Quiz Created with Issues'))
                ->body(__('Quiz created but question generation failed: ' . $e->getMessage()))
                ->send();
        }
    }

    private function generateQuestionsWithAI($quiz, $description, $maxQuestions)
    {
        try {
            // Get AI settings
            $settings = getSetting();
            $openaiKey = $settings->open_api_key ?? null;
            $geminiKey = $settings->gemini_api_key ?? null;
            
            Log::info("AI Keys check - OpenAI: " . (!empty($openaiKey) ? 'Present' : 'Missing') . ", Gemini: " . (!empty($geminiKey) ? 'Present' : 'Missing'));
            
            if (empty($openaiKey) && empty($geminiKey)) {
                Log::warning("No AI keys configured - creating sample questions");
                $this->createSampleQuestions($quiz, $maxQuestions);
                return;
            }

            // Build prompt
            $prompt = $this->buildPrompt($description, $maxQuestions);
            Log::info("Generated prompt length: " . strlen($prompt));
            
            // Generate with OpenAI or Gemini
            $questions = null;
            if (!empty($openaiKey)) {
                Log::info("Attempting OpenAI generation");
                $questions = $this->generateWithOpenAI($prompt, $openaiKey);
            } elseif (!empty($geminiKey)) {
                Log::info("Attempting Gemini generation");
                $questions = $this->generateWithGemini($prompt, $geminiKey);
            }

            if (empty($questions)) {
                Log::error("AI returned empty response");
                throw new \Exception('Failed to generate questions - empty response');
            }

            Log::info("AI response length: " . strlen($questions));

            // Parse and create questions
            $questionCount = $this->createQuestionsAndAnswers($quiz, $questions);
            
            Log::info("Created {$questionCount} questions for quiz {$quiz->id}");

            // Update quiz status
            $quiz->update([
                'generation_status' => 'completed',
                'generation_progress_done' => $questionCount
            ]);

            Notification::make()
                ->success()
                ->title(__('Quiz Created Successfully'))
                ->body(__('Your quiz has been created with ' . $questionCount . ' questions.'))
                ->send();

        } catch (\Exception $e) {
            Log::error("AI generation error: " . $e->getMessage());
            
            $quiz->update([
                'generation_status' => 'completed',
                'generation_error' => $e->getMessage()
            ]);

            // Don't show warning notification - let the outer try-catch handle it
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

    private function buildOptimizedPrompt($description, $maxQuestions)
    {
        return "Create exactly {$maxQuestions} multiple choice questions based on: {$description}

REQUIREMENTS:
- Generate EXACTLY {$maxQuestions} questions
- Each question must have exactly 4 options (A, B, C, D)
- Mark the correct answer clearly
- Questions should be relevant and educational
- Use this exact format:

Question 1: [Your question here?]
A) [Option 1]
B) [Option 2] 
C) [Option 3]
D) [Option 4]
Correct Answer: [A/B/C/D]

Question 2: [Your question here?]
A) [Option 1]
B) [Option 2]
C) [Option 3] 
D) [Option 4]
Correct Answer: [A/B/C/D]

Continue this pattern for all {$maxQuestions} questions.";
    }

    private function buildPrompt($description, $maxQuestions)
    {
        return "Generate exactly {$maxQuestions} multiple choice questions based on this content: {$description}. 

CRITICAL REQUIREMENTS:
- Generate EXACTLY {$maxQuestions} questions
- Each question must have 4 answer options
- Mark the correct answer clearly
- Questions should be relevant to the content
- Format: Question: [question text] Options: A) [option1] B) [option2] C) [option3] D) [option4] Correct: [correct option]";
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


    private function createQuestionsAndAnswers($quiz, $aiResponse)
    {
        Log::info("Parsing AI response for quiz {$quiz->id}");
        
        $lines = explode("\n", $aiResponse);
        $currentQuestion = null;
        $currentOptions = [];
        $correctAnswer = null;
        $questionCount = 0;

        Log::info("AI response has " . count($lines) . " lines");

        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) continue;
            
            // Check for question pattern
            if (preg_match('/^Question \d+:/i', $line)) {
                // Save previous question if exists
                if ($currentQuestion && count($currentOptions) >= 4) {
                    $this->saveQuestion($quiz, $currentQuestion, $currentOptions, $correctAnswer);
                    $questionCount++;
                    Log::info("Saved question {$questionCount}: " . substr($currentQuestion, 0, 50) . "...");
                }
                
                // Start new question
                $currentQuestion = preg_replace('/^Question \d+:\s*/i', '', $line);
                $currentOptions = [];
                $correctAnswer = null;
            } 
            // Check for option pattern
            elseif (preg_match('/^[A-D]\)\s*(.+)$/i', $line, $matches)) {
                $currentOptions[] = $matches[1];
            } 
            // Check for correct answer pattern
            elseif (preg_match('/^Correct Answer:\s*([A-D])/i', $line, $matches)) {
                $correctAnswer = $matches[1];
            }
        }

        // Save last question
        if ($currentQuestion && count($currentOptions) >= 4) {
            $this->saveQuestion($quiz, $currentQuestion, $currentOptions, $correctAnswer);
            $questionCount++;
            Log::info("Saved final question {$questionCount}: " . substr($currentQuestion, 0, 50) . "...");
        }

        Log::info("Successfully parsed {$questionCount} questions from AI response");
        return $questionCount;
    }

    private function createSampleQuestions($quiz, $maxQuestions)
    {
        $sampleQuestions = [
            [
                'question' => 'What is the capital of France?',
                'options' => ['London', 'Paris', 'Berlin', 'Madrid'],
                'correct' => 1
            ],
            [
                'question' => 'Which planet is closest to the Sun?',
                'options' => ['Venus', 'Mercury', 'Earth', 'Mars'],
                'correct' => 1
            ],
            [
                'question' => 'What is 2 + 2?',
                'options' => ['3', '4', '5', '6'],
                'correct' => 1
            ],
            [
                'question' => 'Who wrote "Romeo and Juliet"?',
                'options' => ['Charles Dickens', 'William Shakespeare', 'Mark Twain', 'Jane Austen'],
                'correct' => 1
            ],
            [
                'question' => 'What is the largest ocean?',
                'options' => ['Atlantic', 'Pacific', 'Indian', 'Arctic'],
                'correct' => 1
            ]
        ];

        $questionsToCreate = min($maxQuestions, count($sampleQuestions));
        
        for ($i = 0; $i < $questionsToCreate; $i++) {
            $sample = $sampleQuestions[$i];
            
            $question = $quiz->questions()->create([
                'title' => $sample['question'],
                'type' => 0, // Multiple choice
            ]);

            foreach ($sample['options'] as $index => $option) {
                $question->answers()->create([
                    'title' => $option,
                    'is_correct' => ($index === $sample['correct']),
                ]);
            }
        }

        Log::info("Created {$questionsToCreate} sample questions for quiz {$quiz->id}");
        
        $quiz->update([
            'generation_status' => 'completed',
            'generation_progress_done' => $questionsToCreate
        ]);

        Notification::make()
            ->success()
            ->title(__('Quiz Created Successfully'))
            ->body(__('Your quiz has been created with ' . $questionsToCreate . ' sample questions.'))
            ->send();
    }

    private function saveQuestion($quiz, $questionText, $options, $correctAnswer)
    {
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
            
            Log::info("Created answer option " . ($index + 1) . ": " . substr($option, 0, 30) . "... (Correct: " . ($isCorrect ? 'Yes' : 'No') . ")");
        }
        
        Log::info("Question saved: " . substr($questionText, 0, 50) . "... with " . count($options) . " options");
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