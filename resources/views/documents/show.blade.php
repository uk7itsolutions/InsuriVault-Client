@extends('layouts.app')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="mb-4 flex flex-wrap items-center gap-2 text-sm text-[var(--portal-text-subtle)]">
        <li><a class="text-[var(--portal-accent-on-surface)] no-underline transition hover:underline" href="{{ route('documents.index') }}">Documents</a></li>
        <li aria-hidden="true">/</li>
        <li class="text-[var(--portal-text)]" aria-current="page">{{ $fileInfo['originalFileName'] }}</li>
    </ol>
</nav>

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <h2 class="text-3xl font-semibold text-[var(--portal-text)]">{{ $fileInfo['originalFileName'] }}</h2>
    <a href="{{ route('documents.download', [$accountId, $fileId]) }}"
       class="inline-flex items-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white no-underline transition hover:bg-emerald-700">
        <x-icon.download class="h-4 w-4"/>Download File
    </a>
</div>

<div class="overflow-hidden rounded-lg bg-[var(--portal-surface)] shadow-lg">
    @if(str_contains($fileInfo['contentType'], 'pdf'))
        <iframe src="data:{{ $fileInfo['contentType'] }};base64,{{ $base64Content }}" class="block h-[800px] w-full border-0" title="{{ $fileInfo['originalFileName'] }}"></iframe>
    @elseif(str_contains($fileInfo['contentType'], 'image'))
        <div class="p-4 text-center">
            <img src="data:{{ $fileInfo['contentType'] }};base64,{{ $base64Content }}"
                 class="mx-auto h-auto max-w-full border-[1px] border-[var(--portal-border)]" alt="{{ $fileInfo['originalFileName'] }}">
        </div>
    @else
        <div class="p-12 text-center">
            <p class="mb-4 text-sm text-[var(--portal-text-soft)]">This file type ({{ $fileInfo['contentType'] }}) cannot be previewed directly.</p>
            <a href="{{ route('documents.download', [$accountId, $fileId]) }}"
               class="inline-flex items-center gap-2 rounded-md bg-[var(--portal-accent)] px-4 py-2 text-sm font-medium text-[var(--portal-accent-contrast)] no-underline transition hover:bg-[var(--portal-accent-hover)]">
                <x-icon.download class="h-4 w-4"/>Download to View
            </a>
        </div>
    @endif
</div>
@endsection
