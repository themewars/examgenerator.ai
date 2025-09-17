@extends('layout.app')

@section('title')
    {{ $page->title }}
@endsection

@section('content')
    <main class="page-content">
        <section class="features">
            <div class="container">
                <div class="section-header">
                    <div class="badge badge-primary-light">Legal</div>
                    <h2>{{ $page->title }}</h2>
                </div>
                <article class="prose" style="color:#1f2937">
                    {!! $page->content !!}
                </article>
            </div>
        </section>
    </main>
@endsection


