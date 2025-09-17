@extends('layout.app')

@section('title', 'Verify Email - ' . getAppName())

@section('content')
<div class="container">
    <div class="verify-email-container">
        <div class="verify-email-card">
            <div class="verify-email-header">
                <div class="verify-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <polyline points="22,6 12,13 2,6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h1>Verify Your Email Address</h1>
                <p>Before proceeding, please check your email for a verification link.</p>
            </div>

            <div class="verify-email-content">
                @if (session('message'))
                    <div class="alert alert-success">
                        {{ session('message') }}
                    </div>
                @endif

                <p>We've sent a verification link to <strong>{{ auth()->user()->email }}</strong></p>
                
                <div class="verification-steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h3>Check Your Email</h3>
                            <p>Look for an email from {{ getAppName() }} in your inbox</p>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h3>Click the Link</h3>
                            <p>Click the verification link in the email</p>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h3>Start Using</h3>
                            <p>Your account will be verified and ready to use</p>
                        </div>
                    </div>
                </div>

                <div class="verification-actions">
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            Resend Verification Email
                        </button>
                    </form>
                    
                    <div class="help-links">
                        <p>Didn't receive the email?</p>
                        <ul>
                            <li>Check your spam/junk folder</li>
                            <li>Make sure you entered the correct email address</li>
                            <li>Wait a few minutes and try again</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.verify-email-container {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 0;
}

.verify-email-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    padding: 3rem;
    max-width: 600px;
    width: 100%;
    text-align: center;
}

.verify-email-header {
    margin-bottom: 2rem;
}

.verify-icon {
    color: #007bff;
    margin-bottom: 1rem;
}

.verify-email-header h1 {
    color: #333;
    margin-bottom: 1rem;
    font-size: 2rem;
}

.verify-email-header p {
    color: #666;
    font-size: 1.1rem;
}

.verify-email-content {
    text-align: left;
}

.alert {
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.verification-steps {
    margin: 2rem 0;
}

.step {
    display: flex;
    align-items: flex-start;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.step-number {
    background: #007bff;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-right: 1rem;
    flex-shrink: 0;
}

.step-content h3 {
    color: #333;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}

.step-content p {
    color: #666;
    margin: 0;
}

.verification-actions {
    text-align: center;
    margin-top: 2rem;
}

.btn {
    display: inline-block;
    padding: 0.75rem 2rem;
    background-color: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.btn:hover {
    background-color: #0056b3;
}

.help-links {
    margin-top: 2rem;
    text-align: left;
}

.help-links p {
    color: #333;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.help-links ul {
    color: #666;
    margin: 0;
    padding-left: 1.5rem;
}

.help-links li {
    margin-bottom: 0.25rem;
}

@media (max-width: 768px) {
    .verify-email-card {
        padding: 2rem;
        margin: 1rem;
    }
    
    .verify-email-header h1 {
        font-size: 1.5rem;
    }
}
</style>
@endsection
