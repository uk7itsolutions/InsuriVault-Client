@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('documents.index') }}">Documents</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $fileInfo['originalFileName'] }}</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>{{ $fileInfo['originalFileName'] }}</h2>
            <a href="{{ route('documents.download', [$accountId, $fileId]) }}" class="btn btn-success">Download File</a>
        </div>

        <div class="card shadow">
            <div class="card-body p-0">
                @if(str_contains($fileInfo['contentType'], 'pdf'))
                    <iframe src="data:{{ $fileInfo['contentType'] }};base64,{{ $base64Content }}" width="100%" height="800px" style="border: none;"></iframe>
                @elseif(str_contains($fileInfo['contentType'], 'image'))
                    <div class="text-center p-4">
                        <img src="data:{{ $fileInfo['contentType'] }};base64,{{ $base64Content }}" class="img-fluid border" alt="{{ $fileInfo['originalFileName'] }}">
                    </div>
                @else
                    <div class="p-5 text-center">
                        <p>This file type ({{ $fileInfo['contentType'] }}) cannot be previewed directly.</p>
                        <a href="{{ route('documents.download', [$accountId, $fileId]) }}" class="btn btn-primary">Download to View</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
