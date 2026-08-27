@extends('layouts.app')
@section('title', isset($tab) && $tab === 'moments' ? 'Moments' : 'Itinerary')

@push('styles')
@if(($tab ?? 'itinerary') === 'moments')
{{-- Leaflet + CartoDB Voyager raster tiles — same tile source already used
     by the home-location map in Profile Builder, so both maps in the app
     look consistent. Voyager's labels are Latin-script/English by design
     (unlike some raster providers that bake in the viewer's local script),
     so no vector-tile label-patching trick is needed here. --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endif
<style>
    #itinerary-calendar { font-family: inherit; }
    #itinerary-calendar .fc-toolbar { align-items: center; margin-bottom: 16px !important; }
    #itinerary-calendar .fc-toolbar-title { font-size: 20px !important; font-weight: 700 !important; color: var(--dark) !important; letter-spacing: -0.01em; }
    #itinerary-calendar .fc-button { background: transparent !important; border: none !important; box-shadow: none !important; color: var(--muted) !important; padding: 4px 8px !important; font-size: 16px !important; line-height: 1 !important; }
    #itinerary-calendar .fc-button:hover { color: var(--dark) !important; }
    #itinerary-calendar .fc-button:focus { outline: none !important; box-shadow: none !important; }
    #itinerary-calendar .fc-col-header-cell { border: none !important; padding: 6px 0 10px !important; }
    #itinerary-calendar .fc-col-header-cell-cushion { font-size: 11px !important; font-weight: 600 !important; color: var(--muted) !important; text-transform: uppercase; letter-spacing: .06em; text-decoration: none !important; }
    #itinerary-calendar .fc-daygrid-day { border-color: var(--border-light) !important; }
    #itinerary-calendar .fc-scrollgrid { border: none !important; }
    #itinerary-calendar .fc-scrollgrid td, #itinerary-calendar .fc-scrollgrid th { border-color: var(--border-light) !important; }
    #itinerary-calendar .fc-daygrid-day-number { font-size: 13px !important; color: var(--muted) !important; font-weight: 500 !important; text-decoration: none !important; padding: 6px 8px 2px !important; line-height: 1 !important; }
    #itinerary-calendar .fc-day-other .fc-daygrid-day-number { color: #D1D5DB !important; }
    #itinerary-calendar .fc-day-today { background: transparent !important; }
    #itinerary-calendar .fc-day-today .fc-daygrid-day-number { color: var(--primary) !important; font-weight: 700 !important; }
    #itinerary-calendar .fc-daygrid-day-frame { min-height: 90px !important; cursor: pointer; }
    #itinerary-calendar .fc-daygrid-day-events { display: none !important; }
    #itinerary-calendar .fc-daygrid-day-bg     { display: none !important; }
    #itinerary-calendar .fc-daygrid-day:hover .fc-daygrid-day-frame { background: #FAFAF9; }
    #itinerary-calendar .fc-daygrid-day.has-items .fc-daygrid-day-frame { background: #FDF8F5; }
    .cal-icon-row { display:flex; flex-direction:column; gap:3px; padding:3px 5px 5px; }
    .cal-icon-pip { display:inline-flex; align-items:center; gap:5px; padding:3px 6px 3px 4px; border-radius:6px; font-size:10px; font-weight:600; line-height:1; white-space:nowrap; max-width:100%; overflow:hidden; }
    .cal-icon-pip i  { font-size:10px; flex-shrink:0; }
    .cal-icon-pip span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:9px; }
</style>
@endpush

@section('content')
@livewire('traveler.itinerary-manager', ['tab' => $tab ?? 'itinerary'])
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
@endpush
