@extends('layouts.admin')
@section('title', 'Database Backup')
@section('content')
<h1>Database Backup & Restore</h1>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
    <div class="card">
        <div class="card-body">
            <h3>Download Backup</h3>
            <p>Creates an on-demand SQL dump of the entire database and downloads it as a <code>.sql</code> file.</p>
            <form method="POST" action="{{ route('admin.backup.download') }}">
                @csrf
                <button class="btn btn-primary">Download Backup (.sql)</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h3>Restore from Backup</h3>
            <p style="color:#e74c3c;"><strong>Warning:</strong> This will overwrite existing data. Only restore from a trusted backup file.</p>
            <form method="POST" action="{{ route('admin.backup.restore') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label>Upload .sql file</label>
                    <input type="file" name="sql_file" class="form-control @error('sql_file') is-invalid @enderror" accept=".sql" required>
                    @error('sql_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button class="btn btn-danger" onclick="return confirm('Are you sure? This will overwrite the current database.')">Restore Database</button>
            </form>
        </div>
    </div>
</div>
@endsection
