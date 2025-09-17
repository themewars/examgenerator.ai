@extends('layout.app')

@section('title', 'Contact Us - ' . getAppName())

@section('content')
<div class="container">
    <div class="page-header">
        <h1>Contact Us</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Contact Us</li>
            </ol>
        </nav>
    </div>

    <div class="contact-content">
        <div class="row">
            <div class="col-md-8">
                <div class="contact-form">
                    <h2>Get in Touch</h2>
                    <p>Have questions about our exam generation platform? We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
                    
                    @if(session('success'))
                        <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 4px; margin-bottom: 1rem; border: 1px solid #c3e6cb;">
                            {{ session('success') }}
                        </div>
                    @endif
                    
                    @if($errors->any())
                        <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 4px; margin-bottom: 1rem; border: 1px solid #f5c6cb;">
                            <ul style="margin: 0; padding-left: 1.5rem;">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    
                    <form action="{{ route('contact.submit') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject *</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="contact-info">
                    <h3>Contact Information</h3>
                    
                    <div class="contact-item">
                        <h4>Email</h4>
                        <p><a href="mailto:support@examgenerator.ai">support@examgenerator.ai</a></p>
                        <p><a href="mailto:info@examgenerator.ai">info@examgenerator.ai</a></p>
                    </div>
                    
                    <div class="contact-item">
                        <h4>Phone</h4>
                        <p>+91-XXXXXXXXXX</p>
                        <p>Mon-Fri: 9:00 AM - 6:00 PM IST</p>
                    </div>
                    
                    <div class="contact-item">
                        <h4>Address</h4>
                        <p>
                            [Your Business Address]<br>
                            City, State - PIN Code<br>
                            India
                        </p>
                    </div>
                    
                    <div class="contact-item">
                        <h4>Business Hours</h4>
                        <p>Monday - Friday: 9:00 AM - 6:00 PM</p>
                        <p>Saturday: 10:00 AM - 4:00 PM</p>
                        <p>Sunday: Closed</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.contact-content {
    padding: 2rem 0;
}

.contact-form {
    background: #fff;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.contact-info {
    background: #f8f9fa;
    padding: 2rem;
    border-radius: 8px;
    height: fit-content;
}

.contact-item {
    margin-bottom: 2rem;
}

.contact-item h4 {
    color: #333;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}

.contact-item p {
    margin-bottom: 0.5rem;
    color: #666;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    font-weight: 600;
    color: #333;
    margin-bottom: 0.5rem;
    display: block;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

.form-control:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
}

.btn-primary {
    background-color: #007bff;
    border-color: #007bff;
    padding: 0.75rem 2rem;
    font-size: 1rem;
    border-radius: 4px;
    color: white;
    border: none;
    cursor: pointer;
}

.btn-primary:hover {
    background-color: #0056b3;
    border-color: #0056b3;
}
</style>
@endsection
