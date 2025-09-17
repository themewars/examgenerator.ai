@extends('layout.app')

@section('title', 'Refund Policy - ' . getAppName())

@section('content')
<div class="container">
    <div class="page-header">
        <h1>Refund Policy</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Refund Policy</li>
            </ol>
        </nav>
    </div>

    <div class="legal-content">
        @if($refund)
            {!! $refund !!}
        @else
            <div class="default-content">
                <h2>Refund Policy</h2>
                <p><strong>Last updated:</strong> {{ date('F d, Y') }}</p>

                <h3>1. Refund Eligibility</h3>
                <p>We offer refunds for subscription payments under the following conditions:</p>
                <ul>
                    <li>Refund requests must be made within 7 days of the original purchase</li>
                    <li>Refund requests must be made before the subscription period expires</li>
                    <li>Refund requests must be made through our official support channels</li>
                </ul>

                <h3>2. Refund Process</h3>
                <p>To request a refund:</p>
                <ol>
                    <li>Contact our support team at <a href="mailto:support@examgenerator.ai">support@examgenerator.ai</a></li>
                    <li>Provide your order number and reason for refund</li>
                    <li>We will process your refund within 5-7 business days</li>
                </ol>

                <h3>3. Refund Methods</h3>
                <p>Refunds will be processed using the same payment method used for the original purchase:</p>
                <ul>
                    <li>Credit/Debit Card: 5-7 business days</li>
                    <li>Net Banking: 3-5 business days</li>
                    <li>UPI: 1-2 business days</li>
                </ul>

                <h3>4. Non-Refundable Items</h3>
                <p>The following items are not eligible for refunds:</p>
                <ul>
                    <li>Services already used or consumed</li>
                    <li>Custom development work</li>
                    <li>Third-party integrations</li>
                </ul>

                <h3>5. Contact Information</h3>
                <p>For refund inquiries, please contact us at:</p>
                <ul>
                    <li>Email: <a href="mailto:support@examgenerator.ai">support@examgenerator.ai</a></li>
                    <li>Phone: +91-XXXXXXXXXX</li>
                    <li>Address: [Your Business Address]</li>
                </ul>

                <h3>6. Policy Changes</h3>
                <p>We reserve the right to modify this refund policy at any time. Changes will be effective immediately upon posting on our website.</p>
            </div>
        @endif
    </div>
</div>
@endsection
