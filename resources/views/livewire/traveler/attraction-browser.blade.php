<div>
    {{-- Filters --}}
    <div class="attr-filters mb-24">
        <div class="attr-filter-input">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search attractions...">
        </div>
        <div class="attr-filter-select">
            <i class="fa-solid fa-location-dot"></i>
            <select wire:model.live="destination">
                <option value="">All destinations</option>
                @foreach ($this->destinations as $dest)
                <option value="{{ $dest }}">{{ $dest }}</option>
                @endforeach
            </select>
            <i class="fa-solid fa-chevron-down attr-select-caret"></i>
        </div>
    </div>

    {{-- Result list --}}
    @if ($this->attractions->isEmpty())
    <div class="attr-empty">
        <div class="attr-empty-icon"><i class="fa-solid fa-mountain-sun"></i></div>
        <h3>No attractions found</h3>
        <p>Try a different search or destination filter.</p>
    </div>
    @else
    <div class="attr-list">
        @foreach ($this->attractions as $attraction)
        @php
            $categoryMeta = [
                'Culture'   => ['icon' => 'fa-landmark',      'color' => '#C084FC'],
                'Nature'    => ['icon' => 'fa-leaf',          'color' => '#4ADE80'],
                'Adventure' => ['icon' => 'fa-person-hiking', 'color' => '#FB923C'],
            ][$attraction->category] ?? ['icon' => 'fa-map-pin', 'color' => 'var(--primary)'];
            $ratingRounded = round($attraction->rating);
        @endphp
        <a href="{{ route('attractions.show', $attraction) }}" class="attr-row">
            <div class="attr-row-media">
                @if ($attraction->image)
                <img src="{{ asset('storage/' . $attraction->image) }}" alt="{{ $attraction->name }}" loading="lazy" decoding="async">
                @else
                {{-- The common case by a wide margin: most attractions have no
                     photo, which is half the reason this is a list and not a
                     grid of mostly-empty 4:3 boxes. --}}
                <div class="attr-row-noimg">
                    <i class="fa-solid fa-image"></i>
                    <span>Photo coming soon</span>
                </div>
                @endif
            </div>

            <div class="attr-row-body">
                <div class="attr-row-name">{{ $attraction->name }}</div>
                <div class="attr-row-stars">
                    @for ($i = 1; $i <= 5; $i++)
                        <i class="fa-{{ $i <= $ratingRounded ? 'solid' : 'regular' }} fa-star"></i>
                    @endfor
                </div>
                <div class="attr-row-meta">
                    <span><i class="fa-solid fa-location-dot"></i> {{ $attraction->destination }}</span>
                    @if ($attraction->category)
                    {{-- Inline now rather than floated over the photo: there is
                         no tall image left to sit on. --}}
                    <span class="attr-chip attr-chip-category" style="--chip-color:{{ $categoryMeta['color'] }};">
                        <i class="fa-solid {{ $categoryMeta['icon'] }}"></i> {{ $attraction->category }}
                    </span>
                    @endif
                </div>
            </div>

            <div class="attr-row-end">
                <span class="attr-chip attr-chip-rating">
                    <i class="fa-solid fa-star"></i> {{ number_format($attraction->rating, 1) }}
                </span>
                {{-- Replaces the "View Reviews" bar. The whole row is the link,
                     so it only has to point. --}}
                <i class="fa-solid fa-chevron-right attr-row-go"></i>
            </div>
        </a>
        @endforeach
    </div>
    @endif

    <style>
        .attr-filters { display: grid; grid-template-columns: 1fr 240px; gap: 14px; max-width: 640px; }
        .attr-filter-input, .attr-filter-select {
            position: relative; display: flex; align-items: center;
            background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 12px;
        }
        .attr-filter-input i, .attr-filter-select > i:first-child { padding-left: 14px; color: var(--muted); font-size: 13px; flex-shrink: 0; }
        .attr-filter-input input, .attr-filter-select select {
            width: 100%; border: none; background: none; outline: none;
            padding: 11px 12px; font-size: 13.5px; color: var(--dark); font-family: inherit;
            appearance: none; -webkit-appearance: none;
        }
        .attr-select-caret { padding-right: 14px; color: var(--muted); font-size: 10px; pointer-events: none; }
        .attr-filter-input:focus-within, .attr-filter-select:focus-within { border-color: var(--primary); }

        .attr-list { display: flex; flex-direction: column; gap: 12px; }

        /* One result per row: thumbnail, details, rating. Named -row rather
           than -card because trip-planner-wizard.blade.php already has its own
           page-local .attr-card with different rules, and the two would now be
           confusingly similar. */
        .attr-row {
            display: grid; grid-template-columns: 116px 1fr auto; align-items: stretch;
            text-decoration: none; color: inherit;
            background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 14px;
            overflow: hidden; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        /* A smaller lift than the grid's -5px: repeated down a list, a large
           hop reads as the page twitching. */
        .attr-row:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 26px rgba(0,0,0,0.12);
            border-color: var(--primary);
        }
        .attr-row:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }

        .attr-row-media { position: relative; overflow: hidden; background: var(--bg); min-height: 104px; }
        .attr-row-media img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .35s ease; }
        .attr-row:hover .attr-row-media img { transform: scale(1.06); }

        .attr-row-noimg {
            position: absolute; inset: 0; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 5px;
            background: linear-gradient(160deg, var(--border-light) 0%, var(--border) 100%);
            color: var(--muted); text-align: center; padding: 6px;
        }
        .attr-row-noimg i { font-size: 17px; opacity: .6; }
        .attr-row-noimg span { font-size: 9.5px; font-weight: 600; line-height: 1.25; }

        .attr-row-body {
            min-width: 0; padding: 13px 16px;
            display: flex; flex-direction: column; justify-content: center; gap: 5px;
        }
        .attr-row-name {
            font-size: 15px; font-weight: 700; color: var(--dark); line-height: 1.3;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .attr-row-stars { color: #F5A623; font-size: 10.5px; letter-spacing: 2px; }
        .attr-row-stars i.fa-regular { color: var(--border); }

        .attr-row-meta {
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            font-size: 12px; font-weight: 600; color: var(--muted); min-width: 0;
        }
        .attr-row-meta > span:first-child { display: inline-flex; align-items: center; gap: 5px; min-width: 0; }
        .attr-row-meta i { font-size: 10px; }

        .attr-row-end {
            display: flex; align-items: center; gap: 12px;
            padding: 13px 16px; flex-shrink: 0;
        }
        .attr-row-go { color: var(--muted); font-size: 12px; transition: transform .18s ease, color .18s ease; }
        .attr-row:hover .attr-row-go { transform: translateX(3px); color: var(--primary); }

        /* Inline now, not floated over a photo, so no absolute positioning and
           no backdrop blur to sit on. */
        .attr-chip {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 10.5px; font-weight: 700; letter-spacing: .02em;
            padding: 4px 9px; border-radius: 99px; white-space: nowrap;
        }
        .attr-chip-category {
            color: #fff;
            background: color-mix(in srgb, var(--chip-color) 62%, rgba(0,0,0,.28));
            border: 1px solid rgba(255,255,255,.18);
        }
        .attr-chip-rating {
            color: #1A1225; background: rgba(255,255,255,.92);
            border: 1px solid var(--border);
        }
        .attr-chip-rating i { color: #F5A623; font-size: 10px; }

        /* The grid got this for free from auto-fill; a fixed three-column row
           does not, so the breakpoint has to be explicit. 560px matches the
           one the expenses list already uses. */
        @media (max-width: 560px) {
            .attr-row { grid-template-columns: 84px 1fr; }
            .attr-row-media { min-height: 84px; }
            .attr-row-noimg span { display: none; }
            .attr-row-body { padding: 11px 13px; }
            .attr-row-name { white-space: normal; }
            .attr-row-end {
                grid-column: 2; padding: 0 13px 11px;
                justify-content: space-between;
            }
        }

        .attr-empty { text-align: center; padding: 64px 24px; min-height: 60vh; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .attr-empty-icon {
            width: 64px; height: 64px; border-radius: 18px; background: var(--primary-light);
            display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;
        }
        .attr-empty-icon i { font-size: 26px; color: var(--primary); }
        .attr-empty h3 { font-size: 16px; font-weight: 700; color: var(--dark); margin: 0 0 6px; }
        .attr-empty p { color: var(--muted); font-size: 13px; margin: 0; }
    </style>
</div>
