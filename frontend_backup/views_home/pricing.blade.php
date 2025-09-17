@extends('layout.app')

@section('title', 'Pricing Plans - ' . getAppName())

@section('content')
<div class="container">
    <div class="page-header">
        <h1>Pricing Plans</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Pricing Plans</li>
            </ol>
        </nav>
    </div>

    <div class="pricing-content">
        <div class="pricing-intro">
            <h2>Choose Your Perfect Plan</h2>
            <p>Select the plan that best fits your exam generation needs. All plans include our core AI-powered features with different usage limits and additional benefits.</p>
        </div>

        <div class="pricing-grid">
            @forelse($plans as $plan)
            <div class="pricing-card {{ isset($plan->badge_text) && $plan->badge_text ? 'popular' : '' }}">
                @if(isset($plan->badge_text) && $plan->badge_text)
                    <div class="badge">{{ $plan->badge_text }}</div>
                @endif
                
                <div class="plan-header">
                    <h3>{{ $plan->name ?? 'Plan' }}</h3>
                    <div class="price">
                        @if(isset($plan->price) && $plan->price == 0)
                            <span class="currency">Free</span>
                        @else
                            <span class="currency">₹</span>
                            <span class="amount">{{ number_format($plan->price ?? 0) }}</span>
                            <span class="period">/{{ $plan->frequency ?? 'month' }}</span>
                        @endif
                    </div>
                    <p class="description">{{ $plan->description ?? 'No description available' }}</p>
                </div>

                <div class="plan-features">
                    <div class="feature-item">
                        <span class="feature-label">Exams per Month:</span>
                        <span class="feature-value">
                            @if(isset($plan->exams_per_month) && $plan->exams_per_month == -1)
                                Unlimited
                            @elseif(isset($plan->exams_per_month))
                                {{ $plan->exams_per_month }}
                            @else
                                {{ $plan->exams_per_month ?? 'N/A' }}
                            @endif
                        </span>
                    </div>
                    
                    <div class="feature-item">
                        <span class="feature-label">Questions per Exam:</span>
                        <span class="feature-value">
                            @if(isset($plan->max_questions_per_exam) && $plan->max_questions_per_exam == -1)
                                Unlimited
                            @elseif(isset($plan->max_questions_per_exam))
                                {{ $plan->max_questions_per_exam }}
                            @else
                                {{ $plan->max_questions_per_exam ?? 'N/A' }}
                            @endif
                        </span>
                    </div>

                    @if(isset($plan->pdf_export_enabled) && $plan->pdf_export_enabled)
                        <div class="feature-item">
                            <i class="check-icon">✓</i>
                            <span>PDF Export</span>
                        </div>
                    @endif

                    @if(isset($plan->word_export_enabled) && $plan->word_export_enabled)
                        <div class="feature-item">
                            <i class="check-icon">✓</i>
                            <span>Word Export</span>
                        </div>
                    @endif

                    @if(isset($plan->website_quiz_enabled) && $plan->website_quiz_enabled)
                        <div class="feature-item">
                            <i class="check-icon">✓</i>
                            <span>Website → Exam</span>
                        </div>
                    @endif

                    @if(isset($plan->pdf_to_exam_enabled) && $plan->pdf_to_exam_enabled)
                        <div class="feature-item">
                            <i class="check-icon">✓</i>
                            <span>PDF → Exam</span>
                        </div>
                    @endif

                    @if(isset($plan->answer_key_enabled) && $plan->answer_key_enabled)
                        <div class="feature-item">
                            <i class="check-icon">✓</i>
                            <span>Answer Key Generation</span>
                        </div>
                    @endif

                    @if(isset($plan->priority_support_enabled) && $plan->priority_support_enabled)
                        <div class="feature-item">
                            <i class="check-icon">✓</i>
                            <span>Priority Support</span>
                        </div>
                    @endif

                    @if(isset($plan->white_label_enabled) && $plan->white_label_enabled)
                        <div class="feature-item">
                            <i class="check-icon">✓</i>
                            <span>White Label</span>
                        </div>
                    @endif
                </div>

                <div class="plan-action">
                    @if(isset($plan->price) && $plan->price == 0)
                        <a href="{{ route('filament.auth.register') }}" class="btn btn-outline">Get Started Free</a>
                    @else
                        <a href="{{ route('filament.user.pages.choose-payment-type', ['plan' => $plan->id ?? 1]) }}" class="btn btn-primary">Choose Plan</a>
                    @endif
                </div>
            </div>
            @empty
            <div class="no-plans">
                <h3>No Plans Available</h3>
                <p>Pricing plans are currently being updated. Please check back later or contact us for more information.</p>
                <a href="{{ route('contact') }}" class="btn btn-primary">Contact Us</a>
            </div>
            @endforelse
        </div>

        <div class="pricing-faq">
            <h3>Frequently Asked Questions</h3>
            <div class="faq-grid">
                <div class="faq-item">
                    <h4>Can I change my plan later?</h4>
                    <p>Yes, you can upgrade or downgrade your plan at any time. Changes will be reflected in your next billing cycle.</p>
                </div>
                
                <div class="faq-item">
                    <h4>What payment methods do you accept?</h4>
                    <p>We accept all major credit cards, debit cards, UPI, net banking, and digital wallets through Razorpay.</p>
                </div>
                
                <div class="faq-item">
                    <h4>Is there a free trial?</h4>
                    <p>Yes, our Free plan allows you to create up to 3 exams per month with basic features to test our platform.</p>
                </div>
                
                <div class="faq-item">
                    <h4>Do you offer refunds?</h4>
                    <p>Yes, we offer a 7-day money-back guarantee. Please check our <a href="{{ route('refund') }}">Refund Policy</a> for details.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.pricing-content {
    padding: 2rem 0;
}

.pricing-intro {
    text-align: center;
    margin-bottom: 3rem;
}

.pricing-intro h2 {
    color: #333;
    margin-bottom: 1rem;
}

.pricing-intro p {
    color: #666;
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto;
}

.pricing-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin-bottom: 4rem;
}

.pricing-card {
    background: #fff;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    position: relative;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.pricing-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
}

.pricing-card.popular {
    border: 2px solid #007bff;
    transform: scale(1.05);
}

.pricing-card.popular:hover {
    transform: scale(1.05) translateY(-5px);
}

.badge {
    position: absolute;
    top: -10px;
    right: 20px;
    background: #007bff;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.plan-header {
    text-align: center;
    margin-bottom: 2rem;
}

.plan-header h3 {
    color: #333;
    margin-bottom: 1rem;
    font-size: 1.5rem;
}

.price {
    margin-bottom: 1rem;
}

.currency {
    font-size: 1.2rem;
    color: #666;
}

.amount {
    font-size: 3rem;
    font-weight: bold;
    color: #007bff;
}

.period {
    font-size: 1rem;
    color: #666;
}

.description {
    color: #666;
    font-size: 0.95rem;
}

.plan-features {
    margin-bottom: 2rem;
}

.feature-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.feature-item:last-child {
    border-bottom: none;
}

.feature-label {
    font-weight: 600;
    color: #333;
    flex: 1;
}

.feature-value {
    color: #007bff;
    font-weight: 600;
}

.check-icon {
    color: #28a745;
    font-weight: bold;
    margin-right: 0.5rem;
    width: 20px;
}

.plan-action {
    text-align: center;
}

.btn {
    display: inline-block;
    padding: 0.75rem 2rem;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.btn-primary {
    background-color: #007bff;
    color: white;
    border-color: #007bff;
}

.btn-primary:hover {
    background-color: #0056b3;
    border-color: #0056b3;
    color: white;
}

.btn-outline {
    background-color: transparent;
    color: #007bff;
    border-color: #007bff;
}

.btn-outline:hover {
    background-color: #007bff;
    color: white;
}

.pricing-faq {
    background: #f8f9fa;
    padding: 3rem;
    border-radius: 12px;
}

.pricing-faq h3 {
    text-align: center;
    color: #333;
    margin-bottom: 2rem;
}

.faq-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
}

.faq-item h4 {
    color: #333;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}

.faq-item p {
    color: #666;
    line-height: 1.6;
    margin-bottom: 0;
}

.faq-item a {
    color: #007bff;
    text-decoration: none;
}

.faq-item a:hover {
    text-decoration: underline;
}

.no-plans {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3rem;
    background: #f8f9fa;
    border-radius: 12px;
    margin: 2rem 0;
}

.no-plans h3 {
    color: #333;
    margin-bottom: 1rem;
}

.no-plans p {
    color: #666;
    margin-bottom: 2rem;
}
</style>
@endsection
