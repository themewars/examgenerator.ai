<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\Question;
use App\Models\Answer;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Paragraph;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ExamExportService
{
    /**
     * Export quiz as exam paper in multiple formats
     */
    public function exportExamPaper(Quiz $quiz, $format = 'pdf', $template = 'standard', bool $includeInstructions = true, bool $includeAnswerKey = false, string $fontSize = 'medium', string $pageSize = 'A4', string $orientation = 'portrait', bool $compactMode = false, bool $includeStudentInfo = true, bool $includeTimestamp = true)
    {
        switch ($format) {
            case 'pdf':
                return $this->exportToPDF($quiz, $template, $includeInstructions, $includeAnswerKey, $fontSize, $pageSize, $orientation, $compactMode, $includeStudentInfo, $includeTimestamp);
            case 'word':
                return $this->exportToWord($quiz, $template, $includeInstructions, $includeAnswerKey, $fontSize, $pageSize, $orientation, $compactMode, $includeStudentInfo, $includeTimestamp);
            case 'html':
                return $this->exportToHTML($quiz, $template, $includeInstructions, $includeAnswerKey, $fontSize, $pageSize, $orientation, $compactMode, $includeStudentInfo, $includeTimestamp);
            default:
                throw new \InvalidArgumentException('Unsupported export format');
        }
    }

    /**
     * Generate preview HTML
     */
    public function generatePreviewHtml(Quiz $quiz, $template = 'standard', bool $includeInstructions = true, bool $includeAnswerKey = false, string $fontSize = 'medium')
    {
        try {
            $data = $this->prepareExamData($quiz, $includeInstructions, $includeAnswerKey, $fontSize, false, true, true);
            
            return view("exports.exam.{$template}", $data)->render();
        } catch (\Exception $e) {
            \Log::error('Preview HTML generation failed: ' . $e->getMessage());
            throw new \Exception('Failed to generate preview HTML: ' . $e->getMessage());
        }
    }

    /**
     * Export to PDF
     */
    protected function exportToPDF(Quiz $quiz, $template, bool $includeInstructions, bool $includeAnswerKey, string $fontSize, string $pageSize, string $orientation, bool $compactMode, bool $includeStudentInfo, bool $includeTimestamp)
    {
        try {
            // Ensure exports directory exists
            $exportsPath = public_path('uploads/exports');
            if (!file_exists($exportsPath)) {
                mkdir($exportsPath, 0755, true);
            }

            $data = $this->prepareExamData($quiz, $includeInstructions, $includeAnswerKey, $fontSize, $compactMode, $includeStudentInfo, $includeTimestamp);
            
            // Check if template exists
            $templatePath = "exports.exam.{$template}";
            if (!view()->exists($templatePath)) {
                throw new \Exception("Template '{$templatePath}' not found");
            }
            
            $pdf = Pdf::loadView($templatePath, $data)
                ->setPaper($pageSize, $orientation)
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true,
                    'defaultFont' => 'NotoSansDevanagari',
                    'isPhpEnabled' => true,
                    'isJavascriptEnabled' => false,
                    'fontDir' => storage_path('fonts'),
                    'fontCache' => storage_path('fonts'),
                    'enable_font_subsetting' => false,
                ]);

            $filename = $this->generateFilename($quiz, 'pdf');
            $filePath = "exports/{$filename}";
            
            // Generate PDF content
            $pdfContent = $pdf->output();
            if (empty($pdfContent)) {
                throw new \Exception('PDF generation failed - empty content');
            }
            
            // Save to storage
            $saved = Storage::disk('public')->put($filePath, $pdfContent);
            if (!$saved) {
                throw new \Exception('Failed to save PDF file to storage');
            }
            
            return [
                'success' => true,
                'file_path' => $filePath,
                'download_url' => Storage::disk('public')->url($filePath),
                'filename' => $filename,
            ];
            
        } catch (\Exception $e) {
            \Log::error('PDF export failed: ' . $e->getMessage(), [
                'quiz_id' => $quiz->id,
                'template' => $template,
                'error' => $e->getTraceAsString()
            ]);
            throw new \Exception('PDF export failed: ' . $e->getMessage());
        }
    }

    /**
     * Export to Word
     */
    protected function exportToWord(Quiz $quiz, $template, bool $includeInstructions, bool $includeAnswerKey, string $fontSize, string $pageSize, string $orientation, bool $compactMode, bool $includeStudentInfo, bool $includeTimestamp)
    {
        try {
            // Ensure exports directory exists
            $exportsPath = public_path('uploads/exports');
            if (!file_exists($exportsPath)) {
                mkdir($exportsPath, 0755, true);
            }

            $data = $this->prepareExamData($quiz, $includeInstructions, $includeAnswerKey, $fontSize, $compactMode, $includeStudentInfo, $includeTimestamp);
            
            $phpWord = new PhpWord();
            $phpWord->setDefaultFontName('Arial');
            $phpWord->setDefaultFontSize(12);
            
            $section = $phpWord->addSection([
                'marginTop' => 1134,
                'marginBottom' => 1134,
                'marginLeft' => 1134,
                'marginRight' => 1134,
            ]);

            // Add title
            $section->addText($quiz->title, ['bold' => true, 'size' => 16]);
            $section->addTextBreak();

            // Add instructions if requested
            if ($includeInstructions) {
                $section->addText('Instructions:', ['bold' => true, 'size' => 12]);
                $section->addText('• Read each question carefully before answering');
                $section->addText('• Choose the best answer for each question');
                $section->addText('• Mark your answers clearly');
                $section->addTextBreak();
            }

            // Add questions
            foreach ($data['questions'] as $index => $question) {
                $section->addText(($index + 1) . '. ' . $question->title, ['bold' => true]);
                
                foreach ($question->answers as $answerIndex => $answer) {
                    $option = chr(65 + $answerIndex); // A, B, C, D
                    $section->addText("   {$option}. {$answer->title}");
                }
                $section->addTextBreak();
            }

            // Add answer key if requested
            if ($includeAnswerKey && !empty($data['answerKey'])) {
                $section->addPageBreak();
                $section->addText('Answer Key', ['bold' => true, 'size' => 14]);
                $section->addTextBreak();
                
                foreach ($data['answerKey'] as $questionNum => $answer) {
                    $section->addText("Question {$questionNum}: {$answer}");
                }
            }

            $filename = $this->generateFilename($quiz, 'docx');
            $filePath = "exports/{$filename}";
            
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save(Storage::disk('public')->path($filePath));
            
            return [
                'success' => true,
                'file_path' => $filePath,
                'download_url' => Storage::disk('public')->url($filePath),
                'filename' => $filename,
            ];
            
        } catch (\Exception $e) {
            \Log::error('Word export failed: ' . $e->getMessage(), [
                'quiz_id' => $quiz->id,
                'template' => $template,
                'error' => $e->getTraceAsString()
            ]);
            throw new \Exception('Word export failed: ' . $e->getMessage());
        }
    }

    /**
     * Export to HTML
     */
    protected function exportToHTML(Quiz $quiz, $template, bool $includeInstructions, bool $includeAnswerKey, string $fontSize, string $pageSize, string $orientation, bool $compactMode, bool $includeStudentInfo, bool $includeTimestamp)
    {
        try {
            // Ensure exports directory exists
            $exportsPath = public_path('uploads/exports');
            if (!file_exists($exportsPath)) {
                mkdir($exportsPath, 0755, true);
            }

            $data = $this->prepareExamData($quiz, $includeInstructions, $includeAnswerKey, $fontSize, $compactMode, $includeStudentInfo, $includeTimestamp);
            
            // Check if template exists
            $templatePath = "exports.exam.{$template}";
            if (!view()->exists($templatePath)) {
                throw new \Exception("Template '{$templatePath}' not found");
            }
            
            $html = view($templatePath, $data)->render();
            
            if (empty($html)) {
                throw new \Exception('HTML generation failed - empty content');
            }
            
            $filename = $this->generateFilename($quiz, 'html');
            $filePath = "exports/{$filename}";
            
            $saved = Storage::disk('public')->put($filePath, $html);
            if (!$saved) {
                throw new \Exception('Failed to save HTML file to storage');
            }
            
            return [
                'success' => true,
                'file_path' => $filePath,
                'download_url' => Storage::disk('public')->url($filePath),
                'filename' => $filename,
            ];
            
        } catch (\Exception $e) {
            \Log::error('HTML export failed: ' . $e->getMessage(), [
                'quiz_id' => $quiz->id,
                'template' => $template,
                'error' => $e->getTraceAsString()
            ]);
            throw new \Exception('HTML export failed: ' . $e->getMessage());
        }
    }

    /**
     * Prepare exam data for export
     */
    protected function prepareExamData(Quiz $quiz, bool $includeInstructions, bool $includeAnswerKey = false, string $fontSize = 'medium', bool $compactMode = false, bool $includeStudentInfo = true, bool $includeTimestamp = true)
    {
        try {
            $questions = $quiz->questions()->with('answers')->get();
            
            if ($questions->isEmpty()) {
                throw new \Exception('No questions found for this quiz');
            }
            
            $examData = [
                'quiz' => $quiz,
                'questions' => $questions,
                'totalQuestions' => $questions->count(),
                'examDate' => now()->format('d/m/Y'),
                'timeLimit' => $quiz->time_configuration ? $quiz->time . ' ' . ($quiz->time_type == 1 ? 'minutes per question' : 'minutes total') : 'No time limit',
                'includeInstructions' => $includeInstructions,
                'includeAnswerKey' => $includeAnswerKey,
                'fontSize' => $fontSize,
                'compactMode' => $compactMode,
                'includeStudentInfo' => $includeStudentInfo,
                'includeTimestamp' => $includeTimestamp,
                'exportTimestamp' => now()->format('d/m/Y H:i:s'),
            ];

            // Prepare answer key only if needed
            if ($includeAnswerKey) {
                $answerKey = [];
                foreach ($questions as $index => $question) {
                    $correctAnswers = $question->answers()->where('is_correct', true)->get();
                    $correctOptions = [];
                    
                    foreach ($correctAnswers as $answer) {
                        $answerIndex = $question->answers->search(function($item) use ($answer) {
                            return $item->id === $answer->id;
                        });
                        if ($answerIndex !== false) {
                            $correctOptions[] = chr(65 + $answerIndex);
                        }
                    }
                    
                    $answerKey[$index + 1] = implode(', ', $correctOptions);
                }
                $examData['answerKey'] = $answerKey;
            } else {
                $examData['answerKey'] = [];
            }
            
            return $examData;
            
        } catch (\Exception $e) {
            \Log::error('Failed to prepare exam data: ' . $e->getMessage(), [
                'quiz_id' => $quiz->id,
                'error' => $e->getTraceAsString()
            ]);
            throw new \Exception('Failed to prepare exam data: ' . $e->getMessage());
        }
    }

    /**
     * Generate filename for export
     */
    protected function generateFilename(Quiz $quiz, string $extension): string
    {
        try {
            $title = preg_replace('/[^a-zA-Z0-9_-]/', '_', $quiz->title);
            $timestamp = now()->format('Y-m-d_H-i-s');
            
            return "exam_{$title}_{$timestamp}.{$extension}";
        } catch (\Exception $e) {
            \Log::error('Failed to generate filename: ' . $e->getMessage());
            return "exam_" . time() . ".{$extension}";
        }
    }

    /**
     * Get available export templates
     */
    public function getAvailableTemplates()
    {
        return [
            'standard' => 'Standard Format',
            'compact' => 'Compact Format',
            'detailed' => 'Detailed Format',
            'minimal' => 'Minimal Format',
        ];
    }

    /**
     * Get available export formats
     */
    public function getAvailableFormats()
    {
        return [
            'pdf' => 'PDF Document',
            'word' => 'Word Document',
            'html' => 'HTML File',
        ];
    }
}