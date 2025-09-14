<?php

namespace App\Models;

use Carbon\Carbon;
use Filament\Forms\Get;
use Spatie\MediaLibrary\HasMedia;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Facades\Date;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\CheckboxList;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Models\Category;

class Quiz extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $table = 'quizzes';

    public const QUIZ_PATH = 'exam_document';

    protected $fillable = [
        'title',
        'quiz_description',
        'user_id',
        'status',
        'type',
        'category_id',
        'diff_level',
        'quiz_type',
        'max_questions',
        'unique_code',
        'view_count',
        'time_configuration',
        'time',
        'time_type',
        'quiz_expiry_date',
        'is_show_home',
        'generation_status',
        'generation_progress_total',
        'generation_progress_done',
        'generation_error',
    ];

    protected $casts = [
        'title' => 'string',
        'quiz_description' => 'string',
        'user_id' => 'integer',
        'status' => 'boolean',
        'type' => 'integer',
        'diff_level' => 'integer',
        'quiz_type' => 'integer',
        'max_questions' => 'integer',
        'unique_code' => 'string',
        'view_count' => 'integer',
        'generation_progress_total' => 'integer',
        'generation_progress_done' => 'integer',
    ];

    const TEXT_TYPE = 1;

    const SUBJECT_TYPE = 2;

    const URL_TYPE = 3;

    const UPLOAD_TYPE = 4;

    const IMAGE_TYPE = 5;

    const TIME_OVER_QUESTION = 1;

    const TIME_OVER_QUIZ = 2;

    const QUIZ_INPUT_TYPE = [
        self::TEXT_TYPE => 'Text',
        self::SUBJECT_TYPE => 'Subject',
        self::URL_TYPE => 'URL',
        self::UPLOAD_TYPE => 'Upload File',
        self::IMAGE_TYPE => 'Upload Image',
    ];

    const OPEN_AI = 1;

    const GEMINI_AI = 2;

    const AI_TYPES = [
        self::OPEN_AI => 'Open AI',
        self::GEMINI_AI => 'Gemini AI',
    ];

    protected $appends = [
        'quiz_document',
        'question_count',
    ];

    public function getQuizDocumentAttribute()
    {
        return $this->getFirstMediaUrl(self::QUIZ_PATH);
    }

    const MULTIPLE_CHOICE = 0;
    const SINGLE_CHOICE = 1;
    const SHORT_ANSWER = 2;
    const LONG_ANSWER = 3;
    const TRUE_FALSE = 4;
    const FILL_BLANK = 5;
    
    const QUIZ_TYPE = [
        self::MULTIPLE_CHOICE => 'Multiple Choices',
        self::SINGLE_CHOICE => 'Single Choice',
        self::SHORT_ANSWER => 'Short Answer',
        self::LONG_ANSWER => 'Long Answer',
        self::TRUE_FALSE => 'True/False',
        self::FILL_BLANK => 'Fill in the Blank',
    ];

    public static function getQuizTypeOptions()
    {
        // Get user's plan allowed question types
        $userPlan = auth()->user()?->subscriptions()->where('status', \App\Enums\SubscriptionStatus::ACTIVE->value)->orderByDesc('id')->first()?->plan;
        $allowedTypes = $userPlan?->getAllowedQuestionTypes() ?? ['mcq'];
        
        // Map question types to form options
        $typeMapping = [
            'mcq' => 0, // Multiple Choice
            'short_answer' => 2, // Short Answer
            'long_answer' => 3, // Long Answer
            'true_false' => 4, // True/False
            'fill_blank' => 5, // Fill in the Blank
        ];
        
        $options = [];
        
        // Add Multiple Choice if allowed
        if (in_array('mcq', $allowedTypes)) {
            $options[0] = __('messages.home.multiple_choice');
        }
        
        // Add Single Choice if allowed (this is a subset of MCQ)
        if (in_array('mcq', $allowedTypes)) {
            $options[1] = __('messages.home.single_choice');
        }
        
        // Add other question types if allowed
        if (in_array('short_answer', $allowedTypes)) {
            $options[2] = 'Short Answer';
        }
        
        if (in_array('long_answer', $allowedTypes)) {
            $options[3] = 'Long Answer';
        }
        
        if (in_array('true_false', $allowedTypes)) {
            $options[4] = 'True/False';
        }
        
        if (in_array('fill_blank', $allowedTypes)) {
            $options[5] = 'Fill in the Blank';
        }
        
        return $options;
    }

    const DIFF_LEVEL = [
        0 => 'Basic',
        1 => 'Intermediate',
        2 => 'Advanced',
    ];

    public static function getDiffLevelOptions()
    {
        return [
            0 => __('messages.quiz.basic'),
            1 => __('messages.quiz.intermediate'),
            2 => __('messages.quiz.advanced'),
        ];
    }

    protected static function booted()
    {
        static::created(function (Quiz $quiz) {
            try {
                // Increment monthly exam usage when a quiz is created
                $user = $quiz->user()->first();
                if ($user) {
                    (new \App\Services\PlanValidationService($user))->updateUsage(1, 0);
                }
            } catch (\Throwable $e) {
                \Log::warning('Failed to bump usage on quiz create', [
                    'quiz_id' => $quiz->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    protected function getQuestionCountAttribute()
    {
        return $this->questions()->count();
    }

    public function quizUser()
    {
        return $this->hasMany(UserQuiz::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public static function  getForm(): array
    {
        return [
            Section::make()
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Grid::make(1)
                                ->schema([
                                    Group::make([
                                        TextInput::make('title')
                                            ->label(__('messages.quiz.title') . ':')
                                            ->placeholder(__('messages.quiz.quiz_title'))
                                            ->validationAttribute(__('messages.quiz.title'))
                                            ->required(),
                                        Select::make('category_id')
                                            ->label(__('messages.quiz.select_category') . ':')
                                            ->placeholder(__('messages.quiz.select_category'))
                                            ->validationAttribute(__('messages.quiz.category'))
                                            ->options(function () {
                                                return Category::all()->pluck('name', 'id');
                                            })
                                            ->searchable()
                                            ->required()
                                            ->preload()
                                            ->native(false)
                                    ]),
                                    Section::make()
                                        ->schema([
                                            Select::make('quiz_type')
                                                ->label(__('messages.quiz.question_type') . ':')
                                                ->options(Quiz::getQuizTypeOptions())
                                                ->default(0)
                                                ->searchable()
                                                ->required()
                                                ->preload()
                                                ->live()
                                                ->native(false)
                                                ->placeholder(__('messages.quiz.select_question'))
                                                ->validationAttribute(__('messages.quiz.question_type')),
                                            Select::make('diff_level')
                                                ->label(__('messages.quiz.difficulty') . ':')
                                                ->options(Quiz::getDiffLevelOptions())
                                                ->default(0)
                                                ->required()
                                                ->searchable()
                                                ->preload()
                                                ->native(false)
                                                ->placeholder(__('messages.quiz.select_difficulty'))
                                                ->validationAttribute(__('messages.quiz.difficulty')),
                                            TextInput::make('max_questions')
                                                ->numeric()
                                                ->rules(function () {
                                                    $userPlan = auth()->user()?->subscriptions()->where('status', \App\Enums\SubscriptionStatus::ACTIVE->value)->orderByDesc('id')->first()?->plan;
                                                    $maxQuestions = $userPlan?->max_questions_per_exam ?? 20;
                                                    if ($maxQuestions == -1) {
                                                        $maxQuestions = 20; // Still limit to 20 for API safety
                                                    }
                                                    return ['integer', 'max:' . min($maxQuestions, 20)];
                                                })
                                                ->integer()
                                                ->required()
                                                ->minValue(1)
                                                ->maxValue(function () {
                                                    $userPlan = auth()->user()?->subscriptions()->where('status', \App\Enums\SubscriptionStatus::ACTIVE->value)->orderByDesc('id')->first()?->plan;
                                                    $maxQuestions = $userPlan?->max_questions_per_exam ?? 20;
                                                    if ($maxQuestions == -1) {
                                                        $maxQuestions = 20; // Still limit to 20 for API safety
                                                    }
                                                    return min($maxQuestions, 20);
                                                })
                                                ->label(__('messages.quiz.num_of_questions') . ':')
                                                ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Maximum 20 questions allowed to prevent API failures')
                                                ->placeholder(__('messages.quiz.number_of_questions'))
                                                ->validationAttribute(__('messages.quiz.num_of_questions')),
                                            Select::make('language')
                                                ->label(__('messages.home.language') . ':')
                                                ->options(getAllLanguages())
                                                ->preload()
                                                ->searchable()
                                                ->native(false)
                                                ->default('en')
                                                ->validationAttribute(__('messages.home.language'))
                                        ])
                                        ->columns(2),
                                ])
                                ->columnSpan(1),

                            Grid::make(1)
                                ->schema([
                                    Tabs::make('Tabs')
                                        ->tabs([
                                            Tab::make('Text')
                                                ->label(__('messages.quiz.text'))
                                                ->schema([
                                                    Textarea::make('quiz_description_text')
                                                        ->label(__('messages.quiz.description') . ':')
                                                        ->placeholder(__('messages.quiz.quiz_description'))
                                                        ->formatStateUsing(function ($get, $operation) {
                                                            if ($operation == 'edit' && $get('type') == 1) {
                                                                return $get('quiz_description');
                                                            }
                                                        })
                                                        ->required(function ($get) {
                                                            return getTabType() == 1 || $get('type') == 1;
                                                        })
                                                        ->live()
                                                        ->validationAttribute(__('messages.quiz.description'))
                                                        ->rows(5)
                                                        ->cols(10),
                                                ]),
                                            Tab::make('Subject')
                                                ->label(__('messages.quiz.subject'))
                                                ->schema([
                                                    TextInput::make('quiz_description_sub')
                                                        ->label(__('messages.quiz.subject') . ':')
                                                        ->placeholder(__('messages.quiz.e_g_biology'))
                                                        ->formatStateUsing(function ($get, $operation) {
                                                            if ($operation == 'edit' && $get('type') == 2) {
                                                                return $get('quiz_description');
                                                            }
                                                        })
                                                        ->required(function ($get) {
                                                            return getTabType() == 2 || $get('type') == 2;
                                                        })
                                                        ->live()
                                                        ->validationAttribute(__('messages.quiz.subject'))
                                                        ->maxLength(250)
                                                        ->helperText(__('messages.quiz.enter_a_subject_to_generate_question_about'))
                                                        ->autocomplete('off'),
                                                ]),
                                            Tab::make('URL')
                                                ->label(__('messages.quiz.url'))
                                                ->schema([
                                                    TextInput::make('quiz_description_url')
                                                        ->label(__('messages.quiz.url') . ':')
                                                        ->formatStateUsing(function ($get, $operation) {
                                                            if ($operation == 'edit' && $get('type') == 3) {
                                                                return $get('quiz_description');
                                                            }
                                                        })
                                                        ->required(function ($get) {
                                                            return getTabType() == 3 || $get('type') == 3;
                                                        })
                                                        ->live()
                                                        ->validationAttribute(__('messages.quiz.url'))
                                                        ->url()
                                                        ->placeholder(__('messages.quiz.please_enter_url'))
                                                        ->disabled(function () {
                                                            $planCheck = (new \App\Services\PlanValidationService(auth()->user()))->canUseFeature('website_quiz');
                                                            return !$planCheck['allowed'];
                                                        })
                                                        ->helperText(function () {
                                                            $planCheck = (new \App\Services\PlanValidationService(auth()->user()))->canUseFeature('website_quiz');
                                                            return !$planCheck['allowed'] ? 'Website to Exam feature not available in your current plan' : '';
                                                        }),
                                                ]),
                                            Tab::make('Upload')
                                                ->label(__('messages.quiz.upload'))
                                                ->schema([
                                                    SpatieMediaLibraryFileUpload::make('file_upload')
                                                        ->label(__('messages.quiz.document') . ':')
                                                        ->hintIcon('heroicon-m-question-mark-circle', tooltip: __('messages.quiz.file_upload_hint'))
                                                        ->validationAttribute(__('messages.quiz.document'))
                                                        ->disk(config('app.media_disk'))
                                                        ->required(function ($get) {
                                                            return getTabType() == 4 || $get('type') == 4;
                                                        })
                                                        ->live()
                                                        ->collection(Quiz::QUIZ_PATH)
                                                        ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                                                        ->disabled(function () {
                                                            $planCheck = (new \App\Services\PlanValidationService(auth()->user()))->canUseFeature('pdf_to_exam');
                                                            return !$planCheck['allowed'];
                                                        })
                                                        ->helperText(function () {
                                                            $planCheck = (new \App\Services\PlanValidationService(auth()->user()))->canUseFeature('pdf_to_exam');
                                                            return !$planCheck['allowed'] ? 'PDF to Exam feature not available in your current plan' : '';
                                                        }),
                                                ]),
                                            Tab::make('Image')
                                                ->label(__('messages.quiz.image'))
                                                ->schema([
                                                    SpatieMediaLibraryFileUpload::make('image_upload')
                                                        ->label(__('messages.quiz.image') . ':')
                                                        ->hintIcon('heroicon-m-question-mark-circle', tooltip: __('messages.quiz.image_upload_hint'))
                                                        ->validationAttribute(__('messages.quiz.image'))
                                                        ->disk(config('app.media_disk'))
                                                        ->required(function ($get) {
                                                            return getTabType() == 5 || $get('type') == 5;
                                                        })
                                                        ->live()
                                                        ->collection(Quiz::QUIZ_PATH)
                                                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/bmp', 'image/tiff', 'image/gif'])
                                                        ->helperText(__('messages.quiz.image_upload_helper')),
                                                ]),
                                        ])
                                        ->activeTab(function ($get) {
                                            return $get('type') ?? 1;
                                        })
                                        ->extraAttributes([
                                            'wire:click' => 'currentActiveTab',
                                        ])
                                        ->persistTabInQueryString(),
                                    Section::make()
                                        ->schema([
                                            Toggle::make("time_configuration")
                                                ->live()
                                                ->label(__('messages.quiz.time_configuration') . ':'),
                                            DatePicker::make('quiz_expiry_date')
                                                ->placeholder(__('messages.quiz.expiry_date'))
                                                ->minDate(now()->format('Y-m-d'))
                                                ->label(__('messages.quiz.expiry_date') . ':')
                                                ->native(false)
                                                ->hintAction(
                                                    Action::make('clearDate')
                                                        ->iconButton()
                                                        ->icon('heroicon-o-x-circle')
                                                        ->tooltip(__('messages.common.clear_date'))
                                                        ->action(function (\Filament\Forms\Set $set) {
                                                            $set('quiz_expiry_date', null);
                                                        })
                                                ),
                                            Section::make()
                                                ->schema([
                                                    TextInput::make('time')
                                                        ->numeric()
                                                        ->placeholder(__('messages.quiz.time'))
                                                        ->required()
                                                        ->minValue(1)
                                                        ->rules(['integer', 'min:1'])
                                                        ->label(__('messages.quiz.time_label') . ':')
                                                        ->extraAttributes([
                                                            'onkeydown' => "if(event.key === '-' || event.key === '+' || event.key === 'e'){ event.preventDefault(); }"
                                                        ]),

                                                    Radio::make('time_type')
                                                        ->options([
                                                            1 => __('messages.quiz.time_question'),
                                                            2 => __('messages.quiz.time_quiz'),
                                                        ])
                                                        ->required()
                                                        ->label(__('messages.quiz.time_type') . ':'),

                                                ])->live()->columns(2)->hidden(function ($get) {
                                                    return !$get('time_configuration');
                                                }),
                                        ])
                                        ->columns(2),
                                ])
                                ->columnSpan(1),
                        ])
                        ->columns(2),
                ]),

            Repeater::make('questions')
                ->label(__('messages.common.questions'))
                ->columnSpanFull()
                ->reorderableWithDragAndDrop(true)
                ->schema([
                    TextInput::make('title')
                        ->label(__('messages.common.question') . ':')
                        ->validationAttribute(__('messages.common.question'))
                        ->required(),
                    CheckboxList::make('is_correct')
                        ->options(fn($get) => collect($get('answers'))->mapWithKeys(fn($answer, $index) => [$index => $answer['title']])->toArray())
                        ->required()
                        ->minItems(1)
                        ->maxItems(function (Get $get) {
                            $quizType = $get('../../quiz_type');
                            return $quizType == Quiz::SINGLE_CHOICE ? 1 : 4;
                        })
                        ->columns(2)
                        ->validationAttribute(__('messages.common.answer'))
                        ->label(__('messages.common.answer') . ':')
                        ->afterStateUpdated(function ($state, $set, $get) {
                            $answers = $get('answers') ?? [];

                            foreach ($answers as $index => $answer) {
                                $answers[$index]['is_correct'] = in_array($index, $state);
                            }

                            $set('answers', $answers);
                        })
                        ->afterStateHydrated(function ($set, $get, $state) {
                            $correctAnswer = $get('is_correct');
                            if (is_array($correctAnswer)) {
                                $set('is_correct', $correctAnswer);
                            } elseif ($correctAnswer !== null) {
                                $set('is_correct', [$correctAnswer]);
                            }
                        })
                        ->visible(fn(Get $get) => !empty($get('answers'))),
                ])
                ->visible(fn(Get $get) => !empty($get('questions')))
                ->hidden(fn(string $operation): bool => $operation === 'create')
                ->addable(false),

            Repeater::make('custom_questions')
                ->columnSpanFull()
                ->label('')
                ->reorderableWithDragAndDrop(true)
                ->addActionLabel('Add New Question Manual')
                ->hidden(fn(string $operation): bool => $operation === 'create')
                ->addable(function () {
                    $userPlan = auth()->user()?->subscriptions()->where('status', \App\Enums\SubscriptionStatus::ACTIVE->value)->orderByDesc('id')->first()?->plan;
                    $maxQuestions = $userPlan?->max_questions_per_exam ?? 20;
                    if ($maxQuestions == -1) {
                        $maxQuestions = 20; // Still limit to 20 for API safety
                    }
                    $currentQuestions = $this->questions()->count();
                    $remainingQuestions = min($maxQuestions, 20) - $currentQuestions;
                    return $remainingQuestions > 0;
                })
                ->schema([
                    TextInput::make('title')
                        ->label(__('messages.common.question') . ':')
                        ->placeholder(__('messages.common.question'))
                        ->validationAttribute(__('messages.common.answer'))
                        ->required(),
                    Repeater::make('answers')
                        ->label(__('messages.common.answer') . ':')
                        ->addActionLabel(__('messages.common.add_answer'))
                        ->defaultItems(2)
                        ->minItems(2)
                        ->maxItems(4)
                        ->validationAttribute(__('messages.common.answer'))
                        ->grid(2)
                        ->schema([
                            Group::make([
                                TextInput::make('title')
                                    ->placeholder(__('messages.common.answer'))
                                    ->label(__('messages.common.answer') . ':')
                                    ->required()
                                    ->columnSpan(3),
                                Toggle::make('is_correct')
                                    ->inline(false)
                                    ->label(__('messages.common.is_correct') . ':'),
                            ])->columns(4),
                        ])
                        ->required(),
                ]),

        ];
    }
}
