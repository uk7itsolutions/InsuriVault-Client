@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12">
        <h2>Your Documents</h2>
        <hr>

        @if(empty($accountsWithFiles))
            <div class="alert alert-info">
                No documents found.
            </div>
        @else
            @foreach($accountsWithFiles as $accountEntry)
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Account: {{ $accountEntry['account']['name'] }} ({{ $accountEntry['account']['email'] }})</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>File Name</th>
                                    <th>Category</th>
                                    <th>Date</th>
                                    <th>Uploaded At</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($accountEntry['files'] as $file)
                                    <tr>
                                        <td>{{ $file['originalFileName'] }}</td>
                                        <td><span class="badge bg-secondary">{{ $file['fileCategory'] }}</span></td>
                                        <td>
                                            @if($file['year'] || $file['month'])
                                                {{ $file['month'] ? date('F', mktime(0, 0, 0, $file['month'], 10)) : '' }} {{ $file['year'] }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($file['uploadedAtUtc'])->format('Y-m-d H:i') }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('documents.show', [$accountEntry['account']['id'], $file['fileId']]) }}" class="btn btn-sm btn-outline-primary">View</a>
                                            <a href="{{ route('documents.download', [$accountEntry['account']['id'], $file['fileId']]) }}" class="btn btn-sm btn-outline-success">Download</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection
