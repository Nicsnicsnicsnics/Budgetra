<div style="display:flex;flex-direction:column;flex:1;">
    @php $fmtDate = fn ($d, $f) => str_replace('Sep', 'Sept', $d->format($f)); @endphp
    @if ($trips->isEmpty() && !$search)
    {{-- Pure empty state — no header, no stats --}}
    <div class="empty-state-center" style="min-height:80vh;">
        <div style="width:64px;height:64px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
            <i class="fa-solid fa-layer-group" style="font-size:28px;color:#fff;"></i>
        </div>
        @if (!auth()->user()?->userProfile)
        <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:var(--dark);">Set up your profile first</h2>
        <p style="color:var(--muted);margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Complete your travel profile before planning a trip and comparing trips.</p>
        <a href="{{ route('profile.setup') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
            <i class="fa-solid fa-user"></i> Set Up Your Profile First
        </a>
        @else
        <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:var(--dark);">No trips planned yet</h2>
        <p style="color:var(--muted);margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Plan a trip first to view active and past trips, and compare trips.</p>
        <a href="{{ route('trips.plan') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
            <i class="fa-solid fa-plane"></i> Plan Your First Trip
        </a>
        @endif
    </div>
    @else

    {{-- Trips accordion --}}
    @php
        $mthActiveTrips = $trips->whereIn('status', ['active', 'upcoming']);
        $mthPastTrips   = $trips->where('status', 'past');
        $mthGroups = [
            ['key' => 'active', 'label' => 'Active Trips', 'icon' => 'fa-solid fa-suitcase-rolling',  'items' => $mthActiveTrips],
            ['key' => 'past',   'label' => 'Past Trips',   'icon' => 'fa-solid fa-clock-rotate-left', 'items' => $mthPastTrips],
        ];
    @endphp
    <div x-data="{ tab: 'active' }" style="display:flex;flex-direction:column;">
        {{-- Browser-tab-style switcher --}}
        <div style="display:flex;align-items:flex-end;gap:4px;flex-shrink:0;flex-wrap:wrap;">
            @foreach ($mthGroups as $mthGroup)
            @php $mthCount = $mthGroup['items']->count(); @endphp
            <button @click="tab = '{{ $mthGroup['key'] }}'" type="button"
                    :style="'display:flex;align-items:center;gap:10px;padding:12px 22px;border:1.5px solid;border-bottom:none;border-radius:18px 18px 0 0;cursor:pointer;font-family:inherit;position:relative;white-space:nowrap;flex-wrap:nowrap;transition:background .15s ease,color .15s ease;' + (tab === '{{ $mthGroup['key'] }}' ? 'background:var(--bg-white);border-color:var(--border);color:var(--dark);z-index:2;margin-bottom:-1.5px;' : 'background:var(--bg);border-color:transparent;color:var(--muted);z-index:1;')">
                <div style="width:26px;height:26px;border-radius:8px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="{{ $mthGroup['icon'] }}" style="color:#fff;font-size:11px;"></i>
                </div>
                <span style="font-size:14px;font-weight:700;">{{ $mthGroup['label'] }}</span>
                <span style="font-size:11px;font-weight:800;{{ $mthCount > 0 ? 'color:#fff;background:var(--primary);' : 'color:var(--muted);background:var(--bg);' }}border-radius:99px;min-width:20px;height:20px;padding:0 6px;display:inline-flex;align-items:center;justify-content:center;line-height:1;">{{ $mthCount }}</span>
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
        <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:0 16px 16px 16px;padding:24px;display:flex;flex-direction:column;position:relative;">
        @foreach ($mthGroups as $mthGroup)
        <div x-show="tab === '{{ $mthGroup['key'] }}'" x-cloak style="display:flex;flex-direction:column;">
            @if ($mthGroup['items']->isEmpty())
            {{-- A search that matched nothing is a different situation from an
                 empty tab: the trips may well exist, just not under this term.
                 Saying "No active trips yet" there would be wrong, and would
                 point the traveler at planning a trip they already have. --}}
            <div style="min-height:360px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:40px 20px;">
                <div style="width:56px;height:56px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
                    <i class="{{ $search ? 'fa-solid fa-magnifying-glass' : $mthGroup['icon'] }}" style="font-size:24px;color:#fff;"></i>
                </div>
                @if ($search)
                <h3 style="font-weight:700;font-size:17px;margin:0 0 6px;color:var(--dark);">No trips found</h3>
                <p style="color:var(--muted);font-size:13px;max-width:280px;line-height:1.6;margin:0;">Try searching another trip.</p>
                @else
                <h3 style="font-weight:700;font-size:17px;margin:0 0 6px;color:var(--dark);">No {{ $mthGroup['label'] }} yet</h3>
                <p style="color:var(--muted);font-size:13px;max-width:280px;line-height:1.6;margin:0;">
                    @if ($mthGroup['key'] === 'active')
                    Plan a trip first to see your active trips.
                    @else
                    Plan a trip first to see your past trips.
                    @endif
                </p>
                @endif
            </div>
            @else
            <div style="display:flex;flex-wrap:wrap;gap:20px;justify-content:center;">
                @foreach ($mthGroup['items'] as $trip)
                @php
                    $tType      = strtoupper($trip->travel_type ?? 'Solo');
                    $pct        = min(100, $trip->pct_used);
                    $typeColor  = $tType === 'GROUP' ? '#A855F7' : '#14B8A6';
                    $statusColor = match ($trip->status) {
                        'active'   => '#22C55E',
                        'upcoming' => '#3B82F6',
                        default    => 'var(--muted)',
                    };
                    $statusLabel = match ($trip->status) { 'active' => 'Ongoing', 'upcoming' => 'Upcoming', 'past' => 'Finished', default => ucfirst($trip->status) };
                    $isPickedForCompare = in_array($trip->id, $compareIds, true);
                @endphp
                <div class="card" style="overflow:hidden;border-radius:14px;width:340px;flex-shrink:0;{{ $isPickedForCompare ? 'border-color:var(--primary);box-shadow:0 0 0 2px var(--primary);' : '' }}">
                    <div style="position:relative;height:200px;background:linear-gradient(135deg,var(--primary),#C8874A);">
                        @if ($trip->cover_image)
                        <img src="{{ $trip->cover_image }}" style="width:100%;height:100%;object-fit:cover;display:block;" alt="{{ $trip->destination }}">
                        @endif
                        <div style="position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,0.15),rgba(0,0,0,0.55));"></div>

                        {{-- Stacked badges top-left --}}
                        <div style="position:absolute;top:14px;left:14px;display:flex;flex-direction:column;gap:6px;">
                            <span style="background:{{ $typeColor }};color:#fff;font-size:11px;font-weight:700;letter-spacing:0.5px;padding:4px 12px;border-radius:20px;display:inline-block;text-align:center;">{{ $tType }}</span>
                            <span style="background:{{ $statusColor }};color:#fff;font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;text-transform:uppercase;display:inline-block;">{{ $statusLabel }}</span>
                        </div>

                        {{-- Trip info overlay --}}
                        <div style="position:absolute;bottom:12px;left:16px;right:16px;">
                            <div style="font-size:19px;font-weight:700;color:#fff;line-height:1.3;margin-bottom:8px;">
                                {{ $trip->trip_name ?? $trip->destination }}
                            </div>
                            <div style="display:flex;flex-direction:column;gap:5px;">
                                <div style="display:flex;align-items:center;gap:14px;font-size:12px;color:rgba(255,255,255,0.9);flex-wrap:wrap;">
                                    <span style="display:flex;align-items:center;gap:6px;"><i class="fa-solid fa-plane" style="font-size:11px;color:#F5C97A;"></i>{{ $trip->origin_code ?? 'MNL' }} to {{ $trip->destination_code ?? '—' }}</span>
                                </div>
                                <div style="display:flex;align-items:center;gap:14px;font-size:12px;color:rgba(255,255,255,0.9);flex-wrap:wrap;">
                                    <span style="display:flex;align-items:center;gap:6px;"><i class="fa-regular fa-calendar-days" style="font-size:11px;color:#F5C97A;"></i>{{ $fmtDate($trip->start_date, 'M j') }} - {{ $fmtDate($trip->end_date, 'M j, Y') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div style="padding:20px 22px 22px;display:flex;flex-direction:column;flex:1;">
                        @php $cardPct = min(100, $trip->pct_used); @endphp
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <span style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);">Used</span>
                            <span style="font-size:15px;font-weight:700;color:var(--dark);">{{ $cardPct }}%</span>
                        </div>
                        <div style="height:6px;background:var(--border-light);border-radius:99px;overflow:hidden;margin-bottom:18px;">
                            <div style="height:100%;width:{{ $cardPct }}%;background:var(--primary);border-radius:99px;transition:width 0.3s;"></div>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:20px;">
                            <div>
                                <div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Spent</div>
                                <div style="font-size:23px;font-weight:800;color:#C8874A;">{{ currency_symbol() }}{{ number_format($trip->total_spent, 2) }}</div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Budget Allocated</div>
                                <div style="font-size:23px;font-weight:800;color:var(--dark);">{{ currency_symbol() }}{{ number_format($trip->total_cost ?? $trip->budget_limit, 2) }}</div>
                            </div>
                        </div>

                        <div style="display:flex;gap:10px;margin-top:auto;">
                            <button type="button" class="btn {{ $isPickedForCompare ? '' : 'btn-primary' }}"
                                    style="flex:1;text-transform:uppercase;letter-spacing:.05em;font-size:12px;display:flex;align-items:center;justify-content:center;gap:6px;{{ $isPickedForCompare ? 'background:var(--primary-light);color:var(--primary);border:1.5px solid var(--primary);' : '' }}"
                                    wire:click="toggleCompare({{ $trip->id }})">
                                <i class="fa-solid {{ $isPickedForCompare ? 'fa-check' : 'fa-code-compare' }}" style="font-size:11px;"></i>
                                {{ $isPickedForCompare ? 'Selected' : 'Compare' }}
                            </button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endforeach
        </div>
    </div>

    @endif {{-- end @else (has trips) --}}

    {{-- Floating compare bar — appears once two cards are picked --}}
    @if (count($compareIds) === 2)
    <div style="position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:1000;background:var(--bg-white);border:1.5px solid var(--primary);border-radius:16px;box-shadow:0 8px 28px rgba(0,0,0,.18);padding:12px 16px;display:flex;align-items:center;gap:14px;">
        <span style="font-size:13px;font-weight:600;color:var(--dark);white-space:nowrap;">
            <i class="fa-solid fa-code-compare" style="color:var(--primary);margin-right:6px;"></i> 2 trips selected
        </span>
        <button type="button" wire:click="clearCompareSelection" class="btn btn-outline" style="padding:12px 18px;">Clear</button>
        <button type="button" wire:click="runComparison" class="btn btn-primary" style="white-space:nowrap;">Compare Trips</button>
    </div>
    @endif

    {{-- Details Modal --}}
    @if ($detailTrip)
    @php $dt = $detailTrip; @endphp
    <div style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2000;display:flex;align-items:center;justify-content:center;padding:20px;" wire:click.self="closeDetail">
        <div style="background:var(--bg-white);border-radius:20px;max-width:480px;width:100%;max-height:90vh;overflow-y:auto;padding:28px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <h2 style="margin:0;">{{ $dt->trip_name ?? $dt->destination }}</h2>
                    <span style="background:#EFF6FF;color:#1D4ED8;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;text-transform:uppercase;white-space:nowrap;">{{ $dt->status }}</span>
                </div>
                <button wire:click="closeDetail" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--muted);line-height:1;">&times;</button>
            </div>
            <div class="text-muted" style="font-size:13px;margin-bottom:16px;">{{ $fmtDate($dt->start_date, 'M j') }} - {{ $fmtDate($dt->end_date, 'M j, Y') }}</div>

            <div style="height:170px;border-radius:14px;overflow:hidden;margin-bottom:18px;background:linear-gradient(135deg,var(--primary),#C8874A);">
                @if ($dt->cover_image)
                <img src="{{ $dt->cover_image }}" style="width:100%;height:100%;object-fit:cover;display:block;" alt="{{ $dt->destination }}">
                @endif
            </div>

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px;">
                <div>
                    <div class="text-muted" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px;">Start Date</div>
                    <div style="font-size:13px;font-weight:600;">{{ $fmtDate($dt->start_date, 'M j') }}</div>
                </div>
                <div>
                    <div class="text-muted" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px;">End Date</div>
                    <div style="font-size:13px;font-weight:600;">{{ $fmtDate($dt->end_date, 'M j') }}</div>
                </div>
                <div>
                    <div class="text-muted" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px;">Duration</div>
                    <div style="font-size:13px;font-weight:600;">{{ $dt->days }} days</div>
                </div>
                <div>
                    <div class="text-muted" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px;">Group</div>
                    <div style="font-size:13px;font-weight:600;">{{ $dt->travel_type ?? 'Solo' }}</div>
                </div>
            </div>

            @php
                $spent     = $dt->total_spent;
                $budget    = (float) $dt->budget_limit;
                $remaining = $budget - $spent;
            @endphp
            <div style="background:var(--border-light);border-radius:12px;padding:16px;margin-bottom:18px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                    <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);">Budget Usage</span>
                    <span style="font-size:12px;font-weight:700;">{{ $dt->pct_used }}% Expended</span>
                </div>
                <div style="height:6px;background:var(--border-light);border-radius:99px;overflow:hidden;margin-bottom:12px;">
                    <div style="height:100%;width:{{ min(100,$dt->pct_used) }}%;background:var(--primary);border-radius:99px;"></div>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <div>
                        <div class="text-muted" style="font-size:11px;">Spent</div>
                        <div style="font-weight:700;">{{ currency_symbol() }}{{ number_format($spent, 2) }}</div>
                    </div>
                    <div style="text-align:right;">
                        <div class="text-muted" style="font-size:11px;">Total Budget</div>
                        <div style="font-weight:700;">{{ currency_symbol() }}{{ number_format($budget, 2) }}</div>
                    </div>
                </div>
                <div class="text-muted" style="font-size:12px;margin-top:10px;">Remaining: {{ currency_symbol() }}{{ number_format($remaining, 2) }}</div>
            </div>

            <div style="display:flex;gap:10px;">
                <a href="{{ route('expenses.index') }}?trip_id={{ $dt->id }}" class="btn btn-secondary" style="flex:1;text-align:center;">Expenses</a>
                <a href="{{ route('itinerary.index') }}" class="btn btn-primary" style="flex:1;text-align:center;">Itinerary</a>
            </div>
        </div>
    </div>
    @endif

    {{-- Compare Modal --}}
    @if ($showComparison && count($compareData) === 2)
    @php
        [$a, $b] = $compareData;
        $ta = $a['trip']; $tb = $b['trip'];
        // % used against the SAME savings-based budget figure the cards
        // below display — computed here too so the win/lose ribbon always
        // agrees with what's actually shown, instead of secretly ranking
        // by the old budget_limit while the cards show savings data.
        $aPctUsed = $a['budget'] > 0 ? round($ta->total_spent / $a['budget'] * 100) : 0;
        $bPctUsed = $b['budget'] > 0 ? round($tb->total_spent / $b['budget'] * 100) : 0;
        // "Better" trip = whichever used less of its budget (relative
        // comparison, not just its own over/under status) — a tie means
        // neither card gets a win/lose treatment.
        $tie = $aPctUsed === $bPctUsed;
        $aWins = !$tie && $aPctUsed < $bPctUsed;
    @endphp
    <div class="cmp-backdrop" wire:click.self="closeComparison">
        <div class="cmp-modal">
            <div class="cmp-header">
                <div class="cmp-header-icon"><i class="fa-solid fa-code-compare"></i></div>
                <div>
                    <h2 class="cmp-title">Compare Trips</h2>
                    <p class="cmp-subtitle">Comparing your recent trips to {{ $ta->destination }} and {{ $tb->destination }} to identify spending patterns.</p>
                </div>
            </div>

            <div class="cmp-cards">
                @foreach ([['data' => $a, 'wins' => $aWins], ['data' => $b, 'wins' => !$tie && !$aWins]] as $entry)
                @php
                    $t       = $entry['data']['trip'];
                    $budgetAmount = $entry['data']['budget'];
                    $diff    = $budgetAmount - $t->total_spent;
                    $over    = $diff < 0;
                    $pctUsed = $budgetAmount > 0 ? min(100, round($t->total_spent / $budgetAmount * 100)) : 0;
                    $verdict = $tie ? 'neutral' : ($entry['wins'] ? 'win' : 'lose');
                @endphp
                <div class="cmp-card cmp-card-{{ $verdict }}">
                    @if ($verdict === 'win')
                    <div class="cmp-ribbon"><i class="fa-solid fa-trophy"></i> Better value</div>
                    @endif
                    <div class="cmp-card-top">
                        <span class="cmp-status-chip cmp-status-{{ $over ? 'over' : 'under' }}">
                            <i class="fa-solid {{ $over ? 'fa-triangle-exclamation' : 'fa-circle-check' }}"></i>
                            {{ $over ? 'Over budget · overspent' : 'Under budget · saved' }} {{ currency_symbol() }}{{ number_format(abs($diff), 0) }}
                        </span>
                    </div>
                    <div class="cmp-route">{{ $t->origin_code ?? 'MNL' }} <i class="fa-solid fa-arrow-right"></i> {{ $t->destination_code ?? '—' }}</div>
                    <div class="cmp-dates">{{ $t->destination }} · {{ $fmtDate($t->start_date, 'M j') }} - {{ $fmtDate($t->end_date, 'M j, Y') }}</div>

                    <div class="cmp-stat-row">
                        <span>Budget</span><span>Actual Spend</span>
                    </div>
                    <div class="cmp-stat-row cmp-stat-values">
                        <span>{{ currency_symbol() }}{{ number_format($budgetAmount, 0) }}</span>
                        <span class="cmp-{{ $over ? 'over' : 'under' }}-text">{{ currency_symbol() }}{{ number_format($t->total_spent, 0) }}</span>
                    </div>
                    <div class="cmp-bar-track">
                        <div class="cmp-bar-fill cmp-bar-{{ $over ? 'over' : 'under' }}" style="width:{{ $pctUsed }}%;"></div>
                    </div>
                    <div class="cmp-pct-label">{{ $pctUsed }}% of budget used</div>
                </div>
                @endforeach
            </div>

            <h3 class="cmp-section-title">Spending by Category</h3>
            <div class="cmp-legend">
                <span><span class="cmp-legend-dot" style="background:var(--primary);"></span>{{ $ta->destination }}</span>
                <span><span class="cmp-legend-dot" style="background:#F5A623;"></span>{{ $tb->destination }}</span>
            </div>

            @php
                $catIcons = [
                    'Transportation' => 'fa-plane', 'Accommodation' => 'fa-bed', 'Food' => 'fa-utensils',
                    'Tourist Attractions' => 'fa-camera-retro', 'Shopping' => 'fa-bag-shopping', 'Emergency Expenses' => 'fa-shield-halved',
                ];
            @endphp
            <div class="cmp-cat-grid">
                @foreach ($a['categories'] as $cat => $aVal)
                @php
                    $bVal    = $b['categories'][$cat] ?? 0;
                    $max     = max($aVal, $bVal, 1);
                    $label   = $cat === 'Tourist Attractions' ? 'Attractions' : $cat;
                    $aCheaper = $aVal < $bVal; $bCheaper = $bVal < $aVal;
                @endphp
                <div class="cmp-cat-card">
                    <div class="cmp-cat-head">
                        <div class="cmp-cat-icon"><i class="fa-solid {{ $catIcons[$cat] ?? 'fa-tag' }}"></i></div>
                        <div class="cmp-cat-label">{{ $label }}</div>
                    </div>
                    <div class="cmp-cat-bar-row">
                        <div class="cmp-bar-track">
                            <div class="cmp-bar-fill" style="width:{{ $max > 0 ? min(100, $aVal / $max * 100) : 0 }}%;background:var(--primary);"></div>
                        </div>
                        <span class="cmp-cat-value {{ $aCheaper ? 'cmp-under-text' : ($bCheaper ? 'cmp-over-text' : '') }}">{{ currency_symbol() }}{{ number_format($aVal, 0) }}</span>
                    </div>
                    <div class="cmp-cat-bar-row">
                        <div class="cmp-bar-track">
                            <div class="cmp-bar-fill" style="width:{{ $max > 0 ? min(100, $bVal / $max * 100) : 0 }}%;background:#F5A623;"></div>
                        </div>
                        <span class="cmp-cat-value {{ $bCheaper ? 'cmp-under-text' : ($aCheaper ? 'cmp-over-text' : '') }}">
                            {{ currency_symbol() }}{{ number_format($bVal, 0) }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>

            <div style="display:flex;justify-content:flex-end;margin-top:20px;">
                <button class="btn btn-outline btn-sm" wire:click="closeComparison">Close</button>
            </div>
        </div>
    </div>
    @endif
</div>

<style>
    .cmp-backdrop {
        position: fixed; inset: 0; background: rgba(10,8,16,.6); backdrop-filter: blur(3px);
        z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 20px;
    }
    .cmp-modal {
        position: relative; background: var(--bg-white); border: 1.5px solid var(--border);
        border-radius: 22px; max-width: 720px; width: 100%; max-height: 90vh; overflow-y: auto;
        padding: 30px; box-shadow: 0 24px 60px rgba(0,0,0,.3);
    }

    .cmp-header { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 24px; }
    .cmp-header-icon {
        width: 44px; height: 44px; border-radius: 13px; background: var(--primary-light); color: var(--primary);
        display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;
    }
    .cmp-title { font-size: 20px; font-weight: 800; color: var(--dark); margin: 0 0 4px; }
    .cmp-subtitle { font-size: 13px; color: var(--muted); margin: 0; line-height: 1.5; }

    .cmp-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 28px; }
    @media (max-width: 560px) { .cmp-cards { grid-template-columns: 1fr; } }

    .cmp-card {
        position: relative; border: 1.5px solid var(--border); border-radius: 16px; padding: 18px;
        background: var(--bg-white); overflow: hidden;
    }
    .cmp-card-win { border-color: #4ADE80; box-shadow: 0 0 0 1px #4ADE80 inset; }
    .cmp-card-lose { border-color: #FF4D6D; box-shadow: 0 0 0 1px #FF4D6D inset; }

    .cmp-ribbon {
        display: flex; align-items: center; gap: 6px; width: fit-content;
        background: color-mix(in srgb, #4ADE80 22%, var(--bg-white)); color: #16A34A;
        font-size: 10.5px; font-weight: 800; letter-spacing: .03em; text-transform: uppercase;
        padding: 4px 10px; border-radius: 99px; margin-bottom: 10px;
    }

    .cmp-card-top { margin-bottom: 12px; }
    .cmp-status-chip {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 11px; font-weight: 700; padding: 5px 11px; border-radius: 20px; white-space: nowrap;
    }
    .cmp-status-chip i { font-size: 10px; }
    .cmp-status-under { background: color-mix(in srgb, #4ADE80 20%, var(--bg-white)); color: #16A34A; }
    .cmp-status-over  { background: color-mix(in srgb, #FF4D6D 18%, var(--bg-white)); color: #E11D48; }

    .cmp-route { font-weight: 700; color: var(--dark); font-size: 15px; }
    .cmp-route i { font-size: 11px; color: var(--muted); margin: 0 2px; }
    .cmp-dates { color: var(--muted); font-size: 12px; margin-bottom: 14px; }

    .cmp-stat-row { display: flex; justify-content: space-between; font-size: 11px; color: var(--muted); margin-bottom: 4px; }
    .cmp-stat-values { font-weight: 700; font-size: 14px; color: var(--dark); margin-bottom: 8px; }
    .cmp-under-text { color: #16A34A; }
    .cmp-over-text  { color: #E11D48; }

    .cmp-bar-track { flex: 1; min-width: 0; height: 7px; background: var(--bg); border: 1px solid var(--border); border-radius: 99px; overflow: hidden; box-sizing: border-box; }
    .cmp-bar-fill { height: 100%; border-radius: 99px; min-width: 3px; }
    .cmp-bar-under { background: #4ADE80; }
    .cmp-bar-over  { background: #FF4D6D; }
    .cmp-pct-label { color: var(--muted); font-size: 11px; margin-top: 6px; }

    .cmp-section-title { font-size: 15px; font-weight: 800; color: var(--dark); margin: 0 0 10px; }
    .cmp-legend { display: flex; gap: 16px; font-size: 12px; color: var(--dark); margin-bottom: 16px; }
    .cmp-legend-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; }

    .cmp-cat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 560px) { .cmp-cat-grid { grid-template-columns: 1fr; } }
    .cmp-cat-card { border: 1.5px solid var(--border); border-radius: 14px; padding: 14px 16px; background: var(--bg-white); }
    .cmp-cat-head { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
    .cmp-cat-icon {
        width: 30px; height: 30px; border-radius: 9px; background: var(--primary-light); color: var(--primary);
        display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0;
    }
    .cmp-cat-label { font-size: 13px; font-weight: 700; color: var(--dark); }
    .cmp-cat-bar-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
    .cmp-cat-bar-row:last-child { margin-bottom: 0; }
    .cmp-cat-value { font-size: 11.5px; width: 92px; text-align: right; flex-shrink: 0; color: var(--dark); font-weight: 700; }
</style>
