<div style="width:100%;display:flex;flex-direction:column;flex:1;">

{{-- ═══════════════════════════════════════════════════════════════
     STEP 1 — Plan Your Trip (manual)
     Picking Manual Planning lands here directly. The "Have a trip code?"
     screen that used to sit in front of this step is now an optional
     shortcut under the mode-select cards.
═══════════════════════════════════════════════════════════════ --}}
@if ($planningMode !== '' && $step === 1)
@php
$localCities = [
    ['name'=>'Manila','code'=>'MNL'],['name'=>'Cebu City','code'=>'CEB'],['name'=>'Davao City','code'=>'DVO'],
    ['name'=>'Boracay','code'=>'KLO'],['name'=>'Puerto Princesa','code'=>'PPS'],['name'=>'Tagbilaran','code'=>'TAG'],
    ['name'=>'Siargao','code'=>'IAO'],['name'=>'Iloilo City','code'=>'ILO'],['name'=>'Bacolod','code'=>'BCD'],
    ['name'=>'Zamboanga','code'=>'ZAM'],['name'=>'Cagayan de Oro','code'=>'CGY'],['name'=>'General Santos','code'=>'GES'],
    ['name'=>'Tacloban','code'=>'TAC'],['name'=>'Dumaguete','code'=>'DGT'],['name'=>'El Nido','code'=>'ENI'],
    ['name'=>'Coron','code'=>'USU'],['name'=>'Baguio','code'=>'BAG'],['name'=>'Tagaytay','code'=>'MNL'],
    ['name'=>'Vigan','code'=>'VIG'],['name'=>'Batanes','code'=>'BSO'],['name'=>'Camiguin','code'=>'CGM'],
    ['name'=>'Siquijor','code'=>'DGT'],['name'=>'Surigao','code'=>'SUG'],['name'=>'Laoag','code'=>'LAO'],
    ['name'=>'Legazpi','code'=>'LGP'],
];
$intlCities = [
    ['name'=>'Singapore','code'=>'SIN'],['name'=>'Bangkok','code'=>'BKK'],['name'=>'Bali','code'=>'DPS'],
    ['name'=>'Tokyo','code'=>'NRT'],['name'=>'Seoul','code'=>'ICN'],['name'=>'Kuala Lumpur','code'=>'KUL'],
    ['name'=>'Hong Kong','code'=>'HKG'],['name'=>'Dubai','code'=>'DXB'],['name'=>'London','code'=>'LHR'],
    ['name'=>'Paris','code'=>'CDG'],['name'=>'New York','code'=>'JFK'],['name'=>'Sydney','code'=>'SYD'],
    ['name'=>'Osaka','code'=>'KIX'],['name'=>'Taipei','code'=>'TPE'],['name'=>'Rome','code'=>'FCO'],
    ['name'=>'Barcelona','code'=>'BCN'],['name'=>'Amsterdam','code'=>'AMS'],['name'=>'Maldives','code'=>'MLE'],
    ['name'=>'Phuket','code'=>'HKT'],['name'=>'Ho Chi Minh City','code'=>'SGN'],['name'=>'Hanoi','code'=>'HAN'],
    ['name'=>'Doha','code'=>'DOH'],['name'=>'Istanbul','code'=>'IST'],['name'=>'Toronto','code'=>'YYZ'],
    ['name'=>'Los Angeles','code'=>'LAX'],
];
$allCities = array_merge(
    array_map(fn($c)=>array_merge($c,['group'=>'Local']),$localCities),
    array_map(fn($c)=>array_merge($c,['group'=>'International']),$intlCities)
);
@endphp

<style>
[x-cloak]{display:none!important;}
.pyt-field{background:var(--bg-white);border:1.5px solid var(--border);border-radius:14px;padding:16px 18px;cursor:pointer;transition:border-color .18s,background .18s,box-shadow .18s;}
.pyt-field:hover{border-color:#D9C4AE;}
.pyt-field:focus-within{border-color:var(--primary);background:var(--bg-white);box-shadow:0 0 0 4px rgba(147,75,25,0.08);}
/* Clear (x) — only rendered once a field actually holds a value. */
.pyt-clear{margin-left:auto;flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border:none;border-radius:50%;background:transparent;color:var(--muted);cursor:pointer;font-size:13px;line-height:1;padding:0;transition:background .15s,color .15s;}
.pyt-clear:hover{background:var(--border-light);color:var(--dark);}
.pyt-clear:focus-visible{outline:2px solid var(--primary);outline-offset:2px;}
/* Required-but-empty. Beats :hover and :focus-within so the red survives the
   pointer landing on the field. The dropdown/calendar popovers are siblings
   of .pyt-field, not children, so the transform can't drag them along. */
.pyt-field.is-bad,.pyt-field.is-bad:hover,.pyt-field.is-bad:focus-within{
    border-color:#FF3B3B;box-shadow:0 0 0 3px rgba(255,59,59,.20);
    animation:pyt-shake .48s cubic-bezier(.36,.07,.19,.97) both;}
@keyframes pyt-shake{
  10%,90%{transform:translateX(-2px);}
  20%,80%{transform:translateX(3px);}
  30%,50%,70%{transform:translateX(-6px);}
  40%,60%{transform:translateX(6px);}
}
@media (prefers-reduced-motion:reduce){.pyt-field.is-bad{animation:none;}}
.pyt-icon{width:32px;height:32px;border-radius:9px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.pyt-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:var(--muted);margin-bottom:8px;}
.pyt-value{font-size:16px;font-weight:600;color:var(--dark);}
.pyt-placeholder{font-size:16px;color:#C4B8AC;}
.city-drop{position:absolute;top:calc(100% + 6px);left:0;right:0;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.10);z-index:200;max-height:320px;overflow:hidden;display:flex;flex-direction:column;}
.city-search{padding:10px 14px;border-bottom:1px solid var(--border);}
.city-search input{width:100%;border:none;outline:none;font-size:13px;color:var(--dark);background:transparent;}
.city-list{overflow-y:auto;flex:1;}
.city-group-label{padding:8px 14px 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:var(--muted);}
.city-item{padding:10px 14px;font-size:13px;font-weight:500;color:var(--dark);cursor:pointer;display:flex;align-items:center;gap:10px;}
.city-item:hover{background:var(--bg);}
.city-item .code{font-size:11px;font-weight:700;color:var(--muted);background:var(--bg);border-radius:4px;padding:2px 6px;width:36px;text-align:center;flex-shrink:0;}
.mini-cal{position:absolute;top:calc(100% + 6px);left:0;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.10);z-index:200;padding:16px;min-width:260px;}
.cal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.cal-nav{background:none;border:none;cursor:pointer;color:var(--muted);font-size:14px;padding:4px 8px;}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;text-align:center;}
.cal-day-name{font-size:10px;font-weight:700;color:var(--muted);padding:4px 0;}
.cal-day{font-size:12px;font-weight:500;padding:6px 4px;border-radius:6px;cursor:pointer;}
.cal-day:hover:not(.past):not(.empty){background:var(--bg);}
.cal-day.selected{background:var(--primary);color:#fff;}
.cal-day.empty{cursor:default;}
.cal-day.in-range{background:var(--primary-light);color:var(--primary);}
.cal-day.past{color:var(--muted);opacity:.35;cursor:not-allowed;}
.pyt-budget-input::placeholder{color:#C4B8AC;font-weight:400;}
</style>

<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:16px 24px;min-height:100%;box-sizing:border-box;">

    {{-- Card --}}
    {{-- wire:key forces Livewire to treat this as a fresh element every time
         step becomes 1 again (e.g. via "Back to Planner"), instead of
         possibly morph-reusing a stale DOM node and skipping Alpine's
         x-init — which is what actually re-seeds fromLabel/toLabel/dates/
         budget from the still-intact server-side values. --}}
    @php
        // Seeds handed to pytManual() through this attribute rather than
        // baked into the factory itself. window.pytManual lives in an
        // @script block, which Livewire runs ONCE per page load — so the
        // values compiled into it are whatever the properties held on first
        // render (empty). Coming back here from Select Flight remounts the
        // card and re-ran that stale factory, blanking From/To/dates even
        // though the server still had them. This attribute is part of the
        // component's HTML, so it re-renders with the current values.
        $pytSeed = [
            'fromLabel'  => $manualFrom ? $manualFrom.' ('.\App\Livewire\Traveler\TripPlannerWizard::staticIataCode($manualFrom).')' : '',
            'toLabel'    => $manualTo   ? $manualTo.' ('.\App\Livewire\Traveler\TripPlannerWizard::staticIataCode($manualTo).')'   : '',
            'startLabel' => $startDate ? \Carbon\Carbon::parse($startDate)->format('M d, Y') : '',
            'endLabel'   => $endDate   ? \Carbon\Carbon::parse($endDate)->format('M d, Y')   : '',
            'startVal'   => $startDate ?? '',
            'endVal'     => $endDate   ?? '',
            // The budget <input> is uncontrolled (set via x-init), so its
            // filled-ness has to be seeded separately for the x to know
            // whether to show on first paint.
            'budgetFilled' => trim((string) $manualBudgetMin) !== '',
        ];
    @endphp
    <div wire:key="pyt-manual-card-{{ $step1VisitToken }}" x-data="pytManual(@js($pytSeed))" x-init="init()"
         @trip-details-missing.window="flagBad($event.detail.fields)"
         style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:24px;width:100%;max-width:720px;box-shadow:0 8px 36px rgba(45,27,20,.08);">

        <div style="padding:36px 36px 0;">

            {{-- FROM / TO --}}
            <div style="position:relative;display:flex;align-items:flex-end;gap:14px;margin-bottom:18px;">

                {{-- FROM --}}
                <div style="position:relative;flex:1;" x-ref="fromWrap" @click.stop>
                    <div class="pyt-label">From</div>
                    <div class="pyt-field" :class="{ 'is-bad': bad.from }" @click="toggleDrop('from'); bad.from = false"
                         style="display:flex;align-items:center;gap:12px;">
                        <div class="pyt-icon" style="background:#FEF3E2;"><i class="fa-solid fa-plane-departure" style="color:#F1A53D;font-size:14px;"></i></div>
                        <span x-show="!fromLabel" class="pyt-placeholder" style="font-size:16px;">Leaving from?</span>
                        <span wire:ignore x-show="fromLabel" x-text="fromLabel" class="pyt-value" style="font-size:16px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $pytSeed['fromLabel'] }}</span>
                        <button type="button" class="pyt-clear" title="Clear"
                                x-show="fromLabel" x-cloak @click.stop="clearField('from')">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="city-drop" x-show="activeDrop==='from'" @click.outside="activeDrop=''" x-cloak>
                        <div class="city-search"><input type="text" x-model="fromSearch" placeholder="Select city" x-ref="fromSearch"></div>
                        <div class="city-list">
                            <template x-for="grp in ['Local','International']" :key="grp">
                                <div>
                                    <div class="city-group-label" x-text="grp + ' Destinations'"></div>
                                    <template x-for="c in filteredCities('from',grp)" :key="c.code+c.name">
                                        <div class="city-item" @click="selectCity('from',c)">
                                            <span class="code" x-text="c.code"></span><span x-text="c.name"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Swap button — floats between the two fields --}}
                <div style="flex-shrink:0;display:flex;align-items:flex-end;padding-bottom:12px;">
                    <button @click="swapCities()" type="button"
                            style="width:40px;height:40px;border-radius:50%;background:var(--bg-white);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.10);transition:transform .18s,border-color .18s;"
                            onmouseenter="this.style.transform='rotate(180deg)';this.style.borderColor='var(--primary)'" onmouseleave="this.style.transform='rotate(0deg)';this.style.borderColor='var(--border)'">
                        <i class="fa-solid fa-arrow-right-arrow-left" style="font-size:13px;color:var(--primary);"></i>
                    </button>
                </div>

                {{-- TO --}}
                <div style="position:relative;flex:1;" x-ref="toWrap" @click.stop>
                    <div class="pyt-label">To</div>
                    <div class="pyt-field" :class="{ 'is-bad': bad.to }" @click="toggleDrop('to'); bad.to = false"
                         style="display:flex;align-items:center;gap:12px;">
                        <div class="pyt-icon" style="background:#FEF3E2;"><i class="fa-solid fa-plane-arrival" style="color:#F1A53D;font-size:14px;"></i></div>
                        <span x-show="!toLabel" class="pyt-placeholder" style="font-size:16px;">Going to?</span>
                        <span wire:ignore x-show="toLabel" x-text="toLabel" class="pyt-value" style="font-size:16px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $pytSeed['toLabel'] }}</span>
                        <button type="button" class="pyt-clear" title="Clear"
                                x-show="toLabel" x-cloak @click.stop="clearField('to')">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="city-drop" x-show="activeDrop==='to'" @click.outside="activeDrop=''" x-cloak>
                        <div class="city-search"><input type="text" x-model="toSearch" placeholder="Select city" x-ref="toSearch"></div>
                        <div class="city-list">
                            <template x-for="grp in ['Local','International']" :key="grp">
                                <div>
                                    <div class="city-group-label" x-text="grp + ' Destinations'"></div>
                                    <template x-for="c in filteredCities('to',grp)" :key="c.code+c.name">
                                        <div class="city-item" @click="selectCity('to',c)">
                                            <span class="code" x-text="c.code"></span><span x-text="c.name"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- BUDGET --}}
            <div style="margin-bottom:18px;">
                <div class="pyt-label">Preferred Budget Range (must not exceed 7 digits)</div>
                <div class="pyt-field" :class="{ 'is-bad': bad.budget }" style="cursor:default;display:flex;align-items:center;gap:12px;">
                    <div class="pyt-icon" style="background:#E6F5EC;"><i class="fa-solid fa-money-bill-wave" style="color:#22A06B;font-size:14px;"></i></div>
                    {{-- wire:ignore for the same reason as the label spans: this
                         input is uncontrolled (seeded by x-init, then owned by
                         the traveler), and letting Livewire morph it re-ran that
                         x-init with a stale server value — a budget cleared with
                         the x reappeared on the next round trip. A fresh mount
                         still seeds it, since wire:key changes the node. --}}
                    <input type="text" wire:ignore
                           placeholder="Please input your budget"
                           style="border:none;outline:none;font-size:16px;font-weight:600;color:var(--dark);background:transparent;width:100%;font-family:inherit;"
                           class="pyt-budget-input"
                           x-ref="budgetInput"
                           @input="
                               const fmt = p => { const n = p.trim().replace(/[^0-9]/g,'').slice(0,7); return n ? parseInt(n).toLocaleString('en-PH') : ''; };
                               const raw = $el.value; const parts = raw.split('-');
                               $el.value = parts.length===2 ? fmt(parts[0])+' - '+fmt(parts[1]) : fmt(parts[0]);
                               if ($el.value) bad.budget = false;
                               budgetFilled = !!$el.value;
                           "
                           @change="$wire.set('manualBudgetMin', $el.value)"
                           {{-- Re-seeded on every mount (including coming back
                                from a later step). Stored digits-only, so put the
                                separators back rather than showing a bare 60000
                                where the traveler typed 60,000. --}}
                           x-init="$el.value = @js(ctype_digit((string) $manualBudgetMin) && $manualBudgetMin !== '' ? number_format((int) $manualBudgetMin) : (string) $manualBudgetMin)">
                    <button type="button" class="pyt-clear" title="Clear"
                            x-show="budgetFilled" x-cloak @click.stop="clearField('budget')">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            {{-- TRAVEL DATES — two separate fields --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:32px;">

                {{-- Start Date --}}
                <div>
                    <div class="pyt-label">Start Date</div>
                    <div style="position:relative;">
                        <div class="pyt-field" :class="{ 'is-bad': bad.start }" @click.stop="toggleCal('start'); bad.start = false" style="display:flex;align-items:center;gap:12px;">
                            <div class="pyt-icon"><i class="fa-regular fa-calendar" style="color:var(--primary);font-size:14px;"></i></div>
                            <span x-show="!startLabel" class="pyt-placeholder" style="font-size:16px;">Select date</span>
                            <span wire:ignore x-show="startLabel" x-text="startLabel" class="pyt-value" style="font-size:16px;">{{ $pytSeed['startLabel'] }}</span>
                        <button type="button" class="pyt-clear" title="Clear"
                                x-show="startLabel" x-cloak @click.stop="clearField('start')">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                        </div>
                        <div class="mini-cal" x-show="activeCal==='start'" x-cloak
                             @click.stop style="min-width:260px;z-index:300;">
                            <div class="cal-header">
                                <button class="cal-nav" type="button" @click.stop="prevMonth('start');rebuildCells()"><i class="fa-solid fa-chevron-left"></i></button>
                                <span style="font-size:13px;font-weight:700;color:var(--dark);" x-text="monthName(startYear,startMonth)+' '+startYear"></span>
                                <button class="cal-nav" type="button" @click.stop="nextMonth('start');rebuildCells()"><i class="fa-solid fa-chevron-right"></i></button>
                            </div>
                            <div class="cal-grid">
                                <template x-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']"><div class="cal-day-name" x-text="d"></div></template>
                                <template x-for="cell in startCells" :key="cell.key">
                                    <div class="cal-day"
                                         :class="{'selected': cell.d && cell.val===startVal, 'past': cell.past, 'empty': !cell.d}"
                                         @click.stop="cell.d && !cell.past && pickDate('start',cell.d)"
                                         x-text="cell.d||''"></div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- End Date --}}
                <div>
                    <div class="pyt-label">End Date</div>
                    <div style="position:relative;">
                        <div class="pyt-field" :class="{ 'is-bad': bad.end }" @click.stop="toggleCal('end'); bad.end = false" style="display:flex;align-items:center;gap:12px;">
                            <div class="pyt-icon"><i class="fa-regular fa-calendar" style="color:var(--primary);font-size:14px;"></i></div>
                            <span x-show="!endLabel" class="pyt-placeholder" style="font-size:16px;">Select date</span>
                            <span wire:ignore x-show="endLabel" x-text="endLabel" class="pyt-value" style="font-size:16px;">{{ $pytSeed['endLabel'] }}</span>
                        <button type="button" class="pyt-clear" title="Clear"
                                x-show="endLabel" x-cloak @click.stop="clearField('end')">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                        </div>
                        <div class="mini-cal" x-show="activeCal==='end'" x-cloak
                             @click.stop style="min-width:260px;z-index:300;">
                            <div class="cal-header">
                                <button class="cal-nav" type="button" @click.stop="prevMonth('end');rebuildCells()"><i class="fa-solid fa-chevron-left"></i></button>
                                <span style="font-size:13px;font-weight:700;color:var(--dark);" x-text="monthName(endYear,endMonth)+' '+endYear"></span>
                                <button class="cal-nav" type="button" @click.stop="nextMonth('end');rebuildCells()"><i class="fa-solid fa-chevron-right"></i></button>
                            </div>
                            <div class="cal-grid">
                                <template x-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']"><div class="cal-day-name" x-text="d"></div></template>
                                <template x-for="cell in endCells" :key="cell.key">
                                    <div class="cal-day"
                                         :class="{'selected': cell.d && cell.val===endVal, 'past': cell.past, 'empty': !cell.d}"
                                         @click.stop="cell.d && !cell.past && pickDate('end',cell.d)"
                                         x-text="cell.d||''"></div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        {{-- Card footer / bottom bar --}}
        <div style="border-top:1.5px solid var(--border);padding:20px 32px;display:flex;align-items:center;gap:14px;background:var(--bg-white);border-radius:0 0 24px 24px;">
            <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;">
                <i class="fa-solid fa-circle-info" style="color:var(--muted);font-size:13px;flex-shrink:0;"></i>
                <span style="font-size:13px;color:var(--muted);">Fill the required details for your trip estimates.</span>
            </div>
            <button x-on:click="submitTripDetails()" wire:loading.attr="disabled" wire:target="proceedFromTripDetails"
                    style="background:var(--primary);color:#fff;border:none;border-radius:12px;padding:13px 30px;font-size:14px;font-weight:700;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:8px;transition:background .18s,gap .18s;"
                    onmouseenter="this.style.background='var(--primary-dark)';this.style.gap='11px'" onmouseleave="this.style.background='var(--primary)';this.style.gap='8px'">
                <span wire:loading.remove wire:target="proceedFromTripDetails" style="display:inline-flex;align-items:center;gap:8px;">Next <i class="fa-solid fa-arrow-right" style="font-size:12px;"></i></span>
                <span wire:loading wire:target="proceedFromTripDetails"><i class="fa-solid fa-spinner fa-spin"></i></span>
            </button>
        </div>

    </div>

</div>

@script
<script>
window.pytManual = function (seed) {
    seed = seed || {};
    const cities = @json($allCities);
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const now = new Date();

    return {
        activeDrop: '',
        activeCal: '',
        fromLabel:  seed.fromLabel  || '',
        toLabel:    seed.toLabel    || '',
        fromSearch: '',
        toSearch:   '',
        budgetFilled: seed.budgetFilled === true,
        startLabel: seed.startLabel || '',
        endLabel:   seed.endLabel   || '',
        startVal:   seed.startVal   || '',
        endVal:     seed.endVal     || '',
        startYear: now.getFullYear(), startMonth: now.getMonth()+1,
        endYear:   now.getFullYear(), endMonth:   now.getMonth()+1,
        startCells: [],
        endCells: [],

        // Which required fields are currently flagged empty. Replaces the old
        // "Missing Trip Details" modal — the fields say it themselves now.
        bad: { from: false, to: false, budget: false, start: false, end: false },

        flagBad(keys) {
            // Drop then re-set on the next frame, otherwise clicking Next a
            // second time on an already-red field wouldn't restart the shake.
            keys.forEach(k => this.bad[k] = false);
            requestAnimationFrame(() => keys.forEach(k => { if (k in this.bad) this.bad[k] = true; }));
        },

        clearField(which) {
            // The inverse of selectCity()/pickDate(): drop the local label and
            // the Livewire property together, or the value reappears on the
            // next render from whichever half wasn't cleared.
            if (which === 'from')   { this.fromLabel = ''; this.fromSearch = ''; this.$wire.set('manualFrom', ''); }
            if (which === 'to')     { this.toLabel   = ''; this.toSearch   = ''; this.$wire.set('manualTo', ''); }
            if (which === 'budget') {
                if (this.$refs.budgetInput) this.$refs.budgetInput.value = '';
                this.budgetFilled = false;
                this.$wire.set('manualBudgetMin', '');
            }
            if (which === 'start')  { this.startVal = ''; this.startLabel = ''; this.$wire.set('startDate', ''); }
            if (which === 'end')    { this.endVal   = ''; this.endLabel   = ''; this.$wire.set('endDate', ''); }

            // A cleared date changes which days are selectable in the other
            // calendar's min/max bounds.
            if (which === 'start' || which === 'end') this.rebuildCells();

            this.activeDrop = '';
            this.activeCal  = '';
        },

        budgetDigits() {
            return String(this.$refs.budgetInput?.value ?? '').replace(/[^0-9]/g, '');
        },

        submitTripDetails() {
            // Checked here rather than on the server: the answer is already
            // known client-side, and a Livewire round trip would put ~500ms
            // between the click and the shake.
            const missing = [];
            if (!this.fromLabel)     missing.push('from');
            if (!this.toLabel)       missing.push('to');
            if (!this.budgetDigits()) missing.push('budget');
            if (!this.startVal)      missing.push('start');
            if (!this.endVal)        missing.push('end');
            if (missing.length) { this.flagBad(missing); return; }

            // The budget input only syncs on 'change'. Set it deferred (live
            // = false) so it rides along on proceedFromTripDetails' own
            // request — an immediate set would be a second round trip racing
            // the first.
            this.$wire.set('manualBudgetMin', this.$refs.budgetInput.value, false);
            this.$wire.proceedFromTripDetails();
        },

        init() {
            this.rebuildCells();
            this.$watch('startYear',  () => this.rebuildCells());
            this.$watch('startMonth', () => this.rebuildCells());
            this.$watch('endYear',    () => this.rebuildCells());
            this.$watch('endMonth',   () => this.rebuildCells());
            document.addEventListener('click', () => this.closeCals());

            // No explicit "Save Draft" button — instead, whenever From/To/
            // Budget/Start/End Date change, silently persist them as a
            // draft in the background. By the time the traveler leaves
            // (sidebar link, new tab, switching tabs) whatever they've
            // filled in is already saved; the visibilitychange listener is
            // just a best-effort extra save on the way out.
            ['manualFrom', 'manualTo', 'manualBudgetMin', 'startDate', 'endDate'].forEach(prop => {
                this.$wire.$watch(prop, () => this.$wire.autosaveDraft());
            });
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) this.$wire.autosaveDraft();
            });
        },

        _buildCells(y, m, bound, boundIsMin) {
            const todayStr = now.getFullYear()+'-'+String(now.getMonth()+1).padStart(2,'0')+'-'+String(now.getDate()).padStart(2,'0');
            const first = new Date(y, m-1, 1).getDay();
            const days  = new Date(y, m, 0).getDate();
            const cells = [];
            for (let i = 0; i < first; i++) cells.push({ d: null, key: 'e'+y+m+i, val: '', past: false });
            for (let d = 1; d <= days; d++) {
                const val = y + '-' + String(m).padStart(2,'0') + '-' + String(d).padStart(2,'0');
                const past = val < todayStr || (bound && (boundIsMin ? val < bound : val > bound));
                cells.push({ d, key: 'd'+y+m+d, val, past });
            }
            return cells;
        },

        // The start calendar can't go past the chosen end date; the end
        // calendar can't go before the chosen start date.
        rebuildCells() {
            this.startCells = this._buildCells(this.startYear, this.startMonth, this.endVal || null, false);
            this.endCells   = this._buildCells(this.endYear,   this.endMonth,   this.startVal || null, true);
        },

        toggleDrop(which) {
            this.activeDrop = this.activeDrop === which ? '' : which;
            this.activeCal = '';
            if (this.activeDrop === which) {
                this.$nextTick(() => {
                    const el = this.$refs[which+'Search'];
                    if (el) el.focus();
                });
            }
        },

        toggleCal(which) {
            this.activeCal  = this.activeCal === which ? '' : which;
            this.activeDrop = '';
        },

        closeCals() { this.activeCal = ''; this.activeDrop = ''; },

        filteredCities(which, group) {
            const q = (which === 'from' ? this.fromSearch : this.toSearch).toLowerCase();
            const otherLabel = which === 'from' ? this.toLabel : this.fromLabel;
            const otherName = otherLabel ? otherLabel.replace(/\s*\([^)]+\)$/, '') : '';
            return cities.filter(c => c.group === group && c.name !== otherName && (!q || c.name.toLowerCase().includes(q) || c.code.toLowerCase().includes(q)));
        },

        selectCity(which, c) {
            if (which === 'from') {
                this.fromLabel = c.name + ' (' + c.code + ')';
                this.fromSearch = '';
                $wire.set('manualFrom', c.name);
            } else {
                this.toLabel = c.name + ' (' + c.code + ')';
                this.toSearch = '';
                $wire.set('manualTo', c.name);
            }
            this.activeDrop = '';
        },

        swapCities() {
            [this.fromLabel, this.toLabel] = [this.toLabel, this.fromLabel];
            const fromName = this.fromLabel ? this.fromLabel.replace(/\s*\([^)]+\)$/, '') : '';
            const toName   = this.toLabel   ? this.toLabel.replace(/\s*\([^)]+\)$/, '') : '';
            $wire.set('manualFrom', toName);
            $wire.set('manualTo', fromName);
        },

        formatDate(y, m, d) {
            return y + '-' + String(m).padStart(2,'0') + '-' + String(d).padStart(2,'0');
        },

        // "Sep" is the standard 3-letter abbreviation, but "Sept" is the
        // more common/readable one — match the PHP-side fmtDate() fallback.
        abbrevMonth(m) {
            const abbr = months[m-1].slice(0,3);
            return abbr === 'Sep' ? 'Sept' : abbr;
        },

        pickDate(which, d) {
            const y = which === 'start' ? this.startYear : this.endYear;
            const m = which === 'start' ? this.startMonth : this.endMonth;
            const val = y + '-' + String(m).padStart(2,'0') + '-' + String(d).padStart(2,'0');
            const label = this.abbrevMonth(m) + ' ' + String(d).padStart(2,'0') + ', ' + y;
            if (which === 'start') {
                this.startVal = val; this.startLabel = label;
                if (this.endVal && this.endVal < val) { this.endVal = ''; this.endLabel = ''; $wire.set('endDate', ''); }
            } else {
                this.endVal = val; this.endLabel = label;
                if (this.startVal && this.startVal > val) { this.startVal = ''; this.startLabel = ''; $wire.set('startDate', ''); }
            }
            $wire.set(which === 'start' ? 'startDate' : 'endDate', val);
            this.rebuildCells();
            this.activeCal = '';
        },

        pickRangeDate(y, m, d) {
            const val   = this.formatDate(y, m, d);
            const label = this.abbrevMonth(m) + ' ' + String(d).padStart(2,'0') + ', ' + y;
            if (!this.startVal || (this.startVal && this.endVal)) {
                // First pick — reset range
                this.startVal = val; this.startLabel = label;
                this.endVal = ''; this.endLabel = '';
                $wire.set('startDate', val);
                $wire.set('endDate', '');
            } else if (val >= this.startVal) {
                // Second pick — end date
                this.endVal = val; this.endLabel = label;
                $wire.set('endDate', val);
                this.activeCal = '';
            } else {
                // Picked before start — shift start
                this.startVal = val; this.startLabel = label;
                $wire.set('startDate', val);
            }
        },

        prevMonth(which) {
            if (which === 'start') {
                this.startMonth--; if (this.startMonth < 1) { this.startMonth = 12; this.startYear--; }
            } else {
                this.endMonth--;   if (this.endMonth < 1)   { this.endMonth   = 12; this.endYear--;   }
            }
        },
        nextMonth(which) {
            if (which === 'start') {
                this.startMonth++; if (this.startMonth > 12) { this.startMonth = 1; this.startYear++; }
            } else {
                this.endMonth++;   if (this.endMonth > 12)   { this.endMonth   = 1; this.endYear++;   }
            }
        },

        monthName(y, m) { return months[m-1]; },

        calCells(y, m) {
            const first = new Date(y, m-1, 1).getDay();
            const days  = new Date(y, m, 0).getDate();
            const todayStr = now.getFullYear()+'-'+String(now.getMonth()+1).padStart(2,'0')+'-'+String(now.getDate()).padStart(2,'0');
            const cells = [];
            for (let i=0; i<first; i++) cells.push({d:null, key:'e'+i, past:false});
            for (let d=1; d<=days; d++) {
                const ds = this.formatDate(y,m,d);
                cells.push({d, key:'d'+d, past: ds < todayStr});
            }
            return cells;
        },
    };
};
</script>
@endscript
@endif

{{-- ═══════════════════════════════════════════════════════════════
     STEP 2 — Select Your Flight (manual)
═══════════════════════════════════════════════════════════════ --}}
@if ($planningMode !== '' && $step === 2)
@php
$localCities2 = [
    ['name'=>'Manila','code'=>'MNL'],['name'=>'Cebu City','code'=>'CEB'],['name'=>'Davao City','code'=>'DVO'],
    ['name'=>'Boracay','code'=>'KLO'],['name'=>'Puerto Princesa','code'=>'PPS'],['name'=>'Tagbilaran','code'=>'TAG'],
    ['name'=>'Siargao','code'=>'IAO'],['name'=>'Iloilo City','code'=>'ILO'],['name'=>'Bacolod','code'=>'BCD'],
    ['name'=>'Zamboanga','code'=>'ZAM'],['name'=>'Cagayan de Oro','code'=>'CGY'],['name'=>'General Santos','code'=>'GES'],
    ['name'=>'Tacloban','code'=>'TAC'],['name'=>'Dumaguete','code'=>'DGT'],['name'=>'El Nido','code'=>'ENI'],
    ['name'=>'Coron','code'=>'USU'],['name'=>'Baguio','code'=>'BAG'],['name'=>'Tagaytay','code'=>'MNL'],
    ['name'=>'Vigan','code'=>'VIG'],['name'=>'Batanes','code'=>'BSO'],['name'=>'Camiguin','code'=>'CGM'],
    ['name'=>'Siquijor','code'=>'DGT'],['name'=>'Surigao','code'=>'SUG'],['name'=>'Laoag','code'=>'LAO'],
    ['name'=>'Legazpi','code'=>'LGP'],
];
$intlCities2 = [
    ['name'=>'Singapore','code'=>'SIN'],['name'=>'Bangkok','code'=>'BKK'],['name'=>'Bali','code'=>'DPS'],
    ['name'=>'Tokyo','code'=>'NRT'],['name'=>'Seoul','code'=>'ICN'],['name'=>'Kuala Lumpur','code'=>'KUL'],
    ['name'=>'Hong Kong','code'=>'HKG'],['name'=>'Dubai','code'=>'DXB'],['name'=>'London','code'=>'LHR'],
    ['name'=>'Paris','code'=>'CDG'],['name'=>'New York','code'=>'JFK'],['name'=>'Sydney','code'=>'SYD'],
    ['name'=>'Osaka','code'=>'KIX'],['name'=>'Taipei','code'=>'TPE'],['name'=>'Rome','code'=>'FCO'],
    ['name'=>'Barcelona','code'=>'BCN'],['name'=>'Amsterdam','code'=>'AMS'],['name'=>'Maldives','code'=>'MLE'],
    ['name'=>'Phuket','code'=>'HKT'],['name'=>'Ho Chi Minh City','code'=>'SGN'],['name'=>'Hanoi','code'=>'HAN'],
    ['name'=>'Doha','code'=>'DOH'],['name'=>'Istanbul','code'=>'IST'],['name'=>'Toronto','code'=>'YYZ'],
    ['name'=>'Los Angeles','code'=>'LAX'],
];
$allCities2 = array_merge(
    array_map(fn($c)=>array_merge($c,['group'=>'Local']),$localCities2),
    array_map(fn($c)=>array_merge($c,['group'=>'International']),$intlCities2)
);
@endphp

<style>
@keyframes tpwModalPop{from{opacity:0;transform:scale(.94) translateY(8px);}to{opacity:1;transform:scale(1) translateY(0);}}
[x-cloak]{display:none!important;}
.city-drop{position:absolute;top:calc(100% + 6px);left:0;right:0;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.12);z-index:500;max-height:320px;overflow:hidden;display:flex;flex-direction:column;}
.city-search{padding:10px 14px;border-bottom:1px solid var(--border);}
.city-search input{width:100%;border:none;outline:none;font-size:13px;color:var(--dark);background:transparent;}
.city-list{overflow-y:auto;flex:1;}
.city-group-label{padding:8px 14px 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);}
.city-item{padding:10px 14px;font-size:13px;font-weight:500;color:var(--dark);cursor:pointer;display:flex;align-items:center;gap:10px;}
.city-item:hover{background:var(--bg);}
.city-item .code{font-size:11px;font-weight:700;color:var(--primary);background:var(--bg);border-radius:4px;padding:2px 7px;width:36px;text-align:center;flex-shrink:0;}
.mini-cal{position:absolute;top:calc(100% + 6px);left:0;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.12);z-index:500;padding:16px;min-width:260px;}
.cal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.cal-nav{background:none;border:none;cursor:pointer;color:var(--muted);font-size:14px;padding:4px 8px;}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;text-align:center;}
.cal-day-name{font-size:10px;font-weight:700;color:var(--muted);padding:4px 0;}
.cal-day{font-size:12px;font-weight:500;padding:6px 4px;border-radius:6px;cursor:pointer;}
.cal-day:hover:not(.past):not(.empty){background:var(--bg);}
.cal-day.selected{background:var(--primary);color:#fff;}
.cal-day.empty{cursor:default;}
.cal-day.in-range{background:var(--primary-light);color:var(--primary);}
.cal-day.past{color:var(--muted);opacity:.35;cursor:not-allowed;}
</style>

<div x-data="pytFlight()" x-init="init()" style="padding-bottom:20px;">

    {{-- Page header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:24px;flex-wrap:wrap;">
        <div>
            <button wire:click="backFromEdit(1)"
                    style="display:inline-flex;align-items:center;gap:6px;background:none;border:none;color:var(--primary);font-size:13px;font-weight:600;cursor:pointer;padding:0;margin-bottom:10px;">
                <i class="fa-solid fa-arrow-left" style="font-size:11px;"></i> Back to Trip Details
            </button>
            <h1 style="font-size:26px;font-weight:800;color:var(--dark);margin:0 0 6px;">Select Flight</h1>
            <p style="font-size:14px;color:var(--muted);margin:0;">
                @if($mcSearched && $mcTo)
                    Showing the best flight options for your {{ $manualFrom }} to {{ $manualTo }} and {{ $manualTo }} to {{ $mcTo }} trip.
                @else
                    Showing the best flight options for your {{ $manualFrom }} to {{ $manualTo }} trip.
                @endif
            </p>
        </div>
        {{-- Route + Date badge(s) --}}
        <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,0.06);overflow:hidden;flex-shrink:0;">
            <div style="display:inline-flex;align-items:stretch;">
                <div style="padding:8px 12px;border-right:1px solid var(--border);display:flex;flex-direction:column;justify-content:center;">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:5px;">Route @if($mcSearched && $mcTo)(Leg 1)@endif</div>
                    <div style="font-size:14px;font-weight:800;color:var(--dark);display:flex;align-items:center;gap:4px;">
                        {{ \App\Livewire\Traveler\TripPlannerWizard::staticIataCode($manualFrom) }}
                        <span style="color:var(--muted);font-size:13px;font-weight:400;">→</span>
                        {{ \App\Livewire\Traveler\TripPlannerWizard::staticIataCode($manualTo) }}
                    </div>
                </div>
                <div style="padding:8px 12px;display:flex;flex-direction:column;justify-content:center;">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:5px;">Date</div>
                    <div style="font-size:14px;font-weight:700;color:var(--dark);white-space:nowrap;">
                        @if($startDate)
                            {{ \App\Livewire\Traveler\TripPlannerWizard::fmtDate($startDate) }}
                            @if($endDate) – {{ \App\Livewire\Traveler\TripPlannerWizard::fmtDate($endDate) }}@endif
                        @else —
                        @endif
                    </div>
                </div>
            </div>

            @if($mcSearched && $mcTo)
            <div style="border-top:1px solid var(--border);display:inline-flex;align-items:stretch;width:100%;">
                <div style="padding:8px 12px;border-right:1px solid var(--border);display:flex;flex-direction:column;justify-content:center;">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:5px;">Route (Leg 2)</div>
                    <div style="font-size:14px;font-weight:800;color:var(--dark);display:flex;align-items:center;gap:4px;">
                        {{ \App\Livewire\Traveler\TripPlannerWizard::staticIataCode($manualTo) }}
                        <span style="color:var(--muted);font-size:13px;font-weight:400;">→</span>
                        {{ \App\Livewire\Traveler\TripPlannerWizard::staticIataCode($mcTo) }}
                    </div>
                </div>
                <div style="padding:8px 12px;display:flex;flex-direction:column;justify-content:center;">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:5px;">Date</div>
                    <div style="font-size:14px;font-weight:700;color:var(--dark);white-space:nowrap;">
                        @if($mcStartDate)
                            {{ \App\Livewire\Traveler\TripPlannerWizard::fmtDate($mcStartDate) }}
                            @if($mcEndDate) – {{ \App\Livewire\Traveler\TripPlannerWizard::fmtDate($mcEndDate) }}@endif
                        @else —
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Search panel --}}
    <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;overflow:visible;box-shadow:0 2px 8px rgba(0,0,0,0.04);margin-bottom:14px;width:100%;">

        {{-- LEG 1: FROM | TO | START DATE | END DATE --}}
        <div style="display:flex;align-items:stretch;border-bottom:1px solid var(--border);min-width:0;">

            {{-- FROM --}}
            <div style="flex:1;min-width:0;padding:16px 20px;border-right:1px solid var(--border);position:relative;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">From</div>
                <div style="display:flex;align-items:center;gap:8px;cursor:pointer;" @click="toggleDrop2('from')">
                    <i class="fa-solid fa-plane-departure" style="color:var(--primary);font-size:13px;flex-shrink:0;"></i>
                    <span style="font-size:14px;font-weight:600;color:var(--dark);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" x-text="(fromLabel||'{{ $manualFrom }}').replace(/\s*\([^)]+\)$/,'')"></span>
                    <i class="fa-solid fa-chevron-down" style="font-size:10px;color:var(--muted);flex-shrink:0;"></i>
                </div>
                <div class="city-drop" x-show="activeDrop2==='from'" @click.outside="activeDrop2=''" x-cloak style="min-width:260px;">
                    <div class="city-search"><input type="text" x-model="fromSearch2" placeholder="Select city" @input="$forceUpdate()"></div>
                    <div class="city-list">
                        <template x-for="grp in ['Local','International']" :key="grp">
                            <div>
                                <div class="city-group-label" x-text="grp+' Destinations'"></div>
                                <template x-for="c in filteredCities2('from',grp)" :key="c.code+c.name">
                                    <div class="city-item" @click="selectCity2('from',c)"><span class="code" x-text="c.code"></span><span x-text="c.name"></span></div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- TO --}}
            <div style="flex:1;min-width:0;padding:16px 20px;border-right:1px solid var(--border);position:relative;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">To</div>
                <div style="display:flex;align-items:center;gap:8px;cursor:pointer;" @click="toggleDrop2('to')">
                    <i class="fa-solid fa-plane-arrival" style="color:var(--primary);font-size:13px;flex-shrink:0;"></i>
                    <span style="font-size:14px;font-weight:600;color:var(--dark);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" x-text="(toLabel||'{{ $manualTo }}').replace(/\s*\([^)]+\)$/,'')"></span>
                    <i class="fa-solid fa-chevron-down" style="font-size:10px;color:var(--muted);flex-shrink:0;"></i>
                </div>
                <div class="city-drop" x-show="activeDrop2==='to'" @click.outside="activeDrop2=''" x-cloak style="min-width:260px;">
                    <div class="city-search"><input type="text" x-model="toSearch2" placeholder="Select city" @input="$forceUpdate()"></div>
                    <div class="city-list">
                        <template x-for="grp in ['Local','International']" :key="grp">
                            <div>
                                <div class="city-group-label" x-text="grp+' Destinations'"></div>
                                <template x-for="c in filteredCities2('to',grp)" :key="c.code+c.name">
                                    <div class="city-item" @click="selectCity2('to',c)"><span class="code" x-text="c.code"></span><span x-text="c.name"></span></div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- START DATE --}}
            <div style="flex:1;min-width:0;padding:16px 20px;border-right:1px solid var(--border);position:relative;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">Start Date</div>
                <div style="display:flex;align-items:center;gap:8px;cursor:pointer;" @click="toggleCal2('start')">
                    <i class="fa-regular fa-calendar" style="color:var(--primary);font-size:13px;flex-shrink:0;"></i>
                    <span x-show="!(startLabel2||'{{ $startDate }}')" style="font-size:14px;color:#C4B8AC;flex:1;">Select date</span>
                    <span x-show="startLabel2||'{{ $startDate }}'" style="font-size:14px;font-weight:600;color:var(--dark);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                          x-text="startLabel2||'{{ \App\Livewire\Traveler\TripPlannerWizard::fmtDate($startDate) }}'"></span>
                    <i class="fa-solid fa-chevron-down" style="font-size:10px;color:var(--muted);flex-shrink:0;"></i>
                </div>
                <div class="mini-cal" x-show="activeCal2==='start'" @click.outside="activeCal2=''" x-cloak>
                    <div class="cal-header">
                        <button class="cal-nav" @click.stop="prevMonth2('start')"><i class="fa-solid fa-chevron-left"></i></button>
                        <span style="font-size:13px;font-weight:700;" x-text="monthName2(sY,sM)+' '+sY"></span>
                        <button class="cal-nav" @click.stop="nextMonth2('start')"><i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                    <div class="cal-grid">
                        <template x-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']"><div class="cal-day-name" x-text="d"></div></template>
                        <template x-for="cell in calCells2(sY,sM,'start')" :key="cell.key">
                            <div class="cal-day" :class="{'selected':cell.d&&fmt2(sY,sM,cell.d)===startVal2,'past':cell.past,'empty':!cell.d}"
                                 @click.stop="cell.d&&!cell.past&&pickDate2('start',cell.d)" x-text="cell.d||''"></div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- END DATE --}}
            <div style="flex:1;min-width:0;padding:16px 20px;position:relative;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">End Date</div>
                <div style="display:flex;align-items:center;gap:8px;cursor:pointer;" @click="toggleCal2('end')">
                    <i class="fa-regular fa-calendar" style="color:var(--primary);font-size:13px;flex-shrink:0;"></i>
                    <span x-show="!(endLabel2||'{{ $endDate }}')" style="font-size:14px;color:#C4B8AC;flex:1;">Select date</span>
                    <span x-show="endLabel2||'{{ $endDate }}'" style="font-size:14px;font-weight:600;color:var(--dark);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                          x-text="endLabel2||'{{ \App\Livewire\Traveler\TripPlannerWizard::fmtDate($endDate) }}'"></span>
                    <i class="fa-solid fa-chevron-down" style="font-size:10px;color:var(--muted);flex-shrink:0;"></i>
                </div>
                <div class="mini-cal" x-show="activeCal2==='end'" @click.outside="activeCal2=''" x-cloak style="right:0;left:auto;">
                    <div class="cal-header">
                        <button class="cal-nav" @click.stop="prevMonth2('end')"><i class="fa-solid fa-chevron-left"></i></button>
                        <span style="font-size:13px;font-weight:700;" x-text="monthName2(eY,eM)+' '+eY"></span>
                        <button class="cal-nav" @click.stop="nextMonth2('end')"><i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                    <div class="cal-grid">
                        <template x-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']"><div class="cal-day-name" x-text="d"></div></template>
                        <template x-for="cell in calCells2(eY,eM,'end')" :key="cell.key">
                            <div class="cal-day" :class="{'selected':cell.d&&fmt2(eY,eM,cell.d)===endVal2,'past':cell.past,'empty':!cell.d}"
                                 @click.stop="cell.d&&!cell.past&&pickDate2('end',cell.d)" x-text="cell.d||''"></div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- LEG 2 — Multi-city: FROM (locked) + TO + START DATE + END DATE --}}
        <div :style="tripType==='multi_city'?'display:flex;align-items:stretch;border-bottom:1px solid var(--border);min-width:0;':'display:none;'">

            {{-- FROM (locked = leg 1 TO) --}}
            <div style="flex:1;min-width:0;padding:16px 20px;border-right:1px solid var(--border);">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">From</div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-plane-departure" style="color:var(--primary);font-size:13px;flex-shrink:0;"></i>
                    <span style="font-size:14px;font-weight:600;color:var(--dark);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                          x-text="toLabel ? toLabel.replace(/\s*\([^)]+\)$/,'') : '{{ $manualTo }}'"></span>
                </div>
            </div>

            {{-- TO (mc) --}}
            <div style="flex:1;min-width:0;padding:16px 20px;border-right:1px solid var(--border);position:relative;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">To</div>
                <div style="display:flex;align-items:center;gap:8px;cursor:pointer;" @click="toggleDrop2('mc')">
                    <i class="fa-solid fa-plane-arrival" style="color:var(--primary);font-size:13px;flex-shrink:0;"></i>
                    <span x-show="!mcLabel" style="font-size:14px;color:#C4B8AC;flex:1;">Where to?</span>
                    <span x-show="mcLabel" x-text="mcLabel" style="font-size:14px;font-weight:600;color:var(--dark);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                    <i class="fa-solid fa-chevron-down" style="font-size:10px;color:var(--muted);flex-shrink:0;"></i>
                </div>
                <div class="city-drop" x-show="activeDrop2==='mc'" @click.outside="activeDrop2=''" x-cloak style="min-width:260px;z-index:1000;">
                    <div class="city-search"><input type="text" x-model="mcSearch" placeholder="Select city" @input="$forceUpdate()"></div>
                    <div class="city-list">
                        <template x-for="grp in ['Local','International']" :key="grp">
                            <div>
                                <div class="city-group-label" x-text="grp+' Destinations'"></div>
                                <template x-for="c in filteredCities2('mc',grp)" :key="c.code+c.name">
                                    <div class="city-item" @click="selectCity2('mc',c)"><span class="code" x-text="c.code"></span><span x-text="c.name"></span></div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- MC START DATE --}}
            <div style="flex:1;min-width:0;padding:16px 20px;border-right:1px solid var(--border);position:relative;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">Start Date</div>
                <div style="display:flex;align-items:center;gap:8px;cursor:pointer;" @click="toggleCal2('mc-start')">
                    <i class="fa-regular fa-calendar" style="color:var(--primary);font-size:13px;flex-shrink:0;"></i>
                    <span x-show="!mcStartLabel" style="font-size:14px;color:#C4B8AC;flex:1;">Select date</span>
                    <span x-show="mcStartLabel" x-text="mcStartLabel" style="font-size:14px;font-weight:600;color:var(--dark);flex:1;"></span>
                    <i class="fa-solid fa-chevron-down" style="font-size:10px;color:var(--muted);flex-shrink:0;"></i>
                </div>
                <div class="mini-cal" x-show="activeCal2==='mc-start'" @click.outside="activeCal2=''" x-cloak style="z-index:1000;">
                    <div class="cal-header">
                        <button class="cal-nav" @click.stop="prevMonth2('mc')"><i class="fa-solid fa-chevron-left"></i></button>
                        <span style="font-size:13px;font-weight:700;" x-text="monthName2(mcY,mcM)+' '+mcY"></span>
                        <button class="cal-nav" @click.stop="nextMonth2('mc')"><i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                    <div class="cal-grid">
                        <template x-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']"><div class="cal-day-name" x-text="d"></div></template>
                        <template x-for="cell in calCells2(mcY,mcM,'mc-start')" :key="cell.key">
                            <div class="cal-day" :class="{'selected':cell.d&&fmt2(mcY,mcM,cell.d)===mcStartVal,'past':cell.past,'empty':!cell.d}"
                                 @click.stop="cell.d&&!cell.past&&pickDate2('mc-start',cell.d)" x-text="cell.d||''"></div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- MC END DATE --}}
            <div style="flex:1;min-width:0;padding:16px 20px;position:relative;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">End Date</div>
                <div style="display:flex;align-items:center;gap:8px;cursor:pointer;" @click="toggleCal2('mc-end')">
                    <i class="fa-regular fa-calendar" style="color:var(--primary);font-size:13px;flex-shrink:0;"></i>
                    <span x-show="!mcEndLabel" style="font-size:14px;color:#C4B8AC;flex:1;">Select date</span>
                    <span x-show="mcEndLabel" x-text="mcEndLabel" style="font-size:14px;font-weight:600;color:var(--dark);flex:1;"></span>
                    <i class="fa-solid fa-chevron-down" style="font-size:10px;color:var(--muted);flex-shrink:0;"></i>
                </div>
                <div class="mini-cal" x-show="activeCal2==='mc-end'" @click.outside="activeCal2=''" x-cloak style="right:0;left:auto;z-index:1000;">
                    <div class="cal-header">
                        <button class="cal-nav" @click.stop="prevMonth2('mc2')"><i class="fa-solid fa-chevron-left"></i></button>
                        <span style="font-size:13px;font-weight:700;" x-text="monthName2(mc2Y,mc2M)+' '+mc2Y"></span>
                        <button class="cal-nav" @click.stop="nextMonth2('mc2')"><i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                    <div class="cal-grid">
                        <template x-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']"><div class="cal-day-name" x-text="d"></div></template>
                        <template x-for="cell in calCells2(mc2Y,mc2M,'mc-end')" :key="cell.key">
                            <div class="cal-day" :class="{'selected':cell.d&&fmt2(mc2Y,mc2M,cell.d)===mcEndVal,'past':cell.past,'empty':!cell.d}"
                                 @click.stop="cell.d&&!cell.past&&pickDate2('mc-end',cell.d)" x-text="cell.d||''"></div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- Search Flights button --}}
        <div style="display:flex;justify-content:flex-end;padding:14px 20px;">
            <button wire:click="searchManualFlights" wire:loading.attr="disabled" wire:target="searchManualFlights"
                    style="background:var(--primary);color:#fff;border:none;border-radius:12px;padding:11px 26px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:background .18s;"
                    onmouseenter="this.style.background='var(--primary-dark)'"
                    onmouseleave="this.style.background='var(--primary)'">
                <span wire:loading.remove wire:target="searchManualFlights"><i class="fa-solid fa-magnifying-glass"></i> Search Flights</span>
                <span wire:loading wire:target="searchManualFlights"><i class="fa-solid fa-spinner fa-spin"></i> Searching</span>
            </button>
        </div>
    </div>

    {{-- Filter row: Price sort + Trip type --}}
    <div style="display:flex;align-items:center;gap:16px;margin-top:14px;margin-bottom:14px;flex-wrap:wrap;">

        {{-- Price dropdown --}}
        <div style="position:relative;">
            <button @click="priceOpen=!priceOpen"
                    style="display:inline-flex;align-items:center;gap:10px;background:var(--bg-white);color:var(--dark);border:1.5px solid var(--border);border-radius:24px;padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,0.06);transition:border-color .15s;"
                    onmouseenter="this.style.borderColor='#D9C4AE'" onmouseleave="this.style.borderColor='var(--border)'">
                <span x-text="priceDir==='asc'?'Price: Low to High':'Price: High to Low'"></span>
                <i class="fa-solid fa-chevron-down" :style="'font-size:10px;color:var(--muted);transition:transform .15s;' + (priceOpen?'transform:rotate(180deg)':'')"></i>
            </button>
            <div x-show="priceOpen" @click.outside="priceOpen=false" x-cloak
                 style="position:absolute;top:calc(100% + 6px);left:0;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,0.08);z-index:50;min-width:180px;overflow:hidden;">
                <button @click="priceDir='asc';priceOpen=false;sortFlights()"
                        style="width:100%;text-align:left;padding:13px 18px;border:none;background:none;font-size:13px;font-weight:500;color:var(--dark);cursor:pointer;display:block;"
                        onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background='transparent'">
                    Price: Low to High
                </button>
                <div style="height:1px;background:var(--border);"></div>
                <button @click="priceDir='desc';priceOpen=false;sortFlights()"
                        style="width:100%;text-align:left;padding:13px 18px;border:none;background:none;font-size:13px;font-weight:500;color:var(--dark);cursor:pointer;display:block;"
                        onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background='transparent'">
                    Price: High to Low
                </button>
            </div>
        </div>

        {{-- Airline dropdown — carriers are read off whatever the search
             actually returned, so there are never options that match nothing.
             Filters client-side like the price sort beside it; no re-query,
             so it costs nothing against the SerpAPI daily cap. --}}
        @php
            $airlineOptions = collect($flightResults)->pluck('airline')
                ->filter()->unique()->sort()->values();
        @endphp
        @if ($airlineOptions->count() > 1)
        <div style="position:relative;">
            <button @click="airlineOpen=!airlineOpen"
                    style="display:inline-flex;align-items:center;gap:10px;background:var(--bg-white);color:var(--dark);border:1.5px solid var(--border);border-radius:24px;padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,0.06);transition:border-color .15s;"
                    onmouseenter="this.style.borderColor='#D9C4AE'" onmouseleave="this.style.borderColor='var(--border)'">
                <span x-text="airline === '' ? 'All Airlines' : airline"></span>
                <i class="fa-solid fa-chevron-down" :style="'font-size:10px;color:var(--muted);transition:transform .15s;' + (airlineOpen?'transform:rotate(180deg)':'')"></i>
            </button>
            <div x-show="airlineOpen" @click.outside="airlineOpen=false" x-cloak
                 style="position:absolute;top:calc(100% + 6px);left:0;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,0.08);z-index:50;min-width:210px;max-height:280px;overflow-y:auto;">
                <button @click="airline='';airlineOpen=false;filterAirline()"
                        :style="'width:100%;text-align:left;padding:13px 18px;border:none;background:none;font-size:13px;cursor:pointer;display:block;font-family:inherit;' + (airline==='' ? 'font-weight:700;color:var(--primary);' : 'font-weight:500;color:var(--dark);')"
                        onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background='transparent'">
                    All Airlines
                </button>
                @foreach ($airlineOptions as $al)
                <div style="height:1px;background:var(--border);"></div>
                <button @click="airline=@js($al);airlineOpen=false;filterAirline()"
                        :style="'width:100%;text-align:left;padding:13px 18px;border:none;background:none;font-size:13px;cursor:pointer;display:block;font-family:inherit;' + (airline===@js($al) ? 'font-weight:700;color:var(--primary);' : 'font-weight:500;color:var(--dark);')"
                        onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background='transparent'">
                    {{ $al }}
                </button>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Trip type radios --}}
        <div style="display:flex;align-items:center;gap:20px;">
            @foreach(['one_way'=>'One-way','round_trip'=>'Round Trip','multi_city'=>'Multi-city'] as $val => $label)
            <label style="display:inline-flex;align-items:center;gap:7px;cursor:pointer;font-size:13px;font-weight:500;color:var(--dark);">
                <input type="radio" name="trip_type" value="{{ $val }}"
                       wire:model.live="flightTripType"
                       @change="tripType='{{ $val }}'"
                       {{ $flightTripType === $val ? 'checked' : '' }}
                       style="accent-color:var(--primary);width:15px;height:15px;cursor:pointer;">
                {{ $label }}
            </label>
            @endforeach
        </div>
    </div>

    {{-- Loading --}}
    @if ($flightLoading)
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:70px 20px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;">
        <div style="width:56px;height:56px;border-radius:16px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size:22px;color:var(--primary);"></i>
        </div>
        <p style="color:var(--dark);font-size:15px;font-weight:600;margin:0;">Searching for flights…</p>
    </div>
    @elseif ($flightError)
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:70px 20px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;">
        <div style="width:56px;height:56px;border-radius:16px;background:#FEE2E2;display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size:22px;color:var(--danger);"></i>
        </div>
        <p style="color:var(--danger);font-size:15px;font-weight:600;margin:0;">{{ $flightError }}</p>
    </div>
    @elseif (empty($flightResults))
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:70px 20px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;">
        <div style="width:56px;height:56px;border-radius:16px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
            <i class="fa-solid fa-plane-slash" style="font-size:22px;color:var(--primary);"></i>
        </div>
        <p style="color:var(--muted);font-size:15px;margin:0;">No flights found. Try searching above.</p>
    </div>
    @elseif($mcFlightStep)
    @if($mcFlightLoading)
    <div style="text-align:center;padding:60px 0;color:var(--muted);"><i class="fa-solid fa-spinner fa-spin" style="font-size:22px;"></i></div>
    @elseif(empty($mcFlightResults))
    <div style="text-align:center;padding:60px 0;color:var(--muted);">No flights found for this leg.</div>
    @else
    <div style="display:flex;flex-direction:column;gap:12px;">
        @foreach ($mcFlightResults as $idx => $flight)
        @php
            $dur    = $flight['duration'] ?? 0;
            $durStr = $dur ? (floor($dur/60).'h '.($dur%60).'m') : '';

            // Real stop count from the itinerary's segments. This label used
            // to be a hardcoded "Nonstop", which turned a two-leg trip with a
            // long layover into what looked like an absurdly slow direct
            // flight (CEB→TAG showed "7h 55m · Nonstop" for CEB→MNL→TAG with
            // a 5-hour Manila layover).
            $stops    = (int) ($flight['stops'] ?? 0);
            $stopsStr = $stops === 0 ? 'Nonstop' : ($stops === 1 ? '1 stop' : $stops.' stops');
            $layStr   = collect($flight['layovers'] ?? [])->map(function ($l) {
                $mins = (int) ($l['duration'] ?? 0);
                $h    = intdiv($mins, 60);
                $m    = $mins % 60;
                $len  = trim(($h ? $h.'h ' : '').($m ? $m.'m' : ''));
                return trim(($l['id'] ?? '').($len ? ' '.$len : ''));
            })->filter()->implode(', ');
            $dayOff   = (int) ($flight['day_offset'] ?? 0);
            $dep    = $flight['depart'] ?? '';
            $arr    = $flight['arrive'] ?? '';
            $fmtTime = fn($t) => $t ? date('g:i A', strtotime($t)) : '';
        @endphp
        <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;overflow:hidden;transition:box-shadow .2s,transform .2s,border-color .2s;"
             onmouseenter="this.style.boxShadow='0 10px 30px rgba(45,27,20,0.10)';this.style.transform='translateY(-2px)';this.style.borderColor='#E7D4C4'"
             onmouseleave="this.style.boxShadow='none';this.style.transform='none';this.style.borderColor='var(--border)'">
            @if(!empty($flight['bags']))
            <div style="padding:7px 20px;font-size:11px;font-weight:600;color:var(--muted);display:flex;align-items:center;gap:6px;border-bottom:1px solid var(--border);">
                <i class="fa-solid fa-suitcase" style="font-size:10px;"></i> {{ $flight['bags'] }}
            </div>
            @endif
            <div style="padding:18px 24px;display:grid;grid-template-columns:120px 100px 1fr 100px 160px;align-items:center;gap:0;">
                <div>
                    @if(!empty($flight['logo']))<img src="{{ $flight['logo'] }}" alt="{{ $flight['airline'] }}" style="height:28px;object-fit:contain;max-width:90px;display:block;margin-bottom:6px;">@endif
                    <div style="font-size:12px;font-weight:700;color:var(--dark);line-height:1.3;">{{ $flight['airline'] ?? '' }}</div>
                    <div style="font-size:11px;color:var(--muted);">{{ $flight['number'] ?? '' }}</div>
                </div>
                <div>
                    <div style="font-size:22px;font-weight:800;color:var(--dark);line-height:1;">{{ $fmtTime($dep) }}</div>
                    <div style="font-size:12px;color:var(--muted);margin-top:3px;">{{ $flight['dep_id'] ?? '' }}</div>
                </div>
                <div style="text-align:center;padding:0 16px;">
                    <div style="font-size:11px;color:var(--muted);margin-bottom:6px;">{{ $durStr }}</div>
                    <div style="position:relative;height:1px;background:var(--border);">
                        <span style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);background:var(--bg-white);padding:0 6px;">
                            <i class="fa-solid fa-plane" style="font-size:13px;color:var(--primary);"></i>
                        </span>
                    </div>
                    <div style="font-size:11px;margin-top:6px;color:{{ $stops > 0 ? 'var(--dark)' : 'var(--muted)' }};font-weight:{{ $stops > 0 ? '600' : '400' }};">
                        {{ $stopsStr }}@if($layStr)<span style="color:var(--muted);font-weight:400;"> · via {{ $layStr }}</span>@endif
                    </div>
                </div>
                <div>
                    <div style="font-size:22px;font-weight:800;color:var(--dark);line-height:1;">
                        {{ $fmtTime($arr) }}@if($dayOff > 0)<sup style="font-size:11px;font-weight:700;color:var(--primary);margin-left:2px;">+{{ $dayOff }}</sup>@endif
                    </div>
                    <div style="font-size:12px;color:var(--muted);margin-top:3px;">{{ $flight['arr_id'] ?? '' }}@if($dayOff > 0)<span style="white-space:nowrap;"> · next day</span>@endif</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:20px;font-weight:800;color:var(--primary);line-height:1;">{{ $this->tripDisplayAmount($flight['price'] ?? 0) }}</div>
                    <div style="font-size:11px;color:var(--muted);margin-top:3px;margin-bottom:10px;">{{ $flight['type'] ?? 'One-way' }}</div>
                    <button wire:click="confirmMcFlightPick({{ $idx }})"
                            style="background:var(--primary);color:#fff;border:none;border-radius:12px;padding:10px 22px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:background .18s,gap .18s;"
                            onmouseenter="this.style.background='var(--primary-dark)';this.style.gap='9px'"
                            onmouseleave="this.style.background='var(--primary)';this.style.gap='6px'">
                        Select <i class="fa-solid fa-chevron-right" style="font-size:10px;"></i>
                    </button>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Confirm-flight modal (leg 2) --}}
    @if ($confirmMcFlightIndex !== null && isset($mcFlightResults[$confirmMcFlightIndex]))
    @php $cf = $mcFlightResults[$confirmMcFlightIndex]; @endphp
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);z-index:2100;display:flex;align-items:center;justify-content:center;padding:20px;">
        <div style="background:var(--bg-white);border-radius:24px;width:100%;max-width:380px;overflow:hidden;animation:tpwModalPop .22s cubic-bezier(.34,1.56,.64,1);">
            <div style="position:relative;background:var(--bg);padding:36px 28px 24px;text-align:center;overflow:hidden;">
                <div style="width:64px;height:64px;border-radius:18px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
                    <i class="fa-solid fa-plane" style="font-size:24px;color:#fff;"></i>
                </div>
                <div style="font-size:19px;font-weight:800;color:var(--dark);margin-bottom:8px;">Fly with {{ $cf['airline'] ?? 'this airline' }}?</div>
                <div style="font-size:13px;color:var(--muted);line-height:1.6;max-width:280px;margin:0 auto;">
                    <strong style="color:var(--dark);">{{ $this->tripDisplayAmount($cf['price'] ?? 0) }}</strong> for this {{ $cf['type'] ?? 'flight' }}.<br>You can still change this before your trip is saved.
                </div>
            </div>
            <div style="display:flex;gap:10px;padding:20px 22px 22px;">
                <button wire:click="cancelMcFlightPick"
                        style="flex:1;background:transparent;color:var(--muted);border:1.5px solid var(--border);border-radius:12px;padding:12px 0;font-size:13px;font-weight:700;cursor:pointer;transition:background .18s,border-color .18s;"
                        onmouseenter="this.style.background='var(--border-light)';this.style.borderColor='var(--border)'" onmouseleave="this.style.background='transparent';this.style.borderColor='var(--border)'">
                    Cancel
                </button>
                <button wire:click="selectMcFlight({{ $confirmMcFlightIndex }})" wire:loading.attr="disabled" wire:target="selectMcFlight({{ $confirmMcFlightIndex }})"
                        style="flex:1;background:var(--primary);color:#fff;border:none;border-radius:12px;padding:12px 0;font-size:13px;font-weight:700;cursor:pointer;transition:background .18s,transform .12s;"
                        onmouseenter="this.style.background='var(--primary-dark)'" onmouseleave="this.style.background='var(--primary)'"
                        onmousedown="this.style.transform='scale(.97)'" onmouseup="this.style.transform='scale(1)'">
                    <span wire:loading.remove wire:target="selectMcFlight({{ $confirmMcFlightIndex }})"><i class="fa-solid fa-check" style="font-size:11px;margin-right:6px;"></i>Yes, select</span>
                    <span wire:loading wire:target="selectMcFlight({{ $confirmMcFlightIndex }})"><i class="fa-solid fa-spinner fa-spin"></i></span>
                </button>
            </div>
        </div>
    </div>
    @endif
    @endif

    @else
    {{-- Leg 1 Flight cards --}}
    <div id="flight-list" style="display:flex;flex-direction:column;gap:12px;"
         x-init="$nextTick(() => filterAirline())">
        @foreach ($flightResults as $idx => $flight)
        @php
            $dur    = $flight['duration'] ?? 0;
            $durStr = $dur ? (floor($dur/60).'h '.($dur%60).'m') : '';

            // Real stop count from the itinerary's segments. This label used
            // to be a hardcoded "Nonstop", which turned a two-leg trip with a
            // long layover into what looked like an absurdly slow direct
            // flight (CEB→TAG showed "7h 55m · Nonstop" for CEB→MNL→TAG with
            // a 5-hour Manila layover).
            $stops    = (int) ($flight['stops'] ?? 0);
            $stopsStr = $stops === 0 ? 'Nonstop' : ($stops === 1 ? '1 stop' : $stops.' stops');
            $layStr   = collect($flight['layovers'] ?? [])->map(function ($l) {
                $mins = (int) ($l['duration'] ?? 0);
                $h    = intdiv($mins, 60);
                $m    = $mins % 60;
                $len  = trim(($h ? $h.'h ' : '').($m ? $m.'m' : ''));
                return trim(($l['id'] ?? '').($len ? ' '.$len : ''));
            })->filter()->implode(', ');
            $dayOff   = (int) ($flight['day_offset'] ?? 0);
            $dep    = $flight['depart'] ?? '';
            $arr    = $flight['arrive'] ?? '';
            // Format "2026-07-22 03:50" → "3:50 AM"
            $fmtTime = fn($t) => $t ? date('g:i A', strtotime($t)) : '';
        @endphp
        <div class="flight-card" data-price="{{ $flight['price'] ?? 0 }}" data-airline="{{ $flight['airline'] ?? '' }}"
             style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;overflow:hidden;transition:box-shadow .2s,transform .2s,border-color .2s;"
             onmouseenter="this.style.boxShadow='0 10px 30px rgba(45,27,20,0.10)';this.style.transform='translateY(-2px)';this.style.borderColor='#E7D4C4'"
             onmouseleave="this.style.boxShadow='none';this.style.transform='none';this.style.borderColor='var(--border)'">
            {{-- Baggage strip --}}
            @if(!empty($flight['bags']))
            <div style="padding:7px 20px;font-size:11px;font-weight:600;color:var(--muted);display:flex;align-items:center;gap:6px;border-bottom:1px solid var(--border);">
                <i class="fa-solid fa-suitcase" style="font-size:10px;"></i> {{ $flight['bags'] }}
            </div>
            @endif
            <div style="padding:18px 24px;display:grid;grid-template-columns:120px 100px 1fr 100px 160px;align-items:center;gap:0;">
                {{-- Logo + airline --}}
                <div>
                    @if(!empty($flight['logo']))
                    <img src="{{ $flight['logo'] }}" alt="{{ $flight['airline'] }}" style="height:28px;object-fit:contain;max-width:90px;display:block;margin-bottom:6px;">
                    @endif
                    <div style="font-size:12px;font-weight:700;color:var(--dark);line-height:1.3;">{{ $flight['airline'] ?? '' }}</div>
                    <div style="font-size:11px;color:var(--muted);">{{ $flight['number'] ?? '' }}</div>
                </div>
                {{-- Depart --}}
                <div>
                    <div style="font-size:22px;font-weight:800;color:var(--dark);line-height:1;">{{ $fmtTime($dep) }}</div>
                    <div style="font-size:12px;color:var(--muted);margin-top:3px;">{{ $flight['dep_id'] ?? '' }}</div>
                </div>
                {{-- Duration line --}}
                <div style="text-align:center;padding:0 16px;">
                    <div style="font-size:11px;color:var(--muted);margin-bottom:6px;">{{ $durStr }}</div>
                    <div style="position:relative;height:1px;background:var(--border);">
                        <span style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);background:var(--bg-white);padding:0 6px;">
                            <i class="fa-solid fa-plane" style="font-size:13px;color:var(--primary);"></i>
                        </span>
                    </div>
                    <div style="font-size:11px;margin-top:6px;color:{{ $stops > 0 ? 'var(--dark)' : 'var(--muted)' }};font-weight:{{ $stops > 0 ? '600' : '400' }};">
                        {{ $stopsStr }}@if($layStr)<span style="color:var(--muted);font-weight:400;"> · via {{ $layStr }}</span>@endif
                    </div>
                </div>
                {{-- Arrive --}}
                <div>
                    <div style="font-size:22px;font-weight:800;color:var(--dark);line-height:1;">
                        {{ $fmtTime($arr) }}@if($dayOff > 0)<sup style="font-size:11px;font-weight:700;color:var(--primary);margin-left:2px;">+{{ $dayOff }}</sup>@endif
                    </div>
                    <div style="font-size:12px;color:var(--muted);margin-top:3px;">{{ $flight['arr_id'] ?? '' }}@if($dayOff > 0)<span style="white-space:nowrap;"> · next day</span>@endif</div>
                </div>
                {{-- Price + Select --}}
                <div style="text-align:right;">
                    <div style="font-size:20px;font-weight:800;color:var(--primary);line-height:1;">{{ $this->tripDisplayAmount($flight['price'] ?? 0) }}</div>
                    <div style="font-size:11px;color:var(--muted);margin-top:3px;margin-bottom:10px;">{{ $flight['type'] ?? 'One-way' }}</div>
                    <button wire:click="confirmFlightPick({{ $idx }})"
                            style="background:var(--primary);color:#fff;border:none;border-radius:12px;padding:10px 22px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:background .18s,gap .18s;"
                            onmouseenter="this.style.background='var(--primary-dark)';this.style.gap='9px'"
                            onmouseleave="this.style.background='var(--primary)';this.style.gap='6px'">
                        Select <i class="fa-solid fa-chevron-right" style="font-size:10px;"></i>
                    </button>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Confirm-flight modal (leg 1 / one-way / round-trip) --}}
    @if ($confirmFlightIndex !== null && isset($flightResults[$confirmFlightIndex]))
    @php $cf = $flightResults[$confirmFlightIndex]; @endphp
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);z-index:2100;display:flex;align-items:center;justify-content:center;padding:20px;">
        <div style="background:var(--bg-white);border-radius:24px;width:100%;max-width:380px;overflow:hidden;animation:tpwModalPop .22s cubic-bezier(.34,1.56,.64,1);">
            <div style="position:relative;background:var(--bg);padding:36px 28px 24px;text-align:center;overflow:hidden;">
                <div style="width:64px;height:64px;border-radius:18px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
                    <i class="fa-solid fa-plane" style="font-size:24px;color:#fff;"></i>
                </div>
                <div style="font-size:19px;font-weight:800;color:var(--dark);margin-bottom:8px;">Fly with {{ $cf['airline'] ?? 'this airline' }}?</div>
                <div style="font-size:13px;color:var(--muted);line-height:1.6;max-width:280px;margin:0 auto;">
                    <strong style="color:var(--dark);">{{ $this->tripDisplayAmount($cf['price'] ?? 0) }}</strong> for this {{ $cf['type'] ?? 'flight' }}.<br>You can still change this before your trip is saved.
                </div>
            </div>
            <div style="display:flex;gap:10px;padding:20px 22px 22px;">
                <button wire:click="cancelFlightPick"
                        style="flex:1;background:transparent;color:var(--muted);border:1.5px solid var(--border);border-radius:12px;padding:12px 0;font-size:13px;font-weight:700;cursor:pointer;transition:background .18s,border-color .18s;"
                        onmouseenter="this.style.background='var(--border-light)';this.style.borderColor='var(--border)'" onmouseleave="this.style.background='transparent';this.style.borderColor='var(--border)'">
                    Cancel
                </button>
                <button wire:click="selectFlight({{ $confirmFlightIndex }})" wire:loading.attr="disabled" wire:target="selectFlight({{ $confirmFlightIndex }})"
                        style="flex:1;background:var(--primary);color:#fff;border:none;border-radius:12px;padding:12px 0;font-size:13px;font-weight:700;cursor:pointer;transition:background .18s,transform .12s;"
                        onmouseenter="this.style.background='var(--primary-dark)'" onmouseleave="this.style.background='var(--primary)'"
                        onmousedown="this.style.transform='scale(.97)'" onmouseup="this.style.transform='scale(1)'">
                    <span wire:loading.remove wire:target="selectFlight({{ $confirmFlightIndex }})"><i class="fa-solid fa-check" style="font-size:11px;margin-right:6px;"></i>Yes, select</span>
                    <span wire:loading wire:target="selectFlight({{ $confirmFlightIndex }})"><i class="fa-solid fa-spinner fa-spin"></i></span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Shown only when the airline filter hides every card. Hidden by
         default and toggled by filterAirline(), so it never flashes. --}}
    <div id="flight-airline-empty" style="display:none;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:60px 20px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;">
        <div style="width:52px;height:52px;border-radius:15px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
            <i class="fa-solid fa-plane-slash" style="font-size:20px;color:var(--primary);"></i>
        </div>
        <p style="color:var(--muted);font-size:15px;margin:0 0 14px;">No flights from <b x-text="airline" style="color:var(--dark);"></b> in these results.</p>
        <button type="button" @click="airline='';filterAirline()"
                style="border:none;border-radius:20px;background:var(--primary);color:#fff;font-family:inherit;font-size:12.5px;font-weight:700;padding:8px 18px;cursor:pointer;">
            Show all airlines
        </button>
    </div>
    @endif

</div>

@script
<script>
window.pytFlight = function() {
    const cities = @json($allCities2);
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const now = new Date();

    return {
        activeDrop2: '',
        activeCal2: '',
        priceOpen: false,
        airlineOpen: false,
        airline: '',
        priceDir: 'asc',
        tripType: @json($flightTripType),
        fromLabel: @json($manualFrom ? $manualFrom . ' (' . \App\Livewire\Traveler\TripPlannerWizard::staticIataCode($manualFrom) . ')' : ''),
        toLabel: @json($manualTo ? $manualTo . ' (' . \App\Livewire\Traveler\TripPlannerWizard::staticIataCode($manualTo) . ')' : ''),
        toCode: @json($manualTo ? \App\Livewire\Traveler\TripPlannerWizard::staticIataCode($manualTo) : ''),
        mcCode: @json($mcTo ? \App\Livewire\Traveler\TripPlannerWizard::staticIataCode($mcTo) : ''),
        fromSearch2: '', toSearch2: '',
        startLabel2: '', endLabel2: '',
        startVal2: @json($startDate ?? ''),
        endVal2:   @json($endDate   ?? ''),
        sY: now.getFullYear(), sM: now.getMonth()+1,
        eY: now.getFullYear(), eM: now.getMonth()+1,
        mcLabel: @json($mcTo ? $mcTo . ' (' . \App\Livewire\Traveler\TripPlannerWizard::staticIataCode($mcTo) . ')' : ''), mcSearch: '',
        mcStartLabel: '', mcStartVal: '',
        mcEndLabel: '',   mcEndVal: '',
        mcY: now.getFullYear(), mcM: now.getMonth()+1,
        mc2Y: now.getFullYear(), mc2M: now.getMonth()+1,

        init() {},

        toggleDrop2(w) { this.activeDrop2 = this.activeDrop2===w?'':w; this.activeCal2=''; },
        toggleCal2(w)  { this.activeCal2 = this.activeCal2===w?'':w; this.activeDrop2=''; },

        filteredCities2(which, grp) {
            const q = (which==='from'?this.fromSearch2:which==='to'?this.toSearch2:this.mcSearch).toLowerCase();
            // No city already used elsewhere in the route can be picked again:
            // 'from' excludes whatever's picked for 'to' and 'mc'; 'to'
            // excludes 'from' and 'mc'; 'mc' (leg 2's destination) excludes
            // both 'from' and 'to' (leg 2's own locked FROM is leg 1's TO).
            const stripCode = (label) => label ? label.replace(/\s*\([^)]+\)$/, '') : '';
            const others = (which==='from' ? [this.toLabel, this.mcLabel]
                          : which==='to'   ? [this.fromLabel, this.mcLabel]
                          :                  [this.fromLabel, this.toLabel]).map(stripCode);
            return cities.filter(c=>c.group===grp&&!others.includes(c.name)&&(!q||c.name.toLowerCase().includes(q)||c.code.toLowerCase().includes(q)));
        },

        selectCity2(which, c) {
            const label = c.name+' ('+c.code+')';
            if (which==='from')      { this.fromLabel=label; $wire.set('manualFrom',c.name); }
            else if (which==='to')   { this.toLabel=label; this.toCode=c.code; $wire.set('manualTo',c.name); }
            else if (which==='mc')   { this.mcLabel=label; this.mcCode=c.code; $wire.set('mcTo',c.name); }
            this.activeDrop2='';
        },

        fmt2(y,m,d) { return y+'-'+String(m).padStart(2,'0')+'-'+String(d).padStart(2,'0'); },

        pickDateAndSwitch(which, d) {
            this.pickDate2(which, d);
            if (which === 'start') { this.eY = this.sY; this.eM = this.sM; this.activeCal2 = 'end'; }
        },

        pickDate2(which, d) {
            let y, m;
            if      (which==='start')    { y=this.sY;   m=this.sM; }
            else if (which==='end')      { y=this.eY;   m=this.eM; }
            else if (which==='mc-start') { y=this.mcY;  m=this.mcM; }
            else                         { y=this.mc2Y; m=this.mc2M; }
            const val   = this.fmt2(y,m,d);
            const mAbbr = months[m-1].slice(0,3);
            const label = (mAbbr==='Sep'?'Sept':mAbbr)+' '+String(d).padStart(2,'0')+', '+y;
            if (which==='start') {
                this.startVal2=val; this.startLabel2=label; $wire.set('startDate',val);
                // A start date picked after the existing end date leaves an
                // invalid range — clear the now-stale end date instead of
                // silently allowing start > end.
                if (this.endVal2 && this.endVal2 < val) { this.endVal2=''; this.endLabel2=''; $wire.set('endDate',''); }
            }
            else if (which==='end') {
                this.endVal2=val; this.endLabel2=label; $wire.set('endDate',val);
                if (this.startVal2 && this.startVal2 > val) { this.startVal2=''; this.startLabel2=''; $wire.set('startDate',''); }
                // Leg 2 (multi-city) can't start before leg 1 now ends —
                // pushing leg 1's end date later can leave an already-picked
                // leg-2 start (and therefore end) date stranded before it.
                if (this.mcStartVal && this.mcStartVal < val) {
                    this.mcStartVal=''; this.mcStartLabel=''; $wire.set('mcStartDate','');
                    this.mcEndVal='';   this.mcEndLabel='';   $wire.set('mcEndDate','');
                }
            }
            else if (which==='mc-start') {
                this.mcStartVal=val; this.mcStartLabel=label; $wire.set('mcStartDate',val);
                if (this.mcEndVal && this.mcEndVal < val) { this.mcEndVal=''; this.mcEndLabel=''; $wire.set('mcEndDate',''); }
            }
            else {
                this.mcEndVal=val; this.mcEndLabel=label; $wire.set('mcEndDate',val);
                if (this.mcStartVal && this.mcStartVal > val) { this.mcStartVal=''; this.mcStartLabel=''; $wire.set('mcStartDate',''); }
            }
            this.activeCal2='';
        },

        prevMonth2(w) {
            if      (w==='start')  { this.sM--;   if(this.sM<1)   {this.sM=12;  this.sY--; } }
            else if (w==='end')    { this.eM--;   if(this.eM<1)   {this.eM=12;  this.eY--; } }
            else if (w==='mc')     { this.mcM--;  if(this.mcM<1)  {this.mcM=12; this.mcY--;} }
            else                   { this.mc2M--; if(this.mc2M<1) {this.mc2M=12;this.mc2Y--;} }
        },
        nextMonth2(w) {
            if      (w==='start')  { this.sM++;   if(this.sM>12)   {this.sM=1;  this.sY++; } }
            else if (w==='end')    { this.eM++;   if(this.eM>12)   {this.eM=1;  this.eY++; } }
            else if (w==='mc')     { this.mcM++;  if(this.mcM>12)  {this.mcM=1; this.mcY++;} }
            else                   { this.mc2M++; if(this.mc2M>12) {this.mc2M=1;this.mc2Y++;} }
        },

        monthName2(y,m) { return months[m-1]; },

        calCells2(y,m,which) {
            const first = new Date(y,m-1,1).getDay();
            const days  = new Date(y,m,0).getDate();
            const todayStr = now.getFullYear()+'-'+String(now.getMonth()+1).padStart(2,'0')+'-'+String(now.getDate()).padStart(2,'0');
            // Keep the range coherent: the start calendar can't go past the
            // chosen end date, and the end calendar can't go before the
            // chosen start date. Leg 2 (multi-city) additionally can't start
            // before leg 1 ends — its own start/end relationship stacks on
            // top of that floor.
            let minBound = null, maxBound = null;
            if      (which==='start')    { maxBound = this.endVal2 || null; }
            else if (which==='end')      { minBound = this.startVal2 || null; }
            else if (which==='mc-start') { minBound = this.endVal2 || null; maxBound = this.mcEndVal || null; }
            else if (which==='mc-end')   { minBound = this.mcStartVal || this.endVal2 || null; }
            const cells=[];
            for(let i=0;i<first;i++) cells.push({d:null,key:'e'+i,past:false});
            for(let d=1;d<=days;d++) {
                const ds=this.fmt2(y,m,d);
                const past = ds<todayStr || (minBound && ds<minBound) || (maxBound && ds>maxBound);
                cells.push({d,key:'d'+d,past});
            }
            return cells;
        },

        sortFlights() {
            const list = document.getElementById('flight-list');
            if (!list) return;
            const cards = Array.from(list.querySelectorAll('.flight-card'));
            cards.sort((a,b) => {
                const pa=parseInt(a.dataset.price)||0, pb=parseInt(b.dataset.price)||0;
                return this.priceDir==='asc' ? pa-pb : pb-pa;
            });
            cards.forEach(c=>list.appendChild(c));
        },

        filterAirline() {
            const list = document.getElementById('flight-list');
            if (!list) return;
            const cards = Array.from(list.querySelectorAll('.flight-card'));

            // A carrier chosen before a re-search (trip type change, new dates)
            // may not appear in the new results. Without this the traveler is
            // left staring at an empty list with no obvious cause.
            if (this.airline && !cards.some(c => c.dataset.airline === this.airline)) {
                this.airline = '';
            }

            let shown = 0;
            cards.forEach(c => {
                const match = !this.airline || c.dataset.airline === this.airline;
                c.style.display = match ? '' : 'none';
                if (match) shown++;
            });

            const empty = document.getElementById('flight-airline-empty');
            if (empty) empty.style.display = shown ? 'none' : '';
        },

    };
};

window.sortAccommodations = function(dir) {
    const list = document.getElementById('acc-list');
    if (!list) return;
    const cards = Array.from(list.querySelectorAll('.acc-card'));
    cards.sort((a,b) => {
        const pa=parseInt(a.dataset.price)||0, pb=parseInt(b.dataset.price)||0;
        return dir==='asc' ? pa-pb : pb-pa;
    });
    cards.forEach(c=>list.appendChild(c));
};

window.sortVenues = function(dir) {
    ['venue-list','mc-venue-list'].forEach(id => {
        const list = document.getElementById(id);
        if (!list) return;
        const cards = Array.from(list.querySelectorAll('.venue-card'));
        cards.sort((a,b) => {
            const pa=parseInt(a.dataset.price)||0, pb=parseInt(b.dataset.price)||0;
            return dir==='asc' ? pa-pb : pb-pa;
        });
        cards.forEach(c=>list.appendChild(c));
    });
};
</script>
@endscript
@endif

{{-- ═══════════════════════════════════════════════════════════════
     STEP 3 — Select Your Accommodation (manual)
═══════════════════════════════════════════════════════════════ --}}
@if ($planningMode !== '' && $step === 3)
<style>
[x-cloak]{display:none!important;}
.acc-card{background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;overflow:hidden;display:grid;grid-template-columns:140px 1fr auto;align-items:stretch;transition:box-shadow .2s,transform .2s,border-color .2s;}
.acc-card:hover{box-shadow:0 10px 30px rgba(45,27,20,0.10);transform:translateY(-2px);border-color:#E7D4C4;}
.acc-img{width:100%;height:100%;min-height:132px;object-fit:cover;display:block;}
.acc-body{min-width:0;padding:16px 20px;display:flex;flex-direction:column;justify-content:center;gap:4px;}
.acc-action{padding:16px 20px;display:flex;align-items:center;}
</style>

<div x-data="{guestOpen:false,guests:'1 Adult',filterType:'hotel'}" style="padding-bottom:20px;">

    {{-- Page header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:24px;flex-wrap:wrap;">
        <div>
            <button wire:click="backFromEdit(2)"
                    style="display:inline-flex;align-items:center;gap:6px;background:none;border:none;color:var(--primary);font-size:13px;font-weight:600;cursor:pointer;padding:0;margin-bottom:10px;">
                <i class="fa-solid fa-arrow-left" style="font-size:11px;"></i> Back to Flights
            </button>
            <h1 style="font-size:26px;font-weight:800;color:var(--dark);margin:0 0 6px;">Select Accommodation</h1>
            <p style="font-size:14px;color:var(--muted);margin:0;">Showing the best stays within 15 km of {{ $mcHotelStep ? $mcTo : $manualTo }}.</p>
        </div>
        {{-- Destination + Date badge --}}
        <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;display:inline-flex;align-items:stretch;flex-shrink:0;box-shadow:0 1px 4px rgba(0,0,0,0.06);overflow:hidden;">
            <div style="padding:12px 20px;border-right:1px solid var(--border);display:flex;flex-direction:column;justify-content:center;">
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:5px;">Destination</div>
                <div style="font-size:15px;font-weight:800;color:var(--dark);">{{ $mcHotelStep ? $mcTo : $manualTo }}</div>
            </div>
            <div style="padding:12px 20px;display:flex;flex-direction:column;justify-content:center;">
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:5px;">Date</div>
                @php $accPillSd = $mcHotelStep && $mcStartDate ? $mcStartDate : $startDate; $accPillEd = $mcHotelStep && $mcEndDate ? $mcEndDate : $endDate; @endphp
                <div style="font-size:14px;font-weight:700;color:var(--dark);white-space:nowrap;">
                    @if($accPillSd && $accPillEd)
                        {{ \App\Livewire\Traveler\TripPlannerWizard::fmtDate($accPillSd) }} – {{ \App\Livewire\Traveler\TripPlannerWizard::fmtDate($accPillEd) }}
                    @elseif($accPillSd)
                        {{ \App\Livewire\Traveler\TripPlannerWizard::fmtDate($accPillSd) }}
                    @else —
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Search panel --}}
    <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;overflow:visible;box-shadow:0 2px 8px rgba(0,0,0,.04);margin-bottom:14px;width:100%;">
        <div style="display:flex;align-items:stretch;border-bottom:1px solid var(--border);min-width:0;">

            {{-- LOCATION --}}
            <div style="flex:1;min-width:0;padding:16px 20px;border-right:1px solid var(--border);">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">Location</div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-plane-arrival" style="color:var(--primary);font-size:13px;flex-shrink:0;"></i>
                    <span style="font-size:14px;font-weight:600;color:var(--dark);">{{ $mcHotelStep ? $mcTo : $manualTo }}</span>
                </div>
            </div>

            {{-- GUESTS --}}
            <div style="flex:1;min-width:0;padding:16px 20px;border-right:1px solid var(--border);position:relative;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">Guests</div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-user-group" style="color:var(--primary);font-size:12px;flex-shrink:0;"></i>
                    <span style="font-size:14px;font-weight:600;color:var(--dark);flex:1;">1 Adult</span>
                </div>
            </div>

            {{-- TRAVEL DATES --}}
            @php $accSd = $mcHotelStep && $mcStartDate ? $mcStartDate : $startDate; $accEd = $mcHotelStep && $mcEndDate ? $mcEndDate : $endDate; @endphp
            <div style="flex:1;min-width:0;padding:16px 20px;border-right:1px solid var(--border);">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">Start Date</div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-regular fa-calendar" style="color:var(--primary);font-size:13px;flex-shrink:0;"></i>
                    <span style="font-size:14px;font-weight:600;color:var(--dark);">{{ $accSd ? \App\Livewire\Traveler\TripPlannerWizard::fmtDate($accSd) : '—' }}</span>
                </div>
            </div>
            <div style="flex:1;min-width:0;padding:16px 20px;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">End Date</div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-regular fa-calendar" style="color:var(--primary);font-size:13px;flex-shrink:0;"></i>
                    <span style="font-size:14px;font-weight:600;color:var(--dark);">{{ $accEd ? \App\Livewire\Traveler\TripPlannerWizard::fmtDate($accEd) : '—' }}</span>
                </div>
            </div>
        </div>

        {{-- Search Stays button --}}
        <div style="display:flex;justify-content:flex-end;padding:14px 20px;">
            <button wire:click="searchAccommodations" wire:loading.attr="disabled" wire:target="searchAccommodations"
                    style="background:var(--primary);color:#fff;border:none;border-radius:12px;padding:11px 26px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:background .18s;"
                    onmouseenter="this.style.background='var(--primary-dark)'"
                    onmouseleave="this.style.background='var(--primary)'">
                <span wire:loading.remove wire:target="searchAccommodations"><i class="fa-solid fa-magnifying-glass"></i> Search Accommodations</span>
                <span wire:loading wire:target="searchAccommodations"><i class="fa-solid fa-spinner fa-spin"></i> Searching</span>
            </button>
        </div>
    </div>

    {{-- Filter row --}}
    <div style="display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:16px;padding-right:20px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
        <div style="position:relative;" x-data="{accPriceOpen:false,accPriceDir:'asc'}">
            <button @click="accPriceOpen=!accPriceOpen"
                    style="display:inline-flex;align-items:center;gap:10px;background:var(--bg-white);color:var(--dark);border:1.5px solid var(--border);border-radius:24px;padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
                <span x-text="accPriceDir==='asc'?'Price: Low to High':'Price: High to Low'"></span>
                <i class="fa-solid fa-chevron-down" :style="'font-size:10px;color:var(--muted);transition:transform .15s;' + (accPriceOpen?'transform:rotate(180deg)':'')"></i>
            </button>
            <div x-show="accPriceOpen" @click.outside="accPriceOpen=false" x-cloak
                 style="position:absolute;top:calc(100% + 6px);left:0;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,0.08);z-index:50;min-width:180px;overflow:hidden;">
                <button @click="accPriceDir='asc';accPriceOpen=false;sortAccommodations('asc')"
                        style="width:100%;text-align:left;padding:13px 18px;border:none;background:none;font-size:13px;font-weight:500;color:var(--dark);cursor:pointer;display:block;"
                        onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background='transparent'">
                    Price: Low to High
                </button>
                <div style="height:1px;background:var(--border);"></div>
                <button @click="accPriceDir='desc';accPriceOpen=false;sortAccommodations('desc')"
                        style="width:100%;text-align:left;padding:13px 18px;border:none;background:none;font-size:13px;font-weight:500;color:var(--dark);cursor:pointer;display:block;"
                        onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background='transparent'">
                    Price: High to Low
                </button>
            </div>
        </div>
        <div class="tp-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" wire:model="hotelSearch" wire:keydown.enter.prevent="searchHotelResults"
                   placeholder="Search stays">
            @if ($hotelSearchApplied !== '')
            <button type="button" class="tp-search-clear" wire:click="clearHotelSearch" title="Clear search">
                <i class="fa-solid fa-xmark"></i>
            </button>
            @endif
            <button type="button" class="tp-search-go" wire:click="searchHotelResults"
                    wire:loading.attr="disabled" wire:target="searchHotelResults">
                <span wire:loading.remove wire:target="searchHotelResults">Search</span>
                <span wire:loading wire:target="searchHotelResults"><i class="fa-solid fa-spinner fa-spin"></i></span>
            </button>
        </div>
        @foreach(['hotel'=>'Hotel','apartment'=>'Apartment','inn'=>'Inn','resort'=>'Resort'] as $val => $label)
        <label style="display:inline-flex;align-items:center;gap:7px;cursor:pointer;font-size:13px;font-weight:500;color:var(--dark);">
            <input type="radio" name="acc_type" value="{{ $val }}"
                   x-model="filterType"
                   @change="$wire.set('hotelType', filterType)"
                   style="accent-color:var(--primary);width:15px;height:15px;cursor:pointer;">
            {{ $label }}
        </label>
        @endforeach
        </div>

        <button wire:click="skipAccommodation" wire:loading.attr="disabled" wire:target="skipAccommodation"
                style="background:none;border:none;padding:0;font-size:14px;color:var(--muted);text-decoration:underline;cursor:pointer;">
            <span wire:loading.remove wire:target="skipAccommodation">Skip this step</span>
            <span wire:loading wire:target="skipAccommodation"><i class="fa-solid fa-spinner fa-spin"></i></span>
        </button>
    </div>

    {{-- Results --}}
    @if ($hotelLoading || ($mcHotelStep && $mcHotelLoading))
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:70px 20px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;">
        <div style="width:56px;height:56px;border-radius:16px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size:22px;color:var(--primary);"></i>
        </div>
        <p style="color:var(--dark);font-size:15px;font-weight:600;margin:0;">Searching for accommodations…</p>
    </div>
    @else
    @php
        $allHotels      = $mcHotelStep ? $mcHotelResults : $hotelResults;
        $activeHotels   = $this->filterResults($allHotels, $hotelSearchApplied);
        $isMcHotel      = $mcHotelStep;
        $hasHotels      = !empty($activeHotels);
        $hotelsFiltered = $hotelSearchApplied !== '' && count($activeHotels) !== count($allHotels);
    @endphp

    @if (!$hasHotels)
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:70px 20px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;">
        <div style="width:56px;height:56px;border-radius:16px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
            <i class="fa-solid fa-hotel" style="font-size:22px;color:var(--primary);"></i>
        </div>
        @if ($hotelSearchApplied !== '' && !empty($allHotels))
        <p style="color:var(--muted);font-size:15px;margin:0;">No stays match &ldquo;{{ $hotelSearchApplied }}&rdquo;.</p>
        @else
        <p style="color:var(--muted);font-size:15px;margin:0;">{{ $hotelError ?: 'No stays found. Try searching above.' }}</p>
        @endif
    </div>
    @else
    <div id="acc-list" style="display:flex;flex-direction:column;gap:12px;">
            @foreach ($activeHotels as $idx => $hotel)
            <div class="acc-card" data-price="{{ $hotel['nightly'] ?? 0 }}">
                @if(!empty($hotel['image']))
                <img src="{{ $hotel['image'] }}" alt="{{ $hotel['name'] }}" class="acc-img">
                @else
                <div class="acc-img" style="background:var(--bg);display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-hotel" style="font-size:28px;color:var(--muted);"></i>
                </div>
                @endif
                <div class="acc-body">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:2px;flex-wrap:wrap;">
                        <div style="font-size:16px;font-weight:700;color:var(--dark);">{{ $hotel['name'] }}</div>
                    </div>
                    @if(!empty($hotel['dist']))
                    <div style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:5px;margin-bottom:2px;">
                        <i class="fa-solid fa-location-dot" style="font-size:10px;color:var(--primary);"></i>
                        {{ $hotel['dist'] }}
                    </div>
                    @else
                    <div style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:5px;margin-bottom:2px;">
                        <i class="fa-solid fa-location-dot" style="font-size:10px;color:var(--primary);"></i>
                        {{ $mcHotelStep ? $mcTo : $manualTo }}
                    </div>
                    @endif
                    @if(!empty($hotel['stars']))
                    <div style="margin-top:4px;">
                        @for($s=0;$s<min($hotel['stars'],5);$s++)<i class="fa-solid fa-star" style="font-size:10px;color:#E8A87C;"></i>@endfor
                    </div>
                    @endif
                    <div style="margin-top:8px;">
                        <span style="font-size:18px;font-weight:800;color:var(--dark);">{{ $this->tripDisplayAmount($hotel['nightly'] ?? 0) }}</span>
                        <span style="font-size:12px;color:var(--muted);margin-left:4px;">per night</span>
                    </div>
                    @if(!empty($hotel['total']) && !empty($hotel['nights']))
                    <div style="font-size:12px;color:var(--muted);">{{ $this->tripDisplayAmount($hotel['total']) }} total · {{ $hotel['nights'] }} night{{ $hotel['nights'] > 1 ? 's' : '' }}</div>
                    @endif
                </div>
                <div class="acc-action">
                    @if($isMcHotel)
                    <button wire:click="selectMcAccommodation({{ $idx }})" wire:loading.attr="disabled" wire:target="selectMcAccommodation({{ $idx }})"
                            style="background:var(--primary);color:#fff;border:none;border-radius:10px;padding:10px 22px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:background .18s,gap .18s;"
                            onmouseenter="this.style.background='var(--primary-dark)';this.style.gap='9px'" onmouseleave="this.style.background='var(--primary)';this.style.gap='6px'">
                        <span wire:loading.remove wire:target="selectMcAccommodation({{ $idx }})">Select <i class="fa-solid fa-chevron-right" style="font-size:10px;"></i></span>
                        <span wire:loading wire:target="selectMcAccommodation({{ $idx }})"><i class="fa-solid fa-spinner fa-spin"></i></span>
                    </button>
                    @else
                    <button wire:click="selectAccommodation({{ $idx }})" wire:loading.attr="disabled" wire:target="selectAccommodation({{ $idx }})"
                            style="background:var(--primary);color:#fff;border:none;border-radius:10px;padding:10px 22px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:background .18s,gap .18s;"
                            onmouseenter="this.style.background='var(--primary-dark)';this.style.gap='9px'" onmouseleave="this.style.background='var(--primary)';this.style.gap='6px'">
                        <span wire:loading.remove wire:target="selectAccommodation({{ $idx }})">Select <i class="fa-solid fa-chevron-right" style="font-size:10px;"></i></span>
                        <span wire:loading wire:target="selectAccommodation({{ $idx }})"><i class="fa-solid fa-spinner fa-spin"></i></span>
                    </button>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

    @endif
    @endif

</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════
     STEP 4 — Select Food & Dining
═══════════════════════════════════════════════════════════════ --}}
@if ($planningMode !== '' && $step === 4)
<style>
.venue-card{background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;overflow:hidden;display:grid;grid-template-columns:130px 1fr auto;align-items:stretch;box-shadow:0 2px 8px rgba(0,0,0,.04);transition:box-shadow .2s,transform .2s,border-color .2s;}
.venue-card:hover{box-shadow:0 10px 30px rgba(45,27,20,0.10);transform:translateY(-2px);border-color:#E7D4C4;}
.venue-img{width:100%;height:100%;min-height:110px;object-fit:cover;display:block;}
.venue-body{min-width:0;padding:14px 18px;}
.venue-action{padding:16px 20px;display:flex;align-items:center;}
</style>

<div style="padding-bottom:20px;">

    {{-- Header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:24px;flex-wrap:wrap;">
        <div>
            <button wire:click="backFromEdit(3)"
                    style="display:inline-flex;align-items:center;gap:6px;background:none;border:none;color:var(--primary);font-size:13px;font-weight:600;cursor:pointer;padding:0;margin-bottom:10px;">
                <i class="fa-solid fa-arrow-left" style="font-size:11px;"></i> Back to Accommodations
            </button>
            <h1 style="font-size:26px;font-weight:800;color:var(--dark);margin:0 0 6px;">Select Food &amp; Dining</h1>
            <p style="font-size:14px;color:var(--muted);margin:0;">Showing the best dining options within 15 km of {{ $mcVenueStep ? $mcTo : ($manualTo ?: $mcTo) }}.</p>
        </div>
        {{-- Destination + Date badge --}}
        <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;display:inline-flex;align-items:stretch;flex-shrink:0;box-shadow:0 1px 4px rgba(0,0,0,0.06);overflow:hidden;">
            <div style="padding:12px 20px;border-right:1px solid var(--border);display:flex;flex-direction:column;justify-content:center;">
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:5px;">Destination</div>
                <div style="font-size:15px;font-weight:800;color:var(--dark);">{{ $mcVenueStep ? $mcTo : ($manualTo ?: $mcTo) }}</div>
            </div>
            <div style="padding:12px 20px;display:flex;flex-direction:column;justify-content:center;">
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:5px;">Date</div>
                @php $vPillSd = $mcVenueStep && $mcStartDate ? $mcStartDate : $startDate; $vPillEd = $mcVenueStep && $mcEndDate ? $mcEndDate : $endDate; @endphp
                <div style="font-size:14px;font-weight:700;color:var(--dark);white-space:nowrap;">
                    @if($vPillSd && $vPillEd)
                        {{ \App\Livewire\Traveler\TripPlannerWizard::fmtDate($vPillSd) }} – {{ \App\Livewire\Traveler\TripPlannerWizard::fmtDate($vPillEd) }}
                    @elseif($vPillSd)
                        {{ \App\Livewire\Traveler\TripPlannerWizard::fmtDate($vPillSd) }}
                    @else —
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Search bar --}}
    <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;overflow:visible;box-shadow:0 2px 8px rgba(0,0,0,.04);margin-bottom:16px;width:100%;">
        <div style="display:flex;align-items:stretch;border-bottom:1px solid var(--border);min-width:0;">
            {{-- Location --}}
            <div style="flex:1;min-width:0;padding:16px 20px;border-right:1px solid var(--border);">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">Location</div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-location-dot" style="color:var(--primary);font-size:13px;flex-shrink:0;"></i>
                    <span style="font-size:14px;font-weight:600;color:var(--dark);">{{ $mcVenueStep ? $mcTo : ($manualTo ?: $mcTo) }}</span>
                </div>
            </div>
            {{-- Category --}}
            <div style="flex:1;min-width:0;padding:16px 20px;border-right:1px solid var(--border);position:relative;" x-data="{ catOpen:false }">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">Category</div>
                <div style="display:flex;align-items:center;gap:8px;cursor:pointer;" @click="catOpen=!catOpen">
                    <i class="fa-solid fa-utensils" style="color:var(--primary);font-size:12px;flex-shrink:0;"></i>
                    <span style="font-size:14px;font-weight:600;color:var(--dark);flex:1;">{{ $venueCategory }}</span>
                    <i class="fa-solid fa-chevron-down" style="font-size:10px;color:var(--muted);flex-shrink:0;"></i>
                </div>
                <div x-show="catOpen" @click.outside="catOpen=false" x-cloak
                     style="position:absolute;top:calc(100% + 6px);left:0;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.10);z-index:200;min-width:180px;overflow:hidden;">
                    @foreach(['All Cuisines','Filipino','Asian','International','Seafood','BBQ','Fast Food','Cafe','Bakery'] as $cat)
                    <button type="button" wire:click="$set('venueCategory', '{{ $cat }}')" @click="catOpen=false"
                            style="width:100%;text-align:left;padding:11px 16px;border:none;background:none;font-size:13px;font-family:'Hanken Grotesk',sans-serif;cursor:pointer;{{ $venueCategory === $cat ? 'color:var(--primary);font-weight:700;background:var(--primary-light);' : 'color:var(--dark);' }}">
                        {{ $cat }}
                    </button>
                    @endforeach
                </div>
            </div>
            {{-- Travel dates --}}
            @php $venueSd = $mcVenueStep && $mcStartDate ? $mcStartDate : $startDate; $venueEd = $mcVenueStep && $mcEndDate ? $mcEndDate : $endDate; @endphp
            <div style="flex:1;min-width:0;padding:16px 20px;border-right:1px solid var(--border);">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">Start Date</div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-regular fa-calendar" style="color:var(--primary);font-size:12px;flex-shrink:0;"></i>
                    <span style="font-size:13px;font-weight:600;color:var(--dark);">{{ $venueSd ? \App\Livewire\Traveler\TripPlannerWizard::fmtDate($venueSd) : '—' }}</span>
                </div>
            </div>
            <div style="flex:1;min-width:0;padding:16px 20px;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">End Date</div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-regular fa-calendar" style="color:var(--primary);font-size:12px;flex-shrink:0;"></i>
                    <span style="font-size:13px;font-weight:600;color:var(--dark);">{{ $venueEd ? \App\Livewire\Traveler\TripPlannerWizard::fmtDate($venueEd) : '—' }}</span>
                </div>
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;padding:14px 20px;">
            <button wire:click="searchVenues" wire:loading.attr="disabled" wire:target="searchVenues"
                    style="background:var(--primary);color:#fff;border:none;border-radius:10px;padding:11px 24px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;"
                    onmouseenter="this.style.background='var(--primary-dark)'" onmouseleave="this.style.background='var(--primary)'">
                <span wire:loading.remove wire:target="searchVenues"><i class="fa-solid fa-magnifying-glass" style="font-size:12px;"></i> Search Food & Dining</span>
                <span wire:loading wire:target="searchVenues"><i class="fa-solid fa-spinner fa-spin"></i> Searching</span>
            </button>
        </div>
    </div>


    {{-- Filter row --}}
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:16px;padding-right:20px;flex-wrap:wrap;" x-data="{vPriceOpen:false,vPriceDir:'asc'}">
        {{-- Pill + search share a group so space-between only pushes "Skip this step" away. --}}
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
        <div style="position:relative;">
            <button @click="vPriceOpen=!vPriceOpen"
                    style="display:inline-flex;align-items:center;gap:10px;background:var(--bg-white);color:var(--dark);border:1.5px solid var(--border);border-radius:24px;padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
                <span x-text="vPriceDir==='asc'?'Price: Low to High':'Price: High to Low'"></span>
                <i class="fa-solid fa-chevron-down" :style="'font-size:10px;color:var(--muted);transition:transform .15s;' + (vPriceOpen?'transform:rotate(180deg)':'')"></i>
            </button>
            <div x-show="vPriceOpen" @click.outside="vPriceOpen=false" x-cloak
                 style="position:absolute;top:calc(100% + 6px);left:0;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,0.08);z-index:50;min-width:180px;overflow:hidden;">
                <button @click="vPriceDir='asc';vPriceOpen=false;sortVenues('asc')"
                        style="width:100%;text-align:left;padding:13px 18px;border:none;background:none;font-size:13px;font-weight:500;color:var(--dark);cursor:pointer;display:block;"
                        onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background='transparent'">
                    Price: Low to High
                </button>
                <div style="height:1px;background:var(--border);"></div>
                <button @click="vPriceDir='desc';vPriceOpen=false;sortVenues('desc')"
                        style="width:100%;text-align:left;padding:13px 18px;border:none;background:none;font-size:13px;font-weight:500;color:var(--dark);cursor:pointer;display:block;"
                        onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background='transparent'">
                    Price: High to Low
                </button>
            </div>
        </div>

        <div class="tp-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" wire:model="venueSearch" wire:keydown.enter.prevent="searchVenueResults"
                   placeholder="Search dining">
            @if ($venueSearchApplied !== '')
            <button type="button" class="tp-search-clear" wire:click="clearVenueSearch" title="Clear search">
                <i class="fa-solid fa-xmark"></i>
            </button>
            @endif
            <button type="button" class="tp-search-go" wire:click="searchVenueResults"
                    wire:loading.attr="disabled" wire:target="searchVenueResults">
                <span wire:loading.remove wire:target="searchVenueResults">Search</span>
                <span wire:loading wire:target="searchVenueResults"><i class="fa-solid fa-spinner fa-spin"></i></span>
            </button>
        </div>
        </div>

        <button wire:click="skipVenue" wire:loading.attr="disabled" wire:target="skipVenue"
                style="background:none;border:none;padding:0;font-size:14px;color:var(--muted);text-decoration:underline;cursor:pointer;">
            <span wire:loading.remove wire:target="skipVenue">Skip this step</span>
            <span wire:loading wire:target="skipVenue"><i class="fa-solid fa-spinner fa-spin"></i></span>
        </button>
    </div>

    {{-- Results --}}
    @if(!$mcVenueStep)
    {{-- Leg 1 venue list --}}
    @if($venueLoading)
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:70px 20px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;">
        <div style="width:56px;height:56px;border-radius:16px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size:22px;color:var(--primary);"></i>
        </div>
        <p style="color:var(--dark);font-size:15px;font-weight:600;margin:0;">Searching for dining options…</p>
    </div>
    @elseif(empty($this->filterResults($venueResults, $venueSearchApplied)))
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:70px 20px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;">
        <div style="width:56px;height:56px;border-radius:16px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
            <i class="fa-solid fa-utensils" style="font-size:22px;color:var(--primary);"></i>
        </div>
        @if ($venueSearchApplied !== '' && !empty($venueResults))
        <p style="color:var(--muted);font-size:15px;margin:0;">No dining spots match &ldquo;{{ $venueSearchApplied }}&rdquo;.</p>
        @else
        <p style="color:var(--muted);font-size:15px;margin:0;">{{ $venueError ?: 'No venues found. Try searching above.' }}</p>
        @endif
    </div>
    @else
    <div id="venue-list" style="display:flex;flex-direction:column;gap:12px;">
        @foreach($this->filterResults($venueResults, $venueSearchApplied) as $vi => $venue)
        @php $venueSelected = isset($selectedVenues[$venue['name']]); @endphp
        <div class="venue-card" data-price="{{ $venue['priceMin'] ?? 0 }}"
             style="{{ $venueSelected ? 'border-color:var(--primary);box-shadow:0 0 0 1.5px var(--primary);' : '' }}">
            @if(!empty($venue['image']))
            <img src="{{ $venue['image'] }}" alt="{{ $venue['name'] }}" class="venue-img">
            @else
            <div class="venue-img" style="background:var(--bg);display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-utensils" style="font-size:28px;color:var(--muted);"></i>
            </div>
            @endif
            <div class="venue-body">
                <div style="font-size:16px;font-weight:700;color:var(--dark);margin-bottom:3px;">{{ $venue['name'] }}</div>
                <div style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:12px;margin-bottom:4px;flex-wrap:wrap;">
                    <span><i class="fa-solid fa-tag" style="font-size:10px;color:var(--primary);margin-right:3px;"></i>{{ $venue['cuisine'] }}</span>
                    <span><i class="fa-solid fa-location-dot" style="font-size:10px;color:var(--primary);margin-right:3px;"></i>{{ $venue['city'] }}</span>
                </div>
                @if(!empty($venue['rating']))
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                    <i class="fa-solid fa-star" style="font-size:12px;color:#E8A87C;"></i>
                    <span style="font-size:13px;font-weight:700;color:var(--dark);">{{ $venue['rating'] }}</span>
                    @if(!empty($venue['reviews']))<span style="font-size:11px;color:var(--muted);">({{ number_format($venue['reviews']) }})</span>@endif
                </div>
                @endif
                <div style="font-size:13px;color:var(--primary);font-weight:600;">
                    {{ $this->tripDisplayAmount($venue['priceMin']) }} – {{ $this->tripDisplayAmount($venue['priceMax']) }}
                    <span style="font-size:11px;color:var(--muted);font-weight:400;"> Average price per person</span>
                </div>
            </div>
            <div class="venue-action">
                <button wire:click="toggleVenue({{ $vi }})" wire:loading.attr="disabled" wire:target="toggleVenue({{ $vi }})"
                        style="background:{{ $venueSelected ? 'var(--bg-white)' : 'var(--primary)' }};color:{{ $venueSelected ? 'var(--primary)' : '#fff' }};border:1.5px solid var(--primary);border-radius:10px;padding:10px 22px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;transition:background .18s,gap .18s;">
                    <span wire:loading.remove wire:target="toggleVenue({{ $vi }})">
                        @if($venueSelected)
                            <i class="fa-solid fa-check"></i> Selected
                        @else
                            Select <i class="fa-solid fa-chevron-right" style="font-size:10px;"></i>
                        @endif
                    </span>
                    <span wire:loading wire:target="toggleVenue({{ $vi }})"><i class="fa-solid fa-spinner fa-spin"></i></span>
                </button>
            </div>
        </div>
        @endforeach
    </div>
    <div style="height:64px;"></div>
    <div style="position:fixed;bottom:24px;right:32px;z-index:200;">
        <button wire:click="continueFromVenues" wire:loading.attr="disabled" wire:target="continueFromVenues"
                style="background:var(--primary);color:#fff;border:none;border-radius:30px;padding:14px 28px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;box-shadow:0 8px 24px rgba(0,0,0,.25);">
            <span wire:loading.remove wire:target="continueFromVenues">
                Continue{{ count($selectedVenues) ? ' (' . count($selectedVenues) . ' selected)' : '' }} <i class="fa-solid fa-arrow-right" style="font-size:11px;"></i>
            </span>
            <span wire:loading wire:target="continueFromVenues"><i class="fa-solid fa-spinner fa-spin"></i></span>
        </button>
    </div>
    @endif

    @else
    {{-- Leg 2 venue list (multi-city second destination) --}}
    @if($mcVenueLoading)
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:70px 20px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;">
        <div style="width:56px;height:56px;border-radius:16px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size:22px;color:var(--primary);"></i>
        </div>
        <p style="color:var(--dark);font-size:15px;font-weight:600;margin:0;">Searching for dining options in {{ $mcTo }}…</p>
    </div>
    @elseif(empty($mcVenueResults))
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:70px 20px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;">
        <div style="width:56px;height:56px;border-radius:16px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
            <i class="fa-solid fa-utensils" style="font-size:22px;color:var(--primary);"></i>
        </div>
        <p style="color:var(--muted);font-size:15px;margin:0;">{{ $venueError ?: 'No venues found for '.$mcTo.'.' }}</p>
    </div>
    @else
    <div id="mc-venue-list" style="display:flex;flex-direction:column;gap:12px;">
        @foreach($this->filterResults($mcVenueResults, $venueSearchApplied) as $vi => $venue)
        @php $mcVenueSelected = isset($selectedMcVenues[$venue['name']]); @endphp
        <div class="venue-card" style="{{ $mcVenueSelected ? 'border-color:var(--primary);box-shadow:0 0 0 1.5px var(--primary);' : '' }}">
            @if(!empty($venue['image']))
            <img src="{{ $venue['image'] }}" alt="{{ $venue['name'] }}" class="venue-img">
            @else
            <div class="venue-img" style="background:var(--bg);display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-utensils" style="font-size:28px;color:var(--muted);"></i>
            </div>
            @endif
            <div class="venue-body">
                <div style="font-size:16px;font-weight:700;color:var(--dark);margin-bottom:3px;">{{ $venue['name'] }}</div>
                <div style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:12px;margin-bottom:4px;flex-wrap:wrap;">
                    <span><i class="fa-solid fa-tag" style="font-size:10px;color:var(--primary);margin-right:3px;"></i>{{ $venue['cuisine'] }}</span>
                    <span><i class="fa-solid fa-location-dot" style="font-size:10px;color:var(--primary);margin-right:3px;"></i>{{ $venue['city'] }}</span>
                </div>
                @if(!empty($venue['rating']))
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                    <i class="fa-solid fa-star" style="font-size:12px;color:#E8A87C;"></i>
                    <span style="font-size:13px;font-weight:700;color:var(--dark);">{{ $venue['rating'] }}</span>
                    @if(!empty($venue['reviews']))<span style="font-size:11px;color:var(--muted);">({{ number_format($venue['reviews']) }})</span>@endif
                </div>
                @endif
                <div style="font-size:13px;color:var(--primary);font-weight:600;">
                    {{ $this->tripDisplayAmount($venue['priceMin']) }} – {{ $this->tripDisplayAmount($venue['priceMax']) }}
                    <span style="font-size:11px;color:var(--muted);font-weight:400;"> Average price per person</span>
                </div>
            </div>
            <div class="venue-action">
                <button wire:click="toggleVenue({{ $vi }})" wire:loading.attr="disabled" wire:target="toggleVenue({{ $vi }})"
                        style="background:{{ $mcVenueSelected ? 'var(--bg-white)' : 'var(--primary)' }};color:{{ $mcVenueSelected ? 'var(--primary)' : '#fff' }};border:1.5px solid var(--primary);border-radius:10px;padding:10px 22px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;transition:background .18s,gap .18s;">
                    <span wire:loading.remove wire:target="toggleVenue({{ $vi }})">
                        @if($mcVenueSelected)
                            <i class="fa-solid fa-check"></i> Selected
                        @else
                            Select <i class="fa-solid fa-chevron-right" style="font-size:10px;"></i>
                        @endif
                    </span>
                    <span wire:loading wire:target="toggleVenue({{ $vi }})"><i class="fa-solid fa-spinner fa-spin"></i></span>
                </button>
            </div>
        </div>
        @endforeach
    </div>
    <div style="height:64px;"></div>
    <div style="position:fixed;bottom:24px;right:32px;z-index:200;">
        <button wire:click="continueFromVenues" wire:loading.attr="disabled" wire:target="continueFromVenues"
                style="background:var(--primary);color:#fff;border:none;border-radius:30px;padding:14px 28px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;box-shadow:0 8px 24px rgba(0,0,0,.25);">
            <span wire:loading.remove wire:target="continueFromVenues">
                Continue{{ count($selectedMcVenues) ? ' (' . count($selectedMcVenues) . ' selected)' : '' }} <i class="fa-solid fa-arrow-right" style="font-size:11px;"></i>
            </span>
            <span wire:loading wire:target="continueFromVenues"><i class="fa-solid fa-spinner fa-spin"></i></span>
        </button>
    </div>
    @endif
    @endif

</div>

@endif

{{-- ═══════════════════════════════════════════════════════════════
     STEP 5 — Select Attractions
═══════════════════════════════════════════════════════════════ --}}
@if ($planningMode !== '' && $step === 5)
<style>
.attr-card{background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;overflow:hidden;display:grid;grid-template-columns:140px 1fr auto;align-items:stretch;box-shadow:0 2px 8px rgba(0,0,0,.04);transition:box-shadow .2s,transform .2s,border-color .2s;}
.attr-card:hover{box-shadow:0 10px 30px rgba(45,27,20,0.10);transform:translateY(-2px);border-color:#E7D4C4;}
.attr-img{width:100%;height:100%;min-height:120px;object-fit:cover;object-position:center;display:block;}
.attr-body{min-width:0;padding:14px 18px;overflow:hidden;display:flex;flex-direction:column;justify-content:center;}
.attr-action{padding:16px 20px;display:flex;align-items:center;}
</style>

<div style="padding-bottom:20px;">

    {{-- Header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:24px;flex-wrap:wrap;">
        <div>
            <button wire:click="backFromEdit(4)"
                    style="display:inline-flex;align-items:center;gap:6px;background:none;border:none;color:var(--primary);font-size:13px;font-weight:600;cursor:pointer;padding:0;margin-bottom:10px;">
                <i class="fa-solid fa-arrow-left" style="font-size:11px;"></i> Back to Planner
            </button>
            <h1 style="font-size:26px;font-weight:800;color:var(--dark);margin:0 0 6px;">Select Attractions</h1>
            @php $attrDest = $mcAttractionStep ? $mcTo : ($manualTo ?: $mcTo); @endphp
            <p style="font-size:14px;color:var(--muted);margin:0;">Showing the best attractions within 15 km of {{ $attrDest }}.</p>
        </div>
        <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;display:inline-flex;align-items:stretch;flex-shrink:0;box-shadow:0 1px 4px rgba(0,0,0,0.06);overflow:hidden;">
            <div style="padding:12px 20px;border-right:1px solid var(--border);display:flex;flex-direction:column;justify-content:center;">
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:5px;">Destination</div>
                <div style="font-size:15px;font-weight:800;color:var(--dark);">{{ $attrDest }}</div>
            </div>
            <div style="padding:12px 20px;display:flex;flex-direction:column;justify-content:center;">
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:5px;">Date</div>
                @php $attrSd = $mcAttractionStep && $mcStartDate ? $mcStartDate : $startDate; $attrEd = $mcAttractionStep && $mcEndDate ? $mcEndDate : $endDate; @endphp
                <div style="font-size:14px;font-weight:700;color:var(--dark);white-space:nowrap;">
                    @if($attrSd && $attrEd)
                        {{ \App\Livewire\Traveler\TripPlannerWizard::fmtDate($attrSd) }} – {{ \App\Livewire\Traveler\TripPlannerWizard::fmtDate($attrEd) }}
                    @elseif($attrSd)
                        {{ \App\Livewire\Traveler\TripPlannerWizard::fmtDate($attrSd) }}
                    @else —
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Search bar --}}
    <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;overflow:visible;box-shadow:0 2px 8px rgba(0,0,0,.04);margin-bottom:16px;">
        <div style="display:flex;align-items:stretch;border-bottom:1px solid var(--border);">
            <div style="flex:1;min-width:0;padding:16px 20px;border-right:1px solid var(--border);">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">Location</div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-location-dot" style="color:var(--primary);font-size:13px;flex-shrink:0;"></i>
                    <span style="font-size:14px;font-weight:600;color:var(--dark);">{{ $attrDest }}</span>
                </div>
            </div>
            <div style="flex:1;min-width:0;padding:16px 20px;border-right:1px solid var(--border);position:relative;" x-data="{ typeOpen:false }">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">Type</div>
                <div style="display:flex;align-items:center;gap:8px;cursor:pointer;" @click="typeOpen=!typeOpen">
                    <i class="fa-solid fa-binoculars" style="color:var(--primary);font-size:12px;flex-shrink:0;"></i>
                    <span style="font-size:14px;font-weight:600;color:var(--dark);flex:1;">{{ $attractionType }}</span>
                    <i class="fa-solid fa-chevron-down" style="font-size:10px;color:var(--muted);flex-shrink:0;"></i>
                </div>
                <div x-show="typeOpen" @click.outside="typeOpen=false" x-cloak
                     style="position:absolute;top:calc(100% + 6px);left:0;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.10);z-index:200;min-width:180px;overflow:hidden;">
                    @foreach(['All Attractions','Religious','Historical','Nature','Theme Park','Beach','Museum','Shopping'] as $t)
                    <button type="button" wire:click="$set('attractionType', '{{ $t }}')" @click="typeOpen=false"
                            style="width:100%;text-align:left;padding:11px 16px;border:none;background:none;font-size:13px;font-family:'Hanken Grotesk',sans-serif;cursor:pointer;{{ $attractionType === $t ? 'color:var(--primary);font-weight:700;background:var(--primary-light);' : 'color:var(--dark);' }}">
                        {{ $t }}
                    </button>
                    @endforeach
                </div>
            </div>
            <div style="flex:1;min-width:0;padding:16px 20px;border-right:1px solid var(--border);">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">Start Date</div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-regular fa-calendar" style="color:var(--primary);font-size:12px;flex-shrink:0;"></i>
                    <span style="font-size:13px;font-weight:600;color:var(--dark);">{{ $attrSd ? \App\Livewire\Traveler\TripPlannerWizard::fmtDate($attrSd) : '—' }}</span>
                </div>
            </div>
            <div style="flex:1;min-width:0;padding:16px 20px;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px;">End Date</div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa-regular fa-calendar" style="color:var(--primary);font-size:12px;flex-shrink:0;"></i>
                    <span style="font-size:13px;font-weight:600;color:var(--dark);">{{ $attrEd ? \App\Livewire\Traveler\TripPlannerWizard::fmtDate($attrEd) : '—' }}</span>
                </div>
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;padding:14px 20px;">
            <button wire:click="searchAttractionsList" wire:loading.attr="disabled" wire:target="searchAttractionsList"
                    style="background:var(--primary);color:#fff;border:none;border-radius:10px;padding:11px 24px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;"
                    onmouseenter="this.style.background='var(--primary-dark)'" onmouseleave="this.style.background='var(--primary)'">
                <span wire:loading.remove wire:target="searchAttractionsList"><i class="fa-solid fa-magnifying-glass" style="font-size:12px;"></i> Search Attractions</span>
                <span wire:loading wire:target="searchAttractionsList"><i class="fa-solid fa-spinner fa-spin"></i> Searching</span>
            </button>
        </div>
    </div>

    {{-- Filter row --}}
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:16px;padding-right:20px;flex-wrap:wrap;" x-data="{aPriceOpen:false,aPriceDir:'asc'}">
        {{-- Pill + search share a group so space-between only pushes "Skip this step" away. --}}
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
        <div style="position:relative;">
            <button @click="aPriceOpen=!aPriceOpen"
                    style="display:inline-flex;align-items:center;gap:10px;background:var(--bg-white);color:var(--dark);border:1.5px solid var(--border);border-radius:24px;padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
                <span x-text="aPriceDir==='asc'?'Price: Low to High':'Price: High to Low'"></span>
                <i class="fa-solid fa-chevron-down" :style="'font-size:10px;color:var(--muted);transition:transform .15s;' + (aPriceOpen?'transform:rotate(180deg)':'')"></i>
            </button>
            <div x-show="aPriceOpen" @click.outside="aPriceOpen=false" x-cloak
                 style="position:absolute;top:calc(100% + 6px);left:0;background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,0.08);z-index:50;min-width:180px;overflow:hidden;">
                <button @click="aPriceDir='asc';aPriceOpen=false;sortAttractions('asc')"
                        style="width:100%;text-align:left;padding:13px 18px;border:none;background:none;font-size:13px;font-weight:500;color:var(--dark);cursor:pointer;display:block;"
                        onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background='transparent'">
                    Price: Low to High
                </button>
                <div style="height:1px;background:var(--border);"></div>
                <button @click="aPriceDir='desc';aPriceOpen=false;sortAttractions('desc')"
                        style="width:100%;text-align:left;padding:13px 18px;border:none;background:none;font-size:13px;font-weight:500;color:var(--dark);cursor:pointer;display:block;"
                        onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background='transparent'">
                    Price: High to Low
                </button>
            </div>
        </div>

        <div class="tp-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" wire:model="attractionSearch" wire:keydown.enter.prevent="searchAttractionResults"
                   placeholder="Search attractions">
            @if ($attractionSearchApplied !== '')
            <button type="button" class="tp-search-clear" wire:click="clearAttractionSearch" title="Clear search">
                <i class="fa-solid fa-xmark"></i>
            </button>
            @endif
            <button type="button" class="tp-search-go" wire:click="searchAttractionResults"
                    wire:loading.attr="disabled" wire:target="searchAttractionResults">
                <span wire:loading.remove wire:target="searchAttractionResults">Search</span>
                <span wire:loading wire:target="searchAttractionResults"><i class="fa-solid fa-spinner fa-spin"></i></span>
            </button>
        </div>
        </div>

        <button wire:click="skipAttraction" wire:loading.attr="disabled" wire:target="skipAttraction"
                style="background:none;border:none;padding:0;font-size:14px;color:var(--muted);text-decoration:underline;cursor:pointer;">
            <span wire:loading.remove wire:target="skipAttraction">Skip this step</span>
            <span wire:loading wire:target="skipAttraction"><i class="fa-solid fa-spinner fa-spin"></i></span>
        </button>
    </div>

    {{-- Results --}}
    @php
        $allAttractions    = $mcAttractionStep ? $mcAttractionResults : $attractionResults;
        $activeAttractions = $this->filterResults($allAttractions, $attractionSearchApplied);
    @endphp
    @if($attractionLoading || ($mcAttractionStep && $mcAttractionLoading))
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:70px 20px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;">
        <div style="width:56px;height:56px;border-radius:16px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size:22px;color:var(--primary);"></i>
        </div>
        <p style="color:var(--dark);font-size:15px;font-weight:600;margin:0;">Searching for attractions…</p>
    </div>
    @elseif(empty($activeAttractions))
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:70px 20px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;">
        <div style="width:56px;height:56px;border-radius:16px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
            <i class="fa-solid fa-binoculars" style="font-size:22px;color:var(--primary);"></i>
        </div>
        @if ($attractionSearchApplied !== '' && !empty($allAttractions))
        <p style="color:var(--muted);font-size:15px;margin:0;">No attractions match &ldquo;{{ $attractionSearchApplied }}&rdquo;.</p>
        @else
        <p style="color:var(--muted);font-size:15px;margin:0;">{{ $attractionError ?: 'No attractions found. Try searching above.' }}</p>
        @endif
    </div>
    @else
    @php $activeSelectedAttractions = $mcAttractionStep ? $selectedMcAttractions : $selectedAttractions; @endphp
    <div style="display:flex;flex-direction:column;gap:12px;">
        @foreach($activeAttractions as $ai => $attr)
        @php
            $attrPriceSort  = (int) preg_replace('/[^\d]/', '', $attr['price'] ?? '0');
            $attrSelected   = isset($activeSelectedAttractions[$attr['name']]);
        @endphp
        <div class="attr-card" data-price="{{ $attrPriceSort }}"
             style="{{ $attrSelected ? 'border-color:var(--primary);box-shadow:0 0 0 1.5px var(--primary);' : '' }}">
            @if(!empty($attr['image']))
            <img src="{{ $attr['image'] }}" alt="{{ $attr['name'] }}" class="attr-img">
            @else
            <div class="attr-img" style="background:var(--bg);display:flex;align-items:center;justify-content:center;min-height:120px;">
                <i class="fa-solid fa-landmark" style="font-size:28px;color:var(--muted);"></i>
            </div>
            @endif
            <div class="attr-body">
                <div style="font-size:16px;font-weight:700;color:var(--dark);margin-bottom:3px;">{{ $attr['name'] }}</div>
                @php
                    $attrLocation = $attr['address'] ?? $attr['city'] ?? null;
                    $attrPriceRaw = (int) preg_replace('/[^\d]/', '', $attr['price'] ?? '0');
                @endphp
                @if($attrLocation)
                <div style="font-size:11px;color:var(--muted);display:flex;align-items:center;gap:4px;margin-bottom:3px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">
                    <i class="fa-solid fa-location-dot" style="font-size:10px;color:var(--primary);flex-shrink:0;"></i>
                    <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $attrLocation }}</span>
                </div>
                @endif
                @if(!empty($attr['rating']))
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                    <i class="fa-solid fa-star" style="font-size:12px;color:#E8A87C;"></i>
                    <span style="font-size:13px;font-weight:700;color:var(--dark);">{{ $attr['rating'] }}</span>
                    @if(!empty($attr['reviews']))<span style="font-size:11px;color:var(--muted);">({{ number_format($attr['reviews']) }})</span>@endif
                </div>
                @endif
                <div>
                    @if($attr['isFree'])
                    <span style="font-size:14px;font-weight:700;color:#16A34A;">FREE</span>
                    <span style="font-size:11px;color:var(--muted);margin-left:4px;">Entrance Fee</span>
                    @elseif($attrPriceRaw > 0)
                    <span style="font-size:14px;font-weight:700;color:var(--dark);">{{ $this->tripDisplayAmount($attrPriceRaw) }}</span>
                    <span style="font-size:11px;color:var(--muted);margin-left:4px;">Entrance Fee</span>
                    @else
                    <span style="font-size:12px;color:var(--muted);">Entrance fee may apply</span>
                    @endif
                </div>
            </div>
            <div class="attr-action">
                <button wire:click="toggleAttraction({{ $ai }})" wire:loading.attr="disabled" wire:target="toggleAttraction({{ $ai }})"
                        style="background:{{ $attrSelected ? 'var(--bg-white)' : 'var(--primary)' }};color:{{ $attrSelected ? 'var(--primary)' : '#fff' }};border:1.5px solid var(--primary);border-radius:10px;padding:10px 22px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;transition:background .18s,gap .18s;">
                    <span wire:loading.remove wire:target="toggleAttraction({{ $ai }})">
                        @if($attrSelected)
                            <i class="fa-solid fa-check"></i> Selected
                        @else
                            Select <i class="fa-solid fa-chevron-right" style="font-size:10px;"></i>
                        @endif
                    </span>
                    <span wire:loading wire:target="toggleAttraction({{ $ai }})"><i class="fa-solid fa-spinner fa-spin"></i></span>
                </button>
            </div>
        </div>
        @endforeach
    </div>
    <div style="height:64px;"></div>
    <div style="position:fixed;bottom:24px;right:32px;z-index:200;">
        <button wire:click="continueFromAttractions" wire:loading.attr="disabled" wire:target="continueFromAttractions"
                style="background:var(--primary);color:#fff;border:none;border-radius:30px;padding:14px 28px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;box-shadow:0 8px 24px rgba(0,0,0,.25);">
            <span wire:loading.remove wire:target="continueFromAttractions">
                Continue{{ count($activeSelectedAttractions) ? ' (' . count($activeSelectedAttractions) . ' selected)' : '' }} <i class="fa-solid fa-arrow-right" style="font-size:11px;"></i>
            </span>
            <span wire:loading wire:target="continueFromAttractions"><i class="fa-solid fa-spinner fa-spin"></i></span>
        </button>
    </div>
    @endif

</div>
@script
<script>
window.sortAttractions = function(dir) {
    const cards = Array.from(document.querySelectorAll('.attr-card'));
    if (!cards.length) return;
    const parent = cards[0].parentNode;
    cards.sort((a,b) => {
        const pa=parseInt(a.dataset.price)||0, pb=parseInt(b.dataset.price)||0;
        return dir==='asc' ? pa-pb : pb-pa;
    });
    cards.forEach(c=>parent.appendChild(c));
};
</script>
@endscript
@endif

{{-- ═══════════════════════════════════════════════════════════════
     STEP 6 — Emergency Fund
═══════════════════════════════════════════════════════════════ --}}
@if ($planningMode !== '' && $step === 6)
<style>
.emergency-fund-input::placeholder{font-weight:400 !important;color:var(--muted);}
.ef-field{display:flex;align-items:center;gap:16px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:14px;padding:18px 22px;transition:border-color .18s,background .18s,box-shadow .18s;}
/* Same required-but-empty treatment as the Trip Details fields. Beats
   :hover/:focus-within so the red survives the pointer landing on it. */
.ef-field.is-bad,.ef-field.is-bad:hover,.ef-field.is-bad:focus-within{
    border-color:#FF3B3B;box-shadow:0 0 0 3px rgba(255,59,59,.20);
    animation:pyt-shake .48s cubic-bezier(.36,.07,.19,.97) both;}
@media (prefers-reduced-motion:reduce){.ef-field.is-bad{animation:none;}}
.ef-clear{margin-left:auto;flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border:none;border-radius:50%;background:transparent;color:var(--muted);cursor:pointer;font-size:13px;line-height:1;padding:0;transition:background .15s,color .15s;}
.ef-clear:hover{background:var(--border-light);color:var(--dark);}
.ef-clear:focus-visible{outline:2px solid var(--primary);outline-offset:2px;}
</style>
<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:calc(100vh - 120px);padding:40px 24px;text-align:center;">

    <h1 style="font-size:38px;font-weight:800;color:var(--dark);margin:0 0 14px;">Emergency Fund</h1>
    <p style="font-size:16px;color:var(--muted);margin:0 0 40px;max-width:560px;line-height:1.6;">Set aside a safety net for unexpected expenses during your journey.</p>

    <div x-data="{
            display: '',
            bad: false,
            init() {
                if ($wire.emergency) this.display = Number($wire.emergency).toLocaleString('en-PH');
            },
            format(e) {
                let raw = e.target.value.replace(/[^\d]/g, '').slice(0, 7);
                this.display = raw ? Number(raw).toLocaleString('en-PH') : '';
                this.bad = false;
                $wire.set('emergency', raw ? Number(raw) : 0);
            },
            clear() {
                // Drop the local display and the Livewire property together, or
                // the amount reappears on the next render from whichever half
                // wasn't cleared.
                this.display = '';
                this.bad = false;
                $wire.set('emergency', 0);
                this.$refs.amount?.focus();
            },
            flag() {
                // Drop then re-set on the next frame, otherwise pressing Confirm
                // again on an already-red field wouldn't restart the shake -- a
                // CSS animation only plays when the class lands.
                this.bad = false;
                this.$nextTick(() => requestAnimationFrame(() => { this.bad = true; }));
            },
            submit() {
                if (!this.display) { this.flag(); return; }
                $wire.confirmEmergencyFund();
            }
         }"
         style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:20px;width:100%;max-width:680px;box-shadow:0 4px 20px rgba(0,0,0,0.07);overflow:hidden;">

        {{-- Input area --}}
        <div style="padding:40px 40px 32px;text-align:left;">
            <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:14px;">Your Allocated Emergency Fund (must not exceed 7 digits)</div>
            <div class="ef-field" :class="{ 'is-bad': bad }">
                <div style="width:44px;height:44px;border-radius:12px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa-solid fa-piggy-bank" style="color:var(--primary);font-size:19px;"></i>
                </div>
                <input type="text" x-model="display" @input="format($event)" placeholder="Please input amount"
                       class="emergency-fund-input" x-ref="amount"
                       style="border:none;background:transparent;font-size:20px;font-weight:700;color:var(--dark);outline:none;width:100%;"
                       autocomplete="off">
                <button type="button" class="ef-clear" title="Clear"
                        x-show="display" x-cloak @click.stop="clear()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            @if ($emergencyError)
            <p style="color:var(--danger);font-size:13px;margin:10px 0 0;">{{ $emergencyError }}</p>
            @endif
        </div>

        {{-- Footer --}}
        <div style="border-top:1.5px solid var(--border);padding:20px 28px;display:flex;align-items:center;gap:16px;background:var(--bg-white);">
            <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;">
                <i class="fa-solid fa-circle-info" style="font-size:14px;color:var(--muted);flex-shrink:0;"></i>
                <span style="font-size:13px;color:var(--muted);line-height:1.4;">This amount is excluded from your daily budget</span>
            </div>
            <button type="button" @click="submit()" wire:loading.attr="disabled" wire:target="confirmEmergencyFund"
                    style="background:var(--primary);color:#fff;border:none;border-radius:12px;padding:13px 28px;font-size:14px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;white-space:nowrap;transition:background .18s,gap .18s;"
                    onmouseenter="this.style.background='var(--primary-dark)';this.style.gap='11px'" onmouseleave="this.style.background='var(--primary)';this.style.gap='8px'">
                <span wire:loading.remove wire:target="confirmEmergencyFund" style="display:inline-flex;align-items:center;gap:8px;">Confirm Amount <i class="fa-solid fa-arrow-right" style="font-size:12px;"></i></span>
                <span wire:loading wire:target="confirmEmergencyFund"><i class="fa-solid fa-spinner fa-spin"></i></span>
            </button>
        </div>
    </div>

</div>
@endif

{{-- Currency-conversion modal — shown after Emergency Fund is confirmed,
     only when the destination's currency differs from pesos. --}}
@if ($showCurrencyConvertModal && $destinationCurrencyCode !== null)
@php
    // The traveler's real base currency (from their profile's home city)
    // when one's set, not always pesos — pesos is just the fallback for
    // travelers whose base currency already is PHP.
    $fromCode = ($tripCurrency !== '' && $tripCurrency !== 'PHP') ? $tripCurrency : 'PHP';
    $fromName = \App\Support\PlaceCatalog::CURRENCY_NAMES[$fromCode] ?? $fromCode;
    $toName   = \App\Support\PlaceCatalog::CURRENCY_NAMES[$destinationCurrencyCode] ?? $destinationCurrencyCode;
@endphp
<div style="position:fixed;inset:0;background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);z-index:2100;display:flex;align-items:center;justify-content:center;padding:20px;">
    <div style="background:var(--bg-white);border-radius:24px;width:100%;max-width:380px;overflow:hidden;animation:tpwModalPop .22s cubic-bezier(.34,1.56,.64,1);">
        <div style="position:relative;background:var(--bg);padding:36px 28px 24px;text-align:center;overflow:hidden;">
            <div style="width:64px;height:64px;border-radius:18px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
                <i class="fa-solid fa-money-bill-transfer" style="font-size:24px;color:#fff;"></i>
            </div>
            <div style="font-size:19px;font-weight:800;color:var(--dark);margin-bottom:8px;">Convert your budget?</div>
            <div style="font-size:13px;color:var(--muted);line-height:1.6;max-width:280px;margin:0 auto;">
                Would you like me to convert your budget from {{ $fromName }} ({{ $fromCode }}) to {{ $toName }} ({{ $destinationCurrencyCode }})?
            </div>
            @if ($currencyConvertError)
            <p style="color:var(--danger);font-size:13px;margin:12px 0 0;">{{ $currencyConvertError }}</p>
            @endif
        </div>
        <div style="display:flex;gap:10px;padding:20px 22px 22px;">
            <button wire:click="declineCurrencyConversion"
                    style="flex:1;background:transparent;color:var(--muted);border:1.5px solid var(--border);border-radius:12px;padding:12px 0;font-size:13px;font-weight:700;cursor:pointer;transition:background .18s,border-color .18s;"
                    onmouseenter="this.style.background='var(--border-light)';this.style.borderColor='var(--border)'" onmouseleave="this.style.background='transparent';this.style.borderColor='var(--border)'">
                No, keep pesos
            </button>
            <button wire:click="acceptCurrencyConversion" wire:loading.attr="disabled" wire:target="acceptCurrencyConversion"
                    style="flex:1;background:var(--primary);color:#fff;border:none;border-radius:12px;padding:12px 0;font-size:13px;font-weight:700;cursor:pointer;transition:background .18s,transform .12s;"
                    onmouseenter="this.style.background='var(--primary-dark)'" onmouseleave="this.style.background='var(--primary)'"
                    onmousedown="this.style.transform='scale(.97)'" onmouseup="this.style.transform='scale(1)'">
                <span wire:loading.remove wire:target="acceptCurrencyConversion"><i class="fa-solid fa-check" style="font-size:11px;margin-right:6px;"></i>Yes, convert</span>
                <span wire:loading wire:target="acceptCurrencyConversion"><i class="fa-solid fa-spinner fa-spin"></i></span>
            </button>
        </div>
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════
     STEP 7 — Generate Itinerary
═══════════════════════════════════════════════════════════════ --}}
@if ($planningMode !== '' && $step === 7)
@php
    $profile      = auth()->user()?->userProfile;
    $interests    = $profile?->interests    ?? [];
    $subInterests = $profile?->sub_interests ?? [];
    $allTags      = array_unique(array_merge($interests, $subInterests));

    $dest = $manualTo ?: $mcTo ?: 'Unknown';
    $isMultiCity = $flightTripType === 'multi_city' && $mcTo;
    $route = $isMultiCity
        ? trim($manualFrom) . ' to ' . trim($manualTo) . ' · ' . trim($mcTo)
        : trim($manualFrom) . ' to ' . trim($manualTo);

    $sd = $startDate ? \Carbon\Carbon::parse($startDate)->format('F j, Y') : '—';
    $ed = $endDate   ? \Carbon\Carbon::parse($endDate)->format('F j, Y')   : '—';

    $mcSd = $mcStartDate ? \Carbon\Carbon::parse($mcStartDate)->format('F j, Y') : '—';
    $mcEd = $mcEndDate   ? \Carbon\Carbon::parse($mcEndDate)->format('F j, Y')   : '—';

    $budMaxRaw = (int) preg_replace('/[^\d]/', '', $manualBudgetMax ?: $manualBudgetMin);
    $budMaxRaw = $budMaxRaw ?: 0;

    // First value = profile preferred budget (daily_budget from Profile Builder).
    // Only fall back to the trip's own min budget when the profile value is
    // genuinely unset — NOT when it happens to be >= the trip's total budget,
    // which used to collapse both numbers to the same figure: daily_budget is
    // a PER-DAY rate while budMaxRaw is the trip's TOTAL budget, so comparing
    // them directly is an apples-to-oranges unit mismatch, not a real signal
    // that the profile value is unusable.
    $budMinRaw = (int) ($profile?->daily_budget ?? 0);
    if ($budMinRaw <= 0) {
        $budMinRaw = (int) preg_replace('/[^\d]/', '', $manualBudgetMin);
    }

    // The stored figures above are always pesos — this only changes how
    // they're SHOWN, converting back into the currency the trip started
    // in (tripCurrency, carried over from TARA's handoff) so the traveler
    // sees the same numbers they actually typed, not an unlabeled peso
    // figure that looks disconnected from the rest of the trip.
    $budSymbol = '₱';
    $budDivisor = 1;
    if ($tripCurrency !== '' && $tripCurrency !== 'PHP') {
        $liveRate = (new \App\Services\CurrencyConverterService())->rateToPhp($tripCurrency);
        if ($liveRate !== null) {
            $budSymbol  = \App\Support\PlaceCatalog::CURRENCY_SYMBOLS[$tripCurrency] ?? '₱';
            $budDivisor = $liveRate;
        }
    }

    // The emergency fund is set aside SEPARATELY from the trip budget
    // (added on top of it), not carved out of it — so the top end of this
    // range is the trip budget PLUS the emergency fund.
    $budMin = $budSymbol . ($budMinRaw ? number_format($budMinRaw / $budDivisor) : '0');
    $budMax = $budSymbol . ($budMaxRaw ? number_format(($budMaxRaw + $emergency) / $budDivisor) : '0');
@endphp
<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:calc(100vh - 120px);padding:40px 24px;">

    {{-- Same back affordance every other step carries, aligned to the card's
         own left edge (the step is centred, so it needs the matching
         max-width rather than sitting flush with the page). --}}
    <div style="width:100%;max-width:480px;">
        <button wire:click="backFromEdit(6)"
                style="display:inline-flex;align-items:center;gap:6px;background:none;border:none;color:var(--primary);font-size:13px;font-weight:600;cursor:pointer;padding:0;margin-bottom:10px;">
            <i class="fa-solid fa-arrow-left" style="font-size:11px;"></i> Back to Emergency Fund
        </button>
    </div>

    <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:22px;padding:32px 28px;width:100%;max-width:480px;box-shadow:0 8px 36px rgba(45,27,20,.08);">

        {{-- Header --}}
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:28px;">
            <div style="width:40px;height:40px;border-radius:11px;background:var(--primary);display:flex;align-items:center;justify-content:center;">
                <i class="fa-regular fa-calendar-days" style="color:#fff;font-size:15px;"></i>
            </div>
            <span style="font-size:17px;font-weight:800;color:var(--dark);">Generate Itinerary</span>
        </div>

        @if ($isMultiCity)
        {{-- Trip 1 --}}
        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:22px;">
            <div style="width:100px;flex-shrink:0;display:flex;align-items:center;gap:6px;">
                <i class="fa-solid fa-location-dot" style="color:var(--muted);font-size:11px;"></i>
                <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Trip 1</span>
            </div>
            <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:6px;">
                <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;padding:11px 14px;box-sizing:border-box;">
                    <span style="font-size:14px;font-weight:600;color:var(--dark);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;">{{ trim($manualFrom) }} to {{ trim($manualTo) }}</span>
                </div>
                <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;padding:11px 14px;box-sizing:border-box;">
                    <span style="font-size:14px;font-weight:600;color:var(--dark);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;">{{ $sd }} – {{ $ed }}</span>
                </div>
            </div>
        </div>

        {{-- Trip 2 --}}
        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:22px;">
            <div style="width:100px;flex-shrink:0;display:flex;align-items:center;gap:6px;">
                <i class="fa-solid fa-location-dot" style="color:var(--muted);font-size:11px;"></i>
                <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Trip 2</span>
            </div>
            <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:6px;">
                <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;padding:11px 14px;box-sizing:border-box;">
                    <span style="font-size:14px;font-weight:600;color:var(--dark);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;">{{ trim($manualTo) }} to {{ trim($mcTo) }}</span>
                </div>
                <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;padding:11px 14px;box-sizing:border-box;">
                    <span style="font-size:14px;font-weight:600;color:var(--dark);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;">{{ $mcSd }} – {{ $mcEd }}</span>
                </div>
            </div>
        </div>
        @else
        {{-- Destination --}}
        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:22px;">
            <div style="width:100px;flex-shrink:0;display:flex;align-items:center;gap:6px;">
                <i class="fa-solid fa-location-dot" style="color:var(--muted);font-size:11px;"></i>
                <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Destination</span>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;padding:11px 14px;box-sizing:border-box;">
                    <span style="font-size:14px;font-weight:600;color:var(--dark);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;">{{ $route }}</span>
                </div>
            </div>
        </div>

        {{-- Travel Dates --}}
        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:22px;">
            <div style="width:100px;flex-shrink:0;display:flex;align-items:center;gap:6px;">
                <i class="fa-regular fa-calendar" style="color:var(--muted);font-size:11px;"></i>
                <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Travel Dates</span>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;padding:11px 14px;box-sizing:border-box;">
                    <span style="font-size:14px;font-weight:600;color:var(--dark);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;">{{ $sd }} – {{ $ed }}</span>
                </div>
            </div>
        </div>
        @endif

        {{-- Budget Range --}}
        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:22px;">
            <div style="width:100px;flex-shrink:0;display:flex;align-items:center;gap:6px;">
                <i class="fa-solid fa-wallet" style="color:var(--muted);font-size:11px;"></i>
                <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Budget Range</span>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:12px;padding:11px 14px;box-sizing:border-box;">
                    <span style="font-size:14px;font-weight:600;color:var(--dark);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;">{{ $budMin }} – {{ $budMax }}</span>
                    @if ($convertedBudget !== null && $destinationCurrencyCode !== null)
                    <div style="font-size:12px;color:var(--primary);font-weight:600;margin-top:4px;">
                        ≈ {{ \App\Support\PlaceCatalog::CURRENCY_SYMBOLS[$destinationCurrencyCode] ?? '' }}{{ number_format($convertedBudget) }} in {{ trim($manualTo) }}
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Selected Interests --}}
        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:28px;">
            <div style="min-width:90px;display:flex;align-items:center;gap:6px;padding-top:4px;">
                <i class="fa-regular fa-heart" style="color:var(--muted);font-size:11px;"></i>
                <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Selected Interests</span>
            </div>
            <div style="flex:1;display:flex;flex-wrap:wrap;gap:6px;padding-top:2px;">
                @forelse($allTags as $tag)
                <span style="display:inline-flex;align-items:center;gap:5px;background:var(--bg);color:var(--primary);font-size:11px;font-weight:700;padding:5px 11px;border-radius:20px;">
                    <i class="fa-solid fa-tag" style="font-size:9px;"></i> {{ $tag }}
                </span>
                @empty
                <span style="font-size:13px;color:var(--muted);">No interests set.</span>
                @endforelse
            </div>
        </div>

        {{-- Generate button --}}
        <button wire:click="generateItinerary" wire:loading.attr="disabled"
                style="width:100%;display:flex;align-items:center;justify-content:center;gap:10px;background:var(--primary);color:#fff;border:none;border-radius:12px;padding:14px 24px;font-size:14px;font-weight:700;cursor:pointer;letter-spacing:0.3px;transition:background .18s,gap .18s;"
                onmouseenter="this.style.background='var(--primary-dark)';this.style.gap='13px'" onmouseleave="this.style.background='var(--primary)';this.style.gap='10px'">
            <span wire:loading.remove wire:target="generateItinerary">
                Generate Itinerary <i class="fa-solid fa-wand-magic-sparkles" style="font-size:13px;"></i>
            </span>
            <span wire:loading wire:target="generateItinerary">
                <i class="fa-solid fa-spinner fa-spin"></i>
            </span>
        </button>

    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════
     STEP 8 — Itinerary Preview
═══════════════════════════════════════════════════════════════ --}}
@if ($planningMode !== '' && $step === 8)
@php
    $profile8      = auth()->user()?->userProfile;
    $interests8    = $profile8?->interests    ?? [];
    $subInterests8 = $profile8?->sub_interests ?? [];
    $allTags8      = array_unique(array_merge($interests8, $subInterests8));

    $route8  = trim($manualFrom) . ' to ' . trim($manualTo);
    if ($flightTripType === 'multi_city' && $mcTo) $route8 .= ' · ' . trim($mcTo);

    $isLeg2Now = $flightTripType === 'multi_city' && $mcTo && $itineraryLeg === 2;
    $sd8 = ($isLeg2Now ? $mcStartDate : $startDate) ? \App\Livewire\Traveler\TripPlannerWizard::fmtDate($isLeg2Now ? $mcStartDate : $startDate) : '—';
    $ed8 = ($isLeg2Now ? $mcEndDate   : $endDate)   ? \App\Livewire\Traveler\TripPlannerWizard::fmtDate($isLeg2Now ? $mcEndDate   : $endDate)   : '—';
    $dest8Label = $isLeg2Now ? trim($mcTo) : trim($manualTo);

    $budMax8      = (int) preg_replace('/[^\d]/', '', $manualBudgetMax ?: $manualBudgetMin);
    $profileMin8  = (int) ($profile8?->daily_budget ?? 0);
    $budMin8      = ($profileMin8 > 0 && $profileMin8 < $budMax8) ? $profileMin8 : (int) round($budMax8 * 0.7);
    $budLabel8    = $budMin8 ? ($this->tripDisplayAmount($budMin8) . ($budMax8 && $budMax8 !== $budMin8 ? ' – ' . $this->tripDisplayAmount($budMax8) : '')) : '—';

    // Selections as activity-card entries (icon, type, title, sub, time, cost, isFree)
    // For round-trip flights, split cost evenly: half on arrival, half on departure
    // For a multi-city trip, only the leg currently being shown contributes
    // its own selections — leg 1's picks shouldn't appear while reviewing
    // leg 2's options, and vice versa. Day 1 only ever holds flight + hotel
    // (arrival-day events) — venues and attractions get their own day
    // columns below, spread the same way they'll actually be saved.
    $selCards = [];
    if ($selectedFlight && !$isLeg2Now) {
        $flightIsRT   = strtolower($selectedFlight['type'] ?? '') === 'round trip';
        $flightCost8  = (float)($selectedFlight['price'] ?? 0);
        $flightArrCost = $flightIsRT ? round($flightCost8 / 2) : $flightCost8;
        $flightDepCost = $flightIsRT ? ($flightCost8 - $flightArrCost) : 0;
        $selCards[] = ['icon'=>'fa-plane','type'=>'Flight','title'=>($selectedFlight['airline']??'Flight').' '.($selectedFlight['number']??''),'sub'=>($selectedFlight['dep_id']??'').' → '.($selectedFlight['arr_id']??''),'time'=>$selectedFlight['depart']??'','cost'=>$flightArrCost,'isFree'=>false];
    } else { $flightIsRT = false; $flightDepCost = 0; }
    if ($selectedMcFlight && $isLeg2Now) {
        $mcFlightIsRT  = strtolower($selectedMcFlight['type'] ?? '') === 'round trip';
        $mcFlightCost8 = (float)($selectedMcFlight['price'] ?? 0);
        $mcFlightArrCost = $mcFlightIsRT ? round($mcFlightCost8 / 2) : $mcFlightCost8;
        $mcFlightDepCost = $mcFlightIsRT ? ($mcFlightCost8 - $mcFlightArrCost) : 0;
        $selCards[] = ['icon'=>'fa-plane','type'=>'Flight','title'=>($selectedMcFlight['airline']??'Flight').' '.($selectedMcFlight['number']??''),'sub'=>($selectedMcFlight['dep_id']??'').' → '.($selectedMcFlight['arr_id']??''),'time'=>$selectedMcFlight['depart']??'','cost'=>$mcFlightArrCost,'isFree'=>false];
    } else { $mcFlightIsRT = false; $mcFlightDepCost = 0; }
    if ($selectedHotel && !$isLeg2Now)   $selCards[] = ['icon'=>'fa-bed', 'type'=>'Accommodation', 'title'=>$selectedHotel['name']??'Hotel',   'sub'=>($selectedHotel['stars']??3).'★ · '.($selectedHotel['nights']??1).' nights',   'time'=>'Check-in', 'cost'=>$selectedHotel['total']??0,   'isFree'=>false];
    if ($selectedMcHotel && $isLeg2Now)  $selCards[] = ['icon'=>'fa-bed', 'type'=>'Accommodation', 'title'=>$selectedMcHotel['name']??'Hotel', 'sub'=>($selectedMcHotel['stars']??3).'★ · '.($selectedMcHotel['nights']??1).' nights', 'time'=>'Check-in', 'cost'=>$selectedMcHotel['total']??0, 'isFree'=>false];
    // Venues/attractions deliberately aren't added to $selCards here — they
    // get their own spread-across-days treatment below (matching how
    // saveItinerary() actually schedules them), and their cost is already
    // counted separately via selectedVenuesCost()/selectedAttractionsCost()
    // below. Adding them here too would double-count $totalCost8.
    //
    // The comparison grid's "Your Selections" checklist needs them listed
    // though (it filters out the day-by-day view's own venue/attraction
    // days entirely) — so build a display-only copy just for that, never
    // used for cost math.
    $selCardsWithPicks = $selCards;
    // Multi-select: several venues/attractions can be picked per leg now,
    // so each gets its own line instead of a single if-check.
    foreach (($isLeg2Now ? $selectedMcVenues : $selectedVenues) as $v) {
        $selCardsWithPicks[] = ['title' => 'Lunch/Dinner at ' . ($v['name'] ?? 'Restaurant')];
    }
    foreach (($isLeg2Now ? $selectedMcAttractions : $selectedAttractions) as $a) {
        $selCardsWithPicks[] = ['title' => 'Visit ' . ($a['name'] ?? 'Attraction')];
    }

    $totalCost8 = 0;
    foreach ($selCards as $c) {
        if (!$c['isFree'] && is_numeric($c['cost'])) $totalCost8 += (float) $c['cost'];
    }
    $totalCost8 += $this->selectedVenuesCost() + $this->selectedAttractionsCost();
    foreach ($customActivities as $ca) { $totalCost8 += (float) ($ca['cost'] ?? 0); }
    // Add AI activity costs
    if ($aiItinerary && !empty($aiItinerary['days'])) {
        foreach ($aiItinerary['days'] as $d) {
            foreach ($d['activities'] ?? [] as $a) {
                if (isset($a['cost']) && is_numeric($a['cost'])) $totalCost8 += (float) $a['cost'];
            }
        }
    }

    // Map activity type to icon
    $actIcons = ['food'=>'fa-utensils','attraction'=>'fa-camera','transport'=>'fa-plane','leisure'=>'fa-umbrella-beach','hotel'=>'fa-bed','default'=>'fa-map-pin'];

    // Day 1 = arrival (flight + hotel only)
    $selActivities = [];
    foreach ($selCards as $sc) {
        $selActivities[] = ['time'=>$sc['time'],'title'=>$sc['title'],'description'=>$sc['sub'],'type'=>$sc['type'],'cost'=>$sc['cost'],'isFree'=>$sc['isFree'],'icon'=>$sc['icon'],'isUserPick'=>true];
    }
    // Mirrors saveItinerary()'s day-bucketing (3 attractions / 2 venues per
    // day) so the preview matches what actually gets saved. Scoped to
    // whichever leg is currently being viewed — selectionDayBuckets()
    // combines both legs, which is only right for a single-city trip.
    $attrTimes8  = ['09:00', '11:30', '16:00'];
    $venueTimes8 = ['12:30', '19:00'];
    $dayBucketsFor8 = function (array $attractions, array $venues): array {
        $buckets = [];
        foreach (array_chunk(array_values($attractions), 3) as $i => $chunk) {
            $buckets[$i]['attractions'] = $chunk;
        }
        foreach (array_chunk(array_values($venues), 2) as $i => $chunk) {
            $buckets[$i]['venues'] = $chunk;
        }
        ksort($buckets);
        return $buckets;
    };
    $legAttractions8 = $isLeg2Now ? $selectedMcAttractions : $selectedAttractions;
    $legVenues8      = $isLeg2Now ? $selectedMcVenues      : $selectedVenues;

    // Builds the Day-1-selections + Explore-days + AI-days list for a given
    // itinerary array — used once per option now that every option renders
    // its own full day-by-day list inline instead of only the selected one.
    $returnDepCost = $flightDepCost + ($mcFlightDepCost ?? 0);
    $buildAllDays = function (?array $itin) use ($selActivities, $returnDepCost, $dayBucketsFor8, $legAttractions8, $legVenues8, $attrTimes8, $venueTimes8, $customActivities) {
        $days = [['day'=>1,'label'=>'Arrival','activities'=>$selActivities,'isUserDay'=>true]];

        // Day 2, 3, ... = the traveler's selected venues/attractions for this
        // leg, spread across as many days as needed.
        $dayCursor = 2;
        foreach ($dayBucketsFor8($legAttractions8, $legVenues8) as $bucket) {
            $dayActivities = [];
            foreach ($bucket['attractions'] ?? [] as $slot => $attr) {
                $dayActivities[] = ['time'=>$attrTimes8[$slot] ?? '16:00','title'=>'Visit '.($attr['name']??'Attraction'),'description'=>$attr['type']??'','type'=>'Attraction','cost'=>(int)preg_replace('/[^\d]/','',$attr['price']??'0'),'isFree'=>$attr['isFree']??false,'icon'=>'fa-camera','isUserPick'=>true];
            }
            foreach ($bucket['venues'] ?? [] as $slot => $venue) {
                $label = $slot === 0 ? 'Lunch at ' : 'Dinner at ';
                $dayActivities[] = ['time'=>$venueTimes8[$slot] ?? '20:30','title'=>$label.($venue['name']??'Restaurant'),'description'=>$venue['cuisine']??'','type'=>'Food & Dining','cost'=>(float)($venue['priceMax']??$venue['priceMin']??0),'isFree'=>false,'icon'=>'fa-utensils','isUserPick'=>true];
            }
            usort($dayActivities, fn ($a, $b) => strcmp($a['time'], $b['time']));
            $days[] = ['day'=>$dayCursor,'label'=>'Explore & Dine','activities'=>$dayActivities,'isUserDay'=>true];
            $dayCursor++;
        }
        $selectionDaysUsed8 = $dayCursor - 2; // how many "Explore" days we just added

        // AI days start right after arrival + however many selection days we
        // used; inject return-flight cost onto last day's departure activity
        if ($itin && !empty($itin['days'])) {
            $aiDayList = $itin['days'];
            $lastIdx   = count($aiDayList) - 1;
            foreach ($aiDayList as $i => $aiDay) {
                $aiDay['isUserDay'] = false;
                $aiDay['day']       = $i + 2 + $selectionDaysUsed8;
                // On the last AI day, add return cost to the "Head to Airport" activity
                if ($i === $lastIdx && $returnDepCost > 0) {
                    foreach ($aiDay['activities'] as &$actItem) {
                        if (stripos($actItem['title'] ?? '', 'airport') !== false || stripos($actItem['title'] ?? '', 'departure') !== false) {
                            $actItem['cost'] = $returnDepCost;
                            break;
                        }
                    }
                    unset($actItem);
                }
                $days[] = $aiDay;
            }
        }

        // Merge traveler-added custom activities onto whichever day they picked.
        foreach ($customActivities as $ci => $ca) {
            $dayIdx = null;
            foreach ($days as $k => $d) {
                if (($d['day'] ?? null) == $ca['day']) { $dayIdx = $k; break; }
            }
            if ($dayIdx === null) continue; // picked a day that no longer exists — skip safely
            $days[$dayIdx]['activities'][] = [
                'time' => $ca['time'], 'title' => $ca['title'], 'description' => $ca['description'],
                'type' => $ca['type'], 'cost' => $ca['cost'], 'isFree' => $ca['cost'] <= 0,
                'isCustom' => true, 'customIndex' => $ci,
            ];
        }

        return $days;
    };
    $allDays = $buildAllDays($aiItinerary);
@endphp

<style>
.itin8-wrap{padding:20px 0;display:flex;flex-direction:column;min-height:calc(100vh - 140px);}

/* Top bar */
.itin8-topbar{background:transparent;border:none;padding:0;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.itin8-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;}
.itin8-tag{display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:500;color:var(--muted);line-height:16px;}
.itin8-tag i{font-size:9px;color:var(--muted);}
.itin8-left{flex:1;min-width:0;}
.itin8-right{flex-shrink:0;width:340px;}
.itin8-cost-card{background:var(--bg-white);background-clip:padding-box;border:1px solid var(--border);border-radius:14px;overflow:hidden;padding:12px 16px;box-shadow:0 2px 8px rgba(45,27,20,0.08);text-align:right;width:100%;box-sizing:border-box;isolation:isolate;}
.itin8-cost-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:6px;line-height:16px;}
.itin8-budget-status{display:inline-block;font-size:10px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;line-height:16px;padding:2px 10px;border-radius:99px;margin-bottom:6px;}
.itin8-budget-status.under{background:#FEF3C7;color:#b07e00;}
.itin8-budget-status.over{background:#FEE2E2;color:#ba1a1a;}
.itin8-budget-status.on{background:#FDF3EB;color:var(--primary);}
.itin8-cost-val{font-size:28px;font-weight:700;color:var(--primary);line-height:1.15;letter-spacing:-0.01em;}
.itin8-actions{display:flex;align-items:center;gap:8px;margin-top:12px;width:100%;}
.itin8-actions .itin8-btn-ghost,.itin8-actions .itin8-btn-save{flex:1;justify-content:center;}
.itin8-btn-ghost{background:var(--bg-white)fff;border:1px solid var(--border);color:var(--dark);border-radius:9px;padding:8px 16px;font-size:12px;font-weight:700;font-family:'Hanken Grotesk',sans-serif;cursor:pointer;display:inline-flex;align-items:center;gap:6px;line-height:16px;transition:background .18s,border-color .18s;}
.itin8-btn-ghost:hover{background:var(--bg);border-color:#D9C4AE;}
.itin8-btn-save{background:var(--primary);color:#ffffff;border:none;border-radius:9px;padding:8px 18px;font-size:12px;font-weight:700;font-family:'Hanken Grotesk',sans-serif;cursor:pointer;display:inline-flex;align-items:center;gap:6px;line-height:16px;transition:background .18s;}
.itin8-btn-save:hover{background:var(--primary-dark);}
.itin8-desc{font-size:14px;font-weight:400;color:var(--muted);line-height:20px;margin:0;}

/* Day sections — stacked vertically, one below another */
.itin8-days{display:flex;flex-direction:column;gap:18px;padding-bottom:20px;}
.itin8-day-col{display:flex;flex-direction:column;background:var(--bg-white);border:1px solid var(--border);border-radius:18px;padding:20px 22px 22px;box-shadow:0 4px 16px rgba(45,27,20,0.06);}
.itin8-day-header{display:flex;align-items:center;gap:12px;padding:0 0 14px;border-bottom:1px solid var(--bg);margin-bottom:18px;}
.itin8-day-num{width:40px;height:40px;border-radius:9999px;background:var(--primary);color:#ffffff;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;flex-shrink:0;font-family:'Hanken Grotesk',sans-serif;}
.itin8-day-label{font-size:15px;font-weight:700;color:var(--dark);line-height:20px;}
.itin8-day-date{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:1px;}

/* Vertical timeline of activities within a day */
.itin8-timeline{position:relative;padding-left:26px;}
.itin8-timeline::before{content:'';position:absolute;left:15px;top:8px;bottom:8px;width:2px;background:#EDE0D6;}
.itin8-act-card{position:relative;background:var(--bg-white);border-radius:12px;border:1px solid #efe6dd;padding:14px 16px;font-family:'Hanken Grotesk',sans-serif;margin-bottom:14px;}
.itin8-act-card:last-child{margin-bottom:0;}
.itin8-act-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;}
.itin8-act-icon{position:absolute;left:-26px;top:14px;width:32px;height:32px;border-radius:9999px;border:2px solid #fff;background:var(--bg-white);box-shadow:0 0 0 2px var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.itin8-act-icon .material-symbols-outlined{font-size:16px;}
.itin8-act-time{font-size:11px;font-weight:600;color:var(--muted);line-height:16px;}
.itin8-act-body{margin-bottom:8px;}
.itin8-act-title{font-size:14px;font-weight:700;color:var(--dark);line-height:19px;margin-bottom:3px;}
.itin8-act-sub{font-size:12px;font-weight:400;color:var(--muted);line-height:17px;font-style:italic;}
.itin8-act-footer{border-top:1px solid #ece2d8;padding-top:8px;display:flex;align-items:center;justify-content:space-between;}
.itin8-act-cost-label{font-size:11px;color:var(--muted);font-weight:500;}
.itin8-act-cost-val{font-size:13px;font-weight:700;color:var(--primary);}
.itin8-loading{display:flex;align-items:center;gap:10px;padding:32px 0;color:var(--muted);font-size:14px;}
.material-symbols-outlined{font-family:'Material Symbols Outlined';font-weight:normal;font-style:normal;font-size:20px;line-height:1;letter-spacing:normal;text-transform:none;display:inline-block;white-space:nowrap;direction:ltr;-webkit-font-smoothing:antialiased;font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;}
</style>

<div class="itin8-wrap">
    @php
        $overBudget8  = $budMax8 > 0 && $totalCost8 > $budMax8;
        $underBudget8 = $budMin8 > 0 && $totalCost8 < $budMin8;
        $onBudget8    = !$overBudget8 && !$underBudget8;
    @endphp

    {{-- Top bar --}}
    <div class="itin8-topbar">
        {{-- Left: date + tags + description --}}
        <div class="itin8-left">
            {{-- One back affordance, never two: on leg 2 of a multi-city trip
                 "back" means leg 1's itinerary, everywhere else it means the
                 Generate Itinerary screen this step was launched from. --}}
            @if($flightTripType === 'multi_city' && $mcTo && $itineraryLeg === 2)
            <button wire:click="backToLeg1Itinerary"
                    style="display:inline-flex;align-items:center;gap:6px;background:none;border:none;color:var(--primary);font-size:13px;font-weight:600;cursor:pointer;padding:0;margin-bottom:10px;">
                <i class="fa-solid fa-arrow-left" style="font-size:11px;"></i> Back to Suggested Itineraries
            </button>
            @else
            <button wire:click="backFromEdit(7)"
                    style="display:inline-flex;align-items:center;gap:6px;background:none;border:none;color:var(--primary);font-size:13px;font-weight:600;cursor:pointer;padding:0;margin-bottom:10px;">
                <i class="fa-solid fa-arrow-left" style="font-size:11px;"></i> Back to Generate Itinerary
            </button>
            @endif
            <div class="itin8-meta">
                @if($flightTripType === 'multi_city' && $mcTo)
                <div class="itin8-tag" style="background:var(--bg);color:var(--primary);font-weight:700;padding:3px 10px;border-radius:20px;">Leg {{ $itineraryLeg }} of 2</div>
                @endif
                <div class="itin8-tag"><i class="fa-regular fa-calendar"></i> {{ $sd8 }} - {{ $ed8 }}</div>
                @if(count($allTags8))
                <div class="itin8-tag"><i class="fa-solid fa-utensils"></i> {{ implode(', ', $allTags8) }}</div>
                @endif
            </div>
            <p class="itin8-desc">
                A perfectly balanced trip exploring <strong style="color:var(--dark);">{{ $dest8Label }}</strong> built from your selections and AI-suggested activities.
            </p>
        </div>

        {{-- Right: estimated cost + budget status + action buttons --}}
        <div class="itin8-right">
            <div class="itin8-cost-card">
                <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;">
                    <div class="itin8-cost-label" style="margin-bottom:0;">Estimated Cost</div>
                    @if($overBudget8)
                        <div class="itin8-budget-status over" style="margin-bottom:0;">Over Budget</div>
                    @elseif($underBudget8)
                        <div class="itin8-budget-status under" style="margin-bottom:0;">Under Budget</div>
                    @else
                        <div class="itin8-budget-status on" style="margin-bottom:0;">On Budget</div>
                    @endif
                </div>
                <div class="itin8-cost-val">{{ $this->tripDisplayAmount($totalCost8) }}</div>
                <div class="itin8-actions" style="justify-content:flex-end;margin-top:12px;">
                    <button class="itin8-btn-ghost" wire:click="regenerateItineraryOptions" wire:loading.attr="disabled" wire:target="regenerateItineraryOptions">
                        <span wire:loading.remove wire:target="regenerateItineraryOptions" style="white-space:nowrap;"><i class="fa-solid fa-rotate" style="font-size:11px;"></i> Generate Other Options</span>
                        <span wire:loading wire:target="regenerateItineraryOptions" style="white-space:nowrap;"><i class="fa-solid fa-spinner fa-spin"></i></span>
                    </button>
                    <button class="itin8-btn-save" wire:click="continueItinerary" wire:loading.attr="disabled" wire:target="continueItinerary">
                        <span wire:loading.remove wire:target="continueItinerary" style="white-space:nowrap;">
                            @if($flightTripType === 'multi_city' && $mcTo && $itineraryLeg === 1)
                                Continue to Leg 2 <i class="fa-solid fa-arrow-right" style="font-size:11px;"></i>
                            @else
                                Save Itinerary <i class="fa-solid fa-floppy-disk" style="font-size:11px;"></i>
                            @endif
                        </span>
                        <span wire:loading wire:target="continueItinerary"><i class="fa-solid fa-spinner fa-spin"></i></span>
                    </button>
                </div>
            </div>
        </div>
    </div>


    {{-- AI loading state --}}
    @if($aiLoading)
    <div class="itin8-loading">
        <i class="fa-solid fa-spinner fa-spin" style="color:var(--primary);font-size:18px;"></i>
        Generating itinerary options for your trip…
    </div>
    @endif

    {{-- Each option renders as its own header + full day-by-day list,
         stacked one below another — no separate picker/single-preview. --}}
    @php
        $optionsToRender = count($aiItineraryOptions) ? $aiItineraryOptions : ($aiItinerary ? [$aiItinerary] : []);
    @endphp

    {{-- Every AI provider failed/timed out for every option — surface this
         instead of silently rendering an empty page. --}}
    @if(!$aiLoading && empty($optionsToRender))
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:60px 20px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:16px;">
        <div style="width:56px;height:56px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:18px;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size:24px;color:#fff;"></i>
        </div>
        <h3 style="font-weight:700;font-size:17px;margin:0 0 6px;color:var(--dark);">Couldn't generate itinerary suggestions</h3>
        <p style="color:var(--muted);font-size:13px;max-width:320px;line-height:1.6;margin:0 0 18px;">Our AI providers are temporarily unavailable or rate-limited. Please try again in a moment.</p>
        <button wire:click="regenerateItineraryOptions" wire:loading.attr="disabled" wire:target="regenerateItineraryOptions"
                style="background:var(--primary);color:#fff;border:none;border-radius:10px;padding:11px 22px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:background .18s;"
                onmouseenter="this.style.background='var(--primary-dark)'" onmouseleave="this.style.background='var(--primary)'">
            <i class="fa-solid fa-rotate" style="font-size:11px;"></i> Try Again
        </button>
    </div>
    @elseif(empty($aiItinerary['days'] ?? []))
    <div class="itin8-loading" style="background:#FEF3E2;color:#92400E;">
        <i class="fa-solid fa-triangle-exclamation" style="font-size:16px;"></i>
        Couldn't generate extra AI suggestions right now — you can still save your trip with what you've picked so far, or try "Generate Other Options" again in a bit.
    </div>
    @endif

    {{-- Day grid --}}
    @php
    $typeToMs = [
        // User-selected card types
        'Flight'                 => ['icon'=>'flight_takeoff', 'color'=>'#F1A53D'],
        'Flight (Leg 2)'         => ['icon'=>'flight_takeoff', 'color'=>'#F1A53D'],
        'Accommodation'          => ['icon'=>'hotel',           'color'=>'var(--primary)'],
        'Accommodation (Leg 2)'  => ['icon'=>'hotel',           'color'=>'var(--primary)'],
        'Food & Dining'          => ['icon'=>'restaurant',      'color'=>'#ba4a4a'],
        'Activity'               => ['icon'=>'explore',         'color'=>'#4f7b94'],
        'Transport'              => ['icon'=>'directions_car',  'color'=>'#6b5e8c'],
        'Attraction'             => ['icon'=>'photo_camera',    'color'=>'#4f9648'],
        'Shopping'               => ['icon'=>'shopping_bag',    'color'=>'#b07e00'],
        // AI-returned types (lowercase)
        'flight'                 => ['icon'=>'flight_takeoff', 'color'=>'#F1A53D'],
        'hotel'                  => ['icon'=>'hotel',           'color'=>'var(--primary)'],
        'accommodation'          => ['icon'=>'hotel',           'color'=>'var(--primary)'],
        'food'                   => ['icon'=>'restaurant',      'color'=>'#ba4a4a'],
        'dining'                 => ['icon'=>'restaurant',      'color'=>'#ba4a4a'],
        'restaurant'             => ['icon'=>'restaurant',      'color'=>'#ba4a4a'],
        'attraction'             => ['icon'=>'photo_camera',    'color'=>'#4f9648'],
        'activity'               => ['icon'=>'explore',         'color'=>'#4f7b94'],
        'leisure'                => ['icon'=>'local_activity',  'color'=>'#4f7b94'],
        'transport'              => ['icon'=>'directions_car',  'color'=>'#6b5e8c'],
        'shopping'               => ['icon'=>'shopping_bag',    'color'=>'#b07e00'],
        'nature'                 => ['icon'=>'forest',          'color'=>'#4f9648'],
        'beach'                  => ['icon'=>'beach_access',    'color'=>'#1976b0'],
        'culture'                => ['icon'=>'account_balance', 'color'=>'#6b5e8c'],
        'landmark'               => ['icon'=>'account_balance', 'color'=>'#6b5e8c'],
        'adventure'              => ['icon'=>'hiking',          'color'=>'#4f7b94'],
        'nightlife'              => ['icon'=>'nightlife',       'color'=>'#7b5e94'],
        'spa'                    => ['icon'=>'spa',             'color'=>'#4f9648'],
        'default'                => ['icon'=>'explore',         'color'=>'var(--muted)'],
    ];
    @endphp

    {{-- Pricing-tier-style comparison: one vertical card per option, laid
         out side by side. Each card lists the traveler's own selections
         (same across every card) plus that option's AI-suggested day themes
         — tailored to the traveler's profile interests, destination, and
         budget from earlier in the wizard. --}}
    @if(count($optionsToRender) > 1)
    @php
        // Sort cheapest → most expensive while keeping each option's original
        // index (selectItineraryOption() indexes into aiItineraryOptions, so
        // the display order can't change which index gets sent on click).
        $optCosts = [];
        foreach ($optionsToRender as $idx => $opt) {
            $c = 0;
            foreach ($opt['days'] ?? [] as $d) {
                foreach ($d['activities'] ?? [] as $a) {
                    if (isset($a['cost']) && is_numeric($a['cost'])) $c += (float) $a['cost'];
                }
            }
            $optCosts[$idx] = $c;
        }
        asort($optCosts);
    @endphp
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));grid-auto-rows:1fr;gap:16px;margin-bottom:28px;align-items:stretch;flex:1;min-height:0;">
        @foreach($optCosts as $optIdx => $optCost)
        @php
            $opt            = $optionsToRender[$optIdx];
            $optDaysPreview = $buildAllDays($opt);
            // The generator's own variant names ("Balanced Mix", "Adventure
            // Focus", "Budget Friendly") are no longer shown -- they described
            // the prompt that produced the option, not anything the traveler
            // can verify in the plan itself. The cards are ordered by cost, so
            // number them in the order they appear rather than by $optIdx,
            // which is the pre-sort position.
            $optLabel       = 'Option ' . $loop->iteration;
            $optActive      = $selectedItineraryIndex === $optIdx;
        @endphp
        <div wire:click="selectItineraryOption({{ $optIdx }})"
             style="cursor:pointer;border-radius:18px;padding:22px 20px;display:flex;flex-direction:column;min-height:420px;overflow-y:auto;transition:box-shadow .2s,transform .2s;
                    {{ $optActive ? 'background:var(--primary-light);border:1.5px solid var(--primary);' : 'background:var(--bg-white);border:1.5px solid var(--border);' }}"
             onmouseenter="if(!{{ $optActive ? 'true' : 'false' }}){this.style.boxShadow='0 10px 28px rgba(45,27,20,0.10)';this.style.transform='translateY(-2px)';}"
             onmouseleave="if(!{{ $optActive ? 'true' : 'false' }}){this.style.boxShadow='none';this.style.transform='none';}">

            {{-- Header --}}
            <div style="font-size:13px;font-weight:700;color:{{ $optActive ? 'var(--primary)' : 'var(--dark)' }};margin-bottom:2px;">{{ $optLabel }}</div>
            <div style="font-size:11px;color:var(--muted);margin-bottom:14px;">{{ $sd8 }} – {{ $ed8 }}</div>
            <div style="font-size:26px;font-weight:800;color:var(--primary);line-height:1.1;margin-bottom:14px;">{{ $this->tripDisplayAmount($optCost) }}</div>

            <button type="button" style="width:100%;padding:9px 0;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;margin-bottom:18px;font-family:'Hanken Grotesk',sans-serif;transition:background .18s;
                           {{ $optActive ? 'background:var(--primary);color:#fff;border:none;' : 'background:var(--bg-white);color:var(--primary);border:1.5px solid var(--primary);' }}">
                {{ $optActive ? 'Selected' : 'Select This Option' }}
            </button>

            {{-- Traveler's own selections — same across every option --}}
            @if(!empty($selCardsWithPicks))
            <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;">Your Selections</div>
            <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:16px;">
                @foreach($selCardsWithPicks as $sc8)
                <div style="display:flex;align-items:flex-start;gap:7px;font-size:12px;color:var(--muted);line-height:1.4;">
                    <i class="fa-solid fa-check" style="color:#22C55E;font-size:10px;margin-top:3px;flex-shrink:0;"></i>
                    <span>{{ $sc8['title'] }}</span>
                </div>
                @endforeach
            </div>
            @endif

            {{-- AI-suggested days for this specific option --}}
            <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;">AI Suggested</div>
            <div style="display:flex;flex-direction:column;gap:6px;">
                @foreach($optDaysPreview as $dpItem)
                @if(!($dpItem['isUserDay'] ?? false))
                @php
                    $dpNum   = $dpItem['day'] ?? ($loop->iteration);
                    $dpLabel = $dpItem['label'] ?? ('Day ' . $dpNum);
                @endphp
                <div style="display:flex;align-items:flex-start;gap:7px;font-size:12px;color:var(--muted);line-height:1.4;">
                    <i class="fa-solid fa-sparkles" style="color:var(--primary);font-size:10px;margin-top:3px;flex-shrink:0;"></i>
                    <span><strong style="color:var(--dark);">Day {{ $dpNum }}:</strong> {{ $dpLabel }}</span>
                </div>
                @endif
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Single-option fallback (e.g. only one option ever generated) — the
         cards grid above only renders when there's more than one, so show
         the full day-by-day breakdown here in that edge case. --}}
    @if(count($optionsToRender) <= 1)
    @php
        $selectedOption = $optionsToRender[$selectedItineraryIndex] ?? ($optionsToRender[0] ?? null);
        $optDays        = $buildAllDays($selectedOption);
    @endphp

    <div class="itin8-days">
        @foreach($optDays as $dayItem)
        @php
            $dayNum  = $dayItem['day'] ?? ($loop->iteration);
            $dayLabel= $dayItem['label'] ?? ('Day ' . $dayNum);
            $dayDate = $startDate ? str_replace('Sep ', 'Sept ', \Carbon\Carbon::parse($startDate)->addDays($dayNum - 1)->format('M j')) : '';
        @endphp
        <div class="itin8-day-col">
            <div class="itin8-day-header">
                <div>
                    @if($dayDate)<div class="itin8-day-date">{{ strtoupper($dayDate) }}</div>@endif
                    <div class="itin8-day-label">{{ $dayLabel }}</div>
                </div>
                <div class="itin8-day-num">{{ $dayNum }}</div>
            </div>
            <div class="itin8-timeline">
                @foreach($dayItem['activities'] ?? [] as $act)
                @php
                    $actType = $act['type'] ?? 'default';
                    $msInfo  = $typeToMs[$actType] ?? $typeToMs['default'];
                    $msIcon  = $msInfo['icon'];
                    $msColor = $msInfo['color'];
                    $actCost = $act['cost'] ?? null;
                    $actFree = $act['isFree'] ?? false;
                @endphp
                <div class="itin8-act-card" style="position:relative;">
                    @if($act['isCustom'] ?? false)
                    <button wire:click="removeCustomActivity({{ $act['customIndex'] }})" title="Remove"
                            style="position:absolute;top:8px;right:8px;background:none;border:none;color:var(--muted);cursor:pointer;font-size:12px;padding:2px;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    @endif
                    <div class="itin8-act-icon" style="box-shadow:0 0 0 2px {{ $msColor }};">
                        <span class="material-symbols-outlined" style="color:{{ $msColor }};">{{ $msIcon }}</span>
                    </div>
                    <div class="itin8-act-top">
                        <div class="itin8-act-time">{{ $act['time'] ?? '' }}</div>
                    </div>
                    <div class="itin8-act-body">
                        <div class="itin8-act-title">{{ $act['title'] ?? ($act['name'] ?? '') }}</div>
                        @if($act['description'] ?? ($act['sub'] ?? ''))
                        <div class="itin8-act-sub">{{ $act['description'] ?? $act['sub'] }}</div>
                        @endif
                    </div>
                    <div class="itin8-act-footer">
                        <span class="itin8-act-cost-label">Est. Cost</span>
                        @if($actFree)<span class="itin8-act-cost-val">FREE</span>
                        @elseif($actCost !== null && $actCost !== '' && $actCost != 0)
                            <span class="itin8-act-cost-val">{{ is_numeric($actCost) ? $this->tripDisplayAmount((float)$actCost) : $actCost }}</span>
                        @else<span class="itin8-act-cost-val" style="color:var(--muted);font-weight:400;">—</span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    {{-- Add Custom Activity button --}}
    <div style="display:flex;justify-content:center;padding-top:4px;">
        <button wire:click="openCustomActivityModal" style="display:inline-flex;align-items:center;gap:8px;background:var(--bg-white);border:2px solid var(--primary);border-radius:8px;padding:10px 20px;font-size:12px;font-weight:700;color:var(--primary);cursor:pointer;font-family:'Hanken Grotesk',sans-serif;">
            <span class="material-symbols-outlined" style="font-size:16px;">add</span> Add Custom Activity
        </button>
    </div>
    @endif

    {{-- Add Custom Activity modal --}}
    @if($showCustomActivityModal)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2100;display:flex;align-items:center;justify-content:center;padding:20px;" wire:click.self="closeCustomActivityModal">
        <div style="background:var(--bg-white);border-radius:16px;max-width:420px;width:100%;padding:24px;font-family:'Hanken Grotesk',sans-serif;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <span style="font-size:16px;font-weight:700;color:var(--dark);">Add Custom Activity</span>
                <button wire:click="closeCustomActivityModal" style="background:none;border:none;font-size:18px;cursor:pointer;color:var(--muted);line-height:1;">&times;</button>
            </div>

            <div style="margin-bottom:12px;">
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);display:block;margin-bottom:5px;">Day</label>
                <select wire:model="customActivityDay" style="width:100%;border:1.5px solid var(--border);border-radius:8px;padding:9px 12px;font-size:13px;font-family:inherit;">
                    @foreach($allDays as $d)
                    <option value="{{ $d['day'] }}">Day {{ $d['day'] }} · {{ $d['label'] ?? 'Itinerary' }}</option>
                    @endforeach
                </select>
            </div>

            <div style="margin-bottom:12px;">
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);display:block;margin-bottom:5px;">Title</label>
                <input type="text" wire:model="customActivityTitle" placeholder="e.g. Souvenir shopping at the market"
                       style="width:100%;border:1.5px solid var(--border);border-radius:8px;padding:9px 12px;font-size:13px;font-family:inherit;box-sizing:border-box;">
            </div>

            <div style="display:flex;gap:10px;margin-bottom:12px;">
                <div style="flex:1;">
                    <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);display:block;margin-bottom:5px;">Time</label>
                    <input type="time" wire:model="customActivityTime"
                           style="width:100%;border:1.5px solid var(--border);border-radius:8px;padding:9px 12px;font-size:13px;font-family:inherit;box-sizing:border-box;">
                </div>
                <div style="flex:1;">
                    <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);display:block;margin-bottom:5px;">Type</label>
                    <select wire:model="customActivityType" style="width:100%;border:1.5px solid var(--border);border-radius:8px;padding:9px 12px;font-size:13px;font-family:inherit;">
                        @foreach(['Activity','Food & Dining','Attraction','Transport','Shopping'] as $typeOpt)
                        <option value="{{ $typeOpt }}">{{ $typeOpt }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="margin-bottom:12px;">
                {{-- Peso, not tripDisplayAmount() — customActivityCost is added
                     straight into the peso total with no conversion, so what
                     you type here must actually be pesos. --}}
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);display:block;margin-bottom:5px;">Est. Cost (₱, optional)</label>
                <input type="number" min="0" step="1" wire:model="customActivityCost" placeholder="0"
                       style="width:100%;border:1.5px solid var(--border);border-radius:8px;padding:9px 12px;font-size:13px;font-family:inherit;box-sizing:border-box;">
            </div>

            <div style="margin-bottom:20px;">
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);display:block;margin-bottom:5px;">Notes (optional)</label>
                <textarea wire:model="customActivityDescription" rows="2" placeholder="Any extra detail…"
                          style="width:100%;border:1.5px solid var(--border);border-radius:8px;padding:9px 12px;font-size:13px;font-family:inherit;box-sizing:border-box;resize:vertical;"></textarea>
            </div>

            <div style="display:flex;gap:10px;">
                <button wire:click="closeCustomActivityModal" style="flex:1;background:transparent;color:var(--muted);border:1.5px solid var(--border);border-radius:8px;padding:11px 0;font-size:13px;font-weight:600;cursor:pointer;">
                    Cancel
                </button>
                <button wire:click="addCustomActivity" style="flex:1;background:var(--primary);color:#fff;border:none;border-radius:8px;padding:11px 0;font-size:13px;font-weight:700;cursor:pointer;">
                    Add Activity
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════
     STEP 9 — Trip Summary & Cost Estimation
═══════════════════════════════════════════════════════════════ --}}
@if ($planningMode !== '' && $step === 9)
@php
    $s9dest  = trim($manualTo ?: $mcTo ?: 'Unknown');
    $s9from  = trim($manualFrom ?: 'Manila');
    $s9days  = ($startDate && $endDate) ? max(1,(int)round((strtotime($endDate)-strtotime($startDate))/86400)) : 1;
    $s9sd    = $startDate ? \Carbon\Carbon::parse($startDate)->format('F j, Y') : '—';
    $s9ed    = $endDate   ? \Carbon\Carbon::parse($endDate)->format('F j, Y')   : '—';
    $s9sdow  = $startDate ? \Carbon\Carbon::parse($startDate)->format('l') : '';
    $s9edow  = $endDate   ? \Carbon\Carbon::parse($endDate)->format('l')   : '';

    // Cost breakdown from selections
    $s9flight = ($selectedFlight['price'] ?? 0) + ($selectedMcFlight['price'] ?? 0);
    $s9hotel  = ($selectedHotel['total']  ?? 0) + ($selectedMcHotel['total']  ?? 0);
    $s9venue  = $this->selectedVenuesCost();
    $s9attr   = $this->selectedAttractionsCost();
    $s9isMultiCity = $flightTripType === 'multi_city' && $mcTo;

    // AI-suggested days are generated one leg at a time — $aiItineraryLeg1
    // holds leg 1's days (stashed once leg 2's options were generated) and
    // $aiItinerary holds leg 2's, for a multi-city trip. For a single-city
    // trip $aiItinerary is the only itinerary.
    $s9leg1AiDays = $s9isMultiCity ? ($aiItineraryLeg1['days'] ?? []) : ($aiItinerary['days'] ?? []);
    $s9leg2AiDays = $s9isMultiCity ? ($aiItinerary['days'] ?? []) : [];
    $s9aiDays     = array_merge($s9leg1AiDays, $s9leg2AiDays);
    $s9leg1AiTotals = $this->categorizeAiCost($s9leg1AiDays);
    $s9leg2AiTotals = $this->categorizeAiCost($s9leg2AiDays);
    $s9aiTotals = ['accommodation'=>0.0,'food'=>0.0,'transport'=>0.0,'attraction'=>0.0];
    foreach ($s9aiTotals as $k => $v) $s9aiTotals[$k] = $s9leg1AiTotals[$k] + $s9leg2AiTotals[$k];
    $s9ai = array_sum($s9aiTotals);

    // Cost breakdown from selections — computed per leg, then combined,
    // so each leg's own picks (and its own AI-suggested activities) are
    // never mixed with the other leg's when shown separately below.
    //
    // Base costs (the traveler's own booking, no AI activities added) are
    // used for each Selection Summary line item, so a restaurant/attraction
    // pick shows only its own price. AI totals are added in separately
    // (below) only for the Cost Breakdown category totals on the right,
    // where lumping in AI-suggested activities of the same category is
    // exactly the point.
    $s9flightBase1 = (float) ($selectedFlight['price']   ?? 0);
    $s9flightBase2 = (float) ($selectedMcFlight['price'] ?? 0);
    $s9hotelBase1  = (float) ($selectedHotel['total']    ?? 0);
    $s9hotelBase2  = (float) ($selectedMcHotel['total']  ?? 0);
    // Multi-select: a leg can hold several venues/attractions, so each
    // leg's base cost is a sum, not one item's price.
    $s9venueBase1  = (float) array_sum(array_map(fn($v) => $v['priceMax'] ?? $v['priceMin'] ?? 0, $selectedVenues));
    $s9venueBase2  = (float) array_sum(array_map(fn($v) => $v['priceMax'] ?? $v['priceMin'] ?? 0, $selectedMcVenues));
    $s9attrBase1   = array_sum(array_map(fn($a) => ($a['isFree'] ?? false) ? 0 : (int) preg_replace('/[^\d]/', '', $a['price'] ?? '0'), $selectedAttractions));
    $s9attrBase2   = array_sum(array_map(fn($a) => ($a['isFree'] ?? false) ? 0 : (int) preg_replace('/[^\d]/', '', $a['price'] ?? '0'), $selectedMcAttractions));

    $s9flight1 = $s9flightBase1 + $s9leg1AiTotals['transport'];
    $s9flight2 = $s9flightBase2 + $s9leg2AiTotals['transport'];
    $s9hotel1  = $s9hotelBase1  + $s9leg1AiTotals['accommodation'];
    $s9hotel2  = $s9hotelBase2  + $s9leg2AiTotals['accommodation'];
    $s9venue1  = $s9venueBase1  + $s9leg1AiTotals['food'];
    $s9venue2  = $s9venueBase2  + $s9leg2AiTotals['food'];
    $s9attr1   = $s9attrBase1   + $s9leg1AiTotals['attraction'];
    $s9attr2   = $s9attrBase2   + $s9leg2AiTotals['attraction'];

    $s9flight = $s9flight1 + $s9flight2;
    $s9hotel  = $s9hotel1  + $s9hotel2;
    $s9venue  = $s9venue1  + $s9venue2;
    $s9attr   = $s9attr1   + $s9attr2;

    $s9emergency = (float) $emergency;
    $s9budget    = (int) preg_replace('/[^\d]/', '', $manualBudgetMax ?: $manualBudgetMin);
    $s9total     = $s9flight + $s9hotel + $s9venue + $s9attr + $s9emergency;
    $s9over      = $s9budget > 0 && $s9total > $s9budget;

    // Selections for summary list — leg 1 always; leg 2 only for multi-city.
    // Costs here are each pick's own price only (see $s9*Base* above).
    $s9picks = [];
    if ($selectedFlight)      $s9picks[] = ['icon'=>'fa-plane',    'label'=>'Flight',         'val'=>($selectedFlight['airline']??'').' '.($selectedFlight['number']??''),  'cost'=>$s9flightBase1, 'editStep'=>2, 'color'=>'#3B82F6'];
    if ($selectedHotel)       $s9picks[] = ['icon'=>'fa-bed',      'label'=>'Accommodation',  'val'=>$selectedHotel['name']??'Hotel',                                      'cost'=>$s9hotelBase1,  'editStep'=>3, 'color'=>'#0D9488'];
    // Multi-select: several venues/attractions can be chosen for one leg —
    // combined into a single Selection Summary row (comma-joined names,
    // summed price) instead of one duplicated row per pick.
    if (!empty($selectedVenues)) {
        $s9picks[] = ['icon'=>'fa-utensils', 'label'=>'Food & Dining', 'val'=>implode(', ', array_map(fn($v) => $v['name'] ?? 'Restaurant', $selectedVenues)), 'cost'=>$s9venueBase1, 'editStep'=>4, 'color'=>'#EF4444'];
    }
    if (!empty($selectedAttractions)) {
        $s9picks[] = ['icon'=>'fa-camera', 'label'=>'Attraction', 'val'=>implode(', ', array_map(fn($a) => $a['name'] ?? 'Attraction', $selectedAttractions)), 'cost'=>$s9attrBase1, 'editStep'=>5, 'color'=>'#FFDA03', 'ink'=>'var(--cat-attraction-ink)'];
    }

    $s9picksLeg2 = [];
    if ($s9isMultiCity) {
        if ($selectedMcFlight)     $s9picksLeg2[] = ['icon'=>'fa-plane',    'label'=>'Flight',         'val'=>($selectedMcFlight['airline']??'').' '.($selectedMcFlight['number']??''), 'cost'=>$s9flightBase2, 'editStep'=>2, 'color'=>'#3B82F6'];
        if ($selectedMcHotel)      $s9picksLeg2[] = ['icon'=>'fa-bed',      'label'=>'Accommodation',  'val'=>$selectedMcHotel['name']??'Hotel',                                         'cost'=>$s9hotelBase2,  'editStep'=>3, 'color'=>'#0D9488'];
        if (!empty($selectedMcVenues)) {
            $s9picksLeg2[] = ['icon'=>'fa-utensils', 'label'=>'Food & Dining', 'val'=>implode(', ', array_map(fn($v) => $v['name'] ?? 'Restaurant', $selectedMcVenues)), 'cost'=>$s9venueBase2, 'editStep'=>4, 'color'=>'#EF4444'];
        }
        if (!empty($selectedMcAttractions)) {
            $s9picksLeg2[] = ['icon'=>'fa-camera', 'label'=>'Attraction', 'val'=>implode(', ', array_map(fn($a) => $a['name'] ?? 'Attraction', $selectedMcAttractions)), 'cost'=>$s9attrBase2, 'editStep'=>5, 'color'=>'#FFDA03', 'ink'=>'var(--cat-attraction-ink)'];
        }
    }

    $s9leg1Dest = trim($manualTo ?: 'Unknown');
    $s9leg2Dest = trim($mcTo ?: '');
    $s9leg2Sd    = $mcStartDate ? \App\Livewire\Traveler\TripPlannerWizard::fmtDate($mcStartDate, 'M j') : '—';
    $s9leg2Ed    = $mcEndDate   ? \App\Livewire\Traveler\TripPlannerWizard::fmtDate($mcEndDate)          : '—';
    $s9leg2Sdow  = $mcStartDate ? \Carbon\Carbon::parse($mcStartDate)->format('l') : '';
    $s9leg2Edow  = $mcEndDate   ? \Carbon\Carbon::parse($mcEndDate)->format('l')   : '';
    $s9leg2Days  = ($mcStartDate && $mcEndDate) ? max(1,(int)round((strtotime($mcEndDate)-strtotime($mcStartDate))/86400)) : 1;
@endphp

<div style="max-width:1200px;margin:0 auto;padding:20px 0;">

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <button wire:click="$set('step', 8)" style="display:inline-flex;align-items:center;gap:6px;background:none;border:none;color:var(--primary);font-size:13px;font-weight:600;cursor:pointer;padding:0;">
            <i class="fa-solid fa-arrow-left" style="font-size:11px;"></i> Back to Suggested Itineraries
        </button>
        <div style="display:flex;gap:8px;">
            <button wire:click="saveItinerary" wire:loading.attr="disabled"
                    style="width:170px;padding:8px 18px;border:none;border-radius:10px;background:var(--primary);color:#fff;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:7px;box-sizing:border-box;transition:background .18s;"
                    onmouseenter="this.style.background='var(--primary-dark)'" onmouseleave="this.style.background='var(--primary)'">
                <span wire:loading.remove wire:target="saveItinerary">Confirm Trip <i class="fa-solid fa-check" style="font-size:10px;"></i></span>
                <span wire:loading wire:target="saveItinerary"><i class="fa-solid fa-spinner fa-spin"></i></span>
            </button>
        </div>
    </div>

    <h2 style="font-size:24px;font-weight:800;color:var(--dark);margin:0 0 20px;">Trip Summary &amp; Cost Estimation</h2>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">

        {{-- Left column --}}
        <div>
            {{-- Route & dates card --}}
            <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:18px;padding:24px 26px;margin-bottom:14px;box-shadow:0 4px 16px rgba(45,27,20,0.05);">
                @if($s9isMultiCity)
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--primary);background:var(--bg);display:inline-block;padding:3px 10px;border-radius:20px;margin-bottom:12px;">Trip 1</div>
                @endif
                <div style="display:flex;align-items:flex-start;gap:22px;flex-wrap:wrap;">
                    <div>
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">FROM</div>
                        <div style="font-size:21px;font-weight:800;color:var(--primary);">{{ $s9from }}</div>
                        <div style="font-size:11px;color:var(--muted);">{{ $selectedFlight['dep_id'] ?? 'MNL' }}</div>
                    </div>
                    <div style="flex:1;min-width:120px;margin-top:26px;display:flex;flex-direction:column;align-items:center;">
                        <div style="font-size:11px;color:var(--muted);margin-bottom:5px;text-align:center;">{{ $s9days }}-Day Journey</div>
                        <div style="border-top:2px dashed #D1C5B8;position:relative;width:100%;">
                            <span style="position:absolute;top:-9px;left:50%;transform:translateX(-50%);width:16px;height:16px;border-radius:50%;background:var(--bg-white);border:2px solid #D1C5B8;display:inline-block;box-sizing:border-box;"></span>
                        </div>
                    </div>
                    <div>
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">TO</div>
                        <div style="font-size:21px;font-weight:800;color:var(--primary);">{{ $s9leg1Dest }}</div>
                        <div style="font-size:11px;color:var(--muted);">{{ $selectedFlight['arr_id'] ?? '' }}</div>
                    </div>
                    <div style="border-left:1.5px solid var(--border);padding-left:22px;">
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:4px;">DATES</div>
                        <div style="font-size:15px;font-weight:700;color:var(--dark);">{{ \App\Livewire\Traveler\TripPlannerWizard::fmtDate($startDate, 'M j') }} - {{ \App\Livewire\Traveler\TripPlannerWizard::fmtDate($endDate) }}</div>
                        <div style="font-size:11px;color:var(--muted);">{{ $s9sdow }} - {{ $s9edow }}</div>
                    </div>
                </div>

                @if($s9isMultiCity)
                <div style="border-top:1.5px solid var(--border);margin:20px 0;"></div>
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--primary);background:var(--bg);display:inline-block;padding:3px 10px;border-radius:20px;margin-bottom:12px;">Trip 2</div>
                <div style="display:flex;align-items:flex-start;gap:22px;flex-wrap:wrap;">
                    <div>
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">FROM</div>
                        <div style="font-size:21px;font-weight:800;color:var(--primary);">{{ $s9leg1Dest }}</div>
                        <div style="font-size:11px;color:var(--muted);">{{ $selectedMcFlight['dep_id'] ?? '' }}</div>
                    </div>
                    <div style="flex:1;min-width:120px;margin-top:26px;display:flex;flex-direction:column;align-items:center;">
                        <div style="font-size:11px;color:var(--muted);margin-bottom:5px;text-align:center;">{{ $s9leg2Days }}-Day Journey</div>
                        <div style="border-top:2px dashed #D1C5B8;position:relative;width:100%;">
                            <span style="position:absolute;top:-9px;left:50%;transform:translateX(-50%);width:16px;height:16px;border-radius:50%;background:var(--bg-white);border:2px solid #D1C5B8;display:inline-block;box-sizing:border-box;"></span>
                        </div>
                    </div>
                    <div>
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">TO</div>
                        <div style="font-size:21px;font-weight:800;color:var(--primary);">{{ $s9leg2Dest }}</div>
                        <div style="font-size:11px;color:var(--muted);">{{ $selectedMcFlight['arr_id'] ?? '' }}</div>
                    </div>
                    <div style="border-left:1.5px solid var(--border);padding-left:22px;">
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:4px;">DATES</div>
                        <div style="font-size:15px;font-weight:700;color:var(--dark);">{{ $s9leg2Sd }} - {{ $s9leg2Ed }}</div>
                        <div style="font-size:11px;color:var(--muted);">{{ $s9leg2Sdow }} - {{ $s9leg2Edow }}</div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Itinerary collapsible --}}
            <div x-data="{ open: false }" style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:18px;overflow:hidden;margin-bottom:14px;box-shadow:0 4px 16px rgba(45,27,20,0.05);">
                <button @click="open=!open" style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:18px 22px;background:none;border:none;cursor:pointer;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <i class="fa-regular fa-calendar-check" style="color:var(--muted);font-size:14px;"></i>
                        <span style="font-size:15px;font-weight:700;color:var(--dark);">Itinerary</span>
                    </div>
                    <i class="fa-solid fa-chevron-down" :style="'font-size:12px;color:var(--muted);transition:.2s;' + (open?'transform:rotate(180deg)':'')"></i>
                </button>
                <div x-show="open" x-transition style="border-top:1px solid var(--border);padding:18px 22px;">
                    @if($s9isMultiCity)
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--primary);background:var(--bg);display:inline-block;padding:3px 10px;border-radius:20px;margin-bottom:10px;">Leg 1 — {{ $s9leg1Dest }}</div>
                    @endif
                    @if(!empty($s9picks))
                    <div style="margin-bottom:10px;">
                        <div style="font-size:10px;font-weight:700;color:var(--primary);margin-bottom:5px;">Day 1 — Arrival</div>
                        @foreach($s9picks as $pk9)
                        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--dark);padding:4px 0;border-bottom:1px solid var(--bg);">
                            <span>{{ $pk9['label'] }} · {{ $pk9['val'] }}</span>
                            <span style="color:var(--muted);font-weight:600;">{{ $pk9['cost'] ? $this->tripDisplayAmount($pk9['cost']) : 'Free' }}</span>
                        </div>
                        @endforeach
                    </div>
                    @endif
                    @if(!empty($s9leg1AiDays))
                        @foreach($s9leg1AiDays as $i => $d9)
                        <div style="margin-bottom:10px;">
                            <div style="font-size:10px;font-weight:700;color:var(--primary);margin-bottom:5px;">Day {{ $i+2 }} — {{ $d9['label'] ?? '' }}</div>
                            @foreach($d9['activities'] ?? [] as $a9)
                            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--dark);padding:4px 0;border-bottom:1px solid var(--bg);">
                                <span>{{ $a9['time'] ?? '' }} · {{ $a9['title'] ?? '' }}</span>
                                <span style="color:var(--muted);font-weight:600;">{{ $a9['cost'] ? $this->tripDisplayAmount($a9['cost']) : 'Free' }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endforeach
                    @endif

                    @if($s9isMultiCity)
                    <div style="border-top:1px solid var(--border);margin:14px 0;"></div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--primary);background:var(--bg);display:inline-block;padding:3px 10px;border-radius:20px;margin-bottom:10px;">Leg 2 — {{ $s9leg2Dest }}</div>
                    @if(!empty($s9picksLeg2))
                    <div style="margin-bottom:10px;">
                        <div style="font-size:10px;font-weight:700;color:var(--primary);margin-bottom:5px;">Day 1 — Arrival</div>
                        @foreach($s9picksLeg2 as $pk9)
                        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--dark);padding:4px 0;border-bottom:1px solid var(--bg);">
                            <span>{{ $pk9['label'] }} · {{ $pk9['val'] }}</span>
                            <span style="color:var(--muted);font-weight:600;">{{ $pk9['cost'] ? $this->tripDisplayAmount($pk9['cost']) : 'Free' }}</span>
                        </div>
                        @endforeach
                    </div>
                    @endif
                    @if(!empty($s9leg2AiDays))
                        @foreach($s9leg2AiDays as $i => $d9)
                        <div style="margin-bottom:10px;">
                            <div style="font-size:10px;font-weight:700;color:var(--primary);margin-bottom:5px;">Day {{ $i+2 }} — {{ $d9['label'] ?? '' }}</div>
                            @foreach($d9['activities'] ?? [] as $a9)
                            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--dark);padding:4px 0;border-bottom:1px solid var(--bg);">
                                <span>{{ $a9['time'] ?? '' }} · {{ $a9['title'] ?? '' }}</span>
                                <span style="color:var(--muted);font-weight:600;">{{ $a9['cost'] ? $this->tripDisplayAmount($a9['cost']) : 'Free' }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endforeach
                    @endif
                    @endif

                    @if(empty($s9picks) && empty($s9aiDays))
                        <p style="color:var(--muted);font-size:12px;margin:0;">No itinerary generated yet.</p>
                    @endif
                </div>
            </div>

            {{-- Selection Summary collapsible --}}
            <div x-data="{ open: false }" style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:18px;overflow:hidden;box-shadow:0 4px 16px rgba(45,27,20,0.05);">
                <button @click="open=!open" style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:18px 22px;background:none;border:none;cursor:pointer;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <i class="fa-regular fa-bookmark" style="color:var(--muted);font-size:14px;"></i>
                        <span style="font-size:15px;font-weight:700;color:var(--dark);">Selection Summary</span>
                    </div>
                    <i class="fa-solid fa-chevron-down" :style="'font-size:12px;color:var(--muted);transition:.2s;' + (open?'transform:rotate(180deg)':'')"></i>
                </button>
                <div x-show="open" x-transition style="border-top:1px solid var(--border);padding:18px 22px;">
                    @if($s9isMultiCity)
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--primary);background:var(--bg);display:inline-block;padding:3px 10px;border-radius:20px;margin-bottom:8px;">Leg 1 — {{ $s9leg1Dest }}</div>
                    @endif
                    @foreach($s9picks as $pk)
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--bg);">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:28px;height:28px;border-radius:7px;background:{{ $pk['color'] }}1A;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fa-solid {{ $pk['icon'] }}" style="font-size:11px;color:{{ $pk['ink'] ?? $pk['color'] }};"></i>
                            </div>
                            <div>
                                <div style="font-size:9px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;">{{ $pk['label'] }}</div>
                                <div style="font-size:12px;font-weight:600;color:var(--dark);">{{ $pk['val'] }}</div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <button wire:click="editFromSummary({{ $pk['editStep'] }})" style="display:block;margin-left:auto;font-size:10px;font-weight:600;color:var(--primary);background:none;border:none;cursor:pointer;padding:0 0 2px;">Edit</button>
                            <div style="font-size:12px;font-weight:700;color:var(--dark);">{{ $pk['cost'] ? $this->tripDisplayAmount($pk['cost']) : 'Free' }}</div>
                        </div>
                    </div>
                    @endforeach

                    @if($s9isMultiCity)
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--primary);background:var(--bg);display:inline-block;padding:3px 10px;border-radius:20px;margin:14px 0 8px;">Leg 2 — {{ $s9leg2Dest }}</div>
                    @foreach($s9picksLeg2 as $pk)
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--bg);">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:28px;height:28px;border-radius:7px;background:{{ $pk['color'] }}1A;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fa-solid {{ $pk['icon'] }}" style="font-size:11px;color:{{ $pk['ink'] ?? $pk['color'] }};"></i>
                            </div>
                            <div>
                                <div style="font-size:9px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;">{{ $pk['label'] }}</div>
                                <div style="font-size:12px;font-weight:600;color:var(--dark);">{{ $pk['val'] }}</div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <button wire:click="editFromSummary({{ $pk['editStep'] }})" style="display:block;margin-left:auto;font-size:10px;font-weight:600;color:var(--primary);background:none;border:none;cursor:pointer;padding:0 0 2px;">Edit</button>
                            <div style="font-size:12px;font-weight:700;color:var(--dark);">{{ $pk['cost'] ? $this->tripDisplayAmount($pk['cost']) : 'Free' }}</div>
                        </div>
                    </div>
                    @endforeach
                    @endif

                    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:28px;height:28px;border-radius:7px;background:#FEE2E2;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fa-solid fa-shield-halved" style="font-size:11px;color:#B91C1C;"></i>
                            </div>
                            <div>
                                <div style="font-size:9px;font-weight:700;color:#B91C1C;text-transform:uppercase;letter-spacing:.4px;">Emergency Fund</div>
                                <div style="font-size:12px;font-weight:600;color:var(--dark);">Reserved safety budget</div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <button wire:click="editFromSummary(6)" style="display:block;margin-left:auto;font-size:10px;font-weight:600;color:var(--primary);background:none;border:none;cursor:pointer;padding:0 0 2px;">Edit</button>
                            <div style="font-size:12px;font-weight:700;color:var(--dark);">{{ $this->tripDisplayAmount($s9emergency) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right column: Cost Breakdown --}}
        <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:18px;padding:26px;position:sticky;top:80px;box-shadow:0 4px 16px rgba(45,27,20,0.05);">
            <div style="font-size:16px;font-weight:800;color:var(--dark);margin-bottom:18px;">Cost Breakdown</div>

            @php
                $s9rows = [
                    ['Transportation', $s9flight],
                    ['Accommodation',  $s9hotel],
                    ['Food & Dining',  $s9venue],
                    ['Attractions',    $s9attr],
                ];
            @endphp
            @foreach($s9rows as [$lbl,$amt])
            <div style="display:flex;justify-content:space-between;align-items:center;font-size:14px;padding:11px 0;border-bottom:1px solid #F3F0EB;">
                <span style="color:var(--muted);">{{ $lbl }}</span>
                <span style="font-weight:600;color:var(--dark);">{{ $this->tripDisplayAmount($amt) }}</span>
            </div>
            @endforeach

            <div style="display:flex;justify-content:space-between;align-items:center;font-size:14px;padding:11px 0;">
                <span style="color:#B91C1C;font-weight:600;">Emergency Fund</span>
                <span style="font-weight:700;color:#B91C1C;">{{ $this->tripDisplayAmount($s9emergency) }}</span>
            </div>

            <div style="border-top:1.5px solid #E5E0D8;margin-top:6px;padding-top:16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:15px;font-weight:700;color:var(--dark);">Total Cost</span>
                    <span style="font-size:22px;font-weight:900;color:{{ $s9over ? '#B91C1C' : 'var(--primary)' }};">{{ $this->tripDisplayAmount($s9total) }}</span>
                </div>
                @if($s9budget > 0)
                <div style="margin-top:5px;font-size:12px;color:{{ $s9over ? '#B91C1C' : 'var(--muted)' }};text-align:right;">
                    {{ $s9over ? 'Over '.$this->tripDisplayAmount($s9budget).' budget' : 'Within '.$this->tripDisplayAmount($s9budget).' budget' }}
                </div>
                @endif

                {{-- Split bill. Hidden on a solo trip, where "÷ 1" says nothing. --}}
                @if($travelers > 1)
                <div style="margin-top:14px;padding-top:14px;border-top:1px dashed #E5E0D8;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:14px;font-weight:700;color:var(--dark);">Per Person</span>
                        <span style="font-size:18px;font-weight:800;color:#C8874A;">{{ $this->tripDisplayAmount($s9total / $travelers) }}</span>
                    </div>
                    <div style="margin-top:4px;font-size:12px;color:var(--muted);text-align:right;">
                        Split between {{ $travelers }} travelers
                    </div>
                </div>
                @endif
            </div>

            <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--bg);font-size:12px;color:var(--muted);line-height:1.6;">
                All estimates include upper-bound restaurant pricing.
            </div>
        </div>

    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════
     EMPTY STATE — no trips yet
═══════════════════════════════════════════════════════════════ --}}
@if ($showEmpty && !auth()->user()?->userProfile)
<div class="empty-state-center" style="width:100%;min-height:calc(100vh - 120px);">
    <div style="width:64px;height:64px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
        <i class="fa-solid fa-map-location-dot" style="font-size:28px;color:#fff;"></i>
    </div>
    <h2 style="font-weight:700;font-size:22px;margin-bottom:10px;color:var(--dark);">Set up your profile first</h2>
    <p style="color:var(--muted);margin-bottom:28px;font-size:14px;max-width:320px;line-height:1.6;">Complete your travel profile before planning your trip and view estimations for your trips.</p>
    <a href="{{ route('profile.setup') }}" style="display:inline-flex;align-items:center;gap:10px;background:var(--primary);color:#fff;border-radius:30px;padding:14px 32px;font-size:13px;font-weight:700;letter-spacing:.06em;text-decoration:none;text-transform:uppercase;">
        <i class="fa-solid fa-user"></i> Set Up Your Profile First
    </a>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════
     MODE SELECT — manual or AI
     Shown as the empty state too (no trips yet, but profile exists) —
     skips the old "No trips planned yet" screen and its button so
     first-time planners land straight on Manual/AI Powered Planning.
═══════════════════════════════════════════════════════════════ --}}
@if ((!$showEmpty || auth()->user()?->userProfile) && $planningMode === '' && $step === 0)
<style>
.mode-card{background:var(--bg-white);border:1.5px solid var(--border);border-radius:22px;overflow:hidden;cursor:pointer;transition:box-shadow .25s ease,transform .25s ease,border-color .25s ease;display:flex;flex-direction:column;height:fit-content;align-self:start;text-decoration:none;color:inherit;position:relative;outline:none;-webkit-tap-highlight-color:transparent;}
.mode-card:focus,.mode-card:focus-visible{outline:none;}
.mode-card:hover{box-shadow:0 20px 50px rgba(26,10,0,0.14);transform:translateY(-6px);border-color:#E7D4C4;}
.mode-card .mode-img-wrap{height:320px;flex-shrink:0;overflow:hidden;position:relative;}
.mode-card .mode-img-wrap img{width:100%;height:100%;object-fit:cover;transition:transform .5s ease;display:block;}
.mode-card:hover .mode-img-wrap img{transform:scale(1.06);}
.mode-card .mode-img-fade{position:absolute;inset:0;background:linear-gradient(to top,rgba(20,10,4,0.55) 0%,rgba(20,10,4,0) 45%);}
.mode-tags{position:absolute;bottom:16px;left:20px;right:20px;display:flex;flex-wrap:wrap;gap:6px;}
.mode-tag{font-size:11px;font-weight:600;color:#fff;background:rgba(255,255,255,0.18);border:1px solid rgba(255,255,255,0.35);backdrop-filter:blur(3px);border-radius:20px;padding:4px 11px;}
.mode-cta{font-size:13px;font-weight:800;letter-spacing:0.4px;color:#fff;display:inline-flex;align-items:center;justify-content:center;gap:8px;background:var(--primary);border-radius:12px;padding:13px 22px;width:100%;box-sizing:border-box;transition:background .2s,gap .2s;}
.mode-card:hover .mode-cta{background:var(--primary-dark);gap:11px;}
/* padding + clip so the shake's ±6px can never spill out and nudge the page
   into a horizontal scroll. clip (not hidden) keeps it from becoming a
   scroll container. */
.mode-code{margin-top:26px;display:flex;flex-direction:column;align-items:center;gap:9px;padding-inline:12px;overflow-x:clip;}
.mode-code-label{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:var(--muted);}
.mode-code-label i{font-size:11px;}
.mode-code-row{display:flex;align-items:stretch;gap:9px;}
.mode-code-input{width:190px;box-sizing:border-box;border:1.5px solid var(--border);border-radius:12px;padding:11px 14px;font-family:inherit;font-size:14px;font-weight:600;letter-spacing:.08em;text-align:center;text-transform:uppercase;background:var(--bg-white);color:var(--dark);outline:none;transition:border-color .18s,box-shadow .18s;}
.mode-code-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(124,140,255,.16);}
/* Codes are stored uppercase, so the field uppercases as you type — but the
   placeholder is prose and shouldn't be shouted back in caps. */
.mode-code-input::placeholder{text-transform:none;letter-spacing:.02em;font-weight:500;opacity:.7;}
.mode-code-btn{border:none;border-radius:12px;padding:11px 20px;background:var(--primary);color:#fff;font-family:inherit;font-size:13.5px;font-weight:700;cursor:pointer;white-space:nowrap;transition:background .18s;}
.mode-code-btn:hover{background:var(--primary-dark);}
/* Rejected: candy-red ring plus a short shake. Driven by a class rather than
   an inline style so re-adding it restarts the animation on a repeat click. */
.mode-code-input.is-bad,.mode-code-input.is-bad:focus{border-color:#FF3B3B;box-shadow:0 0 0 3px rgba(255,59,59,.20);animation:mode-code-shake .48s cubic-bezier(.36,.07,.19,.97) both;}
@keyframes mode-code-shake{
  10%,90%{transform:translateX(-2px);}
  20%,80%{transform:translateX(3px);}
  30%,50%,70%{transform:translateX(-6px);}
  40%,60%{transform:translateX(6px);}
}
@media (prefers-reduced-motion:reduce){.mode-code-input.is-bad{animation:none;}}
</style>
<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:16px 32px 20px;height:100%;box-sizing:border-box;position:relative;">

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:32px;width:100%;max-width:1100px;">

        {{-- Manual Planning --}}
        <div wire:click="selectPlanningMode('manual')" class="mode-card">
            <div class="mode-img-wrap">
                <img src="{{ asset('stockimages/manualplanning.jpg') }}?v={{ filemtime(public_path('stockimages/manualplanning.jpg')) }}" alt="Manual Planning">
                <div class="mode-img-fade"></div>
                <div class="mode-tags">
                    <span class="mode-tag"><i class="fa-solid fa-plane" style="font-size:9px;margin-right:4px;"></i>Transportation</span>
                    <span class="mode-tag"><i class="fa-solid fa-bed" style="font-size:9px;margin-right:4px;"></i>Accommodation</span>
                    <span class="mode-tag"><i class="fa-solid fa-utensils" style="font-size:9px;margin-right:4px;"></i>Food and Dining</span>
                    <span class="mode-tag"><i class="fa-solid fa-camera" style="font-size:9px;margin-right:4px;"></i>Attractions</span>
                </div>
            </div>
            <div style="padding:22px 26px 26px;flex-shrink:0;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;">
                    <span style="font-size:21px;font-weight:800;color:var(--dark);">Manual Planning</span>
                    <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;background:#F3EBE1;color:#4A3B32;border-radius:20px;padding:4px 12px;white-space:nowrap;">Full Control</span>
                </div>
                <p style="font-size:14px;color:var(--muted);line-height:1.5;margin:0 0 20px;">
                    Build your own trip with full control over every details.
                </p>
                <span class="mode-cta">GET STARTED <i class="fa-solid fa-arrow-right" style="font-size:12px;"></i></span>
            </div>
        </div>

        {{-- AI Planning --}}
        <a href="{{ route('trips.plan.ai') }}" wire:navigate class="mode-card">
            <div class="mode-img-wrap">
                <img src="{{ asset('stockimages/aipoweredplanning.jpg') }}?v={{ filemtime(public_path('stockimages/aipoweredplanning.jpg')) }}" alt="AI Planning">
                <div class="mode-img-fade"></div>
                <div class="mode-tags">
                    <span class="mode-tag"><i class="fa-solid fa-bolt" style="font-size:9px;margin-right:4px;"></i>Instant Itinerary</span>
                    <span class="mode-tag"><i class="fa-solid fa-wand-magic-sparkles" style="font-size:9px;margin-right:4px;"></i>Personalized Picks</span>
                    <span class="mode-tag"><i class="fa-solid fa-wallet" style="font-size:9px;margin-right:4px;"></i>Budget Optimized</span>
                </div>
            </div>
            <div style="padding:22px 26px 26px;flex-shrink:0;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;">
                    <span style="font-size:21px;font-weight:800;color:var(--dark);">AI Powered Planning</span>
                    <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;background:#FEF3E2;color:#451A03;border:1px solid #FDE68A;border-radius:20px;padding:4px 12px;white-space:nowrap;">Recommended</span>
                </div>
                <p style="font-size:14px;color:var(--muted);line-height:1.5;margin:0 0 20px;">
                    Type in your trip details and let TARA build the perfect trip for you.
                </p>
                <span class="mode-cta">LAUNCH ASSISTANT <i class="fa-solid fa-arrow-right" style="font-size:12px;"></i></span>
            </div>
        </a>

    </div>

    {{-- Optional shortcut, offered to either planning mode. Centred by the
         wrapper's align-items:center.

         An empty field is caught here in Alpine rather than on the server:
         the answer is already known client-side, and a Livewire round trip
         to Supabase would put ~500ms between the click and the shake, which
         reads as a dropped click rather than a rejection. Server-side
         rejections (unknown code) dispatch 'code-rejected' so they land on
         the same treatment. --}}
    <div class="mode-code"
         x-data="{
             bad: false,
             reject() { this.bad = false; requestAnimationFrame(() => this.bad = true); },
             submit() {
                 if (!(($wire.importCodeInput ?? '').trim())) { this.reject(); return; }
                 $wire.importCode();
             }
         }"
         @code-rejected.window="reject()">

        <span class="mode-code-label"><i class="fa-solid fa-key"></i> Have a trip code?</span>

        <div class="mode-code-row">
            <input type="text" class="mode-code-input" :class="{ 'is-bad': bad }"
                   wire:model="importCodeInput" maxlength="8" placeholder="e.g. AB1C2D3E"
                   x-on:input="bad = false" x-on:keydown.enter.prevent="submit()">
            <button type="button" class="mode-code-btn" x-on:click="submit()"
                    wire:loading.attr="disabled" wire:target="importCode">
                <span wire:loading.remove wire:target="importCode">Import Trip</span>
                <span wire:loading wire:target="importCode"><i class="fa-solid fa-spinner fa-spin"></i></span>
            </button>
        </div>
    </div>
</div>
@endif


</div>
