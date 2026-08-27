@extends('layouts.app')
@section('title', $destination->name)

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
@endpush

@section('content')

<a href="{{ route('destinations.index') }}" class="dst-back">
    <i class="fa-solid fa-arrow-left"></i> All Destinations
</a>

{{-- Hero photo --}}
<div class="dst-hero">
    @if ($destination->image)
    <img src="{{ asset('storage/' . $destination->image) }}" alt="{{ $destination->name }}" class="dst-hero-img">
    @else
    <div class="dst-hero-noimg"><i class="fa-solid fa-compass"></i></div>
    @endif

    <div class="dst-hero-overlay">
        @if ($destination->country)
        <div class="dst-hero-eyebrow">{{ $destination->country }}</div>
        @endif
        <h1 class="dst-hero-title">{{ $destination->name }}</h1>
        <div class="dst-hero-meta">
            <i class="fa-solid fa-map-location-dot"></i> {{ $attractions->count() }} {{ Str::plural('attraction', $attractions->count()) }}
        </div>
    </div>
</div>

{{-- Description --}}
@if ($destination->description)
<div class="mb-24">
    <h2 class="dst-section-title">About</h2>
    <p class="dst-body-text">{{ $destination->description }}</p>
</div>
@endif

{{-- Attractions in this destination --}}
<div class="mb-24">
    <h2 class="dst-section-title">Things to Do</h2>

    @if ($attractions->isEmpty())
    <div class="dst-empty">
        <div class="dst-empty-icon"><i class="fa-solid fa-map-location-dot"></i></div>
        <p>No attractions listed for {{ $destination->name }} yet.</p>
    </div>
    @else
    <div class="dst-attraction-grid">
        @foreach ($attractions as $attraction)
        <a href="{{ route('attractions.show', $attraction) }}" class="dst-attraction-card">
            <div class="dst-attraction-img">
                @if ($attraction->image)
                <img src="{{ asset('storage/' . $attraction->image) }}" alt="{{ $attraction->name }}">
                @else
                <i class="fa-solid fa-image"></i>
                @endif
            </div>
            <div class="dst-attraction-body">
                <div class="dst-attraction-name">{{ $attraction->name }}</div>
                @if ($attraction->category)
                <span class="dst-attraction-badge">{{ $attraction->category }}</span>
                @endif
            </div>
        </a>
        @endforeach
    </div>
    @endif
</div>

@endsection

<style>
    .dst-back {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 13px; font-weight: 600; color: var(--primary); text-decoration: none;
        background: none; border: none; padding: 0; margin-bottom: 10px;
    }
    .dst-back i { font-size: 11px; }

    .dst-hero {
        position: relative; border-radius: 22px; overflow: hidden;
        height: 320px; min-height: 320px; flex-shrink: 0;
        background: var(--bg); margin-bottom: 24px;
        box-shadow: 0 12px 32px rgba(0,0,0,0.14);
    }
    .dst-hero-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .dst-hero-noimg {
        position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
        background: linear-gradient(150deg, var(--primary) 0%, #C9A84C 100%); color: rgba(255,255,255,.5); font-size: 48px;
    }
    .dst-hero-overlay {
        position: absolute; inset: 0; z-index: 1;
        display: flex; flex-direction: column; justify-content: flex-end;
        padding: 28px 32px;
        background: linear-gradient(180deg, rgba(0,0,0,0) 35%, rgba(0,0,0,.68) 100%);
    }
    .dst-hero-eyebrow {
        display: inline-block; align-self: flex-start;
        font-family: 'IBM Plex Mono', monospace; font-size: 11px; font-weight: 600; letter-spacing: .1em; text-transform: uppercase;
        color: #fff; background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.28);
        padding: 4px 12px; border-radius: 99px; backdrop-filter: blur(6px); margin-bottom: 12px;
    }
    .dst-hero-title { font-family: 'Fraunces', serif; font-weight: 700; font-size: 34px; line-height: 1.1; color: #fff; margin: 0 0 8px; }
    .dst-hero-meta { font-family: 'IBM Plex Mono', monospace; font-size: 12px; color: rgba(255,255,255,.85); display: flex; align-items: center; gap: 7px; }
    @media (max-width: 600px) {
        .dst-hero { height: 240px; min-height: 240px; }
        .dst-hero-overlay { padding: 20px; }
        .dst-hero-title { font-size: 26px; }
    }

    .dst-section-title { font-size: 18px; font-weight: 800; color: var(--dark); margin: 0 0 14px; }
    .dst-body-text { font-size: 14px; line-height: 1.7; color: var(--muted); margin: 0; max-width: 780px; }

    .dst-attraction-grid { display: flex; flex-wrap: wrap; gap: 20px; }
    .dst-attraction-card {
        flex: 1 1 260px; max-width: 320px;
        background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 18px; overflow: hidden;
        text-decoration: none; color: inherit; display: flex; flex-direction: column;
        transition: box-shadow .2s ease, transform .2s ease, border-color .2s ease;
    }
    .dst-attraction-card:hover {
        box-shadow: 0 10px 28px rgba(0,0,0,.10); transform: translateY(-2px); border-color: var(--primary-light);
    }
    .dst-attraction-img {
        height: 150px; background: var(--bg); display: flex; align-items: center; justify-content: center;
        color: var(--muted); font-size: 26px; flex-shrink: 0;
    }
    .dst-attraction-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .dst-attraction-body { padding: 16px 18px; }
    .dst-attraction-name { font-family: 'Fraunces', serif; font-weight: 700; font-size: 15.5px; color: var(--dark); margin-bottom: 8px; }
    .dst-attraction-badge {
        display: inline-block; font-size: 11px; font-weight: 600; color: var(--primary);
        background: var(--primary-light); padding: 3px 10px; border-radius: 99px;
    }

    .dst-empty {
        text-align: center; padding: 48px 24px; background: var(--bg-white);
        border: 1.5px dashed var(--border); border-radius: 16px;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
    }
    .dst-empty-icon {
        width: 52px; height: 52px; border-radius: 50%; background: var(--bg); margin: 0 auto 14px;
        display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 20px;
    }
    .dst-empty p { color: var(--muted); font-size: 13.5px; margin: 0; }
</style>
