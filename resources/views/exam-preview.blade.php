<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $exam->title }} - Exam Preview | ExamGenerator AI</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .exam-preview-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .exam-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .exam-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .exam-meta {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1rem;
        }
        
        .meta-item {
            background: rgba(255,255,255,0.1);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }
        
        .exam-content {
            padding: 2rem;
        }
        
        .exam-description {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border-left: 4px solid #667eea;
        }
        
        .questions-section {
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 1rem;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
        }
        
        .question-item {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .question-text {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1rem;
        }
        
        .answer-options {
            margin-left: 1rem;
        }
        
        .answer-option {
            background: #f8f9fa;
            padding: 0.8rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 8px;
            border-left: 3px solid #6c757d;
            transition: all 0.2s ease;
        }
        
        .answer-option.correct {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        
        .answer-option:hover {
            background: #e9ecef;
        }
        
        .exam-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .action-btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .back-btn {
            position: fixed;
            top: 2rem;
            left: 2rem;
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 50%;
            cursor: pointer;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.1);
        }
        
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .exam-title {
                font-size: 2rem;
            }
            
            .exam-meta {
                flex-direction: column;
                gap: 1rem;
            }
            
            .exam-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <button class="back-btn" onclick="goBack()">
        <i class="fas fa-arrow-left"></i>
    </button>

    <div class="exam-preview-container">
        <div class="exam-header">
            <h1 class="exam-title">{{ $exam->title }}</h1>
            <div class="exam-meta">
                <div class="meta-item">
                    <i class="fas fa-file-alt"></i>
                    {{ $exam->questions->count() }} Questions
                </div>
                <div class="meta-item">
                    <i class="fas fa-user"></i>
                    {{ $exam->user->name }}
                </div>
                <div class="meta-item">
                    <i class="fas fa-calendar"></i>
                    {{ $exam->created_at->format('M d, Y') }}
                </div>
            </div>
        </div>

        <div class="exam-content">
            @if($exam->quiz_description)
            <div class="exam-description">
                <h3>Exam Description</h3>
                <p>{{ $exam->quiz_description }}</p>
            </div>
            @endif

            <div class="questions-section">
                <h2 class="section-title">Questions Preview</h2>
                
                @foreach($exam->questions->take(3) as $index => $question)
                <div class="question-item">
                    <div class="question-text">
                        {{ $index + 1 }}. {{ $question->title }}
                    </div>
                    <div class="answer-options">
                        @foreach($question->answers as $answer)
                        <div class="answer-option {{ $answer->is_correct ? 'correct' : '' }}">
                            {{ chr(65 + $loop->index) }}) {{ $answer->title }}
                            @if($answer->is_correct)
                                <i class="fas fa-check" style="color: #28a745; margin-left: 0.5rem;"></i>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach

                @if($exam->questions->count() > 3)
                <div class="question-item" style="text-align: center; background: #f8f9fa;">
                    <p>... and {{ $exam->questions->count() - 3 }} more questions</p>
                </div>
                @endif
            </div>

            <div class="exam-actions">
                <a href="/q/{{ $exam->unique_code }}" class="action-btn btn-primary">
                    <i class="fas fa-play"></i>
                    Take This Exam
                </a>
                <a href="/exams" class="action-btn btn-secondary">
                    <i class="fas fa-list"></i>
                    Browse More Exams
                </a>
            </div>
        </div>
    </div>

    <script>
        function goBack() {
            window.history.back();
        }
    </script>
</body>
</html>
