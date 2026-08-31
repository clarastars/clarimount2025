<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="إعتمال - منصة موارد بشرية متكاملة للشركات. إدارة الموظفين، الحضور، الرواتب، والتقارير.">

    <title>{{ config('app.name', 'إعتمال - منصة الموارد البشرية') }}</title>

    @vite(['resources/css/app.css', 'resources/css/landing.css'])
</head>
<body class="landing-page font-sans antialiased bg-white text-slate-900">
    @include('landing.partials.header')

    <main>
        @yield('content')
    </main>

    @include('landing.partials.footer')

    @stack('scripts')
</body>
</html>
