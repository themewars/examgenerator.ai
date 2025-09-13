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

class GenerateQuizJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $quizId;
    public string $model;
    public string $prompt;
    public int $totalQuestions;
    public int $batchSize;

    public function __construct(int $quizId, string $model, string $prompt, int $totalQuestions, int $batchSize = 20)
    {
        $this->quizId = $quizId;
        $this->model = $model;
        $this->prompt = $prompt;
        $this->totalQuestions = $totalQuestions;
        $this->batchSize = $batchSize;
    }

    public function handle(): void
    {
        Log::info("Starting GenerateQuizJob for quiz {$this->quizId}");
        
        $quiz = Quiz::find($this->quizId);
        if (!$quiz) {
            Log::error("Quiz {$this->quizId} not found");
            return;
        }

        // Update quiz status to processing
        $quiz->update([
            'generation_status' => 'processing',
            'generation_progress_total' => $this->totalQuestions,
            'generation_progress_done' => 0,
        ]);

        Log::info("Quiz {$this->quizId} after update - Progress total: {$quiz->generation_progress_total}, Progress done: {$quiz->generation_progress_done}");

        $remaining = $this->totalQuestions - ($quiz->generation_progress_done ?? 0);
        while ($remaining > 0) {
            $take = min($this->batchSize, $remaining);
            try {
                $batchPrompt = $this->prompt . "\n\nGenerate exactly {$take} questions. Return ONLY a JSON array in this format:\n\n[\n  {\n    \"question\": \"Question text\",\n    \"answers\": [\n      {\"title\": \"A\"},\n      {\"title\": \"B\"},\n      {\"title\": \"C\"},\n      {\"title\": \"D\"}\n    ],\n    \"correct_answer_key\": \"B\"\n  }\n]\n\nNo explanations, no markdown, just JSON.";

                $apiKey = \App\Models\Setting::first()?->open_api_key ?? '';
                if (empty($apiKey)) {
                    throw new \RuntimeException('OpenAI API key not configured');
                }
                
                Log::info("Making OpenAI API call for {$take} questions. Model: {$this->model}");
                
                $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->timeout(180)
                    ->retry(3, 2000)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $this->model,
                        'messages' => [
                            ['role' => 'user', 'content' => $batchPrompt],
                        ],
                        'temperature' => 0.7,
                        'max_tokens' => 4000,
                    ]);
                
                Log::info("OpenAI API response status: " . $response->status());

                if ($response->failed()) {
                    throw new \RuntimeException($response->json()['error']['message'] ?? 'OpenAI error');
                }

                $content = $response['choices'][0]['message']['content'] ?? '';
                Log::info("Received content length: " . strlen($content));

                if (empty($content)) {
                    throw new \RuntimeException('Empty response from OpenAI');
                }

                // Clean and parse JSON
                $quizData = trim($content);
                
                // Remove markdown code blocks
                if (stripos($quizData, '```json') !== false) {
                    $quizData = preg_replace('/```json\s*|\s*```/', '', $quizData);
                    $quizData = trim($quizData);
                }
                
                // Remove any leading/trailing text and extract JSON array
                if (preg_match('/\[[\s\S]*\]/', $quizData, $matches)) {
                    $quizData = $matches[0];
                }

                $questions = json_decode($quizData, true);
                if (!is_array($questions) || empty($questions)) {
                    Log::error("Failed to parse JSON. Content: " . substr($content, 0, 500));
                    throw new \RuntimeException('Invalid JSON response from OpenAI');
                }

                Log::info("Parsed {$take} questions from API response");

                // Bulk create questions and answers for better performance
                $createdCount = 0;
                $questionsToInsert = [];
                $answersToInsert = [];
                
                foreach ($questions as $questionData) {
                    if (!isset($questionData['question'], $questionData['answers'])) {
                        Log::warning("Skipping invalid question structure");
                        continue;
                    }

                    $questionsToInsert[] = [
                        'quiz_id' => $this->quizId,
                        'title' => $questionData['question'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $createdCount++;
                }
                
                // Bulk insert questions
                if (!empty($questionsToInsert)) {
                    Question::insert($questionsToInsert);
                    
                    // Get the inserted question IDs
                    $insertedQuestions = Question::where('quiz_id', $this->quizId)
                        ->orderBy('id', 'desc')
                        ->limit(count($questionsToInsert))
                        ->get();
                    
                    // Prepare answers for bulk insert
                    $questionIndex = 0;
                    foreach ($questions as $questionData) {
                        if (!isset($questionData['question'], $questionData['answers'])) {
                            continue;
                        }
                        
                        $questionId = $insertedQuestions[$questionIndex]->id;
                        $correctKey = $questionData['correct_answer_key'] ?? '';
                        
                        foreach ($questionData['answers'] as $answerData) {
                            $isCorrect = false;
                            if (is_array($correctKey)) {
                                $isCorrect = in_array($answerData['title'], $correctKey);
                            } else {
                                $isCorrect = $answerData['title'] === $correctKey;
                            }

                            $answersToInsert[] = [
                                'question_id' => $questionId,
                                'title' => $answerData['title'],
                                'is_correct' => $isCorrect,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                        $questionIndex++;
                    }
                    
                    // Bulk insert answers
                    if (!empty($answersToInsert)) {
                        Answer::insert($answersToInsert);
                    }
                }

                // Update progress
                $currentProgress = $quiz->generation_progress_done ?? 0;
                $newProgress = $currentProgress + $createdCount;
                
                $quiz->update([
                    'generation_progress_done' => $newProgress,
                ]);

                Log::info("Created {$createdCount} questions. Total progress: {$newProgress}/{$this->totalQuestions}");

                $remaining = $this->totalQuestions - $newProgress;
                
                // Small delay to avoid rate limiting
                if ($remaining > 0) {
                    sleep(1);
                }

            } catch (\Exception $e) {
                Log::error("Error in GenerateQuizJob: " . $e->getMessage());
                Log::error("Stack trace: " . $e->getTraceAsString());
                
                $quiz->update([
                    'generation_status' => 'failed',
                    'generation_error' => $e->getMessage(),
                ]);
                
                throw $e;
            }
        }

        // Mark as completed
        $finalQuestionCount = Question::where('quiz_id', $this->quizId)->count();
        $quiz->update([
            'generation_status' => 'completed',
            'generation_progress_done' => $finalQuestionCount,
            'generation_progress_total' => $finalQuestionCount,
        ]);

        Log::info("GenerateQuizJob completed for quiz {$this->quizId}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("GenerateQuizJob failed for quiz {$this->quizId}: " . $exception->getMessage());
        
        $quiz = Quiz::find($this->quizId);
        if ($quiz) {
            $quiz->update([
                'generation_status' => 'failed',
                'generation_error' => $exception->getMessage(),
            ]);
        }
    }
}