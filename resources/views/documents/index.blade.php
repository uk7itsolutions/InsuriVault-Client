@extends('layouts.app')

@section('content')
<h2 class="text-3xl font-semibold text-slate-900">Your Documents</h2>
<hr class="my-4 border-slate-200">

@if(empty($accountsWithFiles))
    <div class="rounded-md border-[1px] border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
        No documents found.
    </div>
@else
    @foreach($accountsWithFiles as $accountEntry)
        <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-sm">
            <div class="border-b-[1px] border-slate-200 bg-slate-100 px-4 py-3">
                <h5 class="text-base font-semibold text-slate-900">Account: {{ $accountEntry['account']['name'] }} ({{ Session::get('user_email') }})</h5>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-2 font-semibold">File Name</th>
                            <th class="hidden px-4 py-2 font-semibold md:table-cell">Category</th>
                            <th class="hidden px-4 py-2 font-semibold md:table-cell">Date</th>
                            <th class="hidden px-4 py-2 font-semibold lg:table-cell">Uploaded At</th>
                            <th class="px-4 py-2 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($accountEntry['files'] as $file)
                            <tr class="border-t-[1px] border-slate-200 transition hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="font-semibold">{{ $file['originalFileName'] }}</div>
                                    <div class="text-xs text-slate-500 md:hidden">
                                        {{ $file['fileCategory'] }}
                                        @if($file['year'] || $file['month'])
                                            • {{ $file['month'] ? date('F', mktime(0, 0, 0, $file['month'], 10)) : '' }} {{ $file['year'] }}
                                        @endif
                                    </div>
                                </td>
                                <td class="hidden px-4 py-3 md:table-cell">
                                    <span class="inline-flex rounded-full bg-slate-600 px-2 py-[0.125rem] text-xs font-medium text-white">{{ $file['fileCategory'] }}</span>
                                </td>
                                <td class="hidden px-4 py-3 md:table-cell">
                                    @if($file['year'] || $file['month'])
                                        {{ $file['month'] ? date('F', mktime(0, 0, 0, $file['month'], 10)) : '' }} {{ $file['year'] }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="hidden px-4 py-3 lg:table-cell">{{ \Carbon\Carbon::parse($file['uploadedAtUtc'])->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('documents.show', [$accountEntry['account']['id'], $file['fileId']]) }}"
                                       class="inline-flex items-center gap-1 rounded-md border-[1px] border-sky-600 px-2 py-1 text-xs font-medium text-sky-700 no-underline transition hover:bg-sky-600 hover:text-white"
                                       title="View">
                                        <x-icon.eye class="h-4 w-4"/> <span class="hidden sm:inline">View</span>
                                    </a>
                                    <a href="{{ route('documents.download', [$accountEntry['account']['id'], $file['fileId']]) }}"
                                       class="inline-flex items-center gap-1 rounded-md border-[1px] border-emerald-600 px-2 py-1 text-xs font-medium text-emerald-700 no-underline transition hover:bg-emerald-600 hover:text-white"
                                       title="Download">
                                        <x-icon.download class="h-4 w-4"/> <span class="hidden sm:inline">Download</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
@endif
@endsection
