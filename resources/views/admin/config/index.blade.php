@extends('layouts.admin')
@section('title', 'App Config')
@section('content')
<h1>App Config</h1>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="card" style="max-width:600px;">
    <div class="card-body">
        <h4>Klook API Settings</h4>
        <form method="POST" action="{{ route('admin.config.store') }}">
            @csrf
            <input type="hidden" name="config_key" value="klook_api_key">
            <div class="form-group" style="margin-bottom:1rem;">
                <label>Klook API Key</label>
                <input type="text" name="config_value" class="form-control"
                    value="{{ $configs['klook_api_key'] ?? '' }}" placeholder="Enter Klook API key">
            </div>
            <button class="btn btn-primary">Save</button>
        </form>
    </div>
</div>
<div class="card" style="max-width:600px;margin-top:1.5rem;">
    <div class="card-body">
        <h4>Klook Base URL</h4>
        <form method="POST" action="{{ route('admin.config.store') }}">
            @csrf
            <input type="hidden" name="config_key" value="klook_base_url">
            <div class="form-group" style="margin-bottom:1rem;">
                <label>Base URL</label>
                <input type="text" name="config_value" class="form-control"
                    value="{{ $configs['klook_base_url'] ?? '' }}" placeholder="https://api.klook.com">
            </div>
            <button class="btn btn-primary">Save</button>
        </form>
    </div>
</div>
@endsection
