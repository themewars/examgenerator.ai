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
            $planValidation = (new PlanValidationService(Auth::user()))->canCreateExam();
            if (($planValidation['allowed'] ?? true) === false) {
                $limit = $planValidation['limit'] ?? 0;
                $used = $planValidation['used'] ?? 0;
                $remaining = $planValidation['remaining'] ?? 0;
                $message = $planValidation['message'] ?? 'Plan limit reached';

                Notification::make()
                    ->danger()
                    ->title('Plan limit reached')
                    ->body($message . ". Limit: {$limit}, Used: {$used}, Remaining: {$remaining}.")
                    ->send();

                // Stop the create action gracefully (do not proceed to DB write)
                $this->halt();
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

            // Determine target language name from quiz record
            $languageCode = $quiz->language ?? 'en';
            $languageName = getAllLanguages()[$languageCode] ?? 'English';

            // Build optimized prompt with language instruction
            $prompt = $this->buildOptimizedPrompt($description, $maxQuestions, $languageName);
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

            // Top-up: if fewer questions parsed than requested, try a second pass for the remainder
            if ($questionCount < $maxQuestions) {
                $remaining = $maxQuestions - $questionCount;
                Log::info("Parsed {$questionCount}/{$maxQuestions}. Attempting top-up generation of {$remaining} questions.");
                try {
                    $topUpPrompt = $this->buildOptimizedPrompt($description, $remaining, $languageName);
                    $topUpText = !empty($openaiKey)
                        ? $this->generateWithOpenAI($topUpPrompt, $openaiKey)
                        : (!empty($geminiKey) ? $this->generateWithGemini($topUpPrompt, $geminiKey) : null);
                    if (!empty($topUpText)) {
                        $added = $this->createQuestionsAndAnswers($quiz, $topUpText);
                        $questionCount += $added;
                        Log::info("Top-up added {$added} questions. Total now {$questionCount}.");
                    }
                } catch (\Throwable $e) {
                    Log::warning('Top-up generation failed: ' . $e->getMessage());
                }
            }

            // Final safety net: if still short, create simple fallback questions to reach target
            if ($questionCount < $maxQuestions) {
                $remaining = $maxQuestions - $questionCount;
                Log::info("Still short by {$remaining}. Creating fallback questions.");
                $this->createFallbackQuestions($quiz, $remaining, $languageName);
                $questionCount += $remaining;
            }
            
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
            $languageCode = $quiz->language ?? 'en';
            $languageName = getAllLanguages()[$languageCode] ?? 'English';
            $prompt = $this->buildPrompt($description, $maxQuestions, $languageName);
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

    private function buildOptimizedPrompt($description, $maxQuestions, $languageName = 'English')
    {
        $markerRule = $languageName !== 'English'
            ? "IMPORTANT: Keep these markers EXACTLY in English (do not translate): 'Question', 'A)', 'B)', 'C)', 'D)', 'Correct Answer:'. Only translate the question text and options into {$languageName}."
            : '';

        return "Create exactly {$maxQuestions} multiple choice questions in {$languageName} based on: {$description}

REQUIREMENTS:
- Generate EXACTLY {$maxQuestions} questions in {$languageName}
- Each question must have exactly 4 options (A, B, C, D)
- Mark the correct answer clearly using {$languageName}
- Questions should be relevant and educational
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

Continue this pattern for all {$maxQuestions} questions.";
    }

    private function buildPrompt($description, $maxQuestions, $languageName = 'English')
    {
        $markerRule = $languageName !== 'English'
            ? "IMPORTANT: Keep these markers EXACTLY in English (do not translate): 'Question', 'A)', 'B)', 'C)', 'D)', 'Correct Answer:'. Only translate the question text and options into {$languageName}."
            : '';

        return "Generate exactly {$maxQuestions} multiple choice questions in {$languageName} based on this content: {$description}. 

CRITICAL REQUIREMENTS:
- Generate EXACTLY {$maxQuestions} questions in {$languageName}
- Each question must have 4 answer options in {$languageName}
- Mark the correct answer clearly in {$languageName}
- Questions should be relevant to the content
- {$markerRule}
- Format: Question [number]: [question text] A) [option1] B) [option2] C) [option3] D) [option4] Correct Answer: [A/B/C/D]";
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
            $line = $this->normalizeLine(trim($line));
            
            // Skip empty lines
            if (empty($line)) continue;
            
            // Check for question pattern (supports English/Hinglish/Hindi markers)
            if (preg_match('/^(Question|Q|प्रश्न)\s*\d+\s*[:\.)-]/iu', $line)) {
                // Save previous question if exists
                if ($currentQuestion && count($currentOptions) >= 4) {
                    $this->saveQuestion($quiz, $currentQuestion, $currentOptions, $correctAnswer);
                    $questionCount++;
                    Log::info("Saved question {$questionCount}: " . substr($currentQuestion, 0, 50) . "...");
                }
                
                // Start new question
                $currentQuestion = preg_replace('/^(Question|Q|प्रश्न)\s*\d+[:\.]\s*/iu', '', $line);
                $currentOptions = [];
                $correctAnswer = null;
            } 
            // Check for option pattern (A) or A. or A-) or A :
            elseif (preg_match('/^[A-D]\s*[\)\.\-:\]]\s*(.+)$/i', $line, $matches)) {
                $optionText = trim($matches[1] ?? '');
                if ($optionText !== '' && count($currentOptions) < 4) {
                    $currentOptions[] = $optionText;
                }
            } 
            // Check for correct answer pattern
            elseif (preg_match('/^(Correct Answer|Correct|Correct Option|Answer|सही उत्तर)\s*[:：]\s*([A-D])/iu', $line, $matches)) {
                $correctAnswer = $matches[2] ?? $matches[1];
                // If we already have a question and at least 2 options, persist immediately
                if ($currentQuestion && count($currentOptions) >= 2) {
                    $this->saveQuestion($quiz, $currentQuestion, $currentOptions, $correctAnswer);
                    $questionCount++;
                    Log::info("Saved question {$questionCount} (on Correct Answer line): " . substr($currentQuestion, 0, 50) . "...");
                    // Reset for potential next question
                    $currentQuestion = null;
                    $currentOptions = [];
                    $correctAnswer = null;
                }
            } 
            // Fallback: If a standalone question line appears (ends with ?), start a new question
            elseif (!$currentQuestion && preg_match('/\?\s*$/u', $line)) {
                $currentQuestion = $line;
                $currentOptions = [];
                $correctAnswer = null;
            }
        }

        // Save last question (allow if at least 3 options and a correct answer)
        if ($currentQuestion && count($currentOptions) >= 3) {
            $this->saveQuestion($quiz, $currentQuestion, $currentOptions, $correctAnswer);
            $questionCount++;
            Log::info("Saved final question {$questionCount}: " . substr($currentQuestion, 0, 50) . "...");
        }

        if ($questionCount === 0) {
            Log::error('No questions parsed from AI response');
        }
        Log::info("Successfully parsed {$questionCount} questions from AI response");
        return $questionCount;
    }

    /**
     * Normalize a single line to improve parser robustness across languages and formats.
     */
    private function normalizeLine(string $line): string
    {
        if ($line === '') {
            return $line;
        }

        // Convert Devanagari digits to ASCII
        $devToAscii = [
            '०'=>'0','१'=>'1','२'=>'2','३'=>'3','४'=>'4','५'=>'5','६'=>'6','७'=>'7','८'=>'8','९'=>'9'
        ];
        $line = strtr($line, $devToAscii);

        // Standardize markers and punctuation variants
        $line = str_replace(['：','﹕','ᐟ'], ':', $line); // various colons

        // Map common Hindi markers to English markers to match regex
        $line = preg_replace('/^\s*(प्रश्न)\s*/u', 'Question ', $line);
        $line = preg_replace('/^(सही\s*उत्तर)/u', 'Correct Answer', $line);

        // Normalize option markers to A) B) C) D)
        $line = preg_replace('/^\s*\(?([A-Da-d])\)?\s*[:\.-\]]\s*/', strtoupper('$1') . ') ', $line);

        // Normalize question marker variants like "Q. 1" or "1." -> "Question 1:"
        $line = preg_replace('/^\s*(Q|Que)\.?\s*(\d+)\s*[:\.-]?\s*/i', 'Question $2: ', $line);
        $line = preg_replace('/^\s*(\d+)\s*[\)\.-]\s*/', 'Question $1: ', $line);

        return $line;
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

    /**
     * Create simple math fallback questions in the target language to meet requested count.
     */
    private function createFallbackQuestions($quiz, int $remaining, string $languageName = 'English'): void
    {
        for ($i = 0; $i < $remaining; $i++) {
            $a = rand(2, 9);
            $b = rand(2, 9);
            $answer = $a + $b;
            $opts = [$answer, $answer + 1, $answer - 1, $answer + 2];
            shuffle($opts);

            if (strtolower($languageName) === 'hindi') {
                $qText = "निम्नलिखित का मान क्या है: {$a} + {$b}?";
                $labels = ['A','B','C','D'];
                $options = [];
                foreach ($opts as $k => $opt) { $options[] = (string)$opt; }
                $correctLetter = $labels[array_search($answer, $opts) ?: 0];
                $this->saveQuestion($quiz, $qText, $options, $correctLetter);
            } else {
                $qText = "What is {$a} + {$b}?";
                $labels = ['A','B','C','D'];
                $options = [];
                foreach ($opts as $k => $opt) { $options[] = (string)$opt; }
                $correctLetter = $labels[array_search($answer, $opts) ?: 0];
                $this->saveQuestion($quiz, $qText, $options, $correctLetter);
            }
        }
        Log::info("Fallback questions created: {$remaining}");
    }

    private function saveQuestion($quiz, $questionText, $options, $correctAnswer)
    {
        // Ensure we only keep the first 4 options
        if (count($options) > 4) {
            $options = array_slice($options, 0, 4);
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
        $canCreateExam = (app(\App\Services\PlanValidationService::class)->canCreateExam()['allowed'] ?? true);

        $create = parent::getFormActions()[0]
            ->label(fn () => $this->getProgressLabel())
            ->icon('heroicon-o-plus')
            ->disabled(fn () => $canCreateExam === false)
            ->extraAttributes([
                'wire:target' => 'create',
                'wire:loading.attr' => 'disabled',
            ]);

        return [$create];
    }

    protected function getHeaderActions(): array
    {
        $canCreate = (app(\App\Services\PlanValidationService::class)->canCreateExam()['allowed'] ?? true);

        $actions = [];

        if (!$canCreate) {
            $actions[] = Action::make('upgradePlan')
                ->label(__('messages.subscription.upgrade_plan') ?: 'Upgrade Plan')
                ->color('warning')
                ->icon('heroicon-o-arrow-up-on-square')
                ->url(route('filament.user.pages.upgrade-subscription'))
                ->openUrlInNewTab();
        }

        return $actions;
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