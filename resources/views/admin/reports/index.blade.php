@extends('layouts.admin')
@section('title', 'System Reports')
@section('content')
<div class="admin-page-head">
    <div>
        <h1>System Reports</h1>
        <p>Key totals and recent activity across the Budgetra platform.</p>
    </div>
</div>

{{-- 1. Key Performance Cards --}}
<div class="admin-stat-row">
    <div class="admin-stat-card">
        <span class="admin-stat-label">Total Travel Volume</span>
        <strong class="admin-stat-value">{{ number_format($stats['total_trips']) }} Total Trips</strong>
    </div>
    <div class="admin-stat-card">
        <span class="admin-stat-label">Gross Platform Expenditures</span>
        <strong class="admin-stat-value">₱{{ number_format($stats['gross_expenditures'], 2) }}</strong>
    </div>
    <div class="admin-stat-card">
        <span class="admin-stat-label">Verified Interactions</span>
        <strong class="admin-stat-value">{{ $stats['total_reviews'] }} Total Reviews &middot; {{ $stats['ocr_success'] }} Successful OCR Scans</strong>
    </div>
</div>

{{-- 2. Top Destinations --}}
<div class="admin-panel" style="margin-bottom:18px;">
    <div class="admin-panel-head"><h3>Top Destinations</h3></div>
    @forelse($topDestinations as $i => $row)
    <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 0;{{ !$loop->first ? 'border-top:1px solid var(--admin-border);' : '' }}">
        <span style="font-size:13px;color:var(--admin-ink);">{{ $i + 1 }}. {{ $row->destination }}</span>
        <span style="font-size:13px;font-weight:700;color:var(--admin-ink);">{{ $row->trip_count }} {{ Str::plural('trip', $row->trip_count) }}</span>
    </div>
    @empty
    <div class="admin-panel-empty">No trip data yet.</div>
    @endforelse
</div>

{{-- 3. Recent Activity Log --}}
<div class="admin-panel-head"><h3 style="margin-bottom:0;">Recent Activity Log</h3></div>
<div class="admin-card" style="margin-top:12px;">
    <table class="admin-table">
        <thead>
            <tr><th>User / Reviewer</th><th>Destination</th><th>Recorded Amount</th><th>Rating</th></tr>
        </thead>
        <tbody>
        @forelse($activity as $row)
            <tr>
                <td>{{ $row['user'] }}</td>
                <td>{{ $row['destination'] }}</td>
                <td>₱{{ number_format($row['amount'], 2) }}</td>
                <td>{{ $row['rating'] ? $row['rating'] . '/5' : 'Pending Check' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="admin-table-empty">No recent activity.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
