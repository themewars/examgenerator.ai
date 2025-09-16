<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ getActiveLanguage()['code'] == 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" href="{{ getFaviconUrl() }}" type="image/png">

    <title>@yield('title', getAppName())</title>
    <meta name="description" content="@yield('meta_description', getSetting()->meta_description ?? getAppName())">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    @yield('seo')

    <link rel="preconnect" href="//fonts.bunny.net">
    <link href="//fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;700&display=swap" rel="stylesheet">
    <!-- Bootstrap via CDN to avoid missing local files -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script async src="https://www.google.com/recaptcha/api.js"></script>
    @stack('head')

</head>

<body class="font-['outfit'] text-black antialiased bg-cover bg-no-repeat bg-center min-h-screen"
    style="background-image: url('{{ asset('images/bg-img.png') }}');">

    <div class="absolute top-5 end-10">
        <!-- Button to open the dropdown modal -->
        <button id="languageButton"
            class="d-flex items-center bg-primary hover:bg-gray-400 text-white font-bold py-2 ps-3 pe-4 rounded-lg">
            @foreach (getAllLanguageFlags() as $imageKey => $imageValue)
                @if ($imageKey == getActiveLanguage()['code'])
                    <img src="{{ asset($imageValue) }}" width="20" class="me-2" height="20">
                    {{ getActiveLanguage()['name'] }}
                @endif
            @endforeach
        </button>

        <div id="languageModal"
            class="hidden z-10 absolute right-0 mt-2 p-1 min-w-[120px] bg-white border rounded-lg shadow-lg">
            <ul class="flex flex-col gap-1">
                @foreach (getAllLanguages() as $code => $language)
                    @foreach (getAllLanguageFlags() as $imageKey => $imageValue)
                        @if ($imageKey == $code)
                            <li>
                                <button
                                    class="w-full text-left rounded-md ps-3 pe-4 py-2 flex items-center {{ $code == getActiveLanguage()['code'] ? 'bg-primary text-white' : 'hover:bg-gray-300' }}"
                                    data-lang="{{ $code }}">
                                    <img src="{{ asset($imageValue) }}" width="20" class="me-2" height="20">
                                    {{ $language }}</button>
                            </li>
                        @endif
                    @endforeach
                @endforeach
            </ul>
        </div>
    </div>

    @yield('content')

    <script>
        // Apply Hindi font to elements with Hindi content
        document.addEventListener('DOMContentLoaded', function() {
            const hindiElements = document.querySelectorAll('[data-language="hi"], .hindi-content');
            hindiElements.forEach(function(element) {
                element.classList.add('hindi-text');
            });
        });
    </script>

</body>

</html>
