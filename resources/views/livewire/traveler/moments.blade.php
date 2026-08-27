{{--
    Moments tab body: destination overview map, single-trip travel-pin map,
    and the pin add/edit/delete modals.

    @include()'d from itinerary-manager.blade.php only when $tab === 'moments'
    (and only once trips exist). Blade's @include shares the caller's
    variable scope, so $this (bound by Livewire to the ItineraryManager
    component) and $tripLabel (defined in the parent) are both available
    here without being passed explicitly.
--}}

@if ($momentsMode === 'overview')
{{-- Destination overview map: trip-status pins (visual only, not clickable)
     plus separate, clickable memory markers for every posted Moment. Click
     anywhere else on the map to post a new Moment there.

     Legend and hint float directly over the map (glassmorphism chips) rather
     than stacking above it, so the map itself gets almost the whole card. --}}
<style>
    .moments-overview-shell { position: relative; border-radius: 20px; overflow: hidden; box-shadow: 0 6px 28px rgba(45,27,20,.10); }
    .moments-overview-map-el { width: 100%; height: 560px; }
    @media (max-width: 900px) { .moments-overview-map-el { height: 480px; } }
    @media (max-width: 560px) { .moments-overview-map-el { height: 400px; } }
    .timeline-entry .timeline-card { transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
    .timeline-entry:hover .timeline-card { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(45,27,20,.10); border-color: #934B19; }
    {{-- Timeline rail: flat, minimal line + dot — no imagery, hierarchy
         comes purely from size/color/spacing (bigger + darker on hover). --}}
    .moments-rail-line { position: absolute; left: 8px; top: 4px; bottom: 4px; width: 2px; border-radius: 2px; background: #E8DDD2; }
    .moments-rail-marker {
        position: absolute; left: -25px; top: 20px; width: 12px; height: 12px; border-radius: 50%;
        background: #C8874A; transition: transform .15s ease, background .15s ease;
    }
    .timeline-entry:hover .moments-rail-marker { transform: scale(1.3); background: #934B19; }
    .moments-float-chip {
        position: absolute; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
        border-radius: 14px; box-shadow: 0 6px 20px rgba(0,0,0,.14); max-width: calc(100% - 32px);
    }
    .moments-float-legend {
        top: 16px; left: 16px; background: rgba(255,255,255,.82);
        padding: 10px 16px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    }
    .moments-float-hint {
        bottom: 16px; left: 16px; background: rgba(28,20,15,.80); color: #fff;
        padding: 10px 16px; font-size: 12px; display: flex; align-items: center; gap: 8px;
    }
    .moments-segmented {
        display: inline-flex; background: var(--bg, #F8F5F2); border: 1px solid var(--border, #E5E7EB);
        border-radius: 999px; padding: 4px; gap: 2px; margin-bottom: 10px;
    }
    .moments-segment {
        display: inline-flex; align-items: center; gap: 7px; border: none; background: transparent;
        color: var(--muted); font-size: 13px; font-weight: 700; padding: 8px 18px; border-radius: 999px;
        cursor: pointer; transition: background .2s ease, color .2s ease, box-shadow .2s ease;
    }
    .moments-segment.is-active { background: var(--bg-white); color: var(--primary); box-shadow: 0 2px 8px rgba(45,27,20,.10); }
    .moments-segment:not(.is-active):hover { color: var(--dark); }
</style>

{{-- Map View / Timeline View toggle for the overview page itself — a
     separate Alpine store from the per-trip toggle below, so the two never
     interfere with each other. --}}
<div x-data class="moments-segmented">
    <button type="button" class="moments-segment" :class="{ 'is-active': $store.overviewMoments.view === 'map' }"
            @click="$store.overviewMoments.view = 'map'">
        <i class="fa-solid fa-map-location-dot"></i> Map View
    </button>
    <button type="button" class="moments-segment" :class="{ 'is-active': $store.overviewMoments.view === 'timeline' }"
            @click="$store.overviewMoments.view = 'timeline'">
        <i class="fa-solid fa-timeline"></i> Timeline View
    </button>
</div>

<div x-data>
    <div x-show="$store.overviewMoments.view === 'map'" x-transition.opacity.duration.200ms>
        <div class="moments-overview-shell">
            <div
                class="moments-overview-map-el"
                wire:key="moments-overview-map"
                wire:ignore
                x-data
                x-init="initOverviewMap($el, $wire, {{ json_encode($this->overviewPins) }}, {{ json_encode($this->allMomentPins) }})"
            ></div>

            <div class="moments-float-chip moments-float-legend">
                <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:#1A1A2E;font-weight:600;"><span style="width:8px;height:8px;border-radius:50%;background:#22C55E;display:inline-block;"></span> Ongoing</span>
                <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:#1A1A2E;font-weight:600;"><span style="width:8px;height:8px;border-radius:50%;background:#3B82F6;display:inline-block;"></span> Upcoming</span>
                <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:#1A1A2E;font-weight:600;"><span style="width:8px;height:8px;border-radius:50%;background:#6B7280;display:inline-block;"></span> Completed</span>
                <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:#1A1A2E;font-weight:600;"><i class="fa-solid fa-camera" style="font-size:9px;color:var(--primary);"></i> Memory</span>
            </div>

            <div class="moments-float-chip moments-float-hint">
                <i class="fa-solid fa-circle-info" style="color:#F5C97A;"></i>
                @if ($this->hasOngoingTrip)
                    Click anywhere to post a Moment
                @else
                    No ongoing trips yet — moments can be added once a trip starts
                @endif
            </div>

            @if ($momentBlockedMessage)
            <div class="moments-float-chip" wire:key="moment-blocked-overview"
                 style="top:50%;left:50%;transform:translate(-50%,-50%);background:rgba(185,28,28,.92);color:#fff;padding:10px 16px;font-size:12px;display:flex;align-items:center;gap:8px;text-align:center;"
                 x-data x-init="setTimeout(() => $wire.set('momentBlockedMessage', ''), 3500)">
                <i class="fa-solid fa-triangle-exclamation"></i> {{ $momentBlockedMessage }}
            </div>
            @endif
        </div>
    </div>

    {{-- All-trips Travel Story Timeline --}}
    <div x-show="$store.overviewMoments.view === 'timeline'" x-transition.opacity.duration.200ms x-cloak>
        @php $overviewTimelineGrouped = collect($this->allMomentsTimeline)->groupBy('trip_destination'); @endphp
        <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:20px;padding:20px 24px;box-shadow:0 6px 28px rgba(45,27,20,.10);max-height:640px;overflow-y:auto;">
            @if ($overviewTimelineGrouped->isEmpty())
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:56px 24px;text-align:center;">
                <div style="width:64px;height:64px;border-radius:16px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:20px;">
                    <i class="fa-solid fa-book-open-reader" style="font-size:26px;color:var(--primary);"></i>
                </div>
                <div style="font-size:16px;font-weight:700;color:var(--dark);margin-bottom:8px;">Your travel diary starts here</div>
                <div style="font-size:13px;color:var(--muted);max-width:320px;line-height:1.6;margin-bottom:18px;">
                    Switch to Map View and click anywhere to post your first Moment — it'll show up here as a timeline entry.
                </div>
                <button type="button" @click="$store.overviewMoments.view = 'map'" class="moments-btn-primary"
                        style="background:var(--primary);color:#fff;border:none;border-radius:10px;padding:10px 20px;font-size:13px;font-weight:700;cursor:pointer;">
                    <i class="fa-solid fa-map-location-dot"></i> Go to Map View
                </button>
            </div>
            @else
            @foreach ($overviewTimelineGrouped as $destination => $destMoments)
            <div style="margin-bottom:28px;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;padding-bottom:8px;border-bottom:1.5px solid var(--border-light);">
                    <div style="font-size:14px;font-weight:800;color:var(--primary);text-transform:uppercase;letter-spacing:.04em;">
                        <i class="fa-solid fa-plane"></i> {{ $destination }}
                    </div>
                </div>
                <div style="position:relative;padding-left:28px;">
                    <div class="moments-rail-line"></div>
                    @foreach ($destMoments as $moment)
                    <div class="timeline-entry" style="position:relative;margin-bottom:20px;">
                        <div class="moments-rail-marker"></div>
                        <div class="timeline-card" style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;padding:16px;">
                            <div class="timeline-card-inner" style="display:flex;gap:12px;">
                                @if (count($moment['photo_urls']))
                                <img src="{{ $moment['photo_urls'][0] }}" alt="{{ $moment['place_name'] }}"
                                     style="width:76px;height:76px;border-radius:10px;object-fit:cover;flex-shrink:0;">
                                @else
                                <div style="width:76px;height:76px;border-radius:10px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fa-solid fa-camera" style="color:var(--primary);font-size:20px;"></i>
                                </div>
                                @endif
                                <div style="flex:1;min-width:0;">
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:2px;">
                                        <span style="font-size:14px;font-weight:700;color:var(--dark);">{{ $moment['place_name'] }}</span>
                                        <span style="font-size:10px;font-weight:700;color:var(--primary);background:var(--primary-light);padding:2px 8px;border-radius:20px;">{{ $moment['day_number'] < 1 ? 'Pre-Trip' : 'Day ' . $moment['day_number'] }}</span>
                                    </div>
                                    @if ($moment['description'])
                                    <div style="font-size:12.5px;color:var(--text);line-height:1.5;margin-bottom:6px;">{{ $moment['description'] }}</div>
                                    @endif
                                    <div style="font-size:11px;color:var(--muted);">
                                        <i class="fa-regular fa-clock" style="font-size:10px;"></i> Posted {{ $moment['posted_at'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
            @endif
        </div>
    </div>
</div>

@else
<button wire:click="backToOverview" type="button" class="moments-back-btn"
        style="display:flex;align-items:center;gap:8px;background:none;border:none;padding:0 0 16px;font-size:13px;font-weight:600;color:var(--primary);cursor:pointer;">
    <i class="fa-solid fa-arrow-left" style="font-size:11px;"></i> All Destinations
</button>

@if ($selectedTripId && $this->selectedTrip)
@php
    $trip      = $this->selectedTrip;
    $tripStart = $trip->start_date->copy()->startOfDay();
    $tripEnd   = $trip->end_date->copy()->startOfDay();
    $mc        = $this->mapCenter;
    $timelineGrouped = collect($this->timelineMoments)->groupBy('day_number');

    // Show every day of the trip in order — Day 1 through the last day —
    // not just the days a moment happens to exist for, so the timeline
    // reads as the trip's full itinerary with gaps visible rather than
    // silently skipping days nothing was logged for. Moments logged before
    // day 1 (negative/zero day_number) or after the trip's last day still
    // get their own group, just placed before/after the numbered range.
    $totalDays    = (int) $tripStart->diffInDays($tripEnd) + 1;
    $preTripDays  = $timelineGrouped->keys()->filter(fn ($d) => $d < 1)->sort();
    $postTripDays = $timelineGrouped->keys()->filter(fn ($d) => $d > $totalDays)->sort();
    $orderedDays  = $preTripDays->concat(range(1, $totalDays))->concat($postTripDays);
    $timelineDisplay = $orderedDays->mapWithKeys(fn ($d) => [$d => $timelineGrouped->get($d, collect())]);
@endphp

<style>
    .timeline-entry .timeline-card { transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
    .timeline-entry:hover .timeline-card { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(45,27,20,.10); border-color: var(--primary); }
    .timeline-entry.is-highlighted .timeline-card { animation: timelinePulse 1.4s ease; border-color: var(--primary); }
    {{-- Timeline rail: flat, minimal line + dot — no imagery, hierarchy
         comes purely from size/color/spacing. Day waypoints get a bigger
         dot than individual moments so "start of a new day" reads at a
         glance without needing a label inside the marker itself. --}}
    .moments-rail-line { position: absolute; left: 8px; top: 4px; bottom: 4px; width: 2px; border-radius: 2px; background: #E8DDD2; }
    .moments-rail-marker {
        position: absolute; left: -25px; top: 20px; width: 12px; height: 12px; border-radius: 50%;
        background: #C8874A; transition: transform .15s ease, background .15s ease;
    }
    .timeline-entry:hover .moments-rail-marker { transform: scale(1.3); background: #934B19; }
    .moments-rail-day-marker { position: absolute; left: -27px; top: 3px; width: 16px; height: 16px; border-radius: 50%; background: var(--primary); }
    @keyframes timelinePulse {
        0%   { box-shadow: 0 0 0 0 rgba(147,75,25,.45); }
        70%  { box-shadow: 0 0 0 12px rgba(147,75,25,0); }
        100% { box-shadow: 0 0 0 0 rgba(147,75,25,0); }
    }
    .marker-pulse { animation: markerPulse 1.4s ease; }
    @keyframes markerPulse {
        0%   { filter: drop-shadow(0 0 2px rgba(147,75,25,.7)); transform: scale(1); }
        50%  { transform: scale(1.3); }
        100% { filter: drop-shadow(0 0 0 rgba(147,75,25,0)); transform: scale(1); }
    }
    .moments-segmented {
        display: inline-flex; background: var(--bg, #F8F5F2); border: 1px solid var(--border, #E5E7EB);
        border-radius: 999px; padding: 4px; gap: 2px; margin-bottom: 10px;
    }
    .moments-segment {
        display: inline-flex; align-items: center; gap: 7px; border: none; background: transparent;
        color: var(--muted); font-size: 13px; font-weight: 700; padding: 8px 18px; border-radius: 999px;
        cursor: pointer; transition: background .2s ease, color .2s ease, box-shadow .2s ease;
    }
    .moments-segment.is-active { background: var(--bg-white); color: var(--primary); box-shadow: 0 2px 8px rgba(45,27,20,.10); }
    .moments-segment:not(.is-active):hover { color: var(--dark); }
    @media (max-width: 640px) {
        .timeline-card-inner { flex-direction: column; }
    }
</style>

{{-- Map / Timeline toggle — client-side only (a global Alpine store), so
     switching views never round-trips through Livewire and the map (which
     is wire:ignore'd) is never torn down or reinitialized. --}}
<div x-data class="moments-segmented">
    <button type="button" class="moments-segment" :class="{ 'is-active': $store.moments.view === 'map' }"
            @click="$store.moments.view = 'map'">
        <i class="fa-solid fa-map-location-dot"></i> Map View
    </button>
    <button type="button" class="moments-segment" :class="{ 'is-active': $store.moments.view === 'timeline' }"
            @click="$store.moments.view = 'timeline'">
        <i class="fa-solid fa-timeline"></i> Timeline View
    </button>
</div>

<div x-data>
    {{-- Single trip's personal travel-pin map — unchanged, just now shown/hidden
         via Alpine instead of always rendered. --}}
    <div x-show="$store.moments.view === 'map'" x-transition.opacity.duration.200ms>
        <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;padding:16px;box-shadow:0 2px 10px rgba(0,0,0,.04);">
            <p style="margin:0 0 12px;font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px;">
                <i class="fa-solid fa-circle-info" style="color:#C8874A;"></i>
                @if ($this->selectedTrip && $this->selectedTrip->resolved_status === 'active')
                    Click anywhere on the map to drop a pin for a place you visited.
                @else
                    Moments can only be added once this trip is ongoing.
                @endif
            </p>

            @if ($momentBlockedMessage)
            <div class="alert alert-danger" wire:key="moment-blocked-trip" style="margin-bottom:12px;text-align:center;"
                 x-data x-init="setTimeout(() => $wire.set('momentBlockedMessage', ''), 3500)">
                <i class="fa-solid fa-triangle-exclamation"></i> {{ $momentBlockedMessage }}
            </div>
            @endif
            <div
                wire:key="moments-map-{{ $selectedTripId }}"
                wire:ignore
                x-data
                x-init="initMomentsMap($el, $wire, {{ $mc['lat'] }}, {{ $mc['lng'] }}, {{ $mc['zoom'] }}, {{ json_encode($tripLabel($trip) . ' · ' . $tripStart->format('M j') . '–' . $tripEnd->format('M j, Y')) }}, {{ json_encode($this->initialPins) }})"
                style="width:100%;height:440px;border-radius:12px;overflow:hidden;"
            ></div>
        </div>
    </div>

    {{-- Travel Story Timeline --}}
    <div x-show="$store.moments.view === 'timeline'" x-transition.opacity.duration.200ms x-cloak>
        <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;padding:20px 24px;box-shadow:0 2px 10px rgba(0,0,0,.04);max-height:620px;overflow-y:auto;">
            @if ($timelineGrouped->isEmpty())
            {{-- Empty state --}}
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:56px 24px;text-align:center;">
                <div style="width:64px;height:64px;border-radius:16px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:20px;">
                    <i class="fa-solid fa-book-open-reader" style="font-size:26px;color:var(--primary);"></i>
                </div>
                <div style="font-size:16px;font-weight:700;color:var(--dark);margin-bottom:8px;">Your travel diary starts here</div>
                <div style="font-size:13px;color:var(--muted);max-width:320px;line-height:1.6;margin-bottom:18px;">
                    Switch to Map View and click anywhere to post your first Moment — it'll show up here as a timeline entry.
                </div>
                <button type="button" @click="$store.moments.view = 'map'" class="moments-btn-primary"
                        style="background:var(--primary);color:#fff;border:none;border-radius:10px;padding:10px 20px;font-size:13px;font-weight:700;cursor:pointer;">
                    <i class="fa-solid fa-map-location-dot"></i> Go to Map View
                </button>
            </div>
            @else
            <div style="position:relative;padding-left:28px;">
                {{-- Connecting vertical line --}}
                <div class="moments-rail-line"></div>

                @foreach ($timelineDisplay as $dayNumber => $dayMoments)
                @php
                    // Days with no moments yet have nothing to read a date
                    // from, so fall back to computing it straight from the
                    // trip's start date instead of $dayMoments->first().
                    $dayDate = $dayMoments->isNotEmpty()
                        ? \Carbon\Carbon::parse($dayMoments->first()['visited_date'])
                        : $tripStart->copy()->addDays($dayNumber - 1);
                @endphp
                <div style="margin-bottom:28px;">
                    <div style="position:relative;margin-bottom:14px;">
                        <div class="moments-rail-day-marker"></div>
                        <div style="font-size:13px;font-weight:800;color:var(--primary);text-transform:uppercase;letter-spacing:.04em;">
                            {{ $dayNumber < 1 ? 'Pre-Trip' : 'Day ' . $dayNumber }}
                            <span style="font-weight:500;color:var(--muted);text-transform:none;">— {{ $dayDate->format('l, M j') }}</span>
                        </div>
                    </div>

                    @if ($dayMoments->isEmpty())
                    <div style="margin-left:2px;padding:10px 14px;border:1.5px dashed var(--border);border-radius:12px;font-size:12px;color:var(--muted);">
                        No moments logged for this day.
                    </div>
                    @else
                    @foreach ($dayMoments as $moment)
                    <div id="moment-{{ $moment['id'] }}" class="timeline-entry" tabindex="0"
                         style="position:relative;margin-bottom:20px;cursor:pointer;"
                         onclick="focusMapOnMoment({{ $moment['id'] }})">
                        <div class="moments-rail-marker"></div>
                        <div class="timeline-card" style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;padding:16px;">
                            <div class="timeline-card-inner" style="display:flex;gap:12px;">
                                @if (count($moment['photo_urls']))
                                <img src="{{ $moment['photo_urls'][0] }}" alt="{{ $moment['place_name'] }}"
                                     style="width:76px;height:76px;border-radius:10px;object-fit:cover;flex-shrink:0;">
                                @else
                                <div style="width:76px;height:76px;border-radius:10px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fa-solid fa-camera" style="color:var(--primary);font-size:20px;"></i>
                                </div>
                                @endif
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:14px;font-weight:700;color:var(--dark);margin-bottom:2px;">{{ $moment['place_name'] }}</div>
                                    @if ($moment['description'])
                                    <div style="font-size:12.5px;color:var(--text);line-height:1.5;margin-bottom:6px;">{{ $moment['description'] }}</div>
                                    @endif
                                    <div style="font-size:11px;color:var(--muted);">
                                        <i class="fa-regular fa-clock" style="font-size:10px;"></i> Posted {{ $moment['posted_at'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                    @endif
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
@endif
@endif

{{-- Shared modal entrance animation — a plain CSS keyframe rather than an
     Alpine x-transition, since these modals are gated by a Blade @if (a
     real DOM insert on each open), not an Alpine x-show, so a CSS
     animation on mount is what actually replays it every time. --}}
<style>
    @keyframes momentsModalBackdropIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes momentsModalCardIn { from { opacity: 0; transform: scale(.96) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
    .moments-modal-backdrop { animation: momentsModalBackdropIn .18s ease both; }
    .moments-modal-card { animation: momentsModalCardIn .22s cubic-bezier(.2,.9,.3,1.1) both; }
    .moments-input { transition: border-color .15s ease, box-shadow .15s ease; }
    .moments-input::placeholder { font-weight: 400; color: var(--muted); }
    .moments-input:focus { outline: none; border-color: var(--primary) !important; box-shadow: 0 0 0 3px rgba(147,75,25,.12); }
    .moments-btn-primary { transition: background .15s ease, transform .12s ease, box-shadow .15s ease; }
    .moments-btn-primary:hover:not(:disabled) { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(147,75,25,.25); }
    .moments-btn-outline { transition: background .15s ease, border-color .15s ease, color .15s ease; }
    .moments-btn-outline:hover:not(:disabled) { background: var(--border-light); border-color: var(--muted); color: var(--dark); }
    .moments-btn-danger { transition: background .15s ease, transform .12s ease, box-shadow .15s ease; }
    .moments-btn-danger:hover:not(:disabled) { background: #B91C1C; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(220,38,38,.25); }
    /* Layout lives here, not in an inline style attribute: the drag-over
       state used an Alpine :style string binding, and Alpine sets the whole
       style attribute for string values — so the moment it evaluated to ''
       (not dragging, i.e. always) it wiped the dashed border, padding and
       centering along with it. :class can't clobber anything. */
    .moments-photo-dropzone {
        width: 100%; box-sizing: border-box;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 12px; text-align: center;
        border: 1.5px dashed var(--border); border-radius: 14px; padding: 28px 16px;
        cursor: pointer; transition: border-color .15s ease, background .15s ease;
    }
    .moments-photo-dropzone:hover,
    .moments-photo-dropzone.is-dragover { border-color: var(--primary); background: var(--primary-light); }
    .moments-dropzone-prompt {
        display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px;
    }
    /* min-height matches the prompt's own height (52px tile + 12px gap + two
       text lines) so swapping to the spinner doesn't collapse the dropzone. */
    /* wire:loading.flex, not plain wire:loading — Livewire shows a loading
       element as display:inline-block by default, and that inline style beats
       this rule, leaving align-items/justify-content inert and the spinner
       stuck at the top of the box. */
    .moments-dropzone-loading {
        align-items: center; justify-content: center; width: 100%;
        min-height: 106px; color: var(--primary); font-size: 26px;
    }

    /* Leaflet's default popup bubble is a fixed white card regardless of
       page theme — without this, the pin popups' var(--dark)/var(--muted)
       text (which turns light in dark theme) becomes nearly invisible
       against that always-white background. */
    .leaflet-popup-content-wrapper { background: var(--bg-white); border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,.18); }
    .leaflet-popup-content { margin: 14px 16px; }
    .leaflet-popup-tip { background: var(--bg-white); }
    .leaflet-popup-close-button { color: var(--muted) !important; font-size: 18px !important; padding: 6px 8px 0 0 !important; }
    .leaflet-popup-close-button:hover { color: var(--dark) !important; }
</style>

{{-- ── Add/Edit Travel Pin modal ──────────────────────── --}}
@if ($showPinModal)
<div class="moments-modal-backdrop" style="position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;display:flex;align-items:center;justify-content:center;padding:16px;overflow-y:auto;" wire:click.self="closePinModal">
    <div class="moments-modal-card" style="margin-top:4vh;background:var(--bg-white);border-radius:20px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(45,27,20,.18);padding:20px 22px 18px;">

        {{-- Header --}}
        @php $pinTripLabel = $this->selectedTrip?->trip_name ?? $this->selectedTrip?->destination; @endphp
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--primary),#C8874A);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 12px rgba(147,75,25,.25);">
                <i class="fa-solid fa-map-pin" style="color:#fff;font-size:16px;"></i>
            </div>
            <div style="min-width:0;">
                <div style="font-size:17px;font-weight:800;color:var(--dark);font-family:'Hanken Grotesk',sans-serif;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    {{ $pinModalMode === 'edit' ? 'Edit Moment' : 'Moments in ' . ($pinTripLabel ?: 'this trip') }}
                </div>
                <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--muted);margin-top:2px;">
                    <i class="fa-solid fa-location-dot" style="color:#C8874A;font-size:10px;"></i>
                    {{ number_format((float) $pinLat, 5) }}, {{ number_format((float) $pinLng, 5) }}
                </div>
            </div>
        </div>

        {{-- Place Name --}}
        <div style="margin-bottom:10px;">
            <label style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:5px;">Place Name</label>
            <input type="text" wire:model="pinPlaceName" placeholder="e.g. Magellan's Cross" class="moments-input"
                   style="width:100%;background:var(--bg);border:1.5px solid var(--border);border-radius:12px;padding:9px 14px;font-size:13px;font-weight:600;color:var(--dark);box-sizing:border-box;">
            @error('pinPlaceName') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
        </div>

        {{-- Description / memory --}}
        <div style="margin-bottom:10px;">
            <label style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:5px;">Description / Memory</label>
            <textarea wire:model="pinDescription" rows="2" placeholder="What happened here?" class="moments-input"
                      style="width:100%;background:var(--bg);border:1.5px solid var(--border);border-radius:12px;padding:9px 14px;font-size:13px;color:var(--dark);box-sizing:border-box;resize:vertical;font-family:inherit;"></textarea>
            @error('pinDescription') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
        </div>

        {{-- Date visited --}}
        <div style="margin-bottom:10px;">
            <label style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:5px;">Date Visited</label>
            {{-- No min/max here on purpose — a Moment's visited_date is
                 allowed to fall before/after the trip's own dates (backfilled
                 pre-trip or post-trip memories; see the "Pre-Trip" grouping
                 in the Timeline View and the day_number comments below).
                 The calendar still opens on the right month via the default
                 value ItineraryManager::openAddPinModal() sets. --}}
            <div style="position:relative;" x-data='momentDateCal(@json($pinVisitedDate ?: ""), @json($pinModalMode === "add"))'>
                <div class="moments-input" style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;background:var(--bg);border:1.5px solid var(--border);border-radius:12px;padding:9px 14px;box-sizing:border-box;" @click="open=!open">
                    <span x-text="val ? fmtLabel(val) : 'Select date'" :style="val ? 'font-size:13px;font-weight:400;color:var(--dark);' : 'font-size:13px;color:var(--muted);'"></span>
                    <i class="fa-regular fa-calendar" style="font-size:12px;color:var(--muted);flex-shrink:0;"></i>
                </div>
                <div x-show="open" @click.outside="open=false" @click.stop x-cloak
                     style="position:absolute;top:calc(100% + 6px);left:0;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.14);z-index:200;padding:16px;min-width:260px;">
                    <div class="exp-cal-header">
                        <button type="button" class="exp-cal-nav" @click="prevMonth()"><i class="fa-solid fa-chevron-left"></i></button>
                        <span x-text="monthName(year,month)+' '+year"></span>
                        <button type="button" class="exp-cal-nav" @click="nextMonth()"><i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                    <div class="exp-cal-grid">
                        <template x-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']"><div class="exp-cal-day-name" x-text="d"></div></template>
                        <template x-for="cell in cells" :key="cell.key">
                            <div class="exp-cal-day" :class="{'selected': cell.d && cell.val===val, 'past': cell.past, 'empty': !cell.d}"
                                 @click.stop="cell.d && !cell.past && pick(cell.val)" x-text="cell.d||''"></div>
                        </template>
                    </div>
                </div>
            </div>
            @error('pinVisitedDate') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
        </div>

        {{-- Photos --}}
        <div style="margin-bottom:16px;">
            <label style="display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Photos (optional, up to 6)</label>

            @if (count($pinExistingPhotos))
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-bottom:8px;">
                @foreach ($pinExistingPhotos as $photo)
                <div style="position:relative;">
                    <img src="{{ $photo['url'] }}" style="width:100%;height:64px;object-fit:cover;border-radius:8px;display:block;">
                    <button type="button" wire:click="removeExistingPhoto({{ $photo['id'] }})" title="Remove photo"
                            style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:#DC2626;color:#fff;border:2px solid #fff;font-size:9px;cursor:pointer;display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                @endforeach
            </div>
            @endif

            @if (count($pinPhotos))
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-bottom:8px;">
                @foreach ($pinPhotos as $index => $newPhoto)
                <div style="position:relative;">
                    <img src="{{ $newPhoto->temporaryUrl() }}" style="width:100%;height:64px;object-fit:cover;border-radius:8px;display:block;">
                    <button type="button" wire:click="removeNewPhoto({{ $index }})" title="Remove"
                            style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:#DC2626;color:#fff;border:2px solid #fff;font-size:9px;cursor:pointer;display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                @endforeach
            </div>
            @endif

            <label for="pinPhotosInput" class="moments-photo-dropzone"
                   x-data="{ over: false }"
                   x-on:dragover.prevent="over = true"
                   x-on:dragleave.prevent="over = false"
                   x-on:drop.prevent="
                        over = false;
                        $refs.pinPhotosInput.files = $event.dataTransfer.files;
                        $refs.pinPhotosInput.dispatchEvent(new Event('change'));
                   "
                   :class="{ 'is-dragover': over }">
                {{-- Prompt and spinner swap in place inside the dropzone, so the
                     box keeps its size instead of a separate line appearing
                     underneath it while an upload is in flight. --}}
                <div wire:loading.remove wire:target="pinPhotos" class="moments-dropzone-prompt">
                    <div style="width:52px;height:52px;border-radius:14px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fa-solid fa-cloud-arrow-up" style="color:var(--primary);font-size:20px;"></i>
                    </div>
                    <div style="min-width:0;">
                        <div style="font-size:14px;font-weight:700;color:var(--dark);">
                            <span style="color:var(--primary);">Click to upload</span> or drag photos
                        </div>
                        <div style="font-size:12px;color:var(--muted);margin-top:4px;">PNG or JPG, up to 6 photos (5MB each)</div>
                    </div>
                </div>
                <div wire:loading.flex wire:target="pinPhotos" class="moments-dropzone-loading" role="status" aria-label="Uploading photos">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                </div>
                <input id="pinPhotosInput" x-ref="pinPhotosInput" type="file" wire:model="pinPhotos" accept="image/*" multiple style="display:none;">
            </label>
            @error('pinPhotos') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
            @error('pinPhotos.*') <span style="display:block;font-size:11px;color:#DC2626;margin-top:4px;">{{ $message }}</span> @enderror
        </div>

        {{-- Actions --}}
        <div style="display:flex;gap:10px;">
            <button wire:click="closePinModal" type="button" class="moments-btn-outline"
                    style="flex:1;background:transparent;color:var(--muted);border:1.5px solid var(--border);border-radius:12px;padding:12px 0;font-size:13px;font-weight:600;cursor:pointer;">
                Cancel
            </button>
            <button wire:click="savePin" class="moments-btn-primary"
                    style="flex:1;background:var(--primary);color:#fff;border:none;border-radius:12px;padding:12px 0;font-size:14px;font-weight:700;cursor:pointer;font-family:'Hanken Grotesk',sans-serif;"
                    wire:loading.attr="disabled" wire:target="savePin">
                <span wire:loading.remove wire:target="savePin">{{ $pinModalMode === 'edit' ? 'Save Changes' : 'Add Moment' }}</span>
                <span wire:loading wire:target="savePin"><i class="fa-solid fa-spinner fa-spin"></i></span>
            </button>
        </div>

    </div>
</div>
@endif

{{-- ── Delete Pin confirmation modal ─────────────────────── --}}
@if ($pinToDelete)
<div class="moments-modal-backdrop" style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:2100;display:flex;align-items:center;justify-content:center;padding:20px;">
    <div class="moments-modal-card" style="background:var(--bg-white);border-radius:20px;width:100%;max-width:360px;box-shadow:0 20px 60px rgba(0,0,0,0.2);overflow:hidden;">
        {{-- Icon header --}}
        <div style="background:var(--bg);padding:28px 24px 20px;text-align:center;">
            <div style="width:52px;height:52px;border-radius:50%;background:rgba(220,38,38,0.12);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                <i class="fa-solid fa-trash-can" style="font-size:22px;color:#DC2626;"></i>
            </div>
            <div style="font-size:17px;font-weight:700;color:var(--dark);margin-bottom:6px;">Delete This Pin?</div>
            <div style="font-size:13px;color:var(--muted);line-height:1.5;">
                This travel pin and its photo will be permanently deleted.<br>This action cannot be undone.
            </div>
        </div>
        {{-- Actions --}}
        <div style="display:flex;gap:10px;padding:18px 20px;">
            <button wire:click="cancelDeletePin" class="moments-btn-outline"
                    style="flex:1;background:transparent;color:var(--muted);border:1.5px solid var(--border);border-radius:10px;padding:11px 0;font-size:13px;font-weight:600;cursor:pointer;">
                Cancel
            </button>
            <button wire:click="deletePin" class="moments-btn-danger"
                    style="flex:1;background:#DC2626;color:#fff;border:none;border-radius:10px;padding:11px 0;font-size:13px;font-weight:600;cursor:pointer;"
                    wire:loading.attr="disabled" wire:target="deletePin">
                <span wire:loading.remove wire:target="deletePin">Delete</span>
                <span wire:loading wire:target="deletePin"><i class="fa-solid fa-spinner fa-spin"></i></span>
            </button>
        </div>
    </div>
</div>
@endif

<script>
    // Map/Timeline toggle state — global Alpine stores rather than a
    // Livewire property, so switching views is instant (no network
    // round-trip) and never tears down the wire:ignore'd map. Two separate
    // stores: 'overviewMoments' for the all-destinations page, 'moments'
    // for a single selected trip — kept apart so toggling one never
    // affects the other.
    (function () {
        // Stores must exist before Alpine evaluates any $store.* binding on
        // this page. On a hard refresh Alpine hasn't booted yet, so we wait
        // for 'alpine:init'. But on a wire:navigate SPA hop into this page
        // from elsewhere, Alpine already booted long ago and 'alpine:init'
        // will never fire again — so if Alpine is already present, register
        // the stores immediately instead of waiting for an event that's
        // already happened.
        function initMomentsStores() {
            if (!Alpine.store('moments')) Alpine.store('moments', { view: 'map' });
            if (!Alpine.store('overviewMoments')) Alpine.store('overviewMoments', { view: 'map' });
        }
        if (window.Alpine) {
            initMomentsStores();
        } else {
            document.addEventListener('alpine:init', initMomentsStores);
        }
    })();

    // Timeline card clicked → jump to Map View, fly to that moment's pin,
    // open its popup, and give the marker a brief highlight pulse.
    window.focusMapOnMoment = function (momentId) {
        if (typeof Alpine === 'undefined' || !Alpine.store('moments')) return;
        Alpine.store('moments').view = 'map';

        setTimeout(function () {
            var map    = window.__momentsMapInstance;
            var marker = window.__momentsMarkersById && window.__momentsMarkersById[momentId];
            if (!map || !marker) return;

            map.invalidateSize();
            map.flyTo(marker.getLatLng(), 15);
            marker.openPopup();

            var el = marker.getElement();
            el.classList.add('marker-pulse');
            setTimeout(function () { el.classList.remove('marker-pulse'); }, 1400);
        }, 60);
    };

    // Map marker clicked → jump to Timeline View, scroll to and briefly
    // highlight the matching timeline card.
    window.focusTimelineOnMoment = function (momentId) {
        if (typeof Alpine === 'undefined' || !Alpine.store('moments')) return;
        Alpine.store('moments').view = 'timeline';

        setTimeout(function () {
            var el = document.getElementById('moment-' + momentId);
            if (!el) return;
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('is-highlighted');
            setTimeout(function () { el.classList.remove('is-highlighted'); }, 1600);
        }, 60);
    };

    // Defined globally so it survives Livewire re-renders; called via x-init
    // on a wire:ignore + wire:key-ed div, so a trip switch always gets a
    // fresh element (and therefore a fresh map + pin state).
    //
    // Uses Leaflet + CartoDB Voyager raster tiles (same source as the
    // Profile Builder home-location map), for consistency across the app.
    window.initMomentsMap = function (el, wire, lat, lng, zoom, label, initialPins) {
        if (typeof L === 'undefined' || !el) return;

        // Livewire.on(...) listeners are global, not scoped to this element.
        // A trip switch runs this function again on a brand-new element (via
        // wire:key), so without this the previous map's listeners would keep
        // firing forever, stacking up one extra set per switch.
        if (window.__momentsMapUnsub) {
            window.__momentsMapUnsub.forEach(function (off) { off(); });
        }
        window.__momentsMapUnsub = [];
        if (window.__momentsMapInstance) {
            window.__momentsMapInstance.remove();
            window.__momentsMapInstance = null;
        }

        var map = L.map(el, { attributionControl: false, zoomControl: false }).setView([lat, lng], zoom);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OpenStreetMap contributors &copy; <a href="https://carto.com/">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 20,
        }).addTo(map);
        L.control.attribution({ prefix: false, position: 'bottomright' }).addTo(map);
        L.control.zoom({ position: 'topright' }).addTo(map);
        window.__momentsMapInstance = map;

        var pinsById    = {};
        var markersById = {};
        // Exposed so a Timeline card click (outside this closure) can reach
        // a specific marker — same live object, mutated in place below.
        window.__momentsMarkersById = markersById;

        function buildPopup(pin) {
            var wrap = document.createElement('div');
            wrap.style.cssText = 'min-width:190px;font-family:\'Hanken Grotesk\',sans-serif;';

            if (pin.photo_urls && pin.photo_urls.length) {
                var gallery = document.createElement('div');
                gallery.style.cssText = 'display:flex;gap:5px;overflow-x:auto;max-width:220px;margin-bottom:10px;';
                pin.photo_urls.forEach(function (url) {
                    var img = document.createElement('img');
                    img.src = url;
                    img.style.cssText = 'width:72px;height:72px;object-fit:cover;border-radius:10px;flex-shrink:0;cursor:pointer;';
                    img.title = 'Open full size';
                    img.addEventListener('click', function () { window.open(url, '_blank'); });
                    gallery.appendChild(img);
                });
                wrap.appendChild(gallery);
            } else {
                var noPhoto = document.createElement('div');
                noPhoto.style.cssText = 'width:72px;height:72px;border-radius:10px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:10px;';
                noPhoto.innerHTML = '<i class="fa-solid fa-camera" style="font-size:18px;color:var(--primary);"></i>';
                wrap.appendChild(noPhoto);
            }

            var title = document.createElement('div');
            title.textContent = pin.place_name;
            title.style.cssText = 'font-size:14px;font-weight:800;color:var(--dark);margin-bottom:3px;';
            wrap.appendChild(title);

            var date = document.createElement('div');
            date.innerHTML = '<i class="fa-regular fa-calendar" style="font-size:10px;margin-right:4px;"></i>' + pin.visited_date;
            date.style.cssText = 'font-size:11px;color:var(--muted);margin-bottom:6px;';
            wrap.appendChild(date);

            if (pin.description) {
                var desc = document.createElement('div');
                desc.textContent = pin.description;
                desc.style.cssText = 'font-size:12px;color:var(--dark);opacity:.85;margin:0 0 10px;line-height:1.5;';
                wrap.appendChild(desc);
            }

            var actions = document.createElement('div');
            actions.style.cssText = 'display:flex;gap:6px;margin-top:6px;';

            var editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.textContent = 'Edit';
            editBtn.style.cssText = 'flex:1;background:var(--primary-light);color:var(--primary);border:none;border-radius:8px;padding:7px 0;font-size:11px;font-weight:700;cursor:pointer;transition:filter .15s;';
            editBtn.onmouseenter = function () { this.style.filter = 'brightness(0.95)'; };
            editBtn.onmouseleave = function () { this.style.filter = 'none'; };
            editBtn.addEventListener('click', function () { wire.call('openEditPinModal', pin.id); });

            var delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.textContent = 'Delete';
            delBtn.style.cssText = 'flex:1;background:#FEF2F2;color:#DC2626;border:none;border-radius:8px;padding:7px 0;font-size:11px;font-weight:700;cursor:pointer;transition:filter .15s;';
            delBtn.onmouseenter = function () { this.style.filter = 'brightness(0.95)'; };
            delBtn.onmouseleave = function () { this.style.filter = 'none'; };
            delBtn.addEventListener('click', function () { wire.call('confirmDeletePin', pin.id); });

            actions.appendChild(editBtn);
            actions.appendChild(delBtn);
            wrap.appendChild(actions);
            return wrap;
        }

        function renderPin(pin) {
            pinsById[pin.id] = pin;
            var marker = markersById[pin.id];
            if (marker) {
                marker.setLatLng([pin.lat, pin.lng]).bindPopup(buildPopup(pin));
            } else {
                marker = L.marker([pin.lat, pin.lng]).addTo(map).bindPopup(buildPopup(pin));
                markersById[pin.id] = marker;
                // Jump to the matching Timeline entry — pin.id is a plain
                // number, captured by value, so this stays correct even if
                // the moment is later edited (its id never changes).
                marker.on('click', function () {
                    if (window.focusTimelineOnMoment) window.focusTimelineOnMoment(pin.id);
                });
            }
        }

        function removePin(pinId) {
            var marker = markersById[pinId];
            if (marker) { map.removeLayer(marker); delete markersById[pinId]; }
            delete pinsById[pinId];
        }

        L.marker([lat, lng]).addTo(map).bindPopup(label).openPopup();

        (initialPins || []).forEach(renderPin);

        // A marker's own click handler captures the click, so this only
        // fires when clicking empty map area — never an existing pin.
        map.on('click', function (e) {
            wire.call('openAddPinModal', e.latlng.lat, e.latlng.lng);
        });

        window.__momentsMapUnsub.push(
            Livewire.on('pin-saved', function (payload) { renderPin(payload.pin); }),
            Livewire.on('pin-deleted', function (payload) { removePin(payload.id); })
        );

        // A one-shot timeout can't know how long the surrounding layout
        // (sidebar collapse/expand, tab switch transitions, wire:navigate)
        // takes to settle, so it sometimes fires before the container has
        // reached its final size — leaving the map canvas short of the
        // right/bottom edge until something else nudges a resize. A
        // ResizeObserver instead reacts to the container's actual size
        // every time it changes, so the map always fills it automatically.
        setTimeout(function () { map.invalidateSize(); }, 200);
        if (typeof ResizeObserver !== 'undefined') {
            var mapResizeObserver = new ResizeObserver(function () { map.invalidateSize(); });
            mapResizeObserver.observe(el);
            window.__momentsMapUnsub.push(function () { mapResizeObserver.disconnect(); });
        }
    };

    // Destination overview map — trip-status pins (visual only) plus a
    // separate layer of clickable memory markers, one per posted Moment
    // across every trip. Separate MapLibre instance from initMomentsMap
    // above (mutually exclusive: only one of the two map divs exists in the
    // DOM at a time, switched via wire:key when momentsMode changes).
    window.initOverviewMap = function (el, wire, pins, allMoments) {
        if (typeof L === 'undefined' || !el) return;

        if (window.__momentsOverviewMapUnsub) {
            window.__momentsOverviewMapUnsub.forEach(function (off) { off(); });
        }
        window.__momentsOverviewMapUnsub = [];

        if (window.__momentsOverviewMapInstance) {
            window.__momentsOverviewMapInstance.remove();
            window.__momentsOverviewMapInstance = null;
        }

        var map = L.map(el, { attributionControl: false, zoomControl: false }).setView([12.8797, 121.7740], 5);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OpenStreetMap contributors &copy; <a href="https://carto.com/">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 20,
        }).addTo(map);
        L.control.attribution({ prefix: false, position: 'bottomright' }).addTo(map);
        L.control.zoom({ position: 'topright' }).addTo(map);
        window.__momentsOverviewMapInstance = map;

        var STATUS_COLORS = { active: '#22C55E', upcoming: '#3B82F6', past: '#6B7280' };
        var STATUS_LABELS = { active: 'Ongoing', upcoming: 'Upcoming', past: 'Completed' };

        // Pure visual indicator — hover tooltip only, no click, no popup.
        function buildTripMarkerIcon(pin) {
            var color = STATUS_COLORS[pin.status] || '#6B7280';
            return L.divIcon({
                className: '',
                html: '<div style="width:22px;height:22px;border-radius:50% 50% 50% 0;background:' + color + ';transform:rotate(-45deg);border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3);"></div>',
                iconSize: [22, 22],
                iconAnchor: [11, 22],
            });
        }

        // Memory markers: a small round pin showing the first photo (or a
        // camera icon if none), clickable to view/edit/delete that Moment.
        function buildMemoryMarkerIcon(pin) {
            var hasPhoto = pin.photo_urls && pin.photo_urls.length > 0;
            var inner = hasPhoto
                ? ''
                : '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-camera" style="font-size:12px;color:var(--primary);"></i></div>';
            var bg = hasPhoto ? "background-image:url('" + pin.photo_urls[0] + "');" : '';
            return L.divIcon({
                className: '',
                html: '<div style="width:30px;height:30px;border-radius:50%;border:3px solid var(--primary);box-shadow:0 2px 6px rgba(0,0,0,.35);cursor:pointer;background-color:#FDF3EB;background-size:cover;background-position:center;' + bg + '">' + inner + '</div>',
                iconSize: [30, 30],
                iconAnchor: [15, 15],
            });
        }

        function buildMemoryPopup(pin) {
            var wrap = document.createElement('div');
            wrap.style.cssText = 'min-width:190px;font-family:\'Hanken Grotesk\',sans-serif;';

            if (pin.photo_urls && pin.photo_urls.length) {
                var gallery = document.createElement('div');
                gallery.style.cssText = 'display:flex;gap:5px;overflow-x:auto;max-width:220px;margin-bottom:10px;';
                pin.photo_urls.forEach(function (url) {
                    var img = document.createElement('img');
                    img.src = url;
                    img.style.cssText = 'width:72px;height:72px;object-fit:cover;border-radius:10px;flex-shrink:0;cursor:pointer;';
                    img.title = 'Open full size';
                    img.addEventListener('click', function () { window.open(url, '_blank'); });
                    gallery.appendChild(img);
                });
                wrap.appendChild(gallery);
            } else {
                var noPhoto = document.createElement('div');
                noPhoto.style.cssText = 'width:72px;height:72px;border-radius:10px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:10px;';
                noPhoto.innerHTML = '<i class="fa-solid fa-camera" style="font-size:18px;color:var(--primary);"></i>';
                wrap.appendChild(noPhoto);
            }

            var title = document.createElement('div');
            title.textContent = pin.place_name;
            title.style.cssText = 'font-size:14px;font-weight:800;color:var(--dark);margin-bottom:3px;';
            wrap.appendChild(title);

            var date = document.createElement('div');
            date.innerHTML = '<i class="fa-regular fa-calendar" style="font-size:10px;margin-right:4px;"></i>' + pin.visited_date;
            date.style.cssText = 'font-size:11px;color:var(--muted);margin-bottom:6px;';
            wrap.appendChild(date);

            if (pin.description) {
                var desc = document.createElement('div');
                desc.textContent = pin.description;
                desc.style.cssText = 'font-size:12px;color:var(--dark);opacity:.85;margin:0 0 10px;line-height:1.5;';
                wrap.appendChild(desc);
            }

            var actions = document.createElement('div');
            actions.style.cssText = 'display:flex;gap:6px;margin-top:6px;';

            var editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.textContent = 'Edit';
            editBtn.style.cssText = 'flex:1;background:var(--primary-light);color:var(--primary);border:none;border-radius:8px;padding:7px 0;font-size:11px;font-weight:700;cursor:pointer;transition:filter .15s;';
            editBtn.onmouseenter = function () { this.style.filter = 'brightness(0.95)'; };
            editBtn.onmouseleave = function () { this.style.filter = 'none'; };
            editBtn.addEventListener('click', function () { wire.call('openEditPinModalFromOverview', pin.id); });

            var delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.textContent = 'Delete';
            delBtn.style.cssText = 'flex:1;background:#FEF2F2;color:#DC2626;border:none;border-radius:8px;padding:7px 0;font-size:11px;font-weight:700;cursor:pointer;transition:filter .15s;';
            delBtn.onmouseenter = function () { this.style.filter = 'brightness(0.95)'; };
            delBtn.onmouseleave = function () { this.style.filter = 'none'; };
            delBtn.addEventListener('click', function () { wire.call('confirmDeletePinFromOverview', pin.id); });

            actions.appendChild(editBtn);
            actions.appendChild(delBtn);
            wrap.appendChild(actions);
            return wrap;
        }

        var memoryMarkersById = {};
        var momentsById = {};
        function renderMemory(pin) {
            momentsById[pin.id] = pin;
            var marker = memoryMarkersById[pin.id];
            if (marker) {
                marker.setLatLng([pin.lat, pin.lng]).bindPopup(buildMemoryPopup(pin));
            } else {
                marker = L.marker([pin.lat, pin.lng], { icon: buildMemoryMarkerIcon(pin) })
                    .addTo(map)
                    .bindPopup(buildMemoryPopup(pin));
                memoryMarkersById[pin.id] = marker;
            }
        }

        function removeMemory(pinId) {
            var marker = memoryMarkersById[pinId];
            if (marker) { map.removeLayer(marker); delete memoryMarkersById[pinId]; }
            delete momentsById[pinId];
        }

        var bounds = L.latLngBounds([]);
        var seenCoords = [];
        function trackCoord(lat, lng) {
            var isDup = seenCoords.some(function (c) {
                return Math.abs(c[0] - lat) < 1e-9 && Math.abs(c[1] - lng) < 1e-9;
            });
            if (!isDup) seenCoords.push([lat, lng]);
        }

        (pins || []).forEach(function (pin) {
            L.marker([pin.lat, pin.lng], { icon: buildTripMarkerIcon(pin) }).addTo(map);
            bounds.extend([pin.lat, pin.lng]);
            trackCoord(pin.lat, pin.lng);
        });
        (allMoments || []).forEach(function (pin) {
            renderMemory(pin);
            bounds.extend([pin.lat, pin.lng]);
            trackCoord(pin.lat, pin.lng);
        });

        if (seenCoords.length === 1) {
            // fitBounds() on a zero-area bounds (every pin at the exact same
            // spot — the common case being just one trip with no photos
            // logged yet) is unreliable: it can leave the view at whatever
            // the map's initial center/zoom was instead of actually moving
            // to the pin, which is what made a brand-new single-trip
            // overview show a random default location instead of that
            // trip's own destination. Jumping straight to the one known
            // point sidesteps the degenerate-bounds case entirely.
            map.setView(seenCoords[0], 8);
        } else if (pins && pins.length) {
            map.fitBounds(bounds, { padding: [60, 60], maxZoom: 8 });
        }

        // A marker's own click handler captures the click, so this only
        // fires when clicking empty map area — never an existing pin.
        map.on('click', function (e) {
            wire.call('openAddPinModalFromOverview', e.latlng.lat, e.latlng.lng);
        });

        window.__momentsOverviewMapUnsub.push(
            Livewire.on('pin-saved', function (payload) { renderMemory(payload.pin); }),
            Livewire.on('pin-deleted', function (payload) { removeMemory(payload.id); })
        );

        // A one-shot timeout can't know how long the surrounding layout
        // (sidebar collapse/expand, tab switch transitions, wire:navigate)
        // takes to settle, so it sometimes fires before the container has
        // reached its final size — leaving the map canvas short of the
        // right/bottom edge until something else nudges a resize. A
        // ResizeObserver instead reacts to the container's actual size
        // every time it changes, so the map always fills it automatically.
        setTimeout(function () { map.invalidateSize(); }, 200);
        if (typeof ResizeObserver !== 'undefined') {
            var mapResizeObserver = new ResizeObserver(function () { map.invalidateSize(); });
            mapResizeObserver.observe(el);
            window.__momentsOverviewMapUnsub.push(function () { mapResizeObserver.disconnect(); });
        }
    };

    // Single-date calendar picker for the "Date Visited" field — same
    // widget style as the Expenses page's From/To calendars, but for one
    // date instead of a range, and syncing straight to Livewire via
    // $wire.set() instead of a form-submitted hidden input.
    window.momentDateCal = function (initialVal, isAdd) {
        var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        var seed = initialVal ? new Date(initialVal + 'T00:00:00') : new Date();

        return {
            open: false,
            val: initialVal || '',
            year: seed.getFullYear(), month: seed.getMonth() + 1,
            cells: [],

            init() {
                // The server runs on UTC, so its "today" can be a day behind
                // the traveler's own clock (UTC+8 here) — the field would open
                // showing yesterday, and on a past trip it could even open on
                // a date the calendar now blocks. Correct it to the browser's
                // local today, which is what "the system date" means to the
                // person looking at the screen. Add mode only: an existing
                // moment's recorded date must never be silently rewritten.
                if (isAdd) {
                    var n = new Date();
                    var todayStr = n.getFullYear() + '-' + String(n.getMonth()+1).padStart(2,'0') + '-' + String(n.getDate()).padStart(2,'0');
                    if (!this.val || this.val < todayStr) {
                        this.val   = todayStr;
                        this.year  = n.getFullYear();
                        this.month = n.getMonth() + 1;
                        this.$wire.set('pinVisitedDate', todayStr);
                    }
                }
                this.rebuild();
            },

            rebuild() {
                var y = this.year, m = this.month;
                var first = new Date(y, m - 1, 1).getDay();
                var days  = new Date(y, m, 0).getDate();
                // Built from the browser's local date, matching the trip
                // planner's calendars — a UTC-based comparison would mark
                // today itself as past for anyone ahead of UTC.
                var now = new Date();
                var todayStr = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0');
                var cells = [];
                for (var i = 0; i < first; i++) cells.push({ d: null, key: 'e'+y+m+i, val: '', past: false });
                for (var d = 1; d <= days; d++) {
                    var val = y + '-' + String(m).padStart(2,'0') + '-' + String(d).padStart(2,'0');
                    cells.push({ d: d, key: 'd'+y+m+d, val: val, past: val < todayStr });
                }
                this.cells = cells;
            },

            monthName(y, m) { return months[m - 1]; },

            prevMonth() {
                this.month--; if (this.month < 1) { this.month = 12; this.year--; }
                this.rebuild();
            },
            nextMonth() {
                this.month++; if (this.month > 12) { this.month = 1; this.year++; }
                this.rebuild();
            },

            fmtLabel(v) {
                var parts = v.split('-');
                return parts[2].padStart(2,'0') + '/' + parts[1].padStart(2,'0') + '/' + parts[0];
            },

            pick(v) {
                this.val = v;
                this.open = false;
                this.$wire.set('pinVisitedDate', v);
            },
        };
    };
</script>

<style>
    .exp-cal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; font-size: 13px; font-weight: 700; color: var(--dark); }
    .exp-cal-nav { background: none; border: none; cursor: pointer; color: var(--muted); font-size: 14px; padding: 4px 8px; }
    .exp-cal-nav:hover { color: var(--primary); }
    .exp-cal-grid { display: grid; grid-template-columns: repeat(7,1fr); gap: 2px; text-align: center; }
    .exp-cal-day-name { font-size: 10px; font-weight: 700; color: var(--muted); padding: 4px 0; }
    .exp-cal-day { font-size: 12px; font-weight: 500; padding: 6px 4px; border-radius: 6px; cursor: pointer; color: var(--dark); }
    .exp-cal-day:hover:not(.empty):not(.past) { background: var(--bg); }
    .exp-cal-day.past { color: var(--muted); opacity: .4; cursor: not-allowed; }
    .exp-cal-day.selected { background: var(--primary); color: #fff; }
    .exp-cal-day.empty { cursor: default; }
</style>
