@extends('layout.app')

@section('title', 'About Us - ' . getAppName())

@section('content')
<div class="container">
    <div class="page-header">
        <h1>About Us</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">About Us</li>
            </ol>
        </nav>
    </div>

    <div class="about-content">
        <div class="row">
            <div class="col-md-8">
                <div class="about-text">
                    <h2>About ExamGenerator AI</h2>
                    <p class="lead">We are revolutionizing the way educational content is created and delivered through our AI-powered exam generation platform.</p>
                    
                    <h3>Our Mission</h3>
                    <p>To empower educators, trainers, and organizations with cutting-edge AI technology that simplifies exam creation, enhances learning outcomes, and saves valuable time in the educational process.</p>
                    
                    <h3>What We Do</h3>
                    <p>ExamGenerator AI is a comprehensive platform that uses artificial intelligence to create high-quality exams and quizzes from various content sources including:</p>
                    <ul>
                        <li><strong>Text Content:</strong> Convert any text into structured exam questions</li>
                        <li><strong>PDF Documents:</strong> Extract content from PDFs and generate exams</li>
                        <li><strong>Website URLs:</strong> Create exams from web content automatically</li>
                        <li><strong>Images:</strong> Use OCR technology to convert images to exam content</li>
                        <li><strong>PowerPoint Presentations:</strong> Transform PPT slides into comprehensive exams</li>
                    </ul>
                    
                    <h3>Key Features</h3>
                    <div class="features-grid">
                        <div class="feature-item">
                            <h4>AI-Powered Generation</h4>
                            <p>Advanced AI algorithms create diverse question types including multiple choice, true/false, and essay questions.</p>
                        </div>
                        
                        <div class="feature-item">
                            <h4>Multiple Export Formats</h4>
                            <p>Export exams in PDF, Word, and HTML formats with customizable templates.</p>
                        </div>
                        
                        <div class="feature-item">
                            <h4>Answer Key Generation</h4>
                            <p>Automatically generate comprehensive answer keys for all questions.</p>
                        </div>
                        
                        <div class="feature-item">
                            <h4>Customizable Templates</h4>
                            <p>Choose from various exam templates including standard, compact, and detailed formats.</p>
                        </div>
                    </div>
                    
                    <h3>Our Technology</h3>
                    <p>We leverage state-of-the-art AI models including OpenAI's GPT and Google's Gemini to ensure the highest quality exam generation. Our platform is built with modern web technologies to provide a seamless user experience.</p>
                    
                    <h3>Who We Serve</h3>
                    <ul>
                        <li><strong>Educational Institutions:</strong> Schools, colleges, and universities</li>
                        <li><strong>Corporate Training:</strong> Employee training and assessment programs</li>
                        <li><strong>Online Educators:</strong> Course creators and online instructors</li>
                        <li><strong>Certification Bodies:</strong> Professional certification organizations</li>
                        <li><strong>Individual Educators:</strong> Teachers and trainers worldwide</li>
                    </ul>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="about-sidebar">
                    <div class="stats-card">
                        <h3>Platform Statistics</h3>
                        <div class="stat-item">
                            <span class="stat-number">10,000+</span>
                            <span class="stat-label">Exams Generated</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">5,000+</span>
                            <span class="stat-label">Active Users</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">50+</span>
                            <span class="stat-label">Countries Served</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">99.9%</span>
                            <span class="stat-label">Uptime</span>
                        </div>
                    </div>
                    
                    <div class="contact-card">
                        <h3>Get Started Today</h3>
                        <p>Ready to revolutionize your exam creation process?</p>
                        <a href="{{ route('pricing') }}" class="btn btn-primary">View Pricing Plans</a>
                        <a href="{{ route('contact') }}" class="btn btn-outline">Contact Us</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.about-content {
    padding: 2rem 0;
}

.about-text {
    background: #fff;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.about-text h2 {
    color: #333;
    margin-bottom: 1rem;
}

.about-text h3 {
    color: #333;
    margin-top: 2rem;
    margin-bottom: 1rem;
    font-size: 1.3rem;
}

.about-text p {
    color: #666;
    line-height: 1.6;
    margin-bottom: 1rem;
}

.about-text .lead {
    font-size: 1.1rem;
    font-weight: 500;
    color: #333;
}

.about-text ul {
    margin-bottom: 1.5rem;
}

.about-text li {
    margin-bottom: 0.5rem;
    color: #666;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin: 2rem 0;
}

.feature-item {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    border-left: 4px solid #007bff;
}

.feature-item h4 {
    color: #333;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}

.feature-item p {
    color: #666;
    margin-bottom: 0;
    font-size: 0.95rem;
}

.about-sidebar {
    position: sticky;
    top: 2rem;
}

.stats-card, .contact-card {
    background: #fff;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.stats-card h3, .contact-card h3 {
    color: #333;
    margin-bottom: 1.5rem;
    text-align: center;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 1px solid #eee;
}

.stat-item:last-child {
    border-bottom: none;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: #007bff;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

.contact-card p {
    color: #666;
    text-align: center;
    margin-bottom: 1.5rem;
}

.btn {
    display: inline-block;
    padding: 0.75rem 1.5rem;
    margin: 0.25rem;
    text-decoration: none;
    border-radius: 4px;
    text-align: center;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary {
    background-color: #007bff;
    color: white;
    border: 2px solid #007bff;
}

.btn-primary:hover {
    background-color: #0056b3;
    border-color: #0056b3;
    color: white;
}

.btn-outline {
    background-color: transparent;
    color: #007bff;
    border: 2px solid #007bff;
}

.btn-outline:hover {
    background-color: #007bff;
    color: white;
}
</style>
@endsection
