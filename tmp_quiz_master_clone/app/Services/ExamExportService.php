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
            $data['template'] = $template;
            $data['includeInstructions'] = $includeInstructions;
            $data['includeAnswerKey'] = $includeAnswerKey;
            $data['fontSize'] = $fontSize;
            
            return view('exports.exam-paper-preview', $data)->render();
        } catch (\Exception $e) {
            \Log::error('Preview HTML generation failed: ' . $e->getMessage());
            throw new \Exception('Failed to generate preview HTML: ' . $e->getMessage());
        }
    }

    /**
     * Export to PDF format
     */
    protected function exportToPDF(Quiz $quiz, $template, bool $includeInstructions, bool $includeAnswerKey, string $fontSize, string $pageSize = 'A4', string $orientation = 'portrait', bool $compactMode = false, bool $includeStudentInfo = true, bool $includeTimestamp = true)
    {
        $data = $this->prepareExamData($quiz, $includeInstructions, $includeAnswerKey, $fontSize, $compactMode, $includeStudentInfo, $includeTimestamp);
        $data['template'] = $template;
        $data['includeInstructions'] = $includeInstructions;
        $data['includeAnswerKey'] = $includeAnswerKey;
        $data['fontSize'] = $fontSize;
        $data['pageSize'] = $pageSize;
        $data['orientation'] = $orientation;
        $data['compactMode'] = $compactMode;
        $data['includeStudentInfo'] = $includeStudentInfo;
        $data['includeTimestamp'] = $includeTimestamp;
        
        $pdf = Pdf::loadView('exports.exam-paper-pdf', $data);
        $pdf->setPaper($pageSize, $orientation);
        
        $filename = 'exam_' . $quiz->unique_code . '_' . time() . '.pdf';
        if ($compactMode) {
            $filename = 'exam_compact_' . $quiz->unique_code . '_' . time() . '.pdf';
        }
        $filepath = 'exports/' . $filename;
        
        Storage::disk('public')->put($filepath, $pdf->output());
        
        return [
            'filepath' => $filepath,
            'filename' => $filename,
            'download_url' => Storage::disk('public')->url($filepath)
        ];
    }

    /**
     * Export to Word format (.docx)
     */
    protected function exportToWord(Quiz $quiz, $template, bool $includeInstructions, bool $includeAnswerKey, string $fontSize, string $pageSize = 'A4', string $orientation = 'portrait', bool $compactMode = false, bool $includeStudentInfo = true, bool $includeTimestamp = true)
    {
        $phpWord = new PhpWord();
        
        // Set document properties
        $phpWord->getDocInfo()->setCreator('ExamGenerator AI')
                              ->setLastModifiedBy('ExamGenerator AI')
                              ->setTitle($quiz->title)
                              ->setSubject('Exam Paper')
                              ->setDescription('Generated exam paper')
                              ->setKeywords('exam, quiz, education')
                              ->setCategory('Education');
        
        // Add a section
        $section = $phpWord->addSection([
            'marginTop' => 1134,
            'marginBottom' => 1134,
            'marginLeft' => 1134,
            'marginRight' => 1134,
        ]);
        
        // Title
        $section->addText($quiz->title, [
            'name' => 'Arial',
            'size' => 16,
            'bold' => true,
            'color' => '000000'
        ], [
            'alignment' => 'center',
            'spaceAfter' => 240
        ]);
        
        // Instructions (if enabled)
        if ($includeInstructions) {
            $section->addText('Instructions:', [
                'name' => 'Arial',
                'size' => 12,
                'bold' => true,
                'color' => '000000'
            ], [
                'spaceBefore' => 120,
                'spaceAfter' => 60
            ]);
            
            $instructions = [
                'Read all questions carefully before answering.',
                'Answer all questions.',
                'Use black or blue ink only.',
                'No calculators or electronic devices allowed.',
                'Time limit: ' . ($quiz->time_limit ?? 'Not specified') . ' minutes.'
            ];
            
            foreach ($instructions as $instruction) {
                $section->addText('• ' . $instruction, [
                    'name' => 'Arial',
                    'size' => 11,
                    'color' => '000000'
                ], [
                    'spaceAfter' => 60,
                    'leftIndent' => 240
                ]);
            }
            
            $section->addText('', [], ['spaceAfter' => 240]);
        }
        
        // Questions
        $questions = $quiz->questions()->with('answers')->get();
        $questionNumber = 1;
        
        foreach ($questions as $question) {
            // Question text
            $section->addText($questionNumber . '. ' . $question->title, [
                'name' => 'Arial',
                'size' => 12,
                'bold' => true,
                'color' => '000000'
            ], [
                'spaceBefore' => 120,
                'spaceAfter' => 60
            ]);
            
            // Answers
            $answers = $question->answers;
            $answerLabels = ['A', 'B', 'C', 'D', 'E', 'F'];
            
            foreach ($answers as $index => $answer) {
                if ($index < count($answerLabels)) {
                    $section->addText($answerLabels[$index] . ') ' . $answer->title, [
                        'name' => 'Arial',
                        'size' => 11,
                        'color' => '000000'
                    ], [
                        'spaceAfter' => 60,
                        'leftIndent' => 240
                    ]);
                }
            }
            
            $section->addText('', [], ['spaceAfter' => 120]);
            $questionNumber++;
        }
        
        // Answer Key (if enabled)
        if ($includeAnswerKey) {
            $section->addText('', [], ['spaceBefore' => 480]);
            
            $section->addText('ANSWER KEY', [
                'name' => 'Arial',
                'size' => 14,
                'bold' => true,
                'color' => '000000'
            ], [
                'alignment' => 'center',
                'spaceAfter' => 120
            ]);
            
            $questions = $quiz->questions()->with('answers')->get();
            foreach ($questions as $index => $question) {
                $correctAnswers = $question->answers()->where('is_correct', true)->get();
                $correctOptions = [];
                
                foreach ($correctAnswers as $answer) {
                    $answerIndex = $question->answers->search(function($item) use ($answer) {
                        return $item->id === $answer->id;
                    });
                    $correctOptions[] = chr(65 + $answerIndex);
                }
                
                $section->addText('Question ' . ($index + 1) . ': ' . implode(', ', $correctOptions), [
                    'name' => 'Arial',
                    'size' => 11,
                    'color' => '000000'
                ], [
                    'spaceAfter' => 60,
                    'leftIndent' => 120
                ]);
            }
        }
        
        // Footer
        $section->addText('Generated by ExamGenerator AI - ' . now()->format('d/m/Y H:i'), [
            'name' => 'Arial',
            'size' => 9,
            'color' => '666666'
        ], [
            'alignment' => 'center',
            'spaceBefore' => 480
        ]);
        
        // Save the document
        $filename = 'exam_' . $quiz->unique_code . '_' . time() . '.docx';
        $filepath = 'exports/' . $filename;
        
        // Ensure directory exists
        $fullPath = Storage::disk('public')->path($filepath);
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // Write the document
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($fullPath);
        
        return [
            'filepath' => $filepath,
            'filename' => $filename,
            'download_url' => Storage::disk('public')->url($filepath)
        ];
    }

    /**
     * Export to HTML format
     */
    protected function exportToHTML(Quiz $quiz, $template, bool $includeInstructions, bool $includeAnswerKey, string $fontSize, string $pageSize = 'A4', string $orientation = 'portrait', bool $compactMode = false, bool $includeStudentInfo = true, bool $includeTimestamp = true)
    {
        $data = $this->prepareExamData($quiz, $includeInstructions, $includeAnswerKey, $fontSize, $compactMode, $includeStudentInfo, $includeTimestamp);
        $data['template'] = $template;
        $data['includeInstructions'] = $includeInstructions;
        $data['includeAnswerKey'] = $includeAnswerKey;
        $data['fontSize'] = $fontSize;
        
        $html = view('exports.exam-paper-html', $data)->render();
        
        $filename = 'exam_' . $quiz->unique_code . '_' . time() . '.html';
        $filepath = 'exports/' . $filename;
        
        Storage::disk('public')->put($filepath, $html);
        
        return [
            'filepath' => $filepath,
            'filename' => $filename,
            'download_url' => Storage::disk('public')->url($filepath)
        ];
    }

    /**
     * Prepare exam data for export
     */
    protected function prepareExamData(Quiz $quiz, bool $includeInstructions, bool $includeAnswerKey = false, string $fontSize = 'medium', bool $compactMode = false, bool $includeStudentInfo = true, bool $includeTimestamp = true)
    {
        $questions = $quiz->questions()->with('answers')->get();
        
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
                    $correctOptions[] = chr(65 + $answerIndex);
                }
                
                $answerKey[$index + 1] = implode(', ', $correctOptions);
            }
            $examData['answerKey'] = $answerKey;
        } else {
            $examData['answerKey'] = [];
        }
        
        return $examData;
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
            'word' => 'Microsoft Word',
            'html' => 'HTML Web Page',
        ];
    }
}
