<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ __('Page Not Found') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="flex flex-col items-center justify-center h-screen bg-slate-900 text-white">
            <!-- 404 Header -->
            <h1 class="text-slate-400 mt-2 text-8xl font-bold">404</h1>
            
            <div class="relative z-10 flex flex-col items-center">
                <p class="text-slate-400 mt-2 text-lg">
                    {{ __("Sorry, the page you are looking for could not be found.") }}
                </p>
                
                <a href="{{ route('home') }}" class="mt-6 px-6 py-2 bg-blue-600 hover:bg-blue-700 transition-colors rounded-lg font-medium text-white no-underline">
                    {{ __('Go back to home page') }}
                </a>
            </div>
        </div>
    </body>
</html>
