<div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:20px;overflow:hidden;display:flex;flex-direction:column;width:100%;">

    @php
        $trip       = $goal->trip;
        $dest       = $trip?->trip_name ?? $trip?->destination ?? $goal->goal_name;
        $tType      = strtoupper($trip?->travel_type ?? 'SOLO');
        $typeColor    = $tType === 'GROUP' ? '#A855F7' : '#14B8A6';
        $tripStatus   = $trip?->status ?? ($trip?->start_date?->gt(\Carbon\Carbon::today()) ? 'upcoming' : ($trip?->end_date?->lt(\Carbon\Carbon::today()) ? 'past' : 'active'));
        $cover      = $trip?->cover_image ?: ($tripStatus === 'draft' ? asset('stockimages/draftimage.jpg') : null);
        $statusColor  = match($tripStatus) { 'active' => '#22C55E', 'upcoming' => '#3B82F6', default => 'var(--muted)' };
        $fromCode   = $trip?->origin_code ?? 'MNL';
        $toCode     = $trip?->destination_code ?? '';
        $dateFrom   = $trip?->start_date?->format('M j');
        $dateTo     = $trip?->end_date?->format('M j, Y');
        $targetCost = $trip?->total_cost ?? $goal->target_amount;
        $isPastGoal = $tripStatus === 'past';
    @endphp

    {{-- Cover image --}}
    <div style="position:relative;height:200px;background:linear-gradient(135deg,var(--primary),#C8874A);overflow:hidden;flex-shrink:0;">
        @if($cover)
        <img src="{{ $cover }}" alt="{{ $dest }}"
             style="width:100%;height:100%;object-fit:cover;display:block;"
             onerror="this.style.display='none'">
        @endif
        <div style="position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,0.1),rgba(0,0,0,0.55));"></div>

        {{-- Stacked badges top-left --}}
        <div style="position:absolute;top:14px;left:14px;display:flex;flex-direction:column;gap:6px;">
            <span style="background:{{ $typeColor }};color:#fff;font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;letter-spacing:0.5px;display:inline-block;text-align:center;">{{ $tType }}</span>
            @php $statusLabel = match($tripStatus) { 'active' => 'Ongoing', 'upcoming' => 'Upcoming', 'past' => 'Finished', default => ucfirst($tripStatus) }; @endphp
            <span style="background:{{ $statusColor }};color:#fff;font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;text-transform:uppercase;display:inline-block;">{{ $statusLabel }}</span>
        </div>

        {{-- Trip info overlay --}}
        <div style="position:absolute;bottom:14px;left:18px;right:18px;">
            <div style="font-size:19px;font-weight:700;color:#fff;margin-bottom:6px;line-height:1.3;">{{ $dest }}</div>
            @if($trip)
            @php
                $leg2From = $trip->is_multi_city && $trip->leg2_destination ? $toCode : null;
                $leg2To   = $trip->is_multi_city && $trip->leg2_destination ? ($trip->leg2_destination_code ?? '') : null;
                $leg2Sd   = $trip->is_multi_city && $trip->leg2_destination ? $trip->leg2_start_date?->format('M j') : null;
                $leg2Ed   = $trip->is_multi_city && $trip->leg2_destination ? $trip->leg2_end_date?->format('M j, Y') : null;
            @endphp
            <div style="display:flex;flex-direction:column;gap:4px;">
                @if($fromCode)
                <div style="display:flex;align-items:center;gap:14px;font-size:13px;color:rgba(255,255,255,0.9);flex-wrap:wrap;">
                    <span style="display:flex;align-items:center;gap:6px;"><i class="fa-solid fa-plane" style="font-size:11px;color:#F5C97A;"></i>{{ $fromCode }}{{ $toCode ? ' to '.$toCode : '' }}</span>
                    @if($leg2From && $leg2To)
                    <span style="display:flex;align-items:center;gap:6px;"><i class="fa-solid fa-plane" style="font-size:11px;color:#F5C97A;"></i>{{ $leg2From }} to {{ $leg2To }}</span>
                    @endif
                </div>
                @endif
                @if($dateFrom)
                <div style="display:flex;align-items:center;gap:14px;font-size:13px;color:rgba(255,255,255,0.9);flex-wrap:wrap;">
                    <span style="display:flex;align-items:center;gap:6px;"><i class="fa-regular fa-calendar-days" style="font-size:11px;color:#F5C97A;"></i>{{ $dateFrom }} – {{ $dateTo }}</span>
                    @if($leg2Sd && $leg2Ed)
                    <span style="display:flex;align-items:center;gap:6px;"><i class="fa-regular fa-calendar-days" style="font-size:11px;color:#F5C97A;"></i>{{ $leg2Sd }} – {{ $leg2Ed }}</span>
                    @endif
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- Body --}}
    <div style="padding:20px 22px 22px;display:flex;flex-direction:column;flex:1;">

        @php $cardPct = $targetCost > 0 ? min(100, round($goal->current_savings / $targetCost * 100, 1)) : 0; @endphp
        @php $cardDone = $goal->current_savings >= $targetCost; @endphp

        {{-- Progress label + pct --}}
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <span style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);">Progress</span>
            @if($cardDone)
            <span style="font-size:13px;font-weight:700;color:var(--success);">Completed</span>
            @else
            <span style="font-size:15px;font-weight:700;color:var(--dark);">{{ $cardPct }}%</span>
            @endif
        </div>

        {{-- Progress bar --}}
        <div style="height:6px;background:var(--border-light);border-radius:99px;overflow:hidden;margin-bottom:18px;">
            <div style="height:100%;width:{{ $cardPct }}%;background:{{ $cardDone ? 'var(--success)' : 'var(--primary)' }};border-radius:99px;transition:width 0.3s;"></div>
        </div>

        {{-- Saved / Target --}}
        <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:20px;">
            <div>
                <div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Saved Amount</div>
                <div style="font-size:23px;font-weight:800;color:#C8874A;">{{ $this->displayAmount($goal->current_savings) }}</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Target Goal</div>
                <div style="font-size:23px;font-weight:800;color:var(--dark);">{{ $this->displayAmount($targetCost) }}</div>
            </div>
        </div>

        {{-- Button --}}
        <div style="margin-top:auto;">
            @if($cardDone)
            {{-- Same neutral, non-actionable treatment as the Trip Finished
                 state below — both are terminal states where there's nothing
                 left to click, so they read the same. --}}
            <div style="width:100%;background:var(--border-light);color:var(--muted);border-radius:12px;padding:14px;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:7px;font-family:'Hanken Grotesk',sans-serif;">
                <i class="fa-solid fa-check"></i> Goal Reached!
            </div>
            @elseif($isPastGoal)
            <button type="button" disabled title="This trip has already finished — savings can no longer be added."
                    style="width:100%;background:var(--border-light);color:var(--muted);border:none;border-radius:12px;padding:14px;font-size:14px;font-weight:700;cursor:not-allowed;display:flex;align-items:center;justify-content:center;gap:7px;font-family:'Hanken Grotesk',sans-serif;">
                Trip Finished
            </button>
            @else
            <button wire:click="openDeposit"
                    style="width:100%;background:var(--primary);color:#fff;border:none;border-radius:12px;padding:14px;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;font-family:'Hanken Grotesk',sans-serif;transition:background .18s,gap .18s;"
                    onmouseenter="this.style.background='var(--primary-dark)';this.style.gap='9px'" onmouseleave="this.style.background='var(--primary)';this.style.gap='7px'">
                <i class="fa-solid fa-circle-plus" style="font-size:15px;"></i> Add Savings
            </button>
            @endif
        </div>
    </div>

    {{-- Deposit modal --}}
    @if ($showDeposit)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.55);backdrop-filter:blur(3px);z-index:1000;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click.self="closeDeposit">
        <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:22px;width:100%;max-width:380px;padding:28px;">

            <div style="display:flex;align-items:center;gap:12px;margin-bottom:22px;">
                <div style="width:40px;height:40px;border-radius:12px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa-solid fa-piggy-bank" style="color:#fff;font-size:16px;"></i>
                </div>
                <span style="font-size:18px;font-weight:700;color:var(--dark);">Add Savings</span>
            </div>

            @php $depositRemaining = max(0, $targetCost - $goal->current_savings); @endphp
            <div style="margin-bottom:6px;">
                <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:8px;">Amount to Save</div>
                <div x-data="{
                        display: '',
                        max: {{ $depositRemaining }},
                        update(e) {
                            let raw = e.target.value.replace(/[^0-9.]/g, '');
                            let parts = raw.split('.');
                            let val = parseFloat(parts[0] + (parts[1] !== undefined ? '.' + parts[1] : '')) || 0;
                            if (val > this.max) {
                                raw = String(this.max);
                                parts = raw.split('.');
                            }
                            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                            this.display = parts.join('.');
                            $wire.set('depositAmount', parseFloat(raw) || 0);
                        }
                    }"
                     style="display:flex;align-items:center;gap:10px;background:var(--bg-white);border:1.5px solid var(--border);border-radius:14px;padding:14px 16px;transition:border-color .18s,box-shadow .18s;"
                     onfocusin="this.style.borderColor='var(--primary)';this.style.boxShadow='0 0 0 4px rgba(147,75,25,0.08)'" onfocusout="this.style.borderColor='var(--border)';this.style.boxShadow='none'">
                    {{-- Pesos, not $this->displayAmount() — deposits are typed
                         and tracked in pesos with no conversion, so this must
                         stay honest about what unit you're actually typing. --}}
                    <span style="font-size:14px;font-weight:600;color:var(--muted);flex-shrink:0;">₱</span>
                    <input type="text" inputmode="decimal"
                           x-model="display" @input="update($event)"
                           style="flex:1;border:none;outline:none;font-size:15px;font-weight:600;color:var(--dark);background:transparent;font-family:'Hanken Grotesk',sans-serif;"
                           placeholder="0.00">
                </div>
                @error('depositAmount')<div style="color:#DC2626;font-size:12px;margin-top:5px;">{{ $message }}</div>@enderror
            </div>

            <div style="font-size:12px;color:var(--muted);margin-bottom:24px;">
                This will be added to your '{{ $dest }}' goal.
            </div>

            <button wire:click="submitDeposit" x-data
                    :disabled="!$wire.depositAmount || $wire.depositAmount <= 0"
                    :style="'width:100%;background:var(--primary);color:#fff;border:none;border-radius:12px;padding:14px;font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;font-family:\'Hanken Grotesk\',sans-serif;margin-bottom:12px;transition:background .18s;' + ((!$wire.depositAmount || $wire.depositAmount <= 0) ? 'opacity:.5;cursor:not-allowed;' : 'cursor:pointer;')"
                    onmouseenter="if(!this.disabled)this.style.background='var(--primary-dark)'" onmouseleave="if(!this.disabled)this.style.background='var(--primary)'">
                Add Savings
            </button>

            <button wire:click="closeDeposit"
                    style="width:100%;background:transparent;color:var(--dark);border:1.5px solid var(--border);border-radius:12px;padding:14px;font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;font-family:'Hanken Grotesk',sans-serif;cursor:pointer;transition:background .18s,border-color .18s;"
                    onmouseenter="this.style.background='var(--bg)';this.style.borderColor='var(--muted)'" onmouseleave="this.style.background='transparent';this.style.borderColor='var(--border)'">
                Cancel
            </button>

        </div>
    </div>
    @endif
</div>
