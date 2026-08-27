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

    {{-- Card grid --}}
    @if ($this->destinations->isEmpty())
    <div class="dst-empty">
        <div class="dst-empty-icon"><i class="fa-solid fa-compass"></i></div>
        <h3>No destinations found</h3>
        <p>Try a different search or country filter.</p>
    </div>
    @else
    <div class="dst-grid">
        @foreach ($this->destinations as $destination)
        @php
            $rating = $destination->attractions_avg_rating;
            $ratingRounded = $rating ? round($rating) : 0;
        @endphp
        <a href="{{ route('destinations.show', $destination) }}" class="dst-card">
            <div class="dst-card-media">
                @if ($destination->image)
                <img src="{{ asset('storage/' . $destination->image) }}" alt="{{ $destination->name }}" loading="eager" decoding="async">
                @else
                <div class="dst-card-noimg">
                    <i class="fa-solid fa-image"></i>
                    <span>Photo coming soon</span>
                </div>
                @endif
                <div class="dst-card-scrim"></div>

                @if ($destination->country)
                <span class="dst-chip dst-chip-country">
                    <i class="fa-solid fa-earth-asia"></i> {{ $destination->country }}
                </span>
                @endif

                @if ($rating)
                <span class="dst-chip dst-chip-rating">
                    <i class="fa-solid fa-star"></i> {{ number_format($rating, 1) }}
                </span>
                @endif

                <div class="dst-card-location">
                    <i class="fa-solid fa-location-dot"></i> {{ $destination->attractions_count }} {{ Str::plural('attraction', $destination->attractions_count) }}
                </div>
            </div>

            <div class="dst-card-body">
                <div class="dst-card-name">{{ $destination->name }}</div>
                @if ($rating)
                <div class="dst-card-stars">
                    @for ($i = 1; $i <= 5; $i++)
                        <i class="fa-{{ $i <= $ratingRounded ? 'solid' : 'regular' }} fa-star"></i>
                    @endfor
                </div>
                @endif
                <div class="dst-card-cta">
                    View Details <i class="fa-solid fa-arrow-right"></i>
                </div>
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

        .dst-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 22px;
        }

        .dst-card {
            display: block; text-decoration: none; color: inherit;
            background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 18px;
            overflow: hidden; transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
        }
        .dst-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 36px rgba(0,0,0,0.16);
            border-color: var(--primary);
        }

        .dst-card-media { position: relative; aspect-ratio: 4 / 3; overflow: hidden; background: var(--bg); }
        .dst-card-media img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .45s ease; }
        .dst-card:hover .dst-card-media img { transform: scale(1.08); }

        .dst-card-noimg {
            position: absolute; inset: 0; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 8px;
            background: linear-gradient(160deg, var(--border-light) 0%, var(--border) 100%);
            color: var(--muted); text-align: center;
        }
        .dst-card-noimg i { font-size: 26px; opacity: .6; }
        .dst-card-noimg span { font-size: 11.5px; font-weight: 600; }

        .dst-card-scrim {
            position: absolute; inset: 0;
            background: linear-gradient(to top, rgba(10,8,20,0.82) 0%, rgba(10,8,20,0.15) 42%, rgba(10,8,20,0) 62%);
            pointer-events: none;
        }

        .dst-chip {
            position: absolute; top: 12px;
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 10.5px; font-weight: 700; letter-spacing: .02em;
            padding: 5px 10px; border-radius: 99px;
            backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
            z-index: 2;
        }
        .dst-chip-country {
            left: 12px; color: #fff;
            background: color-mix(in srgb, var(--primary) 55%, rgba(0,0,0,.35));
            border: 1px solid rgba(255,255,255,.25);
        }
        .dst-chip-rating {
            right: 12px; color: #1A1225;
            background: rgba(255,255,255,.92);
        }
        .dst-chip-rating i { color: #F5A623; font-size: 10px; }

        .dst-card-location {
            position: absolute; left: 14px; right: 14px; bottom: 12px; z-index: 2;
            color: #fff; font-size: 12px; font-weight: 600;
            display: flex; align-items: center; gap: 6px;
            text-shadow: 0 1px 4px rgba(0,0,0,.4);
        }
        .dst-card-location i { font-size: 10px; opacity: .85; }

        .dst-card-body { padding: 14px 16px 16px; }
        .dst-card-name {
            font-size: 15.5px; font-weight: 700; color: var(--dark); line-height: 1.3;
            margin-bottom: 6px;
            display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;
        }
        .dst-card-stars { color: #F5A623; font-size: 11px; letter-spacing: 2px; margin-bottom: 12px; }
        .dst-card-stars i.fa-regular { color: var(--border); }

        .dst-card-cta {
            display: flex; align-items: center; justify-content: space-between;
            font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
            color: var(--primary); padding-top: 12px; border-top: 1px solid var(--border);
            margin-top: 12px;
            transition: gap .2s ease;
        }
        .dst-card-cta i { font-size: 11px; transition: transform .22s ease; }
        .dst-card:hover .dst-card-cta i { transform: translateX(4px); }

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
