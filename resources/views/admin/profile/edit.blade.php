@extends('layouts.admin')
@section('title', 'Admin Profile')

@section('content')
<style>
    .adm-profile-grid { display: grid; grid-template-columns: 260px 1fr; gap: 20px; align-items: start; }
    @media (max-width: 860px) { .adm-profile-grid { grid-template-columns: 1fr; } }

    .adm-profile-avatar {
        width: 96px; height: 96px; border-radius: 50%; object-fit: cover;
        display: flex; align-items: center; justify-content: center;
        background: var(--primary); color: #fff; font-size: 30px; font-weight: 800;
        margin: 0 auto 14px;
    }
    .adm-profile-role {
        display: inline-block; padding: 4px 12px; border-radius: 99px;
        background: var(--primary-light); color: var(--primary);
        font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
    }

    /* Same upload prompt as the traveler profile page. */
    .adm-dropzone {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 14px; width: 100%; box-sizing: border-box; text-align: center;
        border: 1.5px dashed var(--border); border-radius: 14px; padding: 22px 16px;
        cursor: pointer; transition: border-color .15s ease, background .15s ease;
    }
    .adm-dropzone:hover { border-color: var(--primary); background: var(--primary-light); }
    .adm-dropzone-icon {
        width: 52px; height: 52px; border-radius: 14px; background: var(--primary-light);
        display: flex; align-items: center; justify-content: center;
        color: var(--primary); font-size: 20px; flex-shrink: 0;
    }
    .adm-dropzone:hover .adm-dropzone-icon { background: var(--bg-white); }
    .adm-dropzone-title { font-size: 14px; font-weight: 700; color: var(--dark); }
    .adm-dropzone-title span { color: var(--primary); }
    .adm-dropzone-hint { font-size: 12px; color: var(--muted); margin-top: 4px; }
</style>

<div class="admin-page-head">
    <div>
        <h1>Profile</h1>
        <p>Your admin account details.</p>
    </div>
</div>

@if (session('success'))<div class="admin-alert-success">{{ session('success') }}</div>@endif

<form method="POST" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <div class="adm-profile-grid">

        {{-- Identity --}}
        <div class="admin-card"><div class="card-body" style="padding:24px;text-align:center;">
            @if ($user->profile_photo)
            <img src="{{ Illuminate\Support\Facades\Storage::url($user->profile_photo) }}"
                 alt="Profile" class="adm-profile-avatar" id="admAvatarPreview">
            @else
            <div class="adm-profile-avatar" id="admAvatarInitials">
                {{ collect(explode(' ', $user->full_name ?: 'Admin'))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
            </div>
            <img src="" alt="Profile" class="adm-profile-avatar" id="admAvatarPreview" style="display:none;">
            @endif

            <div style="font-size:15px;font-weight:800;color:var(--dark);">{{ $user->full_name }}</div>
            <div style="font-size:12.5px;color:var(--muted);margin:2px 0 12px;">{{ $user->email }}</div>
            <span class="adm-profile-role">{{ $user->role }}</span>
        </div></div>

        {{-- Editable details --}}
        <div class="admin-card"><div class="card-body" style="padding:24px;">
            <h2 style="font-size:15px;font-weight:700;margin:0 0 16px;color:var(--dark);">Account Details</h2>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" class="admin-input" required
                           value="{{ old('first_name', $user->first_name) }}">
                    @error('first_name')<div class="admin-form-error">{{ $message }}</div>@enderror
                </div>
                <div class="admin-form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" class="admin-input" required
                           value="{{ old('last_name', $user->last_name) }}">
                    @error('last_name')<div class="admin-form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="admin-form-group">
                <label>Email Address</label>
                {{-- Read-only: the account's login identity isn't changed from
                     here, and update() doesn't accept an email field. --}}
                <input type="email" class="admin-input" value="{{ $user->email }}" disabled>
            </div>

            <div class="admin-form-group">
                <label>Profile Photo</label>
                <label for="admProfilePhoto" class="adm-dropzone">
                    <div class="adm-dropzone-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                    <div>
                        <div class="adm-dropzone-title"><span>Click to upload</span> a new photo</div>
                        <div class="adm-dropzone-hint" id="admFileName">PNG or JPG, up to 2MB</div>
                    </div>
                </label>
                <input type="file" name="profile_photo" id="admProfilePhoto" style="display:none;"
                       accept="image/jpeg,image/png,image/jpg,image/webp">
                @error('profile_photo')<div class="admin-form-error">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="admin-btn admin-btn-primary" style="margin-top:4px;">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
            </button>
        </div></div>

    </div>
</form>

<script>
    document.getElementById('admProfilePhoto').addEventListener('change', function () {
        var file = this.files[0];
        document.getElementById('admFileName').textContent = file ? file.name : 'PNG or JPG, up to 2MB';
        if (!file) return;

        var preview  = document.getElementById('admAvatarPreview');
        var initials = document.getElementById('admAvatarInitials');
        preview.src = URL.createObjectURL(file);
        preview.style.display = '';
        if (initials) initials.style.display = 'none';
    });
</script>
@endsection
