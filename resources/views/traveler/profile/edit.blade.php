@php $active = 'profile'; @endphp
@extends(auth()->user()?->role === 'admin' ? 'layouts.admin' : 'layouts.app')

@section('title', 'My Profile')

@section('content')
<div style="max-width:1080px;margin:0 auto;flex:1;width:100%;display:flex;flex-direction:column;">

    @if ($errors->any())
    <div style="background:rgba(220,38,38,0.1);border:1px solid rgba(220,38,38,0.3);border-radius:12px;padding:14px 16px;margin-bottom:16px;">
        <ul style="margin:0;padding-left:1.1em;color:#DC2626;font-size:13px;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:stretch;flex:1;">

    {{-- ── LEFT: Personal details ── --}}
    <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:20px;box-shadow:0 4px 16px rgba(45,27,20,0.05);overflow:hidden;display:flex;flex-direction:column;height:100%;box-sizing:border-box;">
        <form id="profileForm" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" x-data="{ preview: null, filename: '' }" style="display:flex;flex-direction:column;flex:1;min-height:0;">
            @csrf
            @method('PUT')

            <div style="padding:28px 28px 8px;display:flex;align-items:center;gap:18px;">
                <div style="position:relative;flex-shrink:0;">
                    <div style="position:relative;width:76px;height:76px;border-radius:50%;overflow:hidden;background:var(--primary-light);display:flex;align-items:center;justify-content:center;border:2px solid var(--border);">
                        @if ($user->profile_photo)
                        <img id="avatarPreview" src="{{ Storage::url($user->profile_photo) }}" style="width:100%;height:100%;object-fit:cover;" alt="Profile photo">
                        @else
                        <img id="avatarPreview" src="" style="width:100%;height:100%;object-fit:cover;display:none;" alt="Profile photo">
                        <span id="avatarInitial" style="font-size:26px;font-weight:800;color:var(--primary);">
                            {{ mb_substr($user->first_name ?: $user->full_name ?? 'U', 0, 1) }}
                        </span>
                        @endif
                        {{-- Covers the avatar while the picked file decodes. A photo
                             straight off a phone is several megabytes, and without
                             this the old picture just sits there looking like the
                             click did nothing. --}}
                        <div id="avatarSpinner" style="position:absolute;inset:0;display:none;align-items:center;justify-content:center;background:var(--primary-light);">
                            <i class="fa-solid fa-spinner fa-spin" style="color:var(--primary);font-size:20px;"></i>
                        </div>
                    </div>
                    {{-- Sits outside the circle, which is overflow:hidden, but
                         inside its positioned parent. A <label for> rather than
                         a button: it opens the picker with no JS at all, and
                         still reaches the keyboard. --}}
                    <label for="profile_photo" class="avatar-add-btn" title="Change profile photo" tabindex="0"
                           onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); this.click(); }">
                        <i class="fa-solid fa-plus"></i>
                        <span class="sr-only">Change profile photo</span>
                    </label>
                    <input type="file" id="profile_photo" name="profile_photo"
                           accept="image/jpeg,image/png,image/jpg,image/webp" style="display:none;"
                           onchange="budgetraPreviewAvatar(this)">
                </div>
                <div style="min-width:0;">
                    <div id="profileCardName" style="font-size:15px;font-weight:700;color:var(--dark);">{{ $user->full_name }}</div>
                    <div style="font-size:12.5px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $user->email }}</div>
                </div>
            </div>

            <div style="padding:20px 28px 28px;display:flex;flex-direction:column;flex:1;min-height:0;">

                <div style="margin-bottom:16px;">
                    <label for="first_name" style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">
                        First Name <span style="color:#DC2626;">*</span>
                    </label>
                    <input type="text" id="first_name" name="first_name" required
                           value="{{ old('first_name', $user->first_name) }}"
                           style="width:100%;background:var(--bg);border:1.5px solid {{ $errors->has('first_name') ? '#DC2626' : 'var(--border)' }};border-radius:12px;padding:11px 14px;font-size:13px;font-weight:600;color:var(--dark);box-sizing:border-box;">
                    <span id="err_first_name" class="pf-field-error" style="display:{{ $errors->has('first_name') ? 'block' : 'none' }};font-size:11px;color:#DC2626;margin-top:4px;">{{ $errors->first('first_name') }}</span>
                </div>

                <div style="margin-bottom:16px;">
                    <label for="last_name" style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">
                        Last Name <span style="color:#DC2626;">*</span>
                    </label>
                    <input type="text" id="last_name" name="last_name" required
                           value="{{ old('last_name', $user->last_name) }}"
                           style="width:100%;background:var(--bg);border:1.5px solid {{ $errors->has('last_name') ? '#DC2626' : 'var(--border)' }};border-radius:12px;padding:11px 14px;font-size:13px;font-weight:600;color:var(--dark);box-sizing:border-box;">
                    <span id="err_last_name" class="pf-field-error" style="display:{{ $errors->has('last_name') ? 'block' : 'none' }};font-size:11px;color:#DC2626;margin-top:4px;">{{ $errors->first('last_name') }}</span>
                </div>

                <div style="margin-bottom:20px;">
                    <label for="email" style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Email Address</label>
                    <div style="display:flex;align-items:center;gap:10px;background:var(--bg);border:1.5px solid var(--border);border-radius:12px;padding:11px 14px;">
                        <i class="fa-solid fa-envelope" style="color:var(--muted);font-size:12px;"></i>
                        <input type="email" id="email" value="{{ $user->email }}" disabled
                               style="flex:1;border:none;outline:none;background:transparent;font-size:13px;font-weight:600;color:var(--muted);">
                    </div>
                </div>

                <div style="margin-top:auto;display:flex;justify-content:flex-end;padding-top:20px;">
                    <button type="submit" id="profileSaveBtn"
                            style="background:var(--primary);color:#fff;border:none;border-radius:12px;padding:13px 28px;font-size:13px;font-weight:700;cursor:pointer;font-family:'Hanken Grotesk',sans-serif;transition:background .18s;"
                            onmouseenter="this.style.background='var(--primary-dark)'" onmouseleave="this.style.background='var(--primary)'">
                        <i class="fa-solid fa-check" style="font-size:11px;"></i> Save Changes
                    </button>
                </div>

            </div>
        </form>
    </div>

    {{-- ── RIGHT: Travel preferences (read-only summary from the profile builder) ── --}}
    <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:20px;box-shadow:0 4px 16px rgba(45,27,20,0.05);padding:28px;display:flex;flex-direction:column;height:100%;box-sizing:border-box;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
            <div>
                <div style="font-size:15px;font-weight:700;color:var(--dark);">Travel Preferences</div>
                <div style="font-size:12px;color:var(--muted);margin-top:2px;">From your Profile Builder setup.</div>
            </div>
        </div>

        @if (!$profile)
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:32px 16px;">
            <div style="width:48px;height:48px;border-radius:14px;background:var(--bg);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                <i class="fa-solid fa-compass" style="color:var(--muted);font-size:18px;"></i>
            </div>
            <p style="font-size:13px;color:var(--muted);margin:0 0 16px;">You haven't set up your travel preferences yet.</p>
            <a href="{{ route('profile.setup') }}" style="display:inline-flex;align-items:center;gap:8px;background:var(--primary);color:#fff;border-radius:12px;padding:11px 20px;font-size:13px;font-weight:700;text-decoration:none;">
                <i class="fa-solid fa-wand-magic-sparkles" style="font-size:11px;"></i> Set Up Preferences
            </a>
        </div>
        @else
        @php
            $icons = \App\Livewire\Traveler\ProfileBuilder::ICONS;
            $travelStyles = \App\Livewire\Traveler\ProfileBuilder::TRAVEL_STYLES;
            $selectedInterests = $profile->interests ?? [];
            $selectedSubInterests = $profile->sub_interests ?? [];
            $interestSubs = \App\Livewire\Traveler\ProfileBuilder::INTERESTS;
        @endphp

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
            <div class="rv-card-sm">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="rv-icon-sm"><i class="fa-solid fa-location-dot"></i></div>
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Address</div>
                    </div>
                    <a href="{{ route('profile.setup') }}?step=1&return=profile.edit" class="rv-edit">Edit</a>
                </div>
                <div style="font-size:15px;font-weight:700;color:var(--dark);">{{ $profile->home_city ?: '—' }}</div>
                {{-- PlaceCatalog knows every destination, not just the cities on
                     the registration dropdown, so this resolves places the old
                     controller-side loop returned nothing for. --}}
                <div style="font-size:11px;color:var(--muted);">{{ \App\Support\PlaceCatalog::countryFor($profile->home_city) ?: '—' }}</div>
            </div>

            @php
                $budgetSymbol = $profile->daily_budget_currency
                    ? (\App\Support\PlaceCatalog::CURRENCY_SYMBOLS[$profile->daily_budget_currency] ?? currency_symbol())
                    : currency_symbol();
                $budgetAmount = $profile->daily_budget_local ?? $profile->daily_budget;
            @endphp
            <div class="rv-card-sm">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="rv-icon-sm"><i class="fa-solid fa-wallet"></i></div>
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Budget Preference</div>
                    </div>
                    <a href="{{ route('profile.setup') }}?step=2&return=profile.edit" class="rv-edit">Edit</a>
                </div>
                <div style="font-size:15px;font-weight:700;color:var(--dark);">{{ $budgetAmount ? $budgetSymbol . number_format($budgetAmount) : '—' }}</div>
                <div style="height:4px;border-radius:2px;background:var(--bg);margin-top:8px;overflow:hidden;">
                    <div style="height:100%;width:{{ $budgetAmount ? min(100, round($budgetAmount / 3000 * 100)) : 0 }}%;background:var(--primary);"></div>
                </div>
            </div>

            <div class="rv-card-sm">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="rv-icon-sm"><i class="fa-solid {{ $travelStyles[$profile->travel_style]['icon'] ?? 'fa-user-group' }}"></i></div>
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Travel Style</div>
                    </div>
                    <a href="{{ route('profile.setup') }}?step=3&return=profile.edit" class="rv-edit">Edit</a>
                </div>
                <div style="font-size:15px;font-weight:700;color:var(--dark);">{{ $profile->travel_style ?: '—' }}</div>
                <div style="font-size:11px;color:var(--muted);">Cost-splitting & accommodation fit</div>
            </div>

            {{-- Transportation and Accommodation are separate steps in the
                 profile builder now, so they get a card each here too, with
                 Edit pointing at the step that actually owns the field. --}}
            <div class="rv-card-sm">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="rv-icon-sm"><i class="fa-solid fa-route"></i></div>
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Transportation</div>
                    </div>
                    <a href="{{ route('profile.setup') }}?step=5&return=profile.edit" class="rv-edit">Edit</a>
                </div>
                <div style="font-size:15px;font-weight:700;color:var(--dark);">{{ $profile->preferred_transportation ?: '—' }}</div>
                <div style="font-size:11px;color:var(--muted);">How you'll travel</div>
            </div>

            <div class="rv-card-sm">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="rv-icon-sm"><i class="fa-solid fa-bed"></i></div>
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Accommodation</div>
                    </div>
                    <a href="{{ route('profile.setup') }}?step=6&return=profile.edit" class="rv-edit">Edit</a>
                </div>
                <div style="font-size:15px;font-weight:700;color:var(--dark);">{{ $profile->preferred_accommodation ?: '—' }}</div>
                <div style="font-size:11px;color:var(--muted);">Where you'll stay</div>
            </div>
        </div>

        <div class="rv-card-sm" style="display:flex;flex-direction:column;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div class="rv-icon-sm"><i class="fa-solid fa-heart"></i></div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Travel Interests</div>
                </div>
                <a href="{{ route('profile.setup') }}?step=4&return=profile.edit" class="rv-edit">Edit</a>
            </div>
            @forelse ($selectedInterests as $interest)
            @php
                $subsForInterest = array_values(array_intersect($interestSubs[$interest] ?? [], $selectedSubInterests));
            @endphp
            <div class="rv-interest-row">
                <div class="rv-interest-icon">
                    <i class="fa-solid {{ $icons[$interest] ?? 'fa-star' }}"></i>
                </div>
                <div class="rv-interest-body">
                    <div class="rv-interest-name">{{ $interest }}</div>
                    @if ($subsForInterest)
                    <div class="rv-interest-subs">
                        @foreach ($subsForInterest as $sub)
                        <span class="rv-interest-sub-chip">{{ $sub }}</span>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
            @empty
            <div class="rv-interests-empty">
                <div class="rv-interests-empty-icon"><i class="fa-regular fa-compass"></i></div>
                <p>No interests selected yet.</p>
            </div>
            @endforelse
        </div>
        @endif
    </div>

    </div>
</div>

<style>
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}

/* The + on the avatar, which replaced the dropzone below the form. Ringed in
   the card's own background so it reads as sitting on top of the photo rather
   than being part of it. */
.avatar-add-btn{
    position:absolute;right:-2px;bottom:-2px;z-index:3;
    width:26px;height:26px;border-radius:50%;
    background:var(--primary);color:#fff;
    display:flex;align-items:center;justify-content:center;
    font-size:11px;cursor:pointer;
    border:2px solid var(--bg-white);
    transition:background .15s ease,transform .15s ease;
}
.avatar-add-btn:hover{background:var(--primary-dark);transform:scale(1.08);}
.avatar-add-btn:focus-visible{outline:2px solid var(--primary);outline-offset:3px;}

/* A refused photo. Scrim is neutral black rather than a brand tint, which
   read as a haze over the darker themes. */
.pf-modal-backdrop{
    position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.55);
    display:flex;align-items:center;justify-content:center;padding:24px;
}
.pf-modal-card{
    background:var(--bg-white);border:1.5px solid var(--border);border-radius:20px;
    padding:28px 26px 22px;max-width:380px;width:100%;text-align:center;
    box-shadow:0 18px 50px rgba(0,0,0,.28);
}
.pf-modal-icon{
    width:52px;height:52px;border-radius:50%;margin:0 auto 16px;
    display:flex;align-items:center;justify-content:center;
    font-size:20px;color:var(--danger);background:rgba(220,38,38,.14);
}
@supports (color: color-mix(in srgb, red, blue)) {
    .pf-modal-icon{background:color-mix(in srgb, var(--danger) 16%, transparent);}
}
.pf-modal-title{font-size:17px;font-weight:800;color:var(--dark);margin:0 0 8px;}
.pf-modal-msg{font-size:13px;color:var(--muted);line-height:1.6;margin:0 0 20px;}
.pf-modal-btn{
    width:100%;background:var(--primary);color:#fff;border:none;border-radius:12px;
    padding:12px 20px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;
    transition:background .18s;
}
.pf-modal-btn:hover{background:var(--primary-dark);}
.rv-card-sm{border:1.5px solid var(--border);border-radius:14px;padding:14px 16px;background:var(--bg-white);}
.rv-edit{font-size:11px;font-weight:700;color:var(--primary);cursor:pointer;white-space:nowrap;flex-shrink:0;text-decoration:none;}
.rv-edit:hover{text-decoration:underline;}

.rv-interest-row{display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-top:1px solid var(--border);}
.rv-interest-row:first-of-type{border-top:none;padding-top:2px;}
.rv-interest-icon{width:38px;height:38px;border-radius:11px;background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;}
.rv-interest-body{min-width:0;padding-top:2px;}
.rv-interest-name{font-size:14px;font-weight:700;color:var(--dark);}
.rv-interest-subs{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;}
.rv-interest-sub-chip{display:inline-flex;align-items:center;background:var(--bg);color:var(--muted);font-size:11.5px;font-weight:600;padding:5px 11px;border-radius:20px;}
.rv-interests-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:24px 16px;}
.rv-interests-empty-icon{width:44px;height:44px;border-radius:50%;background:var(--bg);color:var(--muted);display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:12px;}
.rv-interests-empty p{font-size:13px;color:var(--muted);margin:0;}
.rv-icon-sm{width:26px;height:26px;border-radius:8px;background:var(--bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--primary);font-size:11px;}
@media (max-width: 860px) {
    div[style*="grid-template-columns:1fr 1fr"][style*="align-items:stretch"] { grid-template-columns: 1fr !important; }
}

</style>

{{-- Rendered open when the server refused the photo, so a rejection that
     slipped past the browser check lands in the same place as one that did
     not. @error gives the server's own wording. --}}
<div id="photoErrorModal" class="pf-modal-backdrop"
     style="display:{{ $errors->has('profile_photo') ? 'flex' : 'none' }};"
     onclick="if (event.target === this) budgetraHidePhotoError();">
    <div class="pf-modal-card" role="alertdialog" aria-modal="true" aria-labelledby="photoErrorTitle">
        <div class="pf-modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h3 class="pf-modal-title" id="photoErrorTitle">Photo not accepted</h3>
        <p class="pf-modal-msg" id="photoErrorMsg">{{ $errors->first('profile_photo') }}</p>
        <button type="button" class="pf-modal-btn" onclick="budgetraHidePhotoError()">Got it</button>
    </div>
</div>

{{-- Inline rather than pushed: this view renders under layouts.admin for an
     admin account, and that layout has no @stack('scripts') to push into. --}}
<script>
    // Matches the rule in ProfileController::update (max:5120, in kilobytes).
    // Both are needed: this one spares the traveler a slow upload that was
    // always going to be refused, and that one is what actually enforces it.
    var BUDGETRA_MAX_PHOTO_BYTES = 5 * 1024 * 1024;

    function budgetraShowPhotoError(message) {
        document.getElementById('photoErrorMsg').textContent = message;
        document.getElementById('photoErrorModal').style.display = 'flex';
    }

    function budgetraHidePhotoError() {
        document.getElementById('photoErrorModal').style.display = 'none';
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') budgetraHidePhotoError();
    });

    function budgetraPreviewAvatar(input) {
        var file    = input.files[0];
        var img     = document.getElementById('avatarPreview');
        var initial = document.getElementById('avatarInitial');
        var spinner = document.getElementById('avatarSpinner');

        if (!file) return;

        if (file.size > BUDGETRA_MAX_PHOTO_BYTES) {
            // Cleared so the form cannot post a file the server would refuse
            // anyway — and so choosing the same file again still fires change,
            // which it would not if the value stayed put.
            input.value = '';
            budgetraShowPhotoError(
                'That photo is ' + (file.size / 1048576).toFixed(1) +
                ' MB. Profile photos have to be 5 MB or smaller.'
            );
            return;
        }

        spinner.style.display = 'flex';

        var url = URL.createObjectURL(file);

        // Swap only once the browser has actually decoded it, so the spinner
        // measures the real wait instead of a made-up one.
        img.onload = function () {
            spinner.style.display = 'none';
            img.style.display     = 'block';
            if (initial) initial.style.display = 'none';
            URL.revokeObjectURL(url);
        };
        img.onerror = function () {
            spinner.style.display = 'none';
            input.value           = '';
            budgetraShowPhotoError('That file could not be read as an image. Pick another one.');
            URL.revokeObjectURL(url);
        };

        img.src = url;
    }

    /**
     * Saving without a reload.
     *
     * The photo shows up in three places — this card, the sidebar and your own
     * review rows — and a normal form post repainted all three only because it
     * reloaded the page. Posting with fetch() keeps the page put, so the
     * server's answer is handed to budgetraSyncAvatar(), which repaints every
     * element tagged data-user-avatar. The sidebar is the one that genuinely
     * needs it: it is @@persist'ed, so no wire:navigate will ever re-render it.
     *
     * The form still works with JavaScript off — the submit listener is the
     * only thing standing between it and the ordinary POST.
     */
    (function () {
        var form = document.getElementById('profileForm');
        var btn  = document.getElementById('profileSaveBtn');
        if (!form || !btn) return;

        function clearErrors() {
            ['first_name', 'last_name'].forEach(function (field) {
                var slot = document.getElementById('err_' + field);
                if (slot) { slot.textContent = ''; slot.style.display = 'none'; }
                var input = document.getElementById(field);
                if (input) input.style.borderColor = 'var(--border)';
            });
        }

        function showErrors(errors) {
            clearErrors();

            Object.keys(errors).forEach(function (field) {
                var message = [].concat(errors[field])[0];

                // A rejected photo is the one error with its own dialog — the
                // same one the 5 MB client-side check uses, so the two paths
                // look identical to whoever hit them.
                if (field === 'profile_photo') {
                    budgetraShowPhotoError(message);
                    return;
                }

                var slot = document.getElementById('err_' + field);
                if (slot) { slot.textContent = message; slot.style.display = 'block'; }
                var input = document.getElementById(field);
                if (input) input.style.borderColor = '#DC2626';
            });
        }

        function applySaved(data) {
            clearErrors();

            var name = document.getElementById('profileCardName');
            if (name && data.full_name) name.textContent = data.full_name;

            if (data.photo_url) {
                // The circle is already showing the blob: URL from the picker.
                // Pointing it at the stored file instead lets that blob be
                // released and keeps this card honest about what was saved.
                var img     = document.getElementById('avatarPreview');
                var initial = document.getElementById('avatarInitial');
                if (img) { img.src = data.photo_url; img.style.display = 'block'; }
                if (initial) initial.style.display = 'none';

                if (typeof window.budgetraSyncAvatar === 'function') {
                    window.budgetraSyncAvatar(data.photo_url);
                }
            }

            // Otherwise the same file would be uploaded again on the next save.
            var picker = document.getElementById('profile_photo');
            if (picker) picker.value = '';

            // No success toast: the spinner giving way to "Save Changes" is the
            // confirmation, along with the avatar and name repainting in place.
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (btn.disabled) return;

            var label = btn.innerHTML;
            btn.disabled = true;
            btn.style.opacity = '.7';
            btn.setAttribute('aria-label', 'Saving changes…');
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="font-size:11px;" aria-hidden="true"></i>';

            fetch(form.action, {
                method: 'POST',            // with _method=PUT in the body, so the file survives
                body: new FormData(form),
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (data) {
                    return { ok: res.ok, status: res.status, data: data };
                });
            }).then(function (r) {
                if (r.ok) applySaved(r.data);
                else if (r.status === 422) showErrors(r.data.errors || {});
                else budgetraShowPhotoError('Your changes could not be saved. Please try again.');
            }).catch(function () {
                budgetraShowPhotoError('Your changes could not be saved. Check your connection and try again.');
            }).then(function () {
                btn.disabled = false;
                btn.style.opacity = '';
                btn.removeAttribute('aria-label');
                btn.innerHTML = label;
            });
        });
    })();
</script>
@endsection
