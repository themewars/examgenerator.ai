<?php

namespace App\Jobs;

use App\Models\Quiz;
use App\Models\Question;
use App\Models\Answer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class GenerateQuizJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $quizId;
    public array $quizData;
    public int $totalQuestions;
    public int $timeout;

    public function __construct(int $quizId, array $quizData, int $totalQuestions)
    {
        $this->quizId = $quizId;
        $this->quizData = $quizData;
        $this->totalQuestions = $totalQuestions;
        $this->timeout = $totalQuestions > 30 ? 300 : 180; // 5 minutes for large requests
    }

    public function handle(): void
    {
        Log::info("Starting GenerateQuizJob for quiz {$this->quizId} with {$this->totalQuestions} questions");
        
        $quiz = Quiz::find($this->quizId);
        if (!$quiz) {
            Log::error("Quiz {$this->quizId} not found");
            return;
        }

        try {
            // Update quiz status to processing
            $quiz->update([
                'generation_status' => 'processing',
                'generation_progress_total' => $this->totalQuestions,
                'generation_progress_created' => 0,
            ]);

            // Generate questions using AI
            $questions = $this->generateQuestions($quiz);
            
            if (empty($questions)) {
                throw new \Exception('No questions generated');
            }

            // Create questions and answers in database
            $this->createQuestionsAndAnswers($quiz, $questions);

            // Update quiz status to completed
            $quiz->update([
                'generation_status' => 'completed',
                'generation_progress_created' => count($questions),
            ]);

            // Send success notification
            $this->sendNotification($quiz->user_id, 'success', 
                __('Quiz Created Successfully'), 
                __('Your quiz has been created with :count questions.', ['count' => count($questions)])
            );

            Log::info("Successfully completed GenerateQuizJob for quiz {$this->quizId}");

        } catch (\Exception $e) {
            Log::error("GenerateQuizJob failed for quiz {$this->quizId}: " . $e->getMessage());
            
            // Update quiz status to failed
            $quiz->update([
                'generation_status' => 'failed',
                'generation_progress_created' => 0,
            ]);

            // Send error notification
            $this->sendNotification($quiz->user_id, 'danger', 
                __('Quiz Creation Failed'), 
                __('Unable to create quiz. Please try again.')
            );
        }
    }

    private function generateQuestions(Quiz $quiz): array
    {
        $aiType = getSetting()->ai_type;
        $prompt = $this->buildPrompt($quiz);

        if ($aiType == Quiz::OPEN_AI) {
            return $this->generateWithOpenAI($prompt);
        } elseif ($aiType == Quiz::GEMINI_AI) {
            return $this->generateWithGemini($prompt);
        }

        throw new \Exception('Unsupported AI type');
    }

    private function buildPrompt(Quiz $quiz): string
    {
        $questionType = Quiz::QUIZ_TYPE[$quiz->quiz_type];
        $difficulty = Quiz::DIFF_LEVEL[$quiz->diff_level];
        $language = getAllLanguages()[$quiz->language] ?? 'English';

        $formatInstructions = $this->getFormatInstructions($questionType);
        $guidelines = $this->getGuidelines($questionType, $this->totalQuestions);

        return <<<PROMPT
Generate EXACTLY {$this->totalQuestions} {$questionType} questions.

Title: {$quiz->title}
Subject: {$quiz->quiz_description}
Difficulty: {$difficulty}
Language: {$language}

Format:
{$formatInstructions}

Guidelines:
{$guidelines}

CRITICAL REQUIREMENTS:
1. Generate EXACTLY {$this->totalQuestions} questions - NO MORE, NO LESS
2. Count your questions before responding
3. Ensure each question has proper answers
4. Return ONLY the JSON array with exactly {$this->totalQuestions} question objects

VERIFICATION: Your response must contain exactly {$this->totalQuestions} question objects in the JSON array.

Return ONLY JSON array with {$this->totalQuestions} question objects. No explanations, no additional text.
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

    private function generateWithOpenAI(string $prompt): array
    {
        $openAiKey = getSetting()->open_api_key;
        $model = getSetting()->open_ai_model ?? 'gpt-4o-mini';

        if (!$openAiKey) {
            throw new \Exception('OpenAI API key not configured');
        }

        $maxRetries = 3;
        $questions = [];

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            Log::info("OpenAI attempt {$attempt} for {$this->totalQuestions} questions");

            try {
                $response = Http::withToken($openAiKey)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->timeout($this->timeout)
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
                
                if (count($questions) === $this->totalQuestions) {
                    Log::info("Successfully generated {$this->totalQuestions} questions on attempt {$attempt}");
                    break;
                } else {
                    Log::warning("Attempt {$attempt}: Generated " . count($questions) . " questions instead of {$this->totalQuestions}");
                    
                    if ($attempt < $maxRetries) {
                        $prompt .= "\n\n🚨 RETRY ATTEMPT {$attempt}: You previously failed to generate exactly {$this->totalQuestions} questions. You MUST generate EXACTLY {$this->totalQuestions} questions this time. Count them carefully!";
                    }
                }

            } catch (\Exception $e) {
                Log::error("OpenAI attempt {$attempt} failed: " . $e->getMessage());
                
                if ($attempt === $maxRetries) {
                    throw $e;
                }
            }
        }

        if (count($questions) !== $this->totalQuestions) {
            Log::warning("Final result: Generated " . count($questions) . " questions instead of {$this->totalQuestions}");
            
            if (count($questions) > $this->totalQuestions) {
                $questions = array_slice($questions, 0, $this->totalQuestions);
                Log::info("Trimmed questions to exact count: {$this->totalQuestions}");
            }
        }

        return $questions;
    }

    private function generateWithGemini(string $prompt): array
    {
        $geminiKey = getSetting()->gemini_api_key;
        $model = getSetting()->gemini_ai_model ?? 'gemini-pro';

        if (!$geminiKey) {
            throw new \Exception('Gemini API key not configured');
        }

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout($this->timeout)
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

    private function sendNotification(int $userId, string $type, string $title, string $body): void
    {
        try {
            Notification::make()
                ->{$type}()
                ->title($title)
                ->body($body)
                ->sendToDatabase(\App\Models\User::find($userId));
        } catch (\Exception $e) {
            Log::error("Failed to send notification: " . $e->getMessage());
        }
    }
}