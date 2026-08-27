<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="220" alt="Laravel">
</p>

<h1 align="center">🧭 Budgetra</h1>

<p align="center">
  <b>Plan a trip. See the real cost before you book it.</b><br>
  A Laravel + Livewire travel-budgeting app for planning local and international trips against a real budget.
</p>

<p align="center">
  <img alt="Laravel" src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white">
  <img alt="Livewire" src="https://img.shields.io/badge/Livewire-4-4E56A6?logo=livewire&logoColor=white">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white">
  <img alt="PostgreSQL" src="https://img.shields.io/badge/PostgreSQL-Supabase-3ECF8E?logo=supabase&logoColor=white">
</p>

---

## ✈️ What it does

Budgetra walks a traveler through planning a trip — flights, accommodation, food, attractions — against a budget, fills gaps with AI-suggested itineraries, and tracks real spending against the plan once the trip is saved.

| | |
|---|---|
| 🌏 **Local & international trips** | Choose a scope up front — pricing tiers, currency framing, and budget guidance adjust for domestic Philippine travel vs. flying abroad. |
| 🧳 **Guided trip planner wizard** | Step through trip details → flights → accommodation → food & dining → attractions → AI itinerary → cost summary, with a running budget check at every step. |
| 🗺️ **Multi-city trips** | Add a second destination leg with its own flights, stays, food, attractions, dates, and AI itinerary — kept in sync with leg 1's dates, then combined into one summary and PDF. |
| 🤖 **AI-suggested itineraries** | Fills gaps in the plan with day-by-day activities, generated through a fallback chain of providers (Gemini → Groq → Cerebras → Mistral → OpenRouter) under a shared time budget so one slow provider never blocks the rest. |
| 💰 **Saved Trips & Savings Goals** | Every trip gets tracked by status (draft / upcoming / active / past) with a matching savings goal and real spend vs. planned budget. |
| 🧾 **Expense tracking** | Log expenses against a trip manually or via OCR receipt scanning. |
| 📄 **PDF export** | Download a full trip summary — route, dates, selections, itinerary, cost breakdown — as a PDF. |
| 👤 **Profile Builder** | Captures a traveler's interests and budget preferences, used to steer AI suggestions toward what they'd actually enjoy. |

## 🏗️ Tech stack

- **Backend** — Laravel 13, PHP 8.3+
- **Frontend** — Livewire 4 (server-driven components) + Alpine.js for client-side interactivity (dropdowns, calendars, live search)
- **Database** — PostgreSQL, hosted on Supabase
- **File storage** — Supabase Storage (S3-compatible)
- **PDF export** — `barryvdh/laravel-dompdf`
- **External APIs** — SerpApi / Serper (flights, hotels, restaurants, attractions), Gemini / Groq / Cerebras / Mistral / OpenRouter (AI itinerary generation), an OCR API (receipt scanning)

## 📁 Project layout

```
app/Livewire/Traveler/     Livewire components — trip planning, saved trips, savings goals, expenses, itinerary, profile
app/Models/                 Eloquent models (Trip, Itinerary, SavingsGoal, Expense, UserProfile, ...)
app/Services/                External API integrations (AI providers, SerpApi/Serper, OCR, reports)
resources/views/livewire/   Blade views paired with the Livewire components above
resources/views/traveler/   Non-Livewire traveler-facing views (savings index, PDF report templates)
routes/web.php               Application routes
database/migrations/         Schema migrations
```

## 🚀 Setup

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Configure `.env` with your database connection and API keys:

```env
DB_CONNECTION=pgsql
DB_HOST=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SERPAPI_KEY=...
SERPER_KEY=...
GEMINI_API_KEY=...
GROQ_API_KEY=...
CEREBRAS_API_KEY=...
MISTRAL_API_KEY=...
OPENROUTER_API_KEY=...
OCR_API_KEY=...

SUPABASE_STORAGE_KEY=...
SUPABASE_STORAGE_SECRET=...
SUPABASE_STORAGE_BUCKET=...
SUPABASE_STORAGE_ENDPOINT=...
```

Run migrations and start the app:

```bash
php artisan migrate
npm run build   # or `npm run dev` while developing
php artisan serve
```

## 🧠 Good to know

- Livewire components render as large single Blade files with `$step`-driven conditional sections rather than many small partials — see `resources/views/livewire/traveler/trip-planner-wizard.blade.php` for the wizard.
- Local vs. international scope drives budget-tier pricing (`TripPlannerWizard::RATES`) — see `selectScope()` / `tripScope` in `app/Livewire/Traveler/TripPlannerWizard.php`.
- AI provider calls are budgeted against a shared deadline per itinerary-generation request to stay under PHP's execution time limit — see `TripPlannerWizard::suggestItinerary()`.
