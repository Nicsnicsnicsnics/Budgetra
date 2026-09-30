<div>
    {{-- Filters --}}
    <div class="dst-filters mb-24">
        <div class="dst-filter-input">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search destinations...">
        </div>
        <div class="dst-filter-select">
            <i class="fa-solid fa-globe"></i>
            <select wire:model.live="country">
                <option value="">All countries</option>
                @foreach ($this->countries as $c)
                <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
            <i class="fa-solid fa-chevron-down dst-select-caret"></i>
        </div>
    </div>

    {{-- Result list --}}
    @if ($this->destinations->isEmpty())
    <div class="dst-empty">
        <div class="dst-empty-icon"><i class="fa-solid fa-compass"></i></div>
        <h3>No destinations found</h3>
        <p>Try a different search or country filter.</p>
    </div>
    @else
    <div class="dst-list">
        @foreach ($this->destinations as $destination)
        @php
            $rating = $destination->attractions_avg_rating;
            $ratingRounded = $rating ? round($rating) : 0;
        @endphp
        <a href="{{ route('destinations.show', $destination) }}" class="dst-row">
            <div class="dst-row-media">
                @if ($destination->image)
                <img src="{{ asset('storage/' . $destination->image) }}" alt="{{ $destination->name }}" loading="lazy" decoding="async">
                @else
                {{-- The common case: most destinations have no photo, which is
                     half the reason this is a list and not a grid of
                     mostly-empty 4:3 boxes. --}}
                <div class="dst-row-noimg">
                    <i class="fa-solid fa-image"></i>
                    <span>Photo coming soon</span>
                </div>
                @endif
            </div>

            <div class="dst-row-body">
                <div class="dst-row-name">{{ $destination->name }}</div>
                @if ($rating)
                <div class="dst-row-stars">
                    @for ($i = 1; $i <= 5; $i++)
                        <i class="fa-{{ $i <= $ratingRounded ? 'solid' : 'regular' }} fa-star"></i>
                    @endfor
                </div>
                @endif
                <div class="dst-row-meta">
                    <span><i class="fa-solid fa-location-dot"></i> {{ $destination->attractions_count }} {{ Str::plural('attraction', $destination->attractions_count) }}</span>
                    @if ($destination->country)
                    {{-- Inline now rather than floated over the photo: there is
                         no tall image left to sit on. --}}
                    <span class="dst-chip dst-chip-country">
                        <i class="fa-solid fa-earth-asia"></i> {{ $destination->country }}
                    </span>
                    @endif
                </div>
            </div>

            <div class="dst-row-end">
                @if ($rating)
                <span class="dst-chip dst-chip-rating">
                    <i class="fa-solid fa-star"></i> {{ number_format($rating, 1) }}
                </span>
                @endif
                {{-- Replaces the "View Details" bar. The whole row is the link,
                     so it only has to point. --}}
                <i class="fa-solid fa-chevron-right dst-row-go"></i>
            </div>
        </a>
        @endforeach
    </div>
    @endif

    <style>
        .dst-filters { display: grid; grid-template-columns: 1fr 240px; gap: 14px; max-width: 640px; }
        .dst-filter-input, .dst-filter-select {
            position: relative; display: flex; align-items: center;
            background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 12px;
        }
        .dst-filter-input i, .dst-filter-select > i:first-child { padding-left: 14px; color: var(--muted); font-size: 13px; flex-shrink: 0; }
        .dst-filter-input input, .dst-filter-select select {
            width: 100%; border: none; background: none; outline: none;
            padding: 11px 12px; font-size: 13.5px; color: var(--dark); font-family: inherit;
            appearance: none; -webkit-appearance: none;
        }
        .dst-select-caret { padding-right: 14px; color: var(--muted); font-size: 10px; pointer-events: none; }
        .dst-filter-input:focus-within, .dst-filter-select:focus-within { border-color: var(--primary); }

        .dst-list { display: flex; flex-direction: column; gap: 12px; }

        /* One result per row: thumbnail, details, rating. Mirrors
           attraction-browser.blade.php, which these two pages have always done
           — they are the same component with a country chip instead of a
           category one. */
        .dst-row {
            display: grid; grid-template-columns: 116px 1fr auto; align-items: stretch;
            text-decoration: none; color: inherit;
            background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 14px;
            overflow: hidden; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        /* A smaller lift than the grid's -5px: repeated down a list, a large
           hop reads as the page twitching. */
        .dst-row:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 26px rgba(0,0,0,0.12);
            border-color: var(--primary);
        }
        .dst-row:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }

        .dst-row-media { position: relative; overflow: hidden; background: var(--bg); min-height: 104px; }
        .dst-row-media img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .35s ease; }
        .dst-row:hover .dst-row-media img { transform: scale(1.06); }

        .dst-row-noimg {
            position: absolute; inset: 0; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 5px;
            background: linear-gradient(160deg, var(--border-light) 0%, var(--border) 100%);
            color: var(--muted); text-align: center; padding: 6px;
        }
        .dst-row-noimg i { font-size: 17px; opacity: .6; }
        .dst-row-noimg span { font-size: 9.5px; font-weight: 600; line-height: 1.25; }

        .dst-row-body {
            min-width: 0; padding: 13px 16px;
            display: flex; flex-direction: column; justify-content: center; gap: 5px;
        }
        .dst-row-name {
            font-size: 15px; font-weight: 700; color: var(--dark); line-height: 1.3;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .dst-row-stars { color: #F5A623; font-size: 10.5px; letter-spacing: 2px; }
        .dst-row-stars i.fa-regular { color: var(--border); }

        .dst-row-meta {
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            font-size: 12px; font-weight: 600; color: var(--muted); min-width: 0;
        }
        .dst-row-meta > span:first-child { display: inline-flex; align-items: center; gap: 5px; min-width: 0; }
        .dst-row-meta i { font-size: 10px; }

        .dst-row-end {
            display: flex; align-items: center; gap: 12px;
            padding: 13px 16px; flex-shrink: 0;
        }
        .dst-row-go { color: var(--muted); font-size: 12px; transition: transform .18s ease, color .18s ease; }
        .dst-row:hover .dst-row-go { transform: translateX(3px); color: var(--primary); }

        /* Inline now, not floated over a photo, so no absolute positioning and
           no backdrop blur to sit on. */
        .dst-chip {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 10.5px; font-weight: 700; letter-spacing: .02em;
            padding: 4px 9px; border-radius: 99px; white-space: nowrap;
        }
        .dst-chip-country {
            color: #fff;
            background: color-mix(in srgb, var(--primary) 62%, rgba(0,0,0,.28));
            border: 1px solid rgba(255,255,255,.18);
        }
        .dst-chip-rating {
            color: #1A1225; background: rgba(255,255,255,.92);
            border: 1px solid var(--border);
        }
        .dst-chip-rating i { color: #F5A623; font-size: 10px; }

        /* The grid got this for free from auto-fill; a fixed three-column row
           does not, so the breakpoint has to be explicit. 560px matches the
           one the expenses list already uses. */
        @media (max-width: 560px) {
            .dst-row { grid-template-columns: 84px 1fr; }
            .dst-row-media { min-height: 84px; }
            .dst-row-noimg span { display: none; }
            .dst-row-body { padding: 11px 13px; }
            .dst-row-name { white-space: normal; }
            .dst-row-end {
                grid-column: 2; padding: 0 13px 11px;
                justify-content: space-between;
            }
        }

        .dst-empty { text-align: center; padding: 64px 24px; min-height: 60vh; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .dst-empty-icon {
            width: 64px; height: 64px; border-radius: 50%; background: var(--primary-light);
            display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;
            color: var(--primary); font-size: 24px;
        }
        .dst-empty h3 { font-size: 16px; font-weight: 700; color: var(--dark); margin: 0 0 6px; }
        .dst-empty p { color: var(--muted); font-size: 13px; margin: 0; }
    </style>
</div>
