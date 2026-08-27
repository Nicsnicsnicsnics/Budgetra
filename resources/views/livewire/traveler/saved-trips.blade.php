<div style="display:flex;flex-direction:column;flex:1;">

@if (session('success'))
<div style="color:#16A34A;font-size:13px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:7px;">
    <i class="fa-solid fa-circle-check"></i> {{ session('success') }}
</div>
@endif
@if (session('error'))
<div style="color:var(--danger);font-size:13px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:7px;">
    <i class="fa-solid fa-circle-exclamation"></i> {{ session('error') }}
</div>
@endif

<style>
.st-panel-pop{animation:stPanelPop .22s ease;transform-origin:left center;}
@keyframes stPanelPop{from{opacity:0;transform:scaleX(.9);}to{opacity:1;transform:scaleX(1);}}
@keyframes stModalPop{from{opacity:0;transform:scale(.94) translateY(8px);}to{opacity:1;transform:scale(1) translateY(0);}}
.st-thin-scroll{scrollbar-width:none;-ms-overflow-style:none;}
.st-thin-scroll::-webkit-scrollbar{width:0;height:0;background:transparent;}
.st-sum-row{transition:background .15s;}
.st-sum-row:hover{background:#FBF6F1;}

/* Member chips. These were hardcoded light values (#FDF3EB chip, #FEE2E2
   button, #c3b3a6 avatar) paired with var(--dark) text, so on a dark theme
   the name turned light against a cream chip and vanished. Tints are mixed
   against the surface instead, so they follow whatever theme is active. */
.st-member-row{display:flex;align-items:center;gap:10px;border-radius:12px;padding:9px 12px;margin-bottom:7px;}
.st-member-row-saved{background:var(--bg);}
.st-member-row-pending{background:color-mix(in srgb, var(--primary) 10%, var(--bg-white));border:1px solid var(--border);}
.st-member-avatar{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px;font-weight:700;color:#fff;}
.st-member-avatar-saved{background:var(--muted);}
.st-member-avatar-pending{background:var(--primary);}
.st-member-name{font-size:13px;font-weight:600;color:var(--dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.st-member-email{font-size:11px;color:var(--muted);}
.st-member-remove{
    flex-shrink:0;width:28px;height:28px;border:none;border-radius:8px;cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    background:color-mix(in srgb, #DC2626 14%, var(--bg-white));color:#DC2626;transition:background .18s;
}
.st-member-remove:hover{background:color-mix(in srgb, #DC2626 26%, var(--bg-white));}
</style>

    {{-- && !$search — a search that matches nothing is not the same as having
         no trips at all, and must not offer "Plan Your First Trip". --}}
    @if ($trips->isEmpty() && !$search)
    <div class="empty-state-center" style="min-height:80vh;">
        <div style="width:64px;height:64px;border-radius:18px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
            <i class="fa-solid fa-suitcase-rolling" style="font-size:28px;color:#fff;"></i>
        </div>
        @if (!auth()->user()?->userProfile)
        <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:var(--dark);">Set up your profile first</h2>
        <p style="color:var(--muted);margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Complete your travel profile before planning a trip and view your active, draft, and past trips.</p>
        <a href="{{ route('profile.setup') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;transition:background .18s;"
           onmouseenter="this.style.background='var(--primary-dark)'" onmouseleave="this.style.background='var(--primary)'">
            <i class="fa-solid fa-user"></i> Set Up Your Profile First
        </a>
        @else
        <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:var(--dark);">No saved trips yet</h2>
        <p style="color:var(--muted);margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Plan a trip first to see your saved and draft trips.</p>
        <a href="{{ route('trips.plan') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;transition:background .18s;"
           onmouseenter="this.style.background='var(--primary-dark)'" onmouseleave="this.style.background='var(--primary)'">
            <i class="fa-solid fa-plane"></i> Plan Your First Trip
        </a>
        @endif
    </div>
    @else
    @php
        $draftTrips  = $trips->where('status', '=', 'draft');
        $activeTrips = $trips->whereNotIn('status', ['past', 'draft']);
        $pastTrips   = $trips->where('status', '=', 'past');
        $stGroups = [
            ['key' => 'active', 'label' => 'Active Trips', 'icon' => 'fa-solid fa-suitcase-rolling',  'items' => $activeTrips, 'noun' => 'active trips'],
            ['key' => 'draft',  'label' => 'Draft Trips',  'icon' => 'fa-regular fa-file-lines',      'items' => $draftTrips,  'noun' => 'draft trips'],
            ['key' => 'past',   'label' => 'Past Trips',   'icon' => 'fa-solid fa-clock-rotate-left', 'items' => $pastTrips,   'noun' => 'past trips'],
        ];
    @endphp

    <div x-data="{ tab: 'active' }" style="display:flex;flex-direction:column;">
        {{-- Browser-tab-style switcher --}}
        <div style="display:flex;align-items:flex-end;gap:4px;flex-shrink:0;flex-wrap:wrap;">
            @foreach ($stGroups as $stGroup)
            @php $stCount = $stGroup['items']->count(); @endphp
            <button @click="tab = '{{ $stGroup['key'] }}'" type="button"
                    :style="'display:flex;align-items:center;gap:10px;padding:12px 22px;border:1.5px solid;border-bottom:none;border-radius:18px 18px 0 0;cursor:pointer;font-family:inherit;position:relative;white-space:nowrap;flex-wrap:nowrap;transition:background .15s ease,color .15s ease;' + (tab === '{{ $stGroup['key'] }}' ? 'background:var(--bg-white);border-color:var(--border);color:var(--dark);z-index:2;margin-bottom:-1.5px;' : 'background:var(--bg);border-color:transparent;color:var(--muted);z-index:1;')">
                <div style="width:26px;height:26px;border-radius:8px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="{{ $stGroup['icon'] }}" style="color:#fff;font-size:11px;"></i>
                </div>
                <span style="font-size:14px;font-weight:700;">{{ $stGroup['label'] }}</span>
                <span style="font-size:11px;font-weight:800;{{ $stCount > 0 ? 'color:#fff;background:var(--primary);' : 'color:#B3A69A;background:#F3EEE8;' }}border-radius:99px;min-width:20px;height:20px;padding:0 6px;display:inline-flex;align-items:center;justify-content:center;line-height:1;">{{ $stCount }}</span>
            </button>
            @endforeach

            {{-- Right-aligned via margin-left:auto on .page-search --}}
            <label class="page-search">
                {{-- The icon doubles as the in-flight indicator: the round trip
                     to the DB runs ~0.5s, so without it the box looks frozen
                     between keystroke and result. 500ms debounce (not 300)
                     keeps a fast typist from queueing overlapping requests
                     that then resolve one behind the other. --}}
                <i class="fa-solid fa-magnifying-glass" wire:loading.remove wire:target="search"></i>
                <i class="fa-solid fa-spinner fa-spin" wire:loading wire:target="search" style="color:var(--primary);"></i>
                <input type="text" wire:model.live.debounce.500ms="search" placeholder="Search trips">
                <button type="button" wire:click="$set('search', '')" x-show="$wire.search" x-cloak title="Clear search">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </label>
        </div>

        {{-- Tab panels --}}
        <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:0 16px 16px 16px;padding:32px 24px;display:flex;flex-direction:column;position:relative;">
        @foreach ($stGroups as $stGroup)
        <div x-show="tab === '{{ $stGroup['key'] }}'" x-cloak style="display:flex;flex-direction:column;">
            @if ($stGroup['items']->isEmpty())
            {{-- A search that matched nothing is a different situation from an
                 empty tab: the trips may well exist, just not under this term.
                 Saying "No saved trips yet" there would be wrong, and would
                 point the traveler at planning a trip they already have. --}}
            <div style="min-height:360px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:40px 20px;">
                <div style="width:56px;height:56px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
                    <i class="{{ $search ? 'fa-solid fa-magnifying-glass' : $stGroup['icon'] }}" style="font-size:24px;color:#fff;"></i>
                </div>
                @if ($search)
                <h3 style="font-weight:700;font-size:17px;margin:0 0 6px;color:var(--dark);">No trips found</h3>
                <p style="color:var(--muted);font-size:13px;max-width:280px;line-height:1.6;margin:0;">Try searching another trip.</p>
                @else
                <h3 style="font-weight:700;font-size:17px;margin:0 0 6px;color:var(--dark);">No {{ $stGroup['label'] }} yet</h3>
                <p style="color:var(--muted);font-size:13px;max-width:280px;line-height:1.6;margin:0;">Plan a trip first to see your {{ $stGroup['noun'] }}.</p>
                @endif
            </div>
            @else
            @php $stTotalPages = (int) ceil($stGroup['items']->count() / 3); @endphp
            <div x-data="{ page: 1 }" style="display:flex;flex-direction:column;">
            <div style="width:100%;display:flex;flex-wrap:wrap;justify-content:center;gap:28px;align-items:flex-start;">
                @foreach ($stGroup['items'] as $trip)
                <div x-show="page === {{ (int) floor($loop->index / 3) + 1 }}" style="{{ $detailTripId === $trip->id ? 'flex:0 0 calc((100% - 56px) / 3 * 2 + 28px);max-width:calc((100% - 56px) / 3 * 2 + 28px);' : 'flex:0 0 calc((100% - 56px) / 3);max-width:calc((100% - 56px) / 3);' }}">
        <div style="display:flex;align-items:stretch;">
        @php
            // A never-finished draft (saved before a destination was picked)
            // stores the literal placeholder 'Draft' in the destination
            // column — show a clearer label instead of that raw string,
            // which otherwise reads like an actual place name right next
            // to the "Draft" status badge above it.
            $dest     = $trip->trip_name ?: (($trip->destination && $trip->destination !== 'Draft') ? $trip->destination : 'No destination set');
            $fromCode = $trip->origin_code ?? 'MNL';
            $toCode   = $trip->destination_code ?? '';
            $tType    = strtoupper($trip->travel_type ?? 'SOLO');
            $dateFrom = $trip->start_date->format('M j');
            $dateTo   = $trip->end_date->format('M j, Y');
            $days     = $trip->days;
            $displayCost = $trip->total_cost ?? $trip->budget_limit ?? 0;
            $cover    = $trip->cover_image ?: ($trip->status === 'draft' ? asset('stockimages/draftimage.jpg') : null);
            $statusColor = match($trip->status) {
                'active'   => '#22C55E',
                'upcoming' => '#3B82F6',
                default    => 'var(--muted)',
            };
            $isDraft = $trip->status === 'draft';
        @endphp
        <div wire:key="trip-{{ $trip->id }}" style="position:relative;background:var(--bg-white);border:1.5px solid var(--border);border-radius:20px;overflow:hidden;display:flex;flex-direction:column;width:100%;">
            <div @if($isDraft) style="filter:blur(6px);pointer-events:none;user-select:none;" @endif>
            {{-- Cover image --}}
            <div style="position:relative;height:230px;background:linear-gradient(135deg,var(--primary),#C8874A);overflow:hidden;">
                @if($cover)
                <img src="{{ $cover }}" alt="{{ $dest }}"
                     style="width:100%;height:100%;object-fit:cover;display:block;"
                     onerror="this.style.display='none'">
                @endif
                <div style="position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,0.15),rgba(0,0,0,0.55));"></div>
                {{-- Stacked badges top-left --}}
                <div style="position:absolute;top:14px;left:14px;display:flex;flex-direction:column;gap:6px;">
                    @php $typeColor = $tType === 'GROUP' ? '#A855F7' : '#14B8A6'; @endphp
                    <span style="background:{{ $typeColor }};color:#fff;font-size:11px;font-weight:700;letter-spacing:0.5px;padding:4px 12px;border-radius:20px;display:inline-block;text-align:center;">{{ $tType }}</span>
                    @php $statusLabel = match($trip->status) { 'active' => 'Ongoing', 'upcoming' => 'Upcoming', 'past' => 'Finished', default => ucfirst($trip->status) }; @endphp
                    <span style="background:{{ $statusColor }};color:#fff;font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;text-transform:uppercase;display:inline-block;">{{ $statusLabel }}</span>
                </div>
                {{-- Kebab menu top-right --}}
                <div x-data="{ open: false }" style="position:absolute;top:12px;right:12px;">
                    <button @click.stop="open = !open" @click.away="open = false"
                            style="width:34px;height:34px;border-radius:50%;background:rgba(0,0,0,0.35);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;backdrop-filter:blur(4px);transition:background .18s;"
                            onmouseenter="this.style.background='rgba(0,0,0,0.55)'" onmouseleave="this.style.background='rgba(0,0,0,0.35)'">
                        <i class="fa-solid fa-ellipsis-vertical" style="font-size:14px;"></i>
                    </button>
                    <div x-show="open" x-transition
                         style="position:absolute;top:40px;right:0;background:var(--bg-white);border:1px solid var(--border);border-radius:12px;min-width:160px;z-index:100;overflow:hidden;">
                        <button wire:click="openEditName({{ $trip->id }})" @click="open=false"
                                style="width:100%;background:none;border:none;padding:11px 16px;font-size:13px;font-weight:500;color:var(--dark);cursor:pointer;display:flex;align-items:center;gap:8px;text-align:left;border-bottom:1px solid var(--bg);font-family:'Hanken Grotesk',sans-serif;"
                                onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background='none'">
                            <i class="fa-regular fa-pen-to-square" style="font-size:12px;color:var(--muted);"></i> Edit Trip
                        </button>
                        <button wire:click="confirmDelete({{ $trip->id }})" @click="open=false"
                                style="width:100%;background:none;border:none;padding:11px 16px;font-size:13px;font-weight:500;color:#ba1a1a;cursor:pointer;display:flex;align-items:center;gap:8px;text-align:left;font-family:'Hanken Grotesk',sans-serif;"
                                onmouseenter="this.style.background='#fff5f5'" onmouseleave="this.style.background='none'">
                            <i class="fa-regular fa-trash-can" style="font-size:12px;"></i> Delete Trip
                        </button>
                    </div>
                </div>
                {{-- Share button, directly below the kebab menu --}}
                <button wire:click="openShare({{ $trip->id }})" type="button" title="Share this trip"
                        style="position:absolute;top:52px;right:12px;width:34px;height:34px;border-radius:50%;background:rgba(0,0,0,0.35);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;backdrop-filter:blur(4px);transition:background .18s;"
                        onmouseenter="this.style.background='rgba(0,0,0,0.55)'" onmouseleave="this.style.background='rgba(0,0,0,0.35)'">
                    <i class="fa-solid fa-share-nodes" style="font-size:13px;"></i>
                </button>
                {{-- Download PDF, continuing the same icon stack. Reuses the
                     existing reports.download route + ReportService rather
                     than a second PDF path. Plain link, not wire:navigate —
                     the response is a Content-Disposition attachment, so the
                     browser saves it without leaving the page. --}}
                <a href="{{ route('reports.download') }}?trip_id={{ $trip->id }}" title="Download trip PDF"
                   style="position:absolute;top:92px;right:12px;width:34px;height:34px;border-radius:50%;background:rgba(0,0,0,0.35);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;text-decoration:none;backdrop-filter:blur(4px);transition:background .18s;"
                   onmouseenter="this.style.background='rgba(0,0,0,0.55)'" onmouseleave="this.style.background='rgba(0,0,0,0.35)'">
                    <i class="fa-solid fa-file-arrow-down" style="font-size:13px;"></i>
                </a>
                <div style="position:absolute;bottom:12px;left:16px;right:16px;">
                    <div style="font-size:19px;font-weight:700;color:#fff;line-height:1.3;margin-bottom:8px;">
                        {{ $dest }}
                    </div>
                    @php
                        $leg2From = $trip->is_multi_city && $trip->leg2_destination ? $toCode : null;
                        $leg2To   = $trip->is_multi_city && $trip->leg2_destination ? ($trip->leg2_destination_code ?? '') : null;
                        $leg2Sd   = $trip->is_multi_city && $trip->leg2_destination ? $trip->leg2_start_date?->format('M j') : null;
                        $leg2Ed   = $trip->is_multi_city && $trip->leg2_destination ? $trip->leg2_end_date?->format('M j, Y') : null;
                    @endphp
                    <div style="display:flex;flex-direction:column;gap:5px;">
                        @if($fromCode && $toCode)
                        <div style="display:flex;align-items:center;gap:14px;font-size:12px;color:rgba(255,255,255,0.9);flex-wrap:wrap;">
                            <span style="display:flex;align-items:center;gap:6px;"><i class="fa-solid fa-plane" style="font-size:11px;color:#F5C97A;"></i>{{ $fromCode }} to {{ $toCode }}</span>
                            @if($leg2From && $leg2To)
                            <span style="display:flex;align-items:center;gap:6px;"><i class="fa-solid fa-plane" style="font-size:11px;color:#F5C97A;"></i>{{ $leg2From }} to {{ $leg2To }}</span>
                            @endif
                        </div>
                        @endif
                        <div style="display:flex;align-items:center;gap:14px;font-size:12px;color:rgba(255,255,255,0.9);flex-wrap:wrap;">
                            <span style="display:flex;align-items:center;gap:6px;"><i class="fa-regular fa-calendar-days" style="font-size:11px;color:#F5C97A;"></i>{{ $dateFrom }} - {{ $dateTo }}</span>
                            @if($leg2Sd && $leg2Ed)
                            <span style="display:flex;align-items:center;gap:6px;"><i class="fa-regular fa-calendar-days" style="font-size:11px;color:#F5C97A;"></i>{{ $leg2Sd }} - {{ $leg2Ed }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div style="padding:20px;">
                <div @if(!$isDraft) style="margin-bottom:16px;" @endif>
                    <div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:5px;">Trip Cost</div>
                    <div style="font-size:23px;font-weight:700;color:#C8874A;">
                        {{ $this->displayAmount($trip, $displayCost) }}
                    </div>
                    @if ($trip->shared_with_me)
                    <div style="font-size:11px;color:var(--primary);font-weight:600;margin-top:6px;display:flex;align-items:center;gap:5px;">
                        <i class="fa-solid fa-user-group" style="font-size:10px;"></i>
                        Shared by {{ $trip->user->full_name ?? 'a fellow traveler' }}
                    </div>
                    @endif
                </div>

                @if (!$isDraft)
                <div style="display:flex;gap:10px;">
                    <button wire:click="showDetail({{ $trip->id }})"
                            style="flex:1;background:{{ $detailTripId === $trip->id ? 'var(--primary-dark)' : 'var(--primary)' }};color:#fff;border:none;border-radius:10px;padding:12px 8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;display:flex;align-items:center;justify-content:center;gap:6px;transition:background .18s;"
                            onmouseenter="this.style.background='var(--primary-dark)'" onmouseleave="this.style.background='{{ $detailTripId === $trip->id ? 'var(--primary-dark)' : 'var(--primary)' }}'">
                        <i class="fa-regular fa-eye" style="font-size:13px;"></i> {{ $detailTripId === $trip->id ? 'Hide Details' : 'View Details' }}
                    </button>
                    <a href="{{ route('expenses.index') }}?trip_id={{ $trip->id }}"
                       style="flex:1;background:transparent;color:var(--primary);border:1.5px solid var(--primary);border-radius:10px;padding:12px 6px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:5px;white-space:nowrap;transition:background .18s;"
                       onmouseenter="this.style.background='var(--primary-light)'" onmouseleave="this.style.background='transparent'">
                        <i class="fa-solid fa-receipt" style="font-size:11px;"></i>Add Expense
                    </a>
                </div>
                @endif
            </div>
            </div>

            @if ($isDraft)
            {{-- Unblurred overlay, centered over the whole (blurred) card --}}
            <div style="position:absolute;inset:0;background:rgba(0,0,0,0.4);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;padding:24px;">
                <a href="{{ route('trips.plan') }}?draft={{ $trip->id }}" wire:navigate
                   style="width:100%;max-width:220px;background:var(--primary);color:#fff;border:none;border-radius:10px;padding:12px 8px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;white-space:nowrap;display:flex;align-items:center;justify-content:center;gap:6px;transition:background .18s;"
                   onmouseenter="this.style.background='var(--primary-dark)'" onmouseleave="this.style.background='var(--primary)'">
                    <i class="fa-regular fa-pen-to-square" style="font-size:12px;"></i> Continue Editing
                </a>
                <button wire:click="confirmDelete({{ $trip->id }})"
                        style="width:100%;max-width:220px;background:var(--bg-white);color:#E23A4E;border:1.5px solid #E23A4E;border-radius:10px;padding:12px 6px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;display:flex;align-items:center;justify-content:center;gap:5px;transition:background .18s;"
                        onmouseenter="this.style.background='rgba(226,58,78,0.10)'" onmouseleave="this.style.background='var(--bg-white)'">
                    <i class="fa-regular fa-trash-can" style="font-size:11px;"></i>Delete Trip
                </button>
            </div>
            @endif
        </div>

        @if ($detailTripId === $trip->id)
        @php
            $dt      = $trip;
            $dtDest  = $dt->trip_name ?? $dt->destination;
            $dtTotal = $dt->total_cost ?? $dt->budget_limit ?? 0;
            $sd      = $dt->summary_data ?? [];
            $dtSpendColor = $dt->spend_pct >= 80 ? '#DC2626' : ($dt->spend_pct >= 50 ? '#D97706' : 'var(--dark)');
            $rows = [
                'transportation' => ['label' => 'Transportation',  'icon' => 'fa-solid fa-plane',           'color' => '#3B82F6'],
                'accommodation'  => ['label' => 'Accommodation',   'icon' => 'fa-solid fa-bed',             'color' => '#0D9488'],
                'food'           => ['label' => 'Food & Dining',   'icon' => 'fa-solid fa-utensils',        'color' => '#EF4444'],
                'attractions'    => ['label' => 'Attractions',     'icon' => 'fa-solid fa-landmark',        'color' => '#10B981'],
                'emergency_fund' => ['label' => 'Emergency Fund',  'icon' => 'fa-solid fa-shield-halved',   'color' => '#2563EB'],
            ];
        @endphp
        <div class="st-panel-pop st-thin-scroll" wire:key="detail-{{ $trip->id }}"
             style="width:320px;flex-shrink:0;margin-left:16px;background:var(--bg-white);border-radius:20px;overflow-y:auto;max-height:{{ 200 + 20 + 62 + 44 + 42 }}px;">

            <div style="padding:18px 20px 6px;display:flex;align-items:center;gap:9px;">
                <div style="width:26px;height:26px;border-radius:8px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa-solid fa-receipt" style="color:var(--primary);font-size:11px;"></i>
                </div>
                <span style="font-size:15px;font-weight:800;color:var(--dark);">Trip Summary</span>
            </div>

            {{-- Summary rows --}}
            <div style="margin:14px 16px;display:flex;flex-direction:column;gap:8px;">
                @if(empty($sd))
                <div style="padding:24px 14px;text-align:center;background:var(--bg-white);border-radius:14px;">
                    <i class="fa-solid fa-circle-info" style="font-size:20px;color:#D4C5B5;margin-bottom:8px;display:block;"></i>
                    <div style="font-size:12px;color:var(--muted);">Detailed breakdown not available for this trip.</div>
                </div>
                @else
                @foreach ($rows as $key => $meta)
                @php
                    $rowData   = $sd[$key] ?? null;
                    $rowCost   = $rowData['cost'] ?? 0;
                    $rowDetail = $rowData['detail'] ?? null;
                    $rowExtra  = $rowData['extra'] ?? 0;
                    if (!$rowCost && !$rowDetail) continue;
                @endphp
                {{-- Fixed square tile, not align-self:stretch — stretching made
                     the tile height follow each row's text, so a 3-line row got
                     a tall box with the same tiny icon rattling around inside it
                     while a 1-line row got a short one. --}}
                <div class="st-sum-row" style="padding:12px 13px;background:var(--bg-white);border-radius:14px;display:flex;align-items:flex-start;gap:11px;">
                    <div style="width:42px;height:42px;border-radius:12px;background:{{ $meta['color'] }}1A;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="{{ $meta['icon'] }}" style="color:{{ $meta['color'] }};font-size:17px;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">
                            <div style="font-size:12px;font-weight:700;color:var(--dark);margin-bottom:2px;">{{ $meta['label'] }}</div>
                            <div style="font-size:12px;font-weight:700;color:var(--dark);white-space:nowrap;flex-shrink:0;">{{ $this->displayAmount($dt, $rowCost) }}</div>
                        </div>
                        @if($rowDetail)
                        <div style="font-size:10px;color:var(--muted);line-height:1.4;">{{ $rowDetail }}</div>
                        @endif
                        @if($rowExtra > 0)
                        <div style="font-size:10px;color:var(--primary);font-weight:600;line-height:1.4;margin-top:2px;">+{{ $rowExtra }} more from suggested itinerary</div>
                        @endif
                    </div>
                </div>
                @endforeach
                @endif
            </div>

            {{-- Total --}}
            <div style="margin:0 16px 18px;padding:14px 16px;background:var(--primary-light);border-radius:14px;">
                <div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:4px;">Total Cost</div>
                <div style="font-size:23px;font-weight:800;color:var(--primary);">{{ $this->displayAmount($dt, $dtTotal) }}</div>

                {{-- Split bill — only meaningful once more than one person
                     is on the trip, so a solo trip doesn't show "÷ 1". --}}
                @if ($dt->head_count > 1)
                <div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(0,0,0,.08);display:flex;align-items:flex-end;justify-content:space-between;gap:10px;">
                    <div>
                        <div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:3px;">Per Person</div>
                        <div style="font-size:11px;color:var(--muted);">Split between {{ $dt->head_count }} travelers</div>
                    </div>
                    <div style="font-size:19px;font-weight:800;color:#C8874A;white-space:nowrap;">
                        {{ $this->displayAmount($dt, $dtTotal / $dt->head_count) }}
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif
        </div>
                </div>
                @endforeach
            </div>
            @if ($stTotalPages > 1)
            @php
                // Show first 4 pages, then an ellipsis + the last page once
                // the run grows past what fits comfortably (matches the
                // reference design: 1 2 3 4 … 10).
                $stPageNums = $stTotalPages <= 5
                    ? range(1, $stTotalPages)
                    : [1, 2, 3, 4, '…', $stTotalPages];
            @endphp
            <div style="display:flex;justify-content:flex-end;align-items:center;gap:8px;padding-top:20px;">
                <button type="button" @click="page = Math.max(1, page - 1)" :disabled="page === 1"
                        :style="'display:flex;align-items:center;gap:8px;height:38px;padding:0 16px;border-radius:10px;border:1.5px solid var(--border);background:transparent;color:var(--dark);font-size:13px;font-weight:700;font-family:inherit;' + (page === 1 ? 'opacity:.4;cursor:not-allowed;' : 'cursor:pointer;')">
                    <i class="fa-solid fa-chevron-left" style="font-size:11px;"></i> Prev
                </button>
                @foreach ($stPageNums as $p)
                    @if ($p === '…')
                    <span style="width:38px;text-align:center;color:var(--muted);font-size:13px;font-weight:700;">&hellip;</span>
                    @else
                    <button type="button" @click="page = {{ $p }}"
                            :style="'min-width:38px;height:38px;padding:0 4px;border-radius:10px;border:none;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s ease,color .15s ease;' + (page === {{ $p }} ? 'background:var(--primary);color:#fff;' : 'background:var(--bg);color:var(--muted);')">
                        {{ $p }}
                    </button>
                    @endif
                @endforeach
                <button type="button" @click="page = Math.min({{ $stTotalPages }}, page + 1)" :disabled="page === {{ $stTotalPages }}"
                        :style="'display:flex;align-items:center;gap:8px;height:38px;padding:0 16px;border-radius:10px;border:1.5px solid var(--border);background:transparent;color:var(--dark);font-size:13px;font-weight:700;font-family:inherit;' + (page === {{ $stTotalPages }} ? 'opacity:.4;cursor:not-allowed;' : 'cursor:pointer;')">
                    Next <i class="fa-solid fa-chevron-right" style="font-size:11px;"></i>
                </button>
            </div>
            @endif
            </div>
            @endif
        </div>
        @endforeach
        </div>
    </div>
    @endif

    {{-- Delete Confirmation Modal --}}
    @if ($deleteTripId)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);z-index:2100;display:flex;align-items:center;justify-content:center;padding:20px;">
        <div style="background:var(--bg-white);border-radius:24px;width:100%;max-width:380px;overflow:hidden;animation:stModalPop .22s cubic-bezier(.34,1.56,.64,1);">
            {{-- Icon header --}}
            <div style="position:relative;background:var(--bg);padding:36px 28px 24px;text-align:center;overflow:hidden;">
                <div style="width:64px;height:64px;border-radius:18px;background:linear-gradient(135deg,#EF4444,#DC2626);display:flex;align-items:center;justify-content:center;margin:0 auto 18px;position:relative;">
                    <i class="fa-solid fa-trash-can" style="font-size:24px;color:#fff;"></i>
                </div>
                <div style="font-size:19px;font-weight:800;color:var(--dark);margin-bottom:8px;">Delete Trip?</div>
                <div style="font-size:13px;color:var(--muted);line-height:1.6;max-width:280px;margin:0 auto;">
                    <strong style="color:var(--dark);">{{ $deleteTripName }}</strong> will be permanently deleted.<br>This action cannot be undone.
                </div>
            </div>
            {{-- Actions --}}
            <div style="display:flex;gap:10px;padding:20px 22px 22px;">
                <button wire:click="cancelDelete"
                        style="flex:1;background:transparent;color:var(--muted);border:1.5px solid var(--border);border-radius:12px;padding:12px 0;font-size:13px;font-weight:700;cursor:pointer;transition:background .18s,border-color .18s;"
                        onmouseenter="this.style.background='var(--border-light)';this.style.borderColor='var(--border)'" onmouseleave="this.style.background='transparent';this.style.borderColor='var(--border)'">
                    Cancel
                </button>
                <button wire:click="deleteTrip"
                        style="flex:1;background:#DC2626;color:#fff;border:none;border-radius:12px;padding:12px 0;font-size:13px;font-weight:700;cursor:pointer;transition:background .18s,transform .12s;"
                        onmouseenter="this.style.background='#B91C1C'" onmouseleave="this.style.background='#DC2626'"
                        onmousedown="this.style.transform='scale(.97)'" onmouseup="this.style.transform='scale(1)'">
                    <span wire:loading.remove wire:target="deleteTrip"><i class="fa-solid fa-trash-can" style="font-size:11px;margin-right:6px;"></i>Delete</span>
                    <span wire:loading wire:target="deleteTrip"><i class="fa-solid fa-spinner fa-spin"></i></span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Edit Trip Modal --}}
    @if ($editNameTripId)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);z-index:2100;display:flex;align-items:center;justify-content:center;padding:20px;">
        <div x-data="{ type: '{{ $editType }}' }" style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:24px;width:100%;max-width:400px;padding:26px;max-height:90vh;overflow-y:auto;animation:stModalPop .22s cubic-bezier(.34,1.56,.64,1);">

            {{-- Header --}}
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
                <div style="width:42px;height:42px;border-radius:13px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa-regular fa-pen-to-square" style="color:#fff;font-size:16px;"></i>
                </div>
                <span style="font-size:18px;font-weight:800;color:var(--dark);">Edit Trip</span>
            </div>

            {{-- Trip Name --}}
            <div style="margin-bottom:16px;">
                <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:7px;">Trip Name</div>
                <input wire:model="editNameValue" type="text" placeholder="e.g. Solo Getaway to Siargao"
                       style="width:100%;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;padding:12px 15px;font-size:14px;font-family:'Hanken Grotesk',sans-serif;color:var(--dark);outline:none;box-sizing:border-box;transition:border-color .18s,background .18s,box-shadow .18s;"
                       onfocus="this.style.borderColor='var(--primary)';this.style.boxShadow='0 0 0 4px rgba(147,75,25,0.08)'" onblur="this.style.borderColor='var(--border)';this.style.boxShadow='none'">
            </div>

            {{-- Travel Type --}}
            <div style="margin-bottom:16px;">
                <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:7px;">Type</div>
                <div style="display:flex;gap:8px;">
                    @foreach(['Solo','Group'] as $typeOpt)
                    <button type="button"
                            @click="type = '{{ $typeOpt }}'" wire:click="$set('editType', '{{ $typeOpt }}')"
                            :style="type === '{{ $typeOpt }}'
                                ? 'flex:1;padding:10px 4px;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;font-family:Hanken Grotesk,sans-serif;border:1.5px solid var(--primary);background:var(--primary-light);color:var(--primary);transition:.18s;'
                                : 'flex:1;padding:10px 4px;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;font-family:Hanken Grotesk,sans-serif;border:1.5px solid var(--border);background:var(--bg-white);color:var(--muted);transition:.18s;'">
                        {{ $typeOpt }}
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Group members (only when Group type) --}}
            <div x-show="type === 'Group'" x-transition style="margin-bottom:16px;">
                <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:8px;">Group Members</div>

                {{-- Already-saved members --}}
                @foreach($savedMembers as $sm)
                <div class="st-member-row st-member-row-saved">
                    <div class="st-member-avatar st-member-avatar-saved">{{ strtoupper(substr($sm['name'],0,1)) }}</div>
                    <div style="flex:1;min-width:0;">
                        <div class="st-member-name">{{ $sm['name'] }}</div>
                        <div class="st-member-email">{{ $sm['email'] }}</div>
                    </div>
                    <button wire:click="removeSavedMember({{ $sm['id'] }})" type="button" class="st-member-remove" title="Remove member">
                        <i class="fa-solid fa-xmark" style="font-size:11px;"></i>
                    </button>
                </div>
                @endforeach

                {{-- Pending (newly added) members --}}
                @foreach($pendingMembers as $pi => $pm)
                <div class="st-member-row st-member-row-pending">
                    <div class="st-member-avatar st-member-avatar-pending">{{ strtoupper(substr($pm['name'],0,1)) }}</div>
                    <div style="flex:1;min-width:0;">
                        <div class="st-member-name">{{ $pm['name'] }}</div>
                        <div class="st-member-email">{{ $pm['email'] }}</div>
                    </div>
                    <button wire:click="removePendingMember({{ $pi }})" type="button" class="st-member-remove" title="Remove member">
                        <i class="fa-solid fa-xmark" style="font-size:11px;"></i>
                    </button>
                </div>
                @endforeach

                {{-- Email lookup input --}}
                <div style="display:flex;gap:8px;margin-top:6px;">
                    <input wire:model="memberEmail" type="email" placeholder="Enter member's email address"
                           wire:keydown.enter.prevent="lookupMember"
                           style="flex:1;background:var(--bg-white);border:1.5px solid {{ $memberError ? '#DC2626' : 'var(--border)' }};border-radius:11px;padding:10px 13px;font-size:13px;font-family:'Hanken Grotesk',sans-serif;color:var(--dark);outline:none;box-sizing:border-box;transition:border-color .18s,background .18s;"
                           onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='{{ $memberError ? '#DC2626' : 'var(--border)' }}'">
                    <button wire:click="lookupMember" type="button"
                            style="flex-shrink:0;background:var(--primary);color:#fff;border:none;border-radius:11px;padding:10px 16px;font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:5px;font-family:'Hanken Grotesk',sans-serif;transition:background .18s;"
                            onmouseenter="this.style.background='var(--primary-dark)'" onmouseleave="this.style.background='var(--primary)'">
                        <i class="fa-solid fa-plus" style="font-size:10px;"></i> Add
                    </button>
                </div>
                @if($memberError)
                <div style="margin-top:6px;font-size:12px;color:#DC2626;">{{ $memberError }}</div>
                @endif
                <div style="margin-top:6px;font-size:11px;color:var(--muted);">Only registered users can be added as members.</div>
            </div>

            {{-- Status --}}
            <div style="margin-bottom:24px;">
                <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:7px;">Status</div>
                <div style="display:flex;gap:8px;">
                    @foreach(['upcoming'=>'Upcoming','active'=>'Ongoing','past'=>'Finished'] as $statusVal => $statusLabel)
                    <button type="button" wire:click="$set('editStatus', '{{ $statusVal }}')"
                            style="flex:1;padding:10px 4px;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;font-family:'Hanken Grotesk',sans-serif;transition:.18s;border:1.5px solid {{ $editStatus === $statusVal ? 'var(--primary)' : 'var(--border)' }};background:{{ $editStatus === $statusVal ? 'var(--primary-light)' : 'var(--bg-white)' }};color:{{ $editStatus === $statusVal ? 'var(--primary)' : 'var(--muted)' }};">
                        {{ $statusLabel }}
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Actions --}}
            <div style="display:flex;gap:10px;">
                <button wire:click="cancelEditName"
                        style="flex:1;background:transparent;color:var(--muted);border:1.5px solid var(--border);border-radius:12px;padding:12px 0;font-size:13px;font-weight:700;cursor:pointer;font-family:'Hanken Grotesk',sans-serif;transition:background .18s,border-color .18s;"
                        onmouseenter="this.style.background='var(--border-light)';this.style.borderColor='var(--border)'" onmouseleave="this.style.background='transparent';this.style.borderColor='var(--border)'">
                    Cancel
                </button>
                <button wire:click="saveEditName"
                        style="flex:1;background:var(--primary);color:#fff;border:none;border-radius:12px;padding:12px 0;font-size:13px;font-weight:700;cursor:pointer;font-family:'Hanken Grotesk',sans-serif;transition:background .18s,transform .12s;"
                        onmouseenter="this.style.background='var(--primary-dark)'" onmouseleave="this.style.background='var(--primary)'"
                        onmousedown="this.style.transform='scale(.97)'" onmouseup="this.style.transform='scale(1)'">
                    Save
                </button>
            </div>

        </div>
    </div>
    @endif

    {{-- Share Trip Modal --}}
    @if ($shareTripId)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:2100;display:flex;align-items:center;justify-content:center;padding:20px;">
        <div style="background:var(--bg-white);border-radius:20px;width:100%;max-width:380px;overflow:hidden;">
            <div style="padding:28px 24px 20px;text-align:center;">
                <div style="width:52px;height:52px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                    <i class="fa-solid fa-share-nodes" style="font-size:20px;color:var(--primary);"></i>
                </div>

                @if ($shareNotAvailable)
                <div style="font-size:16px;font-weight:700;color:var(--dark);margin-bottom:6px;">Not Available to Share</div>
                <div style="font-size:13px;color:var(--muted);line-height:1.5;margin-bottom:18px;">
                    This trip was saved before sharing was added, so it has no selections to share. Available for trips saved after this update.
                </div>
                <button type="button" wire:click="closeShareModal"
                        style="width:100%;background:transparent;color:var(--muted);border:1.5px solid var(--border);border-radius:10px;padding:11px 0;font-size:13px;font-weight:600;cursor:pointer;">
                    Close
                </button>
                @else
                <div style="font-size:16px;font-weight:700;color:var(--dark);margin-bottom:6px;">Share This Trip</div>
                <div style="font-size:13px;color:var(--muted);line-height:1.5;margin-bottom:18px;">
                    Share your trip selections such as transportation to attractions, and selected itinerary to other travelers, and they can paste in Planner > Manual Planning.
                </div>

                <div style="text-align:left;margin-bottom:14px;">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:6px;">Share via Link</div>
                    <div style="display:flex;gap:8px;">
                        <input readonly value="{{ $shareLink }}" id="share-link-value"
                               style="flex:1;min-width:0;background:var(--bg-white);border:1.5px solid var(--border);border-radius:8px;padding:9px 10px;font-size:12px;color:var(--dark);">
                        <button type="button" onclick="
                            navigator.clipboard.writeText('{{ $shareLink }}');
                            this.textContent = 'Copied!';
                            setTimeout(() => this.textContent = 'Copy', 1500);
                        " style="background:var(--primary);color:#fff;border:none;border-radius:8px;padding:0 16px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;">
                            Copy
                        </button>
                    </div>
                </div>

                <div style="text-align:left;margin-bottom:20px;">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:6px;">Share via Code</div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="flex:1;font-family:monospace;font-size:20px;font-weight:700;letter-spacing:4px;background:var(--bg);border:1.5px solid var(--border);border-radius:8px;padding:10px;color:var(--dark);text-align:center;">
                            {{ $shareCode }}
                        </div>
                        <button type="button" onclick="
                            navigator.clipboard.writeText('{{ $shareCode }}');
                            this.textContent = 'Copied!';
                            setTimeout(() => this.textContent = 'Copy', 1500);
                        " style="background:var(--primary);color:#fff;border:none;border-radius:8px;padding:0 16px;height:40px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;">
                            Copy
                        </button>
                    </div>
                </div>

                <button type="button" wire:click="closeShareModal"
                        style="width:100%;background:transparent;color:var(--muted);border:1.5px solid var(--border);border-radius:10px;padding:11px 0;font-size:13px;font-weight:600;cursor:pointer;">
                    Close
                </button>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>
