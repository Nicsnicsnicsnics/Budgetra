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
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" x-data="{ preview: null, filename: '' }" style="display:flex;flex-direction:column;flex:1;min-height:0;">
            @csrf
            @method('PUT')

            <div style="padding:28px 28px 8px;display:flex;align-items:center;gap:18px;">
                <div style="position:relative;flex-shrink:0;">
                    <div style="width:76px;height:76px;border-radius:50%;overflow:hidden;background:var(--primary-light);display:flex;align-items:center;justify-content:center;border:2px solid var(--border);">
                        @if ($user->profile_photo)
                        <img id="avatarPreview" src="{{ Storage::url($user->profile_photo) }}" style="width:100%;height:100%;object-fit:cover;" alt="Profile photo">
                        @else
                        <img id="avatarPreview" src="" style="width:100%;height:100%;object-fit:cover;display:none;" alt="Profile photo">
                        <span id="avatarInitial" style="font-size:26px;font-weight:800;color:var(--primary);">
                            {{ mb_substr($user->first_name ?: $user->full_name ?? 'U', 0, 1) }}
                        </span>
                        @endif
                    </div>
                </div>
                <div style="min-width:0;">
                    <div style="font-size:15px;font-weight:700;color:var(--dark);">{{ $user->full_name }}</div>
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
                    @error('first_name') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
                </div>

                <div style="margin-bottom:16px;">
                    <label for="last_name" style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">
                        Last Name <span style="color:#DC2626;">*</span>
                    </label>
                    <input type="text" id="last_name" name="last_name" required
                           value="{{ old('last_name', $user->last_name) }}"
                           style="width:100%;background:var(--bg);border:1.5px solid {{ $errors->has('last_name') ? '#DC2626' : 'var(--border)' }};border-radius:12px;padding:11px 14px;font-size:13px;font-weight:600;color:var(--dark);box-sizing:border-box;">
                    @error('last_name') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
                </div>

                <div style="margin-bottom:20px;">
                    <label for="email" style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Email Address</label>
                    <div style="display:flex;align-items:center;gap:10px;background:var(--bg);border:1.5px solid var(--border);border-radius:12px;padding:11px 14px;">
                        <i class="fa-solid fa-envelope" style="color:var(--muted);font-size:12px;"></i>
                        <input type="email" id="email" value="{{ $user->email }}" disabled
                               style="flex:1;border:none;outline:none;background:transparent;font-size:13px;font-weight:600;color:var(--muted);">
                    </div>
                </div>

                <div style="margin-bottom:24px;display:flex;flex-direction:column;flex:1;min-height:0;">
                    <label style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Profile Photo</label>
                    <label for="profile_photo" class="profile-photo-dropzone"
                           style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;width:100%;flex:1;min-height:100px;box-sizing:border-box;border:1.5px dashed var(--border);border-radius:14px;padding:18px 16px;cursor:pointer;transition:border-color .15s ease,background .15s ease;text-align:center;">
                        <div style="width:52px;height:52px;border-radius:14px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fa-solid fa-cloud-arrow-up" style="color:var(--primary);font-size:20px;"></i>
                        </div>
                        <div style="min-width:0;">
                            <div style="font-size:14px;font-weight:700;color:var(--dark);">
                                <span style="color:var(--primary);">Click to upload</span> a new photo
                            </div>
                            <div id="fileNameLabel" style="font-size:12px;color:var(--muted);margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">PNG or JPG, up to 5MB</div>
                        </div>
                    </label>
                    <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/jpg,image/webp" style="display:none;"
                           onchange="
                               var f = this.files[0];
                               document.getElementById('fileNameLabel').textContent = f ? f.name : 'PNG or JPG, up to 5MB';
                               if (f) {
                                   var url = URL.createObjectURL(f);
                                   var img = document.getElementById('avatarPreview');
                                   var initial = document.getElementById('avatarInitial');
                                   img.src = url; img.style.display = 'block';
                                   if (initial) initial.style.display = 'none';
                               }
                           ">
                    @error('profile_photo') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
                </div>

                <div style="margin-top:auto;display:flex;justify-content:flex-end;padding-top:20px;">
                    <button type="submit"
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
                <div style="font-size:11px;color:var(--muted);">{{ $homeCountry ?: '—' }}</div>
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
.profile-photo-dropzone:hover { border-color: var(--primary); background: var(--primary-light); }
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
@endsection
