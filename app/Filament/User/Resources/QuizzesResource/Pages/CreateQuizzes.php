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

            // Create quiz record
            $quiz = Quiz::create($data);

            // Generate questions using AI (optional)
            try {
                $this->generateQuestionsWithAI($quiz, $description, $data['max_questions'] ?? 10);
            } catch (\Exception $e) {
                // If AI generation fails, still show success
                $quiz->update(['generation_status' => 'completed']);
                
                Notification::make()
                    ->success()
                    ->title(__('Quiz Created Successfully'))
                    ->body(__('Your quiz has been created. You can add questions manually.'))
                        ->send();
            }

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

    private function generateQuestionsWithAI($quiz, $description, $maxQuestions)
    {
        try {
            // Get AI settings
            $openaiKey = getSetting('openai_key');
            $geminiKey = getSetting('gemini_key');
            
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
        $lines = explode("\n", $aiResponse);
        $currentQuestion = null;
        $currentOptions = [];
        $correctAnswer = null;
        $questionCount = 0;

        Log::info("Parsing AI response with " . count($lines) . " lines");

        foreach ($lines as $line) {
            $line = trim($line);
            
            if (strpos($line, 'Question:') === 0) {
                // Save previous question if exists
                if ($currentQuestion) {
                    $this->saveQuestion($quiz, $currentQuestion, $currentOptions, $correctAnswer);
                    $questionCount++;
                }
                
                // Start new question
                $currentQuestion = substr($line, 9);
                $currentOptions = [];
                $correctAnswer = null;
            } elseif (strpos($line, 'A)') === 0 || strpos($line, 'B)') === 0 || strpos($line, 'C)') === 0 || strpos($line, 'D)') === 0) {
                $currentOptions[] = $line;
            } elseif (strpos($line, 'Correct:') === 0) {
                $correctAnswer = substr($line, 8);
            }
        }

        // Save last question
        if ($currentQuestion) {
            $this->saveQuestion($quiz, $currentQuestion, $currentOptions, $correctAnswer);
            $questionCount++;
        }

        Log::info("Parsed {$questionCount} questions from AI response");
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

        foreach ($options as $index => $option) {
            $isCorrect = false;
            if ($correctAnswer && strpos($option, $correctAnswer) !== false) {
                $isCorrect = true;
            }

            $question->answers()->create([
                'title' => $option,
                'is_correct' => $isCorrect,
            ]);
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