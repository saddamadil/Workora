<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Workora')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-gradient-to-br from-brand-50 via-slate-50 to-white px-4 py-10">
    <div class="w-full @yield('width', 'max-w-md')">
        <a href="{{ url('/') }}" class="mb-6 flex items-center justify-center gap-2.5 text-xl font-bold text-slate-900">
            <span class="grid size-10 place-items-center rounded-xl bg-brand-600 text-white"><i class="bi bi-cloud-arrow-up-fill"></i></span> Workora
        </a>
        @include('partials.flash')
        @yield('content')
    </div>
</body>
</html>
