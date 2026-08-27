@extends('layouts.admin')
@section('title', 'OCR Logs')
@section('content')
<h1>OCR Logs</h1>
@forelse($logs as $log)
    <div class="card" style="margin-bottom:.5rem;">
        <div class="card-body" style="padding:.75rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <strong>{{ $log->filename ?? '(unnamed)' }}</strong>
                    <span style="margin-left:8px;padding:2px 6px;border-radius:4px;font-size:.75rem;
                        background:{{ $log->status === 'success' ? '#d4edda' : ($log->status === 'failed' ? '#f8d7da' : '#fff3cd') }};
                        color:{{ $log->status === 'success' ? '#155724' : ($log->status === 'failed' ? '#721c24' : '#856404') }};">
                        {{ $log->status }}
                    </span>
                    @if($log->confidence !== null)
                        <span style="margin-left:8px;font-size:.85rem;color:#555;">{{ $log->confidence }}% confidence</span>
                    @endif
                    @if($log->error_message)
                        <p style="margin:.25rem 0 0;color:#dc3545;font-size:.85rem;">{{ $log->error_message }}</p>
                    @endif
                </div>
                <div style="text-align:right;font-size:.8rem;color:#999;flex-shrink:0;margin-left:1rem;">
                    {{ $log->user?->full_name ?? 'Unknown' }}<br>
                    {{ $log->created_at->format('M j, Y H:i') }}
                </div>
            </div>
        </div>
    </div>
@empty
    <p>No OCR logs found.</p>
@endforelse
{{ $logs->links() }}
@endsection
