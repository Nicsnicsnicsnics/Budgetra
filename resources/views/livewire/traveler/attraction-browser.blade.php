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

    {{-- Card grid --}}
    @if ($this->attractions->isEmpty())
    <div class="attr-empty">
        <div class="attr-empty-icon"><i class="fa-solid fa-mountain-sun"></i></div>
        <h3>No attractions found</h3>
        <p>Try a different search or destination filter.</p>
    </div>
    @else
    <div class="attr-grid">
        @foreach ($this->attractions as $attraction)
        @php
            $categoryMeta = [
                'Culture'   => ['icon' => 'fa-landmark',      'color' => '#C084FC'],
                'Nature'    => ['icon' => 'fa-leaf',          'color' => '#4ADE80'],
                'Adventure' => ['icon' => 'fa-person-hiking', 'color' => '#FB923C'],
            ][$attraction->category] ?? ['icon' => 'fa-map-pin', 'color' => 'var(--primary)'];
            $ratingRounded = round($attraction->rating);
        @endphp
        <a href="{{ route('attractions.show', $attraction) }}" class="attr-card">
            <div class="attr-card-media">
                @if ($attraction->image)
                <img src="{{ asset('storage/' . $attraction->image) }}" alt="{{ $attraction->name }}" loading="eager" decoding="async" fetchpriority="high">
                @else
                <div class="attr-card-noimg">
                    <i class="fa-solid fa-image"></i>
                    <span>Photo coming soon</span>
                </div>
                @endif
                <div class="attr-card-scrim"></div>

                @if ($attraction->category)
                <span class="attr-chip attr-chip-category" style="--chip-color:{{ $categoryMeta['color'] }};">
                    <i class="fa-solid {{ $categoryMeta['icon'] }}"></i> {{ $attraction->category }}
                </span>
                @endif

                <span class="attr-chip attr-chip-rating">
                    <i class="fa-solid fa-star"></i> {{ number_format($attraction->rating, 1) }}
                </span>

                <div class="attr-card-location">
                    <i class="fa-solid fa-location-dot"></i> {{ $attraction->destination }}
                </div>
            </div>

            <div class="attr-card-body">
                <div class="attr-card-name">{{ $attraction->name }}</div>
                <div class="attr-card-stars">
                    @for ($i = 1; $i <= 5; $i++)
                        <i class="fa-{{ $i <= $ratingRounded ? 'solid' : 'regular' }} fa-star"></i>
                    @endfor
                </div>
                <div class="attr-card-cta">
                    View Reviews <i class="fa-solid fa-arrow-right"></i>
                </div>
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

        .attr-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 22px;
        }

        .attr-card {
            display: block; text-decoration: none; color: inherit;
            background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 18px;
            overflow: hidden; transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
        }
        .attr-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 36px rgba(0,0,0,0.16);
            border-color: var(--primary);
        }

        .attr-card-media { position: relative; aspect-ratio: 4 / 3; overflow: hidden; background: var(--bg); }
        .attr-card-media img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .45s ease; }
        .attr-card:hover .attr-card-media img { transform: scale(1.08); }

        .attr-card-noimg {
            position: absolute; inset: 0; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 8px;
            background: linear-gradient(160deg, var(--border-light) 0%, var(--border) 100%);
            color: var(--muted); text-align: center;
        }
        .attr-card-noimg i { font-size: 26px; opacity: .6; }
        .attr-card-noimg span { font-size: 11.5px; font-weight: 600; }

        .attr-card-scrim {
            position: absolute; inset: 0;
            background: linear-gradient(to top, rgba(10,8,20,0.82) 0%, rgba(10,8,20,0.15) 42%, rgba(10,8,20,0) 62%);
            pointer-events: none;
        }

        .attr-chip {
            position: absolute; top: 12px;
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 10.5px; font-weight: 700; letter-spacing: .02em;
            padding: 5px 10px; border-radius: 99px;
            backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
            z-index: 2;
        }
        .attr-chip-category {
            left: 12px; color: #fff;
            background: color-mix(in srgb, var(--chip-color) 55%, rgba(0,0,0,.35));
            border: 1px solid rgba(255,255,255,.25);
        }
        .attr-chip-rating {
            right: 12px; color: #1A1225;
            background: rgba(255,255,255,.92);
        }
        .attr-chip-rating i { color: #F5A623; font-size: 10px; }

        .attr-card-location {
            position: absolute; left: 14px; right: 14px; bottom: 12px; z-index: 2;
            color: #fff; font-size: 12px; font-weight: 600;
            display: flex; align-items: center; gap: 6px;
            text-shadow: 0 1px 4px rgba(0,0,0,.4);
        }
        .attr-card-location i { font-size: 10px; opacity: .85; }

        .attr-card-body { padding: 14px 16px 16px; }
        .attr-card-name {
            font-size: 15.5px; font-weight: 700; color: var(--dark); line-height: 1.3;
            margin-bottom: 6px;
            display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;
        }
        .attr-card-stars { color: #F5A623; font-size: 11px; letter-spacing: 2px; margin-bottom: 12px; }
        .attr-card-stars i.fa-regular { color: var(--border); }

        .attr-card-cta {
            display: flex; align-items: center; justify-content: space-between;
            font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
            color: var(--primary); padding-top: 12px; border-top: 1px solid var(--border);
            transition: gap .2s ease;
        }
        .attr-card-cta i { font-size: 11px; transition: transform .22s ease; }
        .attr-card:hover .attr-card-cta i { transform: translateX(4px); }

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
