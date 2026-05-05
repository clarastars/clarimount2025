<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>{{ config('app.name', 'إعتمال - منصة الموارد البشرية') }}</title>
    
    <link rel="preload" href="/assets/fonts/Tajawal-Regular.ttf" as="font" type="font/ttf" crossorigin>
    <link rel="preload" href="/assets/fonts/Tajawal-Bold.ttf" as="font" type="font/ttf" crossorigin>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased bg-white">
    @include('landing.partials.header')
    
    <main>
        @yield('content')
    </main>
    
    @include('landing.partials.footer')
    
    @stack('scripts')
</body>
</html>

