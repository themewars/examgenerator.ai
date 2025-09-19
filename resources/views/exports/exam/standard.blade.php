<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $quiz->title }} - Exam Paper</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .exam-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .exam-info {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }
        
        .info-item {
            background: rgba(255,255,255,0.1);
            padding: 10px 20px;
            border-radius: 25px;
            backdrop-filter: blur(10px);
        }
        
        .content {
            padding: 30px;
        }
        
        .instructions {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 10px;
            border-left: 5px solid #2196f3;
        }
        
        .instructions h3 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        
        .question {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
        }
        
        .question:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .question-number {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .question-text {
            font-size: 16px;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .options {
            margin-left: 20px;
        }
        
        .option {
            background: #f8f9fa;
            padding: 10px 15px;
            margin-bottom: 8px;
            border-radius: 8px;
            border-left: 3px solid #6c757d;
            transition: all 0.2s ease;
        }
        
        .option:hover {
            background: #e9ecef;
            border-left-color: #495057;
        }
        
        .option-letter {
            font-weight: bold;
            color: #495057;
            margin-right: 10px;
        }
        
        .answer-key {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            padding: 30px;
            margin-top: 40px;
            border-radius: 10px;
        }
        
        .answer-key-title {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 25px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .answer-item {
            background: rgba(255,255,255,0.1);
            padding: 12px 20px;
            margin-bottom: 10px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        
        .answer-item strong {
            color: #fff;
        }
        
        .footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 40px;
        }
        
        @media print {
            body { background: white; }
            .container { box-shadow: none; }
            .question:hover { transform: none; box-shadow: none; }
            .option:hover { background: #f8f9fa; }
        }
        
        @media (max-width: 768px) {
            .exam-info {
                flex-direction: column;
                align-items: center;
            }
            
            .content {
                padding: 20px;
            }
            
            .exam-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="exam-title">{{ $quiz->title }}</div>
            <div class="exam-info">
                <div class="info-item">📅 Date: {{ $examDate }}</div>
                <div class="info-item">⏱️ Time: {{ $timeLimit }}</div>
                <div class="info-item">📝 Questions: {{ $totalQuestions }}</div>
            </div>
        </div>

        <div class="content">
            @if($includeInstructions && $quiz->quiz_description)
            <div class="instructions">
                <h3>📋 Instructions</h3>
                {{ $quiz->quiz_description }}
            </div>
            @endif

            <div class="questions">
                @foreach($questions as $index => $question)
                <div class="question">
                    <div class="question-number">Question {{ $index + 1 }}</div>
                    <div class="question-text">{{ $question->title }}</div>
                    <div class="options">
                        @foreach($question->answers as $answerIndex => $answer)
                        <div class="option">
                            <span class="option-letter">{{ chr(65 + $answerIndex) }})</span>
                            {{ $answer->title }}
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>

            @if($includeAnswerKey && count($answerKey) > 0)
            <div class="answer-key">
                <div class="answer-key-title">🔑 ANSWER KEY</div>
                @foreach($answerKey as $questionNumber => $correctAnswer)
                <div class="answer-item">
                    <strong>Question {{ $questionNumber }}:</strong> {{ $correctAnswer }}
                </div>
                @endforeach
            </div>
            @endif
        </div>

        <div class="footer">
            Generated by ExamGenerator.ai - {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
