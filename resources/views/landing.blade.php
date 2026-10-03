@extends('layouts.guest')
@section('title', 'Workora · Upload, share and sync your work')
@section('width', 'max-w-2xl')
@section('content')
<div class="text-center">
    <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">Upload. Share. Done.</h1>
    <p class="mx-auto mt-3 max-w-lg text-slate-600">Keep documents and images in one place, send them with a link, and pull in files from Google Drive.</p>
    <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
        <a href="{{ route('register') }}" class="btn-primary px-6 py-3">Get started free</a>
        <a href="{{ route('login') }}" class="btn-secondary px-6 py-3">Sign in</a>
    </div>
    <div class="mt-10 grid gap-3 text-left sm:grid-cols-3">
        @foreach ([['bi-cloud-arrow-up', 'Drag and drop', 'Upload many files at once and watch the progress.'], ['bi-link-45deg', 'Private links', 'Add a password, an expiry date or a download limit.'], ['bi-google', 'Google Drive', 'Import from Drive and save files back with one click.']] as [$icon, $title, $text])
            <div class="card p-4">
                <i class="bi {{ $icon }} text-2xl text-brand-600"></i>
                <div class="mt-2 font-semibold text-slate-900">{{ $title }}</div>
                <p class="mt-1 text-sm text-slate-500">{{ $text }}</p>
            </div>
        @endforeach
    </div>
</div>
@endsection
