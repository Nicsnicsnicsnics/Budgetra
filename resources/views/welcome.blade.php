<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budgetra — Plan Smart. Travel More.</title>
    <link rel="icon" type="image/png" href="{{ asset('systemicons/budgetraicon-modified.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>

{{-- ── Navbar ───────────────────────────────────────────── --}}
<nav class="site-navbar">
    <div class="container-nav">
        <div class="nav-brand">
            <img src="{{ asset('systemicons/budgetraicon-modified.png') }}" alt="Budgetra" style="height:56px;width:auto;">
        </div>
        <div class="nav-actions" style="margin-left:auto;">
            <a href="{{ route('login') }}" class="btn btn-sm" style="background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.4);backdrop-filter:blur(6px);">Login</a>
        </div>
    </div>
</nav>

{{-- ── Hero (full-bleed video) ──────────────────────────── --}}
<section class="hero-fullbleed">
    <video class="hero-video" autoplay muted loop playsinline>
        <source src="{{ asset('stockvideos/hero-fullbleed.mp4') }}" type="video/mp4">
    </video>
    <div class="hero-video-overlay"></div>
    <div class="hero-video-content">
        <div class="hero-badge" style="background:rgba(255,255,255,.18);color:#fff;backdrop-filter:blur(6px);">
            <i class="fa-solid fa-location-dot"></i> Next-Gen Travel Finance
        </div>
        <h1 class="hero-title" style="color:#fff;text-shadow:0 2px 20px rgba(0,0,0,.4);">Smart Vacation Budget Planning and Expense Management System</h1>
        <p class="hero-subtitle" style="color:rgba(255,255,255,.85);text-shadow:0 1px 8px rgba(0,0,0,.3);">Plan and manage travel expenses before, during, and after your trip using real-time API data and OCR receipt scanning.</p>
        <div class="hero-actions">
            <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Get Started for Free</a>
            <a href="{{ route('features') }}" class="btn btn-lg" style="background:rgba(255,255,255,.15);color:#fff;border:2px solid rgba(255,255,255,.4);backdrop-filter:blur(6px);">
                <i class="fa-regular fa-circle-play"></i> How it works
            </a>
        </div>
    </div>
</section>

{{-- ── Features ─────────────────────────────────────────── --}}
<section class="features-section" id="features">
    <video class="features-video-bg" autoplay muted loop playsinline>
        <source src="{{ asset('stockvideos/page-background-fullbleed.mp4') }}" type="video/mp4">
    </video>
    <div class="features-video-overlay"></div>
    <div class="features-section-inner">
    <div class="section-header">
        <h2 class="section-title" style="color:#fff;text-shadow:0 2px 12px rgba(0,0,0,.4);">Engineered for the Modern Explorer</h2>
        <p class="section-subtitle" style="color:rgba(255,255,255,.85);">Stop worrying about the math and start focusing on the memories. Budgetra gives you the tools to stay financially focused without the friction.</p>
    </div>

    <div class="bento-grid">

        {{-- Top left: Vacation Budget Planner --}}
        <div class="bento-card bento-card-white" style="padding:32px;">
            <div class="bento-icon bento-icon-light">
                <i class="fa-regular fa-calendar"></i>
            </div>
            <h3 style="color:#fff;">Vacation Budget Planner</h3>
            <p style="font-size:14px;color:#fff;margin:8px 0 20px;">Comprehensive planning suite that syncs with your travel calendar to predict daily spend.</p>

            {{-- Budget overview mini-card --}}
            <div style="background:#fff;border:1px solid var(--border);border-radius:12px;padding:16px;text-shadow:none;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                    <span style="font-size:13px;font-weight:600;color:#1A1A2E;text-shadow:none;">Budget Overview</span>
                    <span style="font-size:12px;color:var(--muted);text-shadow:none;">14 Days</span>
                </div>
                <div style="display:flex;align-items:center;gap:16px;">
                    {{-- Donut chart --}}
                    <svg width="56" height="56" viewBox="0 0 56 56" style="flex-shrink:0;">
                        <circle cx="28" cy="28" r="20" fill="none" stroke="#E5E7EB" stroke-width="9"/>
                        <circle cx="28" cy="28" r="20" fill="none" stroke="var(--primary)" stroke-width="9"
                                stroke-dasharray="44 82" stroke-linecap="round"
                                transform="rotate(-90 28 28)"/>
                        <circle cx="28" cy="28" r="20" fill="none" stroke="var(--tertiary)" stroke-width="9"
                                stroke-dasharray="28 98" stroke-dashoffset="-44"
                                stroke-linecap="round"
                                transform="rotate(-90 28 28)"/>
                    </svg>
                    <div style="flex:1;">
                        <div class="expense-item" style="padding:6px 0;">
                            <span style="font-size:13px;color:var(--text);flex:1;">Dining</span>
                            <span style="font-size:13px;font-weight:600;color:var(--primary);">$650</span>
                        </div>
                        <div class="expense-item" style="padding:6px 0;">
                            <span style="font-size:13px;color:var(--text);flex:1;">Transport</span>
                            <span style="font-size:13px;font-weight:600;color:var(--primary);">$430</span>
                        </div>
                        <div style="display:flex;align-items:center;padding:6px 0;">
                            <span style="font-size:13px;color:var(--text);flex:1;">Stay</span>
                            <span style="font-size:13px;font-weight:600;color:var(--muted);">$380</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Top right: Trip Cost Estimator --}}
        <div class="bento-card bento-card-blue" style="padding:32px;">
            <div class="bento-icon bento-icon-white">
                <i class="fa-solid fa-wave-square"></i>
            </div>
            <h3 style="color:#fff;">Trip Cost Estimator</h3>
            <p style="font-size:14px;color:rgba(255,255,255,0.8);margin:8px 0 24px;">Get precise estimates for any destination using real-time local cost data APIs.</p>
            <div class="bento-mini-card" style="display:block;width:100%;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span class="dest">Tokyo, Japan</span>
                    <span style="font-size:12px;color:rgba(255,255,255,0.7);">7 Days</span>
                </div>
                <div class="bar-wrap">
                    <div class="bar"><div class="bar-fill" style="width:72%;"></div></div>
                </div>
                <div class="price">$2,450 Est.</div>
            </div>
        </div>

        {{-- Bottom row: 3 cards --}}
        <div class="bento-row-bottom">

            {{-- Expense Tracking --}}
            <div class="bento-card bento-card-white" style="padding:32px;">
                <div class="bento-icon bento-icon-light">
                    <i class="fa-solid fa-credit-card"></i>
                </div>
                <h3>Expense Tracking</h3>
                <p style="font-size:14px;color:#fff;margin:8px 0 20px;">Real-time currency conversion and categorization for every transaction abroad.</p>
                <div>
                    <div class="expense-item">
                        <div class="expense-avatar" style="background:var(--primary-light);color:var(--primary);">D</div>
                        <span class="expense-name">Dinner at Le Marais</span>
                        <span class="expense-amt">-$84.00</span>
                    </div>
                    <div class="expense-item">
                        <div class="expense-avatar" style="background:var(--tertiary-light);color:var(--tertiary);">T</div>
                        <span class="expense-name">Taxi to Louvre</span>
                        <span class="expense-amt">-$12.50</span>
                    </div>
                </div>
            </div>

            {{-- Savings Goal Calculator --}}
            <div class="bento-card bento-card-orange" style="padding:32px;">
                <div class="bento-icon bento-icon-white">
                    <i class="fa-solid fa-piggy-bank"></i>
                </div>
                <h3 style="color:#fff;">Savings Goal Calculator</h3>
                <p style="font-size:14px;color:rgba(255,255,255,0.85);margin:8px 0 0;">Calculate how much you need to save daily to reach your dream destination.</p>
                <div class="savings-big">$15.20 <span style="font-size:20px;font-weight:500;">/day</span></div>
                <div class="savings-label">Remaining for Swiss Alps</div>
            </div>

            {{-- OCR Scanning --}}
            <div class="bento-card bento-card-yellow" style="padding:32px;">
                <div class="bento-icon bento-icon-dark">
                    <i class="fa-solid fa-file-invoice"></i>
                </div>
                <h3>OCR Scanning</h3>
                <p style="font-size:14px;color:#fff;margin:8px 0 20px;">Instantly scan and parse receipts from anywhere in the world – no manual entry.</p>
                {{-- Receipt skeleton preview --}}
                <div style="background:rgba(255,255,255,0.5);border-radius:10px;padding:14px;">
                    <div style="height:8px;background:rgba(0,0,0,0.15);border-radius:4px;margin-bottom:8px;width:80%;"></div>
                    <div style="height:8px;background:rgba(0,0,0,0.10);border-radius:4px;margin-bottom:8px;width:60%;"></div>
                    <div style="height:8px;background:rgba(0,0,0,0.10);border-radius:4px;width:70%;"></div>
                </div>
            </div>

        </div>{{-- end bento-row-bottom --}}

    </div>{{-- end bento-grid --}}
    </div>{{-- end features-section-inner --}}
</section>

{{-- ── CTA + Footer (shared video background) ─────────────── --}}
<div class="cta-footer-wrap">
    <video class="features-video-bg" autoplay muted loop playsinline>
        <source src="{{ asset('stockvideos/hero-fullbleed.mp4') }}" type="video/mp4">
    </video>
    <div class="features-video-overlay" style="background:rgba(0,0,0,.5);"></div>

    <section class="cta-section">
        <div class="cta-card-glass">
            <h2 style="color:#fff;font-size:clamp(28px,4vw,48px);font-weight:800;text-shadow:0 2px 20px rgba(0,0,0,.5);margin-bottom:16px;">Ready to travel without financial stress?</h2>
            <p style="color:rgba(255,255,255,.85);font-size:17px;line-height:1.7;max-width:520px;margin:0 auto 32px;text-shadow:0 1px 8px rgba(0,0,0,.4);">Join 50,000+ travelers who use Budgetra to keep their adventures on track and under budget.</p>
            <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Get Started for Free</a>
        </div>
    </section>

    <footer class="site-footer" style="background:transparent;border-top:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.7);">
        © {{ date('Y') }} Budgetra. Smart travel, smarter spending.
    </footer>
</div>

@if (!empty($scrollToFeatures))
<script>
    document.getElementById('features').scrollIntoView({ behavior: 'smooth' });
</script>
@endif
</body>
</html>
