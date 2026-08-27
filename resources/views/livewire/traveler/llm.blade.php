<div>

{{-- History button — floats above every screen state (landing, active
     chat, loading), since a past conversation should always be
     reachable regardless of where the current one is at. Hidden on the
     results screen, where "Back to Chat" already covers going back. --}}
@unless ($aiStep === 'results' && !empty($aiPackage))
<button type="button" wire:click="openHistory" title="Past conversations"
        style="position:fixed;top:20px;right:24px;z-index:900;width:38px;height:38px;border-radius:50%;background:var(--bg-white);border:1.5px solid var(--border);color:var(--primary);cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(45,27,20,.08);transition:border-color .15s ease,box-shadow .15s ease;"
        onmouseenter="this.style.borderColor='var(--primary)';this.style.boxShadow='0 4px 12px rgba(147,75,25,.14)';"
        onmouseleave="this.style.borderColor='var(--border)';this.style.boxShadow='0 2px 8px rgba(45,27,20,.08)';">
    <i class="fa-solid fa-clock-rotate-left" style="font-size:14px;"></i>
</button>
@endunless

{{-- ═══════════════════════════════════════════════════════════════
     AI PLANNER — results
═══════════════════════════════════════════════════════════════ --}}
@if ($aiStep === 'results' && !empty($aiPackage))
@php
    $pkg    = $aiPackage;
    $total  = $pkg['total']  ?? 0;
    $budget = $pkg['budget'] ?? ($aiBudgetMax ?: $aiBudgetMin ?: 0);
    $pct    = $budget > 0 ? min(100, round($total / $budget * 100)) : 0;
@endphp
<div style="padding-bottom:110px;">

    <div style="text-align:left;margin-bottom:14px;">
        <button wire:click="backToConversation"
                style="display:inline-flex;align-items:center;gap:6px;background:none;border:none;color:var(--primary);font-size:13px;font-weight:600;cursor:pointer;padding:0;">
            <i class="fa-solid fa-arrow-left" style="font-size:11px;"></i> Back to Chat
        </button>
    </div>

    {{-- YOUR REQUEST --}}
    <div style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <div style="width:28px;height:28px;border-radius:50%;background:#F5F0EB;display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-user" style="font-size:11px;color:var(--primary);"></i>
            </div>
            <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--muted);">Your Request</span>
        </div>
        <div style="background:var(--primary);color:#fff;border-radius:12px;padding:16px 22px;font-size:14px;font-weight:600;line-height:1.5;">
            {{ $aiFrom }} to {{ $aiTo }}
            @if ($aiTravelers > 0)
                &nbsp;·&nbsp; {{ $aiTravelers }} {{ \Illuminate\Support\Str::plural('traveler', $aiTravelers) }}
            @endif
            @if ($aiBudgetMin || $aiBudgetMax)
                &nbsp;·&nbsp;
                @if ($aiBudgetMin && $aiBudgetMax && $aiBudgetMin !== $aiBudgetMax)
                    {{ $this->displayAmount($aiBudgetMin) }}–{{ $this->displayAmount($aiBudgetMax) }}
                @else
                    {{ $this->displayAmount($aiBudgetMax ?: $aiBudgetMin) }}
                @endif
            @endif
            @if ($aiDateFrom && $aiDateTo)
                &nbsp;·&nbsp; {{ $aiDateFrom }} – {{ $aiDateTo }}
            @endif
        </div>
    </div>

    {{-- Heading --}}
    <div style="margin:0 0 20px;">
        <h2 style="font-size:20px;font-weight:800;color:var(--dark);margin:0;display:flex;align-items:center;gap:10px;">
            <span style="width:28px;height:28px;background:#F5F0EB;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:14px;color:var(--primary);flex-shrink:0;">✦</span>
            Your trip package for {{ $aiTo }}
        </h2>
    </div>

    {{-- Package cards --}}
    <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:32px;">

        @php
        $cards = [
            ['key'=>'transport',     'label'=>'Transportation', 'icon'=>'fa-solid fa-plane',    'name_field'=>null,    'name_fallback'=>($pkg['transport']['from_code'] ?? 'MNL').' → '.($pkg['transport']['to_code'] ?? '')],
            ['key'=>'accommodation', 'label'=>'Accommodation',  'icon'=>'fa-solid fa-bed',      'name_field'=>'name',  'name_fallback'=>''],
            ['key'=>'food',          'label'=>'Food & Dining',  'icon'=>'fa-solid fa-utensils', 'name_field'=>'name',  'name_fallback'=>''],
            ['key'=>'attractions',   'label'=>'Attractions',    'icon'=>'fa-solid fa-building-columns', 'name_field'=>null, 'name_fallback'=>''],
        ];
        @endphp

        @foreach ($cards as $card)
        @php $sec = $pkg[$card['key']] ?? []; @endphp
        @if (!empty($sec))
        <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:14px;padding:20px 22px;display:flex;align-items:flex-start;gap:16px;">

            {{-- Icon --}}
            <div style="width:44px;height:44px;border-radius:10px;background:#F5F0EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">
                <i class="{{ $card['icon'] }}" style="color:var(--primary);font-size:17px;"></i>
            </div>

            {{-- Content --}}
            <div style="flex:1;min-width:0;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--muted);margin-bottom:5px;">{{ $card['label'] }}</div>

                @if ($card['key'] === 'transport')
                    <div style="font-size:16px;font-weight:800;color:var(--dark);margin-bottom:4px;">
                        {{ $sec['from_code'] ?? 'MNL' }} → {{ $sec['to_code'] ?? '' }}
                    </div>
                    <div style="font-size:12px;color:var(--muted);">{{ $sec['detail'] ?? '' }}</div>

                @elseif ($card['key'] === 'accommodation')
                    <div style="font-size:16px;font-weight:800;color:var(--dark);margin-bottom:4px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        {{ $sec['name'] ?? '' }}
                        @if (!empty($sec['stars']))
                            <span>@for ($s=0;$s<$sec['stars'];$s++)<i class="fa-solid fa-star" style="font-size:10px;color:#F59E0B;"></i>@endfor</span>
                        @endif
                    </div>
                    <div style="font-size:12px;color:var(--muted);">{{ $sec['detail'] ?? '' }}</div>

                @elseif ($card['key'] === 'food')
                    <div style="font-size:16px;font-weight:800;color:var(--dark);margin-bottom:4px;">{{ $sec['name'] ?? '' }}</div>
                    <div style="font-size:12px;color:var(--muted);">{{ $sec['detail'] ?? '' }}</div>

                @elseif ($card['key'] === 'attractions')
                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:2px;">
                        @foreach ($sec['items'] ?? [] as $att)
                        <span style="display:inline-flex;align-items:center;background:#F5F0EB;border-radius:20px;padding:4px 12px;font-size:12px;font-weight:600;color:var(--primary);">
                            {{ $att[0] ?? '' }} ({{ $att[1] ?? 'Free' }})
                        </span>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Cost --}}
            <div style="text-align:right;flex-shrink:0;min-width:80px;">
                <button wire:click="editWithWizard('{{ $card['key'] }}')" wire:loading.attr="disabled" wire:target="editWithWizard"
                        title="Edit {{ $card['label'] }}"
                        style="background:none;border:none;padding:0;margin:0 0 4px;cursor:pointer;font-size:12px;font-weight:600;color:var(--primary);"
                        onmouseenter="this.style.textDecoration='underline'" onmouseleave="this.style.textDecoration='none'">
                    Edit
                </button>
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--muted);margin-bottom:4px;">Est. Cost</div>
                <div style="font-size:18px;font-weight:800;color:var(--dark);">{{ $this->displayAmount($sec['cost'] ?? 0) }}</div>
            </div>

        </div>
        @endif
        @endforeach

    </div>
</div>

{{-- ── Bottom bar ── --}}
<div class="fixed-bottom-bar" style="position:fixed;bottom:0;right:0;background:var(--bg-white);border-top:1.5px solid var(--border);padding:14px 28px;display:flex;align-items:center;gap:20px;z-index:100;transition:left .2s ease;">
    <div style="flex:1;min-width:0;">
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:var(--muted);margin-bottom:5px;">Estimated Cost (Total)</div>
        <div style="font-size:18px;font-weight:800;color:var(--dark);margin-bottom:6px;">
            {{ $this->displayAmount($total) }} of {{ $this->displayAmount($budget) }} budget
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="flex:1;height:6px;background:var(--border-light);border-radius:99px;overflow:hidden;">
                <div style="height:100%;background:{{ $pct >= 100 ? 'var(--danger)' : 'var(--primary)' }};border-radius:99px;width:{{ $pct }}%;"></div>
            </div>
            <span style="font-size:12px;font-weight:600;color:var(--muted);">{{ $pct }}%</span>
        </div>
    </div>
    <button wire:click="regeneratePackage" wire:loading.attr="disabled" wire:target="regeneratePackage"
            style="background:var(--bg-white);border:1.5px solid var(--border);color:var(--dark);border-radius:10px;padding:11px 20px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:8px;"
            onmouseenter="this.style.background='var(--border-light)'"
            onmouseleave="this.style.background='var(--bg-white)'">
        <span wire:loading.remove wire:target="regeneratePackage"><i class="fa-solid fa-rotate"></i> Regenerate</span>
        <span wire:loading wire:target="regeneratePackage"><i class="fa-solid fa-spinner fa-spin"></i> Regenerating…</span>
    </button>
    <button wire:click="proceedToWizardItinerary" wire:loading.attr="disabled" wire:target="proceedToWizardItinerary"
            style="background:var(--primary);color:#fff;border:none;border-radius:10px;padding:11px 28px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;"
            onmouseenter="this.style.background='var(--primary-dark)'"
            onmouseleave="this.style.background='var(--primary)'">
        <span wire:loading.remove wire:target="proceedToWizardItinerary">Next</span>
        <span wire:loading wire:target="proceedToWizardItinerary"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</span>
    </button>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════
     AI PLANNER — loading / building package
═══════════════════════════════════════════════════════════════ --}}
@if ($aiStep === 'loading')
<style>
    @keyframes aiSpin{to{transform:rotate(360deg)}}
    @keyframes llmShimmer{0%{background-position:-400px 0;}100%{background-position:400px 0;}}
    @keyframes llmTypingDot{0%,60%,100%{opacity:.25;transform:translateY(0);}30%{opacity:1;transform:translateY(-3px);}}
    .llm-skel{background:linear-gradient(90deg,#F0EBE4 0%,#FAF7F3 50%,#F0EBE4 100%);background-size:800px 100%;animation:llmShimmer 1.4s ease-in-out infinite;border-radius:10px;}
</style>
<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:70vh;text-align:center;padding:40px 24px;">
    <svg style="width:44px;height:44px;animation:aiSpin 1s linear infinite;margin-bottom:20px;" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round">
        <path d="M12 2a10 10 0 1 0 10 10" />
    </svg>
    <h2 style="font-size:20px;font-weight:800;color:var(--dark);margin:0 0 8px;">Building your trip package</h2>
    <p style="font-size:14px;color:var(--muted);margin:0 0 6px;display:flex;align-items:center;gap:6px;">
        TARA is thinking
        <span style="display:inline-flex;gap:3px;">
            <span style="width:5px;height:5px;border-radius:50%;background:var(--primary);display:inline-block;animation:llmTypingDot 1.2s ease-in-out infinite;animation-delay:0s;"></span>
            <span style="width:5px;height:5px;border-radius:50%;background:var(--primary);display:inline-block;animation:llmTypingDot 1.2s ease-in-out infinite;animation-delay:.2s;"></span>
            <span style="width:5px;height:5px;border-radius:50%;background:var(--primary);display:inline-block;animation:llmTypingDot 1.2s ease-in-out infinite;animation-delay:.4s;"></span>
        </span>
    </p>

    {{-- Skeleton preview of the results cards about to appear --}}
    <div style="width:100%;max-width:520px;margin-top:28px;display:flex;flex-direction:column;gap:12px;">
        @for ($i = 0; $i < 4; $i++)
        <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:14px;padding:20px 22px;display:flex;align-items:center;gap:16px;">
            <div class="llm-skel" style="width:44px;height:44px;border-radius:10px;flex-shrink:0;"></div>
            <div style="flex:1;display:flex;flex-direction:column;gap:8px;">
                <div class="llm-skel" style="height:10px;width:35%;"></div>
                <div class="llm-skel" style="height:14px;width:65%;"></div>
            </div>
            <div class="llm-skel" style="width:60px;height:16px;flex-shrink:0;"></div>
        </div>
        @endfor
    </div>
</div>
@script
<script>
    setTimeout(() => $wire.call('showResults'), 3000);
</script>
@endscript
@endif

{{-- ═══════════════════════════════════════════════════════════════
     AI PLANNER — landing / prompt input
═══════════════════════════════════════════════════════════════ --}}
@if ($aiStep === '')
@php
    $greeting = 'Good day, ' . (auth()->user()->first_name ?? '') . '!';
    $hour = now()->hour;
    $unusedGreeting = match(true) {
        $hour >= 5 && $hour < 12  => 'Good morning',
        $hour >= 12 && $hour < 17 => 'Good afternoon',
        $hour >= 17 && $hour < 21 => 'Good evening',
        default                   => 'Hello, night owl',
    };
@endphp
<div style="min-height:calc(100vh - 80px);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px 24px 60px;position:relative;">

    <style>
        @keyframes llmFadeUp{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}
        @keyframes llmMsgIn{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:translateY(0);}}
        .llm-fade-up{animation:llmFadeUp .5s ease both;}
        .llm-back-link{display:inline-flex;align-items:center;gap:6px;color:var(--primary);font-size:13px;font-weight:600;text-decoration:none;}
        .llm-greeting{font-size:clamp(24px,3.2vw,32px);font-weight:600;color:var(--dark);margin:0 0 16px;font-family:Georgia,'Times New Roman',serif;letter-spacing:-.01em;}
        .llm-interest-pill{display:inline-flex;align-items:center;gap:6px;background:var(--primary-light);border:1.5px solid transparent;color:var(--primary);border-radius:999px;padding:6px 15px;font-size:12px;font-weight:700;transition:border-color .18s,transform .18s;}
        .llm-interest-pill:hover{border-color:var(--primary);transform:translateY(-1px);}
        .llm-interest-edit{color:var(--primary);font-size:12px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
        .llm-interest-edit:hover{text-decoration:underline;}
        .llm-composer{width:100%;max-width:520px;margin:0 auto;background:var(--bg-white);border:1.5px solid var(--border);border-radius:999px;padding:8px 8px 8px 22px;display:flex;align-items:center;gap:10px;transition:border-color .2s ease;}
        .llm-composer:focus-within{border-color:var(--primary);}
        .llm-composer textarea{flex:1;min-width:0;border:none;outline:none;resize:none;font-family:inherit;font-size:14px;color:var(--dark);background:transparent;line-height:1.4;max-height:100px;padding:8px 0;overflow:hidden;}
        .llm-send-btn{width:38px;height:38px;border-radius:50%;background:var(--primary);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:transform .15s ease,background .15s ease;}
        .llm-send-btn:hover{background:var(--primary-dark);transform:scale(1.08);}
        .llm-send-btn:active{transform:scale(.92);}
        .llm-thread{width:100%;max-width:600px;margin:0 auto;flex:1;overflow-y:auto;padding:80px 4px 16px;display:flex;flex-direction:column;gap:12px;}
        .llm-msg-row{display:flex;align-items:flex-end;gap:8px;max-width:100%;}
        .llm-msg-row-user{align-self:flex-end;flex-direction:row-reverse;}
        .llm-msg-row-assistant{align-self:flex-start;}
        .llm-avatar{width:28px;height:28px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;}
        .llm-avatar-img{object-fit:cover;}
        .llm-avatar-user{background:var(--primary);color:#fff;}
        .llm-avatar-ai{background:linear-gradient(135deg,var(--primary),#C8874A);color:#fff;font-size:14px;}
        .llm-msg{max-width:calc(100% - 36px);padding:11px 16px;border-radius:16px;font-size:14px;line-height:1.5;animation:llmMsgIn .3s ease both;}
        .llm-msg-user{background:var(--primary);color:#fff;border-bottom-right-radius:4px;}
        .llm-msg-assistant{background:var(--bg-white);border:1.5px solid var(--border);color:var(--dark);border-bottom-left-radius:4px;}
        @media (max-width:640px){
            .llm-composer{padding:6px 6px 6px 16px;}
        }
    </style>

    @if (empty($messages))
        {{-- First-time welcome state: greeting + composer + quick pills --}}
        <div class="llm-fade-up" style="text-align:center;width:100%;max-width:600px;">

            <h1 class="llm-greeting">✦ {{ $greeting }}</h1>
            <p style="font-size:14px;color:var(--muted);margin:0 0 20px;">Plan your trip with TARA</p>

            @if (!empty($this->profileInterests))
                <div style="display:flex;flex-wrap:wrap;gap:9px;justify-content:center;align-items:center;margin-bottom:28px;">
                    @foreach (array_slice($this->profileInterests, 0, 4) as $interest)
                        <span class="llm-interest-pill"><i class="fa-solid fa-tag" style="font-size:9px;"></i>{{ $interest }}</span>
                    @endforeach
                    <a href="{{ route('profile.setup', ['step' => 4, 'return' => 'trips.plan.ai']) }}" wire:navigate class="llm-interest-edit"><i class="fa-regular fa-pen-to-square" style="font-size:11px;"></i>Edit</a>
                </div>
            @else
                <div style="margin-bottom:28px;">
                    <a href="{{ route('profile.setup', ['step' => 4, 'return' => 'trips.plan.ai']) }}" wire:navigate class="llm-interest-edit">+ Add your travel interests</a>
                </div>
            @endif

            <div class="llm-composer">
                <style>#llm-ai-prompt::placeholder{color:#B7A99B;opacity:1;}</style>
                <textarea id="llm-ai-prompt" wire:model="aiPrompt"
                          placeholder="Describe your trip details"
                          rows="1"
                          x-data
                          x-on:input="$el.style.height='auto';$el.style.height=$el.scrollHeight+'px'"
                          x-on:keydown.enter.prevent="if (!$event.shiftKey) $wire.automateTrip()"></textarea>
                <button type="button" wire:click="automateTrip" wire:loading.attr="disabled" wire:target="automateTrip" class="llm-send-btn" aria-label="Send">
                    <span wire:loading.remove wire:target="automateTrip"><i class="fa-solid fa-paper-plane" style="font-size:13px;"></i></span>
                    <span wire:loading wire:target="automateTrip"><i class="fa-solid fa-spinner fa-spin" style="font-size:13px;"></i></span>
                </button>
            </div>

        </div>
    @else
        {{-- Conversation is underway: chat thread + composer pinned below --}}
        <div style="width:100%;height:calc(100vh - 80px);display:flex;flex-direction:column;align-items:center;">

            @php
                $llmUserInitials = collect(explode(' ', auth()->user()->full_name ?? ''))
                    ->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
            @endphp
            <div id="llm-thread" class="llm-thread">
                @foreach ($messages as $msg)
                    <div class="llm-msg-row llm-msg-row-{{ $msg['role'] }}">
                        @if ($msg['role'] === 'user')
                            @if (auth()->user()->profile_photo)
                            <img src="{{ Illuminate\Support\Facades\Storage::url(auth()->user()->profile_photo) }}" alt="You" class="llm-avatar llm-avatar-img">
                            @else
                            <div class="llm-avatar llm-avatar-user">{{ strtoupper($llmUserInitials) }}</div>
                            @endif
                        @else
                        <div class="llm-avatar llm-avatar-ai"><i class="fa-regular fa-paper-plane"></i></div>
                        @endif
                        <div class="llm-msg llm-msg-{{ $msg['role'] }}">{{ $msg['text'] }}</div>
                    </div>
                @endforeach
            </div>

            <div class="llm-composer" style="margin-top:auto;">
                <style>#llm-ai-prompt::placeholder{color:#B7A99B;opacity:1;}</style>
                <textarea id="llm-ai-prompt" wire:model="aiPrompt"
                          placeholder="Type your reply"
                          rows="1"
                          x-data
                          x-on:input="$el.style.height='auto';$el.style.height=$el.scrollHeight+'px'"
                          x-on:keydown.enter.prevent="if (!$event.shiftKey) $wire.automateTrip()"></textarea>
                <button type="button" wire:click="automateTrip" wire:loading.attr="disabled" wire:target="automateTrip" class="llm-send-btn" aria-label="Send">
                    <span wire:loading.remove wire:target="automateTrip"><i class="fa-solid fa-paper-plane" style="font-size:13px;"></i></span>
                    <span wire:loading wire:target="automateTrip"><i class="fa-solid fa-spinner fa-spin" style="font-size:13px;"></i></span>
                </button>
            </div>

        </div>
    @endif
</div>
@script
<script>
    const scrollThread = () => {
        const el = document.getElementById('llm-thread');
        if (el) el.scrollTop = el.scrollHeight;
    };
    $wire.on('message-added', () => $nextTick(() => scrollThread()));
    scrollThread();
</script>
@endscript
@endif

{{-- History panel — view-only browsing of past conversations. Self-
     contained styles (not reusing .llm-msg etc.) since this can be opened
     from any aiStep, including ones whose own <style> block never rendered. --}}
@if ($showHistory)
<div style="position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);z-index:2000;display:flex;align-items:center;justify-content:center;padding:20px;" wire:click.self="closeHistory">
    <div style="background:var(--bg-white);border:1.5px solid var(--border);border-radius:18px;width:100%;max-width:480px;max-height:80vh;display:flex;flex-direction:column;overflow:hidden;">

        @php $entry = $this->viewingHistoryEntry; @endphp

        <div style="padding:18px 20px;border-bottom:1.5px solid var(--border);display:flex;align-items:center;gap:10px;">
            @if ($entry)
            <button type="button" wire:click="backToHistoryList" title="Back to list"
                    style="background:none;border:none;color:var(--primary);cursor:pointer;padding:4px;display:flex;">
                <i class="fa-solid fa-arrow-left" style="font-size:14px;"></i>
            </button>
            @endif
            <div style="font-size:15px;font-weight:700;color:var(--dark);flex:1;">
                {{ $entry ? ($entry->ai_from . ' to ' . $entry->ai_to) : 'Past Conversations' }}
            </div>
        </div>

        <div style="overflow-y:auto;padding:16px 20px;flex:1;">
            @if ($entry)
                {{-- Read-only transcript of one past conversation --}}
                <div style="display:flex;flex-direction:column;gap:10px;">
                    @foreach ($entry->messages as $msg)
                    <div style="max-width:85%;padding:10px 14px;border-radius:14px;font-size:13px;line-height:1.5;
                                {{ $msg['role'] === 'user'
                                    ? 'align-self:flex-end;background:var(--primary);color:#fff;border-bottom-right-radius:4px;'
                                    : 'align-self:flex-start;background:var(--bg);color:var(--dark);border-bottom-left-radius:4px;' }}">
                        {{ $msg['text'] }}
                    </div>
                    @endforeach
                </div>
                @if ($entry->ai_date_from && $entry->ai_date_to)
                <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border-light);font-size:12px;color:var(--muted);">
                    <i class="fa-regular fa-calendar" style="margin-right:5px;"></i>{{ $entry->ai_date_from }} – {{ $entry->ai_date_to }}
                    @if ($entry->ai_budget_min || $entry->ai_budget_max)
                        &nbsp;·&nbsp;{{ $this->displayAmount($entry->ai_budget_max ?: $entry->ai_budget_min, $entry->ai_currency) }}
                    @endif
                </div>
                @endif
            @else
                {{-- List of past conversations --}}
                @if ($this->conversationHistory->isEmpty())
                <div style="text-align:center;padding:40px 20px;color:var(--muted);font-size:13px;">
                    <i class="fa-regular fa-comments" style="font-size:28px;display:block;margin-bottom:10px;color:var(--border);"></i>
                    No past conversations yet, once you finish planning a trip with TARA, it'll show up here.
                </div>
                @else
                <div style="display:flex;flex-direction:column;gap:8px;">
                    @foreach ($this->conversationHistory as $past)
                    <div style="background:var(--bg);border:1.5px solid transparent;border-radius:12px;transition:border-color .15s ease;"
                         onmouseenter="this.style.borderColor='var(--primary)'" onmouseleave="this.style.borderColor='transparent'">
                        @if ($historyEntryToDelete === $past->id)
                        {{-- Inline confirm — kept in the same row instead of stacking a
                             second modal on top of the one already open. --}}
                        <div style="padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;">
                            <span style="font-size:12px;font-weight:600;color:var(--dark);">Delete this conversation?</span>
                            <div style="display:flex;gap:6px;flex-shrink:0;">
                                <button type="button" wire:click="cancelDeleteHistoryEntry"
                                        style="background:var(--bg-white);border:1px solid var(--border);color:var(--muted);border-radius:6px;padding:5px 10px;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;">
                                    No
                                </button>
                                <button type="button" wire:click="deleteHistoryEntry"
                                        style="background:#B91C1C;border:none;color:#fff;border-radius:6px;padding:5px 10px;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;">
                                    Yes, delete
                                </button>
                            </div>
                        </div>
                        @else
                        <div style="display:flex;align-items:stretch;">
                            <button type="button" wire:click="viewHistoryEntry({{ $past->id }})"
                                    style="text-align:left;flex:1;min-width:0;background:none;border:none;padding:12px 14px;cursor:pointer;font-family:inherit;">
                                <div style="font-size:13px;font-weight:700;color:var(--dark);margin-bottom:3px;">
                                    {{ $past->ai_from ?: '—' }} to {{ $past->ai_to ?: '—' }}
                                </div>
                                <div style="font-size:11px;color:var(--muted);">
                                    {{ $past->created_at->format('M j, Y · g:i A') }}
                                    @if ($past->ai_date_from && $past->ai_date_to)
                                        &nbsp;·&nbsp;{{ $past->ai_date_from }} – {{ $past->ai_date_to }}
                                    @endif
                                </div>
                            </button>
                            <button type="button" wire:click="confirmDeleteHistoryEntry({{ $past->id }})" title="Delete this conversation"
                                    style="flex-shrink:0;width:38px;background:none;border:none;color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;font-family:inherit;"
                                    onmouseenter="this.style.color='#B91C1C'" onmouseleave="this.style.color='var(--muted)'">
                                <i class="fa-solid fa-trash" style="font-size:13px;"></i>
                            </button>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif
            @endif
        </div>

        <div style="padding:14px 20px;border-top:1.5px solid var(--border);display:flex;justify-content:flex-end;">
            <button type="button" wire:click="closeHistory"
                    style="background:var(--primary);color:#fff;border:none;border-radius:10px;padding:9px 22px;font-size:13px;font-weight:700;cursor:pointer;">
                Close
            </button>
        </div>
    </div>
</div>
@endif

</div>
