@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

<div style="min-height:100vh;background:var(--bg);display:flex;flex-direction:column;align-items:center;padding:24px;">

@if(!$returnTo)
<div style="display:flex;align-items:center;width:100%;max-width:560px;margin:8px 0 36px;padding:0 24px;box-sizing:border-box;">
    @for ($i = 1; $i <= 7; $i++)
        @php $state = $step > $i ? 'done' : ($step === $i ? 'current' : 'upcoming'); @endphp
        <div style="width:32px;height:32px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;
                    background:{{ $state === 'upcoming' ? 'var(--bg-white)' : 'var(--primary)' }};
                    color:{{ $state === 'upcoming' ? 'var(--muted)' : '#fff' }};
                    border:2px solid {{ $state === 'upcoming' ? 'var(--border)' : 'var(--primary)' }};
                    transition:all .3s ease;">
            @if($state === 'done')
                <i class="fa-solid fa-check" style="font-size:12px;"></i>
            @else
                {{ $i }}
            @endif
        </div>
        @if($i < 6)
        <div style="flex:1;height:3px;background:{{ $step > $i ? 'var(--primary)' : 'var(--border)' }};transition:background .3s ease;"></div>
        @endif
    @endfor
</div>
@endif

<style>
.pb-icon-wrap{width:56px;height:56px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:20px;}
.pb-title{font-size:22px;font-weight:800;color:var(--dark);margin:0 0 8px;}
.pb-sub{font-size:13px;color:var(--muted);line-height:1.6;margin:0 0 28px;}
.pb-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:10px;}
.pb-input-wrap{display:flex;align-items:center;gap:10px;border:1.5px solid var(--border);border-radius:10px;padding:13px 16px;background:var(--bg-white);}
.pb-input-wrap:focus-within{border-color:var(--primary);}
/* Required-but-empty. Replaces the old "missing fields" modal — the field
   itself says what's wrong. Beats :focus-within so the red survives a click. */
.pb-clear{margin-left:auto;flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border:none;border-radius:50%;background:transparent;color:var(--muted);cursor:pointer;font-size:13px;line-height:1;padding:0;transition:background .15s,color .15s;}
.pb-clear:hover{background:var(--border);color:var(--dark);}
.pb-clear:focus-visible{outline:2px solid var(--primary);outline-offset:2px;}
.pb-input-wrap.is-bad,.pb-input-wrap.is-bad:focus-within{
    border-color:#FF3B3B;box-shadow:0 0 0 3px rgba(255,59,59,.20);
    animation:pb-shake .48s cubic-bezier(.36,.07,.19,.97) both;}
/* Card pickers shake the cards themselves rather than their scroll container:
   .int-scroll is overflow-x:auto and holds the absolutely-tracked side panel,
   so transforming it would drag that along. */
.int-card-img.is-bad{box-shadow:0 0 0 2px #FF3B3B;
    animation:pb-shake .48s cubic-bezier(.36,.07,.19,.97) both;}
@keyframes pb-shake{
  10%,90%{transform:translateX(-2px);}
  20%,80%{transform:translateX(3px);}
  30%,50%,70%{transform:translateX(-6px);}
  40%,60%{transform:translateX(6px);}
}
@media (prefers-reduced-motion:reduce){
  .pb-input-wrap.is-bad,.int-card-img.is-bad{animation:none;}
}
.pb-input{border:none;background:transparent;font-size:14px;font-weight:700;color:var(--dark);outline:none;width:100%;}
.pb-input::placeholder{color:var(--muted);font-weight:400;}
.pb-suggest{font-size:12px;color:var(--muted);margin-top:8px;}
.pb-suggest span{cursor:pointer;color:var(--primary);font-weight:600;text-decoration:underline;margin-left:4px;}
.pb-btn{display:inline-flex;align-items:center;gap:8px;padding:13px 28px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;border:none;}
.pb-btn-primary{background:var(--primary);color:#fff;}
.pb-btn-primary:hover{background:var(--primary-dark);}
.pb-btn-ghost{background:var(--bg-white);border:1.5px solid var(--border);color:var(--dark);}
.pb-btn-ghost:hover{background:var(--bg);}
.pb-nav{display:flex;align-items:center;justify-content:space-between;width:100%;max-width:480px;margin-top:20px;}

/* Interest grid */
.int-card{border:1.5px solid var(--border);border-radius:16px;padding:26px 14px;height:140px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;cursor:pointer;transition:all .15s;background:var(--bg-white);}
.int-card:hover{border-color:var(--primary);background:#FDF8F4;}
.int-card.active{border-color:var(--primary);background:#FDF8F4;box-shadow:0 0 0 2px var(--primary)33;}
.int-icon{width:56px;height:56px;border-radius:14px;background:var(--bg);display:flex;align-items:center;justify-content:center;margin:0 0 12px;}
.int-card.active .int-icon{background:var(--primary)15;}
.int-label{font-size:13px;font-weight:700;color:var(--dark);}

/* Interest grid — image cards */
.int-card-img{position:relative;border-radius:16px;height:170px;box-sizing:border-box;cursor:pointer;transition:transform .15s;}
.int-card-img:hover{transform:translateY(-2px);}
.int-card-img-bg{position:relative;width:100%;height:100%;border-radius:16px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;overflow:hidden;background-size:cover;background-position:center;}
.int-card-img-bg::before{content:'';position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0.05) 0%,rgba(0,0,0,0.45) 100%);transition:background .15s;}
.int-card-img:hover .int-card-img-bg::before{background:linear-gradient(180deg,rgba(0,0,0,0.05) 0%,rgba(0,0,0,0.5) 100%);}
.int-card-img .int-check{position:absolute;top:10px;right:10px;z-index:2;width:24px;height:24px;border-radius:50%;background:var(--primary);color:#fff;display:none;align-items:center;justify-content:center;font-size:12px;box-shadow:0 2px 6px rgba(0,0,0,0.25);}
.int-card-img.active .int-check{display:flex;}
.int-icon-img{position:relative;z-index:1;width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,0.9);display:flex;align-items:center;justify-content:center;margin:0 0 10px;}
.int-label-img{position:relative;z-index:1;font-size:14px;font-weight:700;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,0.4);}

/* Sub-interest list */
.sub-list{display:flex;flex-direction:column;gap:6px;max-height:260px;overflow-y:auto;padding-right:4px;scrollbar-width:none;-ms-overflow-style:none;}
.sub-list::-webkit-scrollbar{display:none;}
.sub-list-item{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;font-size:13px;font-weight:600;color:var(--dark);border:1.5px solid var(--border);background:var(--bg-white);cursor:pointer;transition:all .15s;}
.sub-list-item:hover{border-color:var(--primary);}
.sub-list-item.active{background:var(--primary);color:#fff;border-color:var(--primary);}
.sub-list-item .sub-check{width:16px;height:16px;border-radius:5px;border:1.5px solid var(--muted);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:10px;}
.sub-list-item.active .sub-check{background:var(--bg-white);border-color:#fff;color:var(--primary);}

/* Custom horizontal scrollbar for the interest row */
.int-scroll{scrollbar-width:thin;scrollbar-color:#E8B187 var(--bg);}
.int-scroll::-webkit-scrollbar{height:8px;}
.int-scroll::-webkit-scrollbar-track{background:var(--bg);border-radius:8px;}
.int-scroll::-webkit-scrollbar-thumb{background:#E8B187;border-radius:8px;}
.int-scroll::-webkit-scrollbar-thumb:hover{background:#D89A68;}

/* Interests sub-panel — pop accordion (animated in AND out) */
.int-side-panel-pop{flex:0 0 260px;border-left:1.5px solid var(--border);padding-left:18px;display:flex;flex-direction:column;justify-content:center;transform-origin:left center;}
.pop-enter{transition:opacity .22s ease,transform .22s ease;}
.pop-enter-start{opacity:0;transform:scaleX(.85);}
.pop-enter-end{opacity:1;transform:scaleX(1);}
.pop-leave{transition:opacity .16s ease,transform .16s ease;}
.pop-leave-start{opacity:1;transform:scaleX(1);}
.pop-leave-end{opacity:0;transform:scaleX(.85);}
.sub-chip{display:inline-flex;align-items:center;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;border:1.5px solid var(--border);background:var(--bg-white);cursor:pointer;transition:all .15s;}
.sub-chip:hover{border-color:var(--primary);}
.sub-chip.active{background:var(--primary);color:#fff;border-color:var(--primary);}

/* Review */
.rv-card{border:1.5px solid var(--border);border-radius:14px;padding:18px 20px;margin-bottom:14px;background:var(--bg-white);display:flex;align-items:flex-start;justify-content:space-between;gap:16px;}
.rv-card-sm{border:1.5px solid var(--border);border-radius:14px;padding:14px 16px;background:var(--bg-white);}
.rv-icon-sm{width:26px;height:26px;border-radius:8px;background:var(--bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--primary);font-size:11px;}
.rv-edit{font-size:12px;font-weight:700;color:var(--primary);cursor:pointer;white-space:nowrap;flex-shrink:0;text-decoration:none;}
.rv-edit:hover{text-decoration:underline;}
[x-cloak]{display:none!important;}
</style>

<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;width:100%;">

{{-- ── STEP 1: Address ── --}}
@if($step === 1)
<div wire:key="step-1"
     x-data="{
        query: '{{ addslashes($homeCity) }}',
        code: '{{ addslashes(array_search($homeCity, $localDestinations) ?: '') }}',
        timer: null,
        map: null,
        marker: null,
        open: false,
        search: '',
        selectCity(name, code) {
            this.query = name;
            this.code = code;
            this.open = false;
            this.search = '';
            $wire.set('homeCity', name);
            this.onInput(name);
        },
        loadLeaflet() {
            if (window.L) return Promise.resolve();
            if (window.__leafletLoading) return window.__leafletLoading;
            window.__leafletLoading = new Promise((resolve) => {
                const s = document.createElement('script');
                s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                s.onload = resolve;
                document.head.appendChild(s);
            });
            return window.__leafletLoading;
        },
        async initMap() {
            await this.loadLeaflet();
            this.map = L.map(this.$refs.mapEl).setView([12.8797, 121.7740], 6);
            L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; OpenStreetMap contributors &copy; <a href=\'https://carto.com/\'>CARTO</a>',
                subdomains: 'abcd',
                maxZoom: 20,
            }).addTo(this.map);
            setTimeout(() => this.map.invalidateSize(), 100);
            if (this.query.trim()) { this.geocode(this.query); }
        },
        onInput(value) {
            this.query = value;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.geocode(value), 600);
        },
        async geocode(place) {
            if (!place || !place.trim() || !this.map) return;
            try {
                const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(place));
                const data = await res.json();
                if (!data.length) return;
                const lat = parseFloat(data[0].lat), lng = parseFloat(data[0].lon);
                this.map.setView([lat, lng], 12);
                if (this.marker) { this.marker.setLatLng([lat, lng]); }
                else { this.marker = L.marker([lat, lng]).addTo(this.map); }
            } catch (e) {}
        }
     }"
     style="width:100%;max-width:1280px;padding:0 24px;display:flex;gap:48px;align-items:flex-start;flex-wrap:wrap;">

    <div style="flex:1 1 480px;min-width:340px;">
        <div class="pb-icon-wrap" style="width:68px;height:68px;">
            <i class="fa-solid fa-location-dot" style="font-size:30px;color:#fff;"></i>
        </div>
        <h1 class="pb-title" style="font-size:32px;">Where does your journey begin?</h1>
        <p class="pb-sub" style="font-size:16px;">We'll use your location to calculate flight durations, estimated costs, and suggest custom activities for your adventure.</p>

        <div class="pb-label" style="font-size:12px;">Address</div>
        <div style="position:relative;" x-on:click.away="open = false">
            <div class="pb-input-wrap" :class="{ 'is-bad': $store.pbBad.home }" style="padding:18px 20px;cursor:pointer;" x-on:click="open = !open; $store.pbBad.home = false">
                <i class="fa-solid fa-location-dot" style="color:var(--primary);font-size:16px;flex-shrink:0;"></i>
                <span x-text="query ? (code ? query + ' (' + code + ')' : query) : 'Where are you from?'"
                      :style="query ? 'font-size:16px;font-weight:700;color:var(--dark);' : 'font-size:17px;font-weight:400;color:var(--muted);'"></span>
                {{-- Clears the label, the code and the Livewire property
                     together; clearing only one lets the value come back on
                     the next render. --}}
                <button type="button" class="pb-clear" title="Clear" x-show="query" x-cloak
                        x-on:click.stop="query=''; code=''; search=''; open=false; $wire.set('homeCity',''); $store.pbBad.home=false">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div x-show="open" x-cloak x-transition
                 style="position:absolute;top:calc(100% + 8px);left:0;right:0;background:var(--bg-white);border:1.5px solid var(--border);border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,0.12);z-index:50;overflow:hidden;">
                <div style="padding:12px 16px;border-bottom:1.5px solid var(--bg);">
                    <input type="text" x-model="search" placeholder="Search city"
                           x-on:keydown="if ($event.key.length === 1 && /[0-9]/.test($event.key)) $event.preventDefault();"
                           x-on:input="if (/[0-9]/.test($event.target.value)) { $event.target.value = $event.target.value.replace(/[0-9]/g, ''); search = $event.target.value; }"
                           style="width:100%;border:none;outline:none;font-size:14px;font-weight:500;color:var(--dark);background:transparent;">
                </div>
                <div style="max-height:280px;overflow-y:auto;padding:8px;">
                    @foreach($localDestinations as $code => $city)
                    <div x-show="!search || '{{ addslashes($city) }}'.toLowerCase().includes(search.toLowerCase())"
                         x-on:click="selectCity('{{ addslashes($city) }}', '{{ addslashes($code) }}')"
                         style="display:flex;align-items:center;gap:12px;text-align:left;padding:10px 12px;border-radius:10px;cursor:pointer;"
                         onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background='transparent'">
                        <span style="display:inline-block;box-sizing:border-box;font-size:11px;font-weight:700;color:var(--primary);background:var(--bg);border-radius:8px;padding:6px 0;width:44px;flex-shrink:0;text-align:center;">{{ $code }}</span>
                        <span style="font-size:14px;font-weight:500;color:var(--dark);">{{ $city }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @error('homeCity') <p style="color:#e74c3c;font-size:12px;margin-top:6px;">{{ $message }}</p> @enderror

        <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin-top:14px;">
            <span style="font-size:13px;color:var(--muted);">Suggested cities:</span>
            @foreach($suggested as $city)
            <span x-on:click="selectCity('{{ addslashes($city) }}', '{{ addslashes(array_search($city, $localDestinations) ?: '') }}')"
                  style="display:inline-flex;align-items:center;padding:6px 14px;border-radius:20px;font-size:13px;font-weight:600;border:1.5px solid var(--border);background:var(--bg-white);color:var(--primary);cursor:pointer;transition:all .15s;"
                  onmouseenter="this.style.borderColor='var(--primary)'" onmouseleave="this.style.borderColor='var(--border)'">
                {{ $city }}
            </span>
            @endforeach
        </div>
    </div>

    <div style="flex:1 1 480px;min-width:340px;">
        <div x-ref="mapEl" x-init="$nextTick(() => initMap())" wire:ignore
             style="width:100%;height:520px;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);"></div>
    </div>
</div>

{{-- ── STEP 2: Budget ── --}}
@elseif($step === 2)
<div wire:key="step-2" style="width:100%;max-width:720px;padding:0 24px;">
    <div class="pb-icon-wrap" style="width:68px;height:68px;">
        <i class="fa-solid fa-wallet" style="font-size:30px;color:#fff;"></i>
    </div>
    <h1 class="pb-title" style="font-size:32px;">What is your preferred budget range?</h1>
    <p class="pb-sub" style="font-size:16px;">Enter the budget that best fits your travel style.</p>

    <div class="pb-label" style="font-size:12px;">Budget Level</div>
    <div class="pb-input-wrap" :class="{ 'is-bad': $store.pbBad.budget }" style="padding:20px 22px;" x-data="{ display: '{{ $dailyBudgetDisplay }}' }" x-init="$nextTick(() => { $el.querySelector('input').value = display; })">
        <i class="fa-solid fa-wallet" style="color:var(--muted);font-size:18px;flex-shrink:0;"></i>
        <input type="text" class="pb-input" style="font-size:19px;" placeholder="Please enter your desired budget"
               x-ref="budgetInput"
               :value="display"
               @input="
                   let raw = $event.target.value.replace(/[^\d]/g, '');
                   let fmt = raw ? Number(raw).toLocaleString('en-PH') : '';
                   $event.target.value = fmt;
                   display = fmt;
                   if (fmt) $store.pbBad.budget = false;
               "
               @change="
                   let raw = $event.target.value.replace(/[^\d]/g, '');
                   $wire.set('dailyBudget', raw ? Number(raw) : 0);
                   $wire.set('dailyBudgetDisplay', $event.target.value);
               ">
        <button type="button" class="pb-clear" title="Clear" x-show="display" x-cloak
                x-on:click.stop="display=''; $el.closest('.pb-input-wrap').querySelector('input').value='';
                                 $wire.set('dailyBudget', 0); $wire.set('dailyBudgetDisplay', '');
                                 $store.pbBad.budget = false">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <p style="font-size:14px;color:var(--muted);margin-top:10px;">This helps us calculate more accurate estimates for your trips.</p>
</div>

{{-- ── STEP 6: Review ── --}}
@elseif($step === 7)
<div wire:key="step-7" style="width:100%;max-width:640px;padding:0 24px;">
    <h1 style="font-size:18px;font-weight:800;color:var(--dark);margin:0 0 4px;">Review your profile</h1>
    <p style="font-size:13px;color:var(--muted);margin:0 0 20px;">Verify your travel preferences before we finalize your workspace. These settings will help us tailor your budgeting tools.</p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        {{-- Address --}}
        <div class="rv-card-sm">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div class="rv-icon-sm"><i class="fa-solid fa-location-dot"></i></div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Address</div>
                </div>
                <span class="rv-edit" wire:click="$set('step', 1)">Edit</span>
            </div>
            <div style="font-size:15px;font-weight:700;color:var(--dark);">{{ $homeCity ?: '—' }}</div>
        </div>

        {{-- Budget --}}
        <div class="rv-card-sm">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div class="rv-icon-sm"><i class="fa-solid fa-wallet"></i></div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Budget Preference</div>
                </div>
                <span class="rv-edit" wire:click="$set('step', 2)">Edit</span>
            </div>
            <div style="font-size:15px;font-weight:700;color:var(--dark);">{{ $dailyBudget ? $budgetSymbol.number_format($dailyBudget) : '—' }}</div>
            <div style="height:4px;border-radius:2px;background:var(--bg);margin-top:8px;overflow:hidden;">
                <div style="height:100%;width:{{ $dailyBudget ? min(100, round($dailyBudget / 3000 * 100)) : 0 }}%;background:var(--primary);"></div>
            </div>
        </div>

        {{-- Travel Style --}}
        <div class="rv-card-sm">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div class="rv-icon-sm"><i class="fa-solid {{ $travelStyles[$travelStyle]['icon'] ?? 'fa-user-group' }}"></i></div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Travel Style</div>
                </div>
                <span class="rv-edit" wire:click="$set('step', 3)">Edit</span>
            </div>
            <div style="font-size:15px;font-weight:700;color:var(--dark);">{{ $travelStyle ?: '—' }}</div>
            <div style="font-size:11px;color:var(--muted);">Cost-splitting & accommodation fit</div>
        </div>

        {{-- Transportation and Accommodation are separate steps now, so each
             gets its own card and its own Edit target. --}}
        <div class="rv-card-sm">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div class="rv-icon-sm"><i class="fa-solid fa-route"></i></div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Transportation</div>
                </div>
                <span class="rv-edit" wire:click="$set('step', 5)">Edit</span>
            </div>
            <div style="font-size:15px;font-weight:700;color:var(--dark);">{{ $preferredTransportation ?: '—' }}</div>
            <div style="font-size:11px;color:var(--muted);">How you'll travel</div>
        </div>

        <div class="rv-card-sm">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div class="rv-icon-sm"><i class="fa-solid fa-bed"></i></div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Accommodation</div>
                </div>
                <span class="rv-edit" wire:click="$set('step', 6)">Edit</span>
            </div>
            <div style="font-size:15px;font-weight:700;color:var(--dark);">{{ $preferredAccommodation ?: '—' }}</div>
            <div style="font-size:11px;color:var(--muted);">Where you'll stay</div>
        </div>
    </div>

    {{-- Travel Interests --}}
    <div class="rv-card-sm" style="margin-bottom:20px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <div class="rv-icon-sm"><i class="fa-solid fa-heart"></i></div>
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Travel Interests</div>
            </div>
            <span class="rv-edit" wire:click="$set('step', 4)">Edit</span>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            @forelse($selectedInterests as $interest)
            @php
                $subsForInterest = array_values(array_intersect($interests[$interest] ?? [], $selectedSubInterests));
                $tagLabel = $interest . ($subsForInterest ? ' • ' . implode(', ', $subsForInterest) : '');
            @endphp
            <span style="display:inline-flex;align-items:center;gap:6px;background:var(--bg);color:var(--dark);font-size:12px;font-weight:600;padding:6px 12px;border-radius:20px;">
                <i class="fa-solid {{ $icons[$interest] ?? 'fa-star' }}" style="color:var(--primary);font-size:11px;"></i>
                {{ $tagLabel }}
            </span>
            @empty
            <span style="font-size:13px;color:var(--muted);">No interests selected.</span>
            @endforelse
        </div>
    </div>

</div>
@endif

{{-- ── STEP 3: Travel Style (always rendered, CSS-toggled — required so wire:ignore hydrates correctly on first arrival) ── --}}
<div wire:key="step-3" style="width:100%;padding:0 24px;max-width:1100px;{{ $step === 3 ? '' : 'display:none;' }}">
    <h1 style="font-size:22px;font-weight:800;color:var(--dark);text-align:center;margin:0 0 8px;">Who are you traveling with?</h1>
    <p style="font-size:13px;color:var(--muted);text-align:center;margin:0 0 28px;">This helps us customize budget suggestions, accommodation types, and cost-splitting calculations for your upcoming journeys.</p>

    <div class="int-scroll" x-data="{ style: '{{ $travelStyle }}' }" style="display:flex;gap:20px;justify-content:center;align-items:stretch;overflow-x:auto;padding-bottom:6px;">
        @foreach($travelStyles as $name => $meta)
        <div class="int-card-img"
             :class="{ 'active': style === '{{ $name }}', 'is-bad': $store.pbBad.style }"
             style="flex:0 0 340px;height:420px;"
             x-on:click="style = (style === '{{ $name }}' ? '' : '{{ $name }}'); $store.pbBad.style = false"
             wire:click="selectTravelStyle('{{ $name }}')"
             wire:ignore>
            <div class="int-card-img-bg" style="background-image:url('{{ asset('stockimages/' . $meta['image']) }}');">
                <div class="int-icon-img" style="width:72px;height:72px;">
                    <i class="fa-solid {{ $meta['icon'] }}" style="font-size:30px;color:var(--primary);"></i>
                </div>
                <div class="int-label-img" style="font-size:22px;margin-bottom:8px;">{{ $name }}</div>
                <div style="position:relative;z-index:1;font-size:14px;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,0.4);padding:0 28px;">{{ $meta['desc'] }}</div>
            </div>
            <div class="int-check"><i class="fa-solid fa-check"></i></div>
        </div>

        @if($name === 'Group')
        <div class="int-side-panel-pop" style="flex:0 0 320px;" x-show="style === 'Group'" x-cloak
             x-transition:enter="pop-enter" x-transition:enter-start="pop-enter-start" x-transition:enter-end="pop-enter-end"
             x-transition:leave="pop-leave" x-transition:leave-start="pop-leave-start" x-transition:leave-end="pop-leave-end"
             wire:key="group-members-panel">
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:10px;">
                Group Members
            </div>

            @foreach($memberEmailInputs as $i => $value)
            <div style="display:flex;gap:8px;{{ $i > 0 ? 'margin-top:10px;' : '' }}" wire:key="member-row-{{ $i }}">
                <div class="pb-input-wrap" :class="{ 'is-bad': $store.pbBad.members }" style="flex:1;padding:10px 14px;" x-on:input="$store.pbBad.members = false">
                    <i class="fa-solid fa-envelope" style="color:var(--muted);font-size:12px;flex-shrink:0;"></i>
                    <input type="email" wire:model="memberEmailInputs.{{ $i }}" wire:keydown.enter.prevent="addGroupMember({{ $i }})"
                           class="pb-input" style="font-size:13px;" placeholder="Enter member's email address">
                </div>
                <button type="button" class="pb-btn pb-btn-ghost" wire:click="addGroupMember({{ $i }})" style="flex-shrink:0;padding:10px 14px;">
                    <i class="fa-solid fa-plus" style="font-size:12px;color:#27ae60;"></i>
                </button>
                @if(count($memberEmailInputs) > 1)
                <button type="button" class="pb-btn pb-btn-ghost" wire:click="removeMemberRow({{ $i }})" style="flex-shrink:0;padding:10px 14px;">
                    <i class="fa-solid fa-trash" style="font-size:12px;color:#e74c3c;"></i>
                </button>
                @endif
            </div>
            @error("memberEmailInputs.$i") <p style="color:#e74c3c;font-size:12px;margin-top:6px;">{{ $message }}</p> @enderror
            @endforeach

            <button type="button" class="pb-btn pb-btn-ghost" style="margin-top:10px;width:100%;justify-content:center;" wire:click="addMemberRow">
                <i class="fa-solid fa-plus" style="font-size:11px;"></i> Add more people
            </button>

            @if(count($groupMemberEmails))
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;">
                @foreach($groupMemberEmails as $email)
                <span class="sub-chip active" style="display:inline-flex;align-items:center;gap:8px;">
                    {{ $email }}
                    <i class="fa-solid fa-xmark" style="cursor:pointer;font-size:11px;" wire:click="removeGroupMember('{{ $email }}')"></i>
                </span>
                @endforeach
            </div>
            @endif
        </div>
        @endif
        @endforeach
    </div>
</div>

{{-- ── STEP 5: Transportation & Accommodation (always rendered, CSS-toggled — required so wire:ignore hydrates correctly on first arrival) ── --}}
<div wire:key="step-5"
     x-data="{
        transport: '{{ $preferredTransportation }}',
        stay: '{{ $preferredAccommodation }}'
     }"
     style="width:100%;padding:0 24px;{{ in_array($step, [5, 6]) ? '' : 'display:none;' }}">
    {{-- Transport and stay used to share one screen. They're now a step each,
         so this wrapper keeps the single x-data (transport/stay) both halves
         bind to while showing only the half that belongs to the current step. --}}
    <div style="{{ $step === 5 ? '' : 'display:none;' }}">
    <h1 style="font-size:22px;font-weight:800;color:var(--dark);text-align:center;margin:0 0 8px;">How do you like to get around?</h1>
    <p style="font-size:13px;color:var(--muted);text-align:center;margin:0 0 28px;">Select your preferred transportation type.</p>

    <div class="pb-label" style="text-align:center;">Preferred Transportation</div>
    <div class="int-scroll" style="display:flex;gap:16px;align-items:stretch;justify-content:center;flex-wrap:nowrap;overflow-x:auto;padding-bottom:16px;margin-bottom:24px;">
        @foreach($transportationOptions as $name => $icon)
        <div class="int-card-img"
             :class="{ 'active': transport === '{{ $name }}', 'is-bad': $store.pbBad.transportation }"
             style="flex:0 0 220px;height:220px;"
             x-on:click="transport = (transport === '{{ $name }}' ? '' : '{{ $name }}'); $store.pbBad.transportation = false"
             wire:click="selectTransportation('{{ $name }}')"
             wire:ignore>
            <div class="int-card-img-bg" style="background-image:url('{{ asset('stockimages/' . ($transportationImages[$name] ?? '')) }}');">
                <div class="int-icon-img" style="width:56px;height:56px;">
                    <i class="fa-solid {{ $icon }}" style="font-size:24px;color:var(--primary);"></i>
                </div>
                <div class="int-label-img" style="font-size:16px;">{{ $name }}</div>
            </div>
            <div class="int-check"><i class="fa-solid fa-check"></i></div>
        </div>
        @endforeach
    </div>

    </div>

    <div style="{{ $step === 6 ? '' : 'display:none;' }}">
    <h1 style="font-size:22px;font-weight:800;color:var(--dark);text-align:center;margin:0 0 8px;">Where do you like to stay?</h1>
    <p style="font-size:13px;color:var(--muted);text-align:center;margin:0 0 28px;">Select your preferred accommodation type.</p>

    <div class="pb-label" style="text-align:center;">Preferred Accommodation</div>
    <div class="int-scroll" style="display:flex;gap:16px;align-items:stretch;justify-content:center;flex-wrap:nowrap;overflow-x:auto;padding-bottom:16px;">
        @foreach($accommodationOptions as $name => $icon)
        <div class="int-card-img"
             :class="{ 'active': stay === '{{ $name }}', 'is-bad': $store.pbBad.accommodation }"
             style="flex:0 0 220px;height:220px;"
             x-on:click="stay = (stay === '{{ $name }}' ? '' : '{{ $name }}'); $store.pbBad.accommodation = false"
             wire:click="selectAccommodation('{{ $name }}')"
             wire:ignore>
            <div class="int-card-img-bg" style="background-image:url('{{ asset('stockimages/' . ($accommodationImages[$name] ?? '')) }}');">
                <div class="int-icon-img" style="width:56px;height:56px;">
                    <i class="fa-solid {{ $icon }}" style="font-size:24px;color:var(--primary);"></i>
                </div>
                <div class="int-label-img" style="font-size:16px;">{{ $name }}</div>
            </div>
            <div class="int-check"><i class="fa-solid fa-check"></i></div>
        </div>
        @endforeach
    </div>
    </div>
</div>

{{-- ── STEP 4: Interests (always rendered, CSS-toggled — required so wire:ignore hydrates correctly on first arrival) ── --}}
<div wire:key="step-4" style="width:100%;padding:0 24px;{{ $step === 4 ? '' : 'display:none;' }}">
    <h1 style="font-size:22px;font-weight:800;color:var(--dark);text-align:center;margin:0 0 8px;">What do you enjoy doing?</h1>
    <p style="font-size:13px;color:var(--muted);text-align:center;margin:0 0 28px;">Select all travel interests that apply to your exploration style.</p>

    <div class="int-scroll"
         x-data="{
            expanded: '{{ $expandedInterest }}',
            selected: @js($selectedInterests),
            subs: @js($selectedSubInterests),
            interestSubs: @js($interests),
            toggleInterest(name) {
                this.expanded = (this.expanded === name ? '' : name);
                const i = this.selected.indexOf(name);
                if (i === -1) {
                    this.selected.push(name);
                } else {
                    this.selected.splice(i, 1);
                    const subsForName = this.interestSubs[name] || [];
                    this.subs = this.subs.filter(s => !subsForName.includes(s));
                }
                $wire.toggleInterest(name);
            },
            toggleSub(name, sub) {
                const i = this.subs.indexOf(sub);
                if (i === -1) { this.subs.push(sub); } else { this.subs.splice(i, 1); }
                $wire.toggleSubInterest(sub);

                const anySelected = (this.interestSubs[name] || []).some(s => this.subs.includes(s));
                const isSelected = this.selected.includes(name);
                if (anySelected && !isSelected) {
                    this.selected.push(name);
                    $wire.toggleInterest(name);
                } else if (!anySelected && isSelected) {
                    this.selected.splice(this.selected.indexOf(name), 1);
                    $wire.toggleInterest(name);
                }
            }
         }"
         wire:ignore
         style="display:flex;gap:16px;align-items:stretch;flex-wrap:nowrap;overflow-x:auto;padding-bottom:16px;">
        @foreach($interests as $name => $subs)
        <div class="int-card-img"
             :class="{ 'active': selected.includes('{{ $name }}'), 'is-bad': $store.pbBad.interests }"
             style="flex:0 0 260px;height:300px;"
             x-on:click="toggleInterest('{{ $name }}'); $store.pbBad.interests = false">
            <div class="int-card-img-bg" style="background-image:url('{{ asset('stockimages/' . ($images[$name] ?? '')) }}');">
                <div class="int-icon-img" style="width:64px;height:64px;">
                    <i class="fa-solid {{ $icons[$name] ?? 'fa-star' }}" style="font-size:28px;color:var(--primary);"></i>
                </div>
                <div class="int-label-img" style="font-size:18px;">{{ $name }}</div>
            </div>
            <div class="int-check"><i class="fa-solid fa-check"></i></div>
        </div>

        @if(isset($interests[$name]))
        <div class="int-side-panel-pop" x-show="expanded === '{{ $name }}'" x-cloak
             x-transition:enter="pop-enter" x-transition:enter-start="pop-enter-start" x-transition:enter-end="pop-enter-end"
             x-transition:leave="pop-leave" x-transition:leave-start="pop-leave-start" x-transition:leave-end="pop-leave-end"
             wire:key="sub-panel-{{ $name }}">
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:8px;">
                {{ strtoupper($name) }}
            </div>
            <div class="sub-list">
                @foreach($interests[$name] as $sub)
                <div class="sub-list-item" :class="subs.includes('{{ $sub }}') ? 'active' : ''"
                     x-on:click="toggleSub('{{ $name }}', '{{ $sub }}')">
                    <span class="sub-check" x-show="subs.includes('{{ $sub }}')" x-cloak><i class="fa-solid fa-check"></i></span>
                    <span class="sub-check" x-show="!subs.includes('{{ $sub }}')" x-cloak></span>
                    <span>{{ $sub }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
        @endforeach
    </div>
</div>

{{-- Navigation --}}
<div class="pb-nav" style="max-width:{{ in_array($step, [4, 5, 6]) ? '100%' : ($step === 3 ? '1100px' : ($step === 1 ? '1280px' : ($step === 2 ? '720px' : ($step === 7 ? '640px' : '480px')))) }};{{ in_array($step, [1, 2, 3, 4, 5, 6, 7]) ? ' padding:0 24px;' : '' }}">
    @if($step > 1 && !$returnTo)
    <button class="pb-btn pb-btn-ghost" wire:click="prevStep">
        <i class="fa-solid fa-arrow-left" style="font-size:11px;"></i> Back
    </button>
    @else
    <div></div>
    @endif

    @if($returnTo)
    <button class="pb-btn pb-btn-primary" wire:click="saveAndReturn" wire:loading.attr="disabled">
        <span wire:loading.remove wire:target="saveAndReturn"><i class="fa-solid fa-check" style="font-size:11px;"></i> Save Changes</span>
        <span wire:loading wire:target="saveAndReturn"><i class="fa-solid fa-spinner fa-spin"></i></span>
    </button>
    @elseif($step < 7)
    <button class="pb-btn pb-btn-primary" wire:click="nextStep">
        Next Step <i class="fa-solid fa-arrow-right" style="font-size:11px;"></i>
    </button>
    @else
    <button class="pb-btn pb-btn-primary" wire:click="confirmProfile" wire:loading.attr="disabled">
        <span wire:loading.remove wire:target="confirmProfile">Confirm Profile</span>
        <span wire:loading wire:target="confirmProfile"><i class="fa-solid fa-spinner fa-spin"></i></span>
    </button>
    @endif
</div>


</div>

</div>

{{-- Required-field flags live in an Alpine STORE rather than x-data on this
     component's root: Livewire re-renders that root on every step change and
     would re-initialise (and silently wipe) any x-data sitting on it. A store
     lives outside the DOM, so morphs can't touch it. --}}
@script
<script>
    const PB_BAD_KEYS = ['home','budget','style','members','interests','transportation','accommodation'];

    Alpine.store('pbBad', Object.assign(
        Object.fromEntries(PB_BAD_KEYS.map(k => [k, false])),
        {
            clear() { PB_BAD_KEYS.forEach(k => this[k] = false); },
            flag(keys) {
                // Drop then re-set on the next frame, otherwise clicking Next
                // again on an already-red field wouldn't restart the shake.
                this.clear();
                requestAnimationFrame(() => (keys || []).forEach(k => {
                    if (PB_BAD_KEYS.includes(k)) this[k] = true;
                }));
            },
        }
    ));

    window.addEventListener('profile-missing', e => Alpine.store('pbBad').flag(e.detail?.fields));
</script>
@endscript
