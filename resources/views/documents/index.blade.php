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
                        <h5 class="mb-0">Account: {{ $accountEntry['account']['name'] }} ({{ Session::get('user_email') }})</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>File Name</th>
                                        <th class="d-none d-md-table-cell">Category</th>
                                        <th class="d-none d-md-table-cell">Date</th>
                                        <th class="d-none d-lg-table-cell">Uploaded At</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($accountEntry['files'] as $file)
                                        <tr>
                                            <td>
                                                <div class="fw-bold">{{ $file['originalFileName'] }}</div>
                                                <div class="d-md-none small text-muted">
                                                    {{ $file['fileCategory'] }}
                                                    @if($file['year'] || $file['month'])
                                                        • {{ $file['month'] ? date('F', mktime(0, 0, 0, $file['month'], 10)) : '' }} {{ $file['year'] }}
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell"><span class="badge bg-secondary">{{ $file['fileCategory'] }}</span></td>
                                            <td class="d-none d-md-table-cell">
                                                @if($file['year'] || $file['month'])
                                                    {{ $file['month'] ? date('F', mktime(0, 0, 0, $file['month'], 10)) : '' }} {{ $file['year'] }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="d-none d-lg-table-cell">{{ \Carbon\Carbon::parse($file['uploadedAtUtc'])->format('Y-m-d H:i') }}</td>
                                            <td class="text-end text-nowrap">
                                                <a href="{{ route('documents.show', [$accountEntry['account']['id'], $file['fileId']]) }}" class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="bi bi-eye"></i> <span class="d-none d-sm-inline">View</span>
                                                </a>
                                                <a href="{{ route('documents.download', [$accountEntry['account']['id'], $file['fileId']]) }}" class="btn btn-sm btn-outline-success" title="Download">
                                                    <i class="bi bi-download"></i> <span class="d-none d-sm-inline">Download</span>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection
