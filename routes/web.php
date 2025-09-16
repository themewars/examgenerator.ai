<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaypalController;
use App\Http\Controllers\PollResultController;
use App\Http\Controllers\RazorpayController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SocialiteController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserQuizController;
use App\Http\Controllers\ExamShowcaseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/


Route::middleware('SetLanguage')->group(function () {
    Route::get('/auth/{provider}/redirect', [SocialiteController::class, 'redirect'])->name('socialite.redirect');
    Route::get('/auth/{provider}/callback', [SocialiteController::class, 'callback'])->name('socialite.callback');

    Route::get('change-language/{code}', [UserQuizController::class, 'changeLanguage'])->name('change.language');

    // Exam Showcase Routes
    Route::get('/exams', [ExamShowcaseController::class, 'index'])->name('exam.showcase');
    Route::get('/exam/{id}/preview', [ExamShowcaseController::class, 'preview'])->name('exam.preview');
    Route::get('/api/exams', [ExamShowcaseController::class, 'getExams'])->name('api.exams');

    // Route of Quiz player (SEO + legacy)
    Route::get('{slug}/{code}', [UserQuizController::class, 'createSeo'])
        ->where([
            'slug' => '^(?!auth$|exams$|exam$|api$|razorpay$|paypal$|q$|og$).+',
            'code' => '[A-Z0-9]{6,12}'
        ])
        ->name('quiz-player-seo');

    // Legacy routes redirect to SEO
    Route::get('q/{code}', function ($code) {
        $quiz = \App\Models\Quiz::where('unique_code', strtoupper($code))->first();
        if (!$quiz) { abort(404); }
        $slug = \Illuminate\Support\Str::slug($quiz->title ?: 'exam');
        return redirect()->route('quiz-player-seo', ['slug' => $slug, 'code' => strtoupper($code)], 301);
    })->name('quiz-player');
    Route::get('q/{code}/player', [UserQuizController::class, 'createPlayer'])->name('create.quiz-player');

    // Pretty OG image proxy: /og/{slug}/{code}.png
    Route::get('og/{slug}/{code}.png', function ($slug, $code) {
        $path = public_path('images/og/' . strtoupper($code) . '.png');
        if (!file_exists($path)) { $path = public_path('images/og/default.png'); }
        return response()->file($path, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    })->name('quiz.og');
    Route::post('q/quiz-player', [UserQuizController::class, 'store'])->name('store.quiz-player');
    Route::get('q/quiz/question', [UserQuizController::class, 'quizQuestion'])->name('quiz.question');
    Route::post('q/quiz/answer', [UserQuizController::class, 'quizAnswer'])->name('quiz.answer');
    Route::get('q/quiz/finished/{uuid}', [UserQuizController::class, 'quizResult'])->name('quiz.result');
    Route::get('q/result/{uuid}', [UserQuizController::class, 'show'])->name('show.quizResult');

    // Route of Subscrion RazorPay Payment
    Route::post('/razorpay/purchase', [RazorpayController::class, 'purchase'])->name('razorpay.purchase');
    Route::post('/razorpay/success', [RazorpayController::class, 'success'])->name('razorpay.success');
    Route::get('/razorpay/failed', [RazorpayController::class, 'failed'])->name('razorpay.failed');

    // Route of Subscrion Paypal Payment
    Route::post('paypal-purchase', [PaypalController::class, 'purchase'])->name('paypal.purchase');
    Route::get('paypal-success', [PaypalController::class, 'success'])->name('paypal.success');
    Route::get('paypal-failed', [PaypalController::class, 'failed'])->name('paypal.failed');

    // Route of Subscrion Stripe Payment
    Route::post('stripe/purchase', [StripeController::class, 'purchase'])->name('stripe.purchase');
    Route::get('stripe-success', [StripeController::class, 'success'])->name('stripe.success');
    Route::get('stripe-failed', [StripeController::class, 'failed'])->name('stripe.failed');

    // Route of Download subscription Invoice
    Route::get('invoice/{subscription}/pdf', [SubscriptionController::class, 'subscriptionInvoice'])->name('subscription.invoice');

    // Route for the landing home page
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/terms', [HomeController::class, 'terms'])->name('terms');
    Route::get('/privacy', [HomeController::class, 'policy'])->name('policy');
    Route::get('/cookie', [HomeController::class, 'cookie'])->name('cookie');
    Route::get('/refund', [HomeController::class, 'refund'])->name('refund');
    Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
    Route::post('/contact', [HomeController::class, 'contactSubmit'])->name('contact.submit');
    Route::get('/about', [HomeController::class, 'about'])->name('about');
    Route::get('/pricing', [HomeController::class, 'pricing'])->name('pricing');

    // Dynamic Legal Pages
    Route::get('/legal/{slug}', [LegalPageController::class, 'show'])->name('legal.show');

    // Route of Poll votes
    Route::get('p/{code}', [PollResultController::class, 'create'])->name('poll.create');
    Route::post('p/vote-poll', [PollResultController::class, 'store'])->name('store.poll_result');
    
    // Sitemap route
    Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
    
    // Reset usage counter route (for fixing old user data)
    Route::post('/reset-usage-counter', function() {
        if (auth()->check()) {
            app(\App\Services\PlanValidationService::class)->forceResetUsageCounters();
            return response()->json(['success' => true, 'message' => 'Usage counter reset successfully']);
        }
        return response()->json(['success' => false, 'message' => 'Not authenticated']);
    })->name('reset.usage.counter');
    
});

include 'auth.php';
include 'upgrade.php';
