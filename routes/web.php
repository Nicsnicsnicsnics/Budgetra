<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth;
use App\Http\Controllers\Traveler;
use App\Http\Controllers\Admin;

Route::get('/', function () {
    return auth()->check() ? redirect('/dashboard') : view('welcome');
});

Route::get('/features', function () {
    return view('welcome', ['scrollToFeatures' => true]);
})->name('features');

// Guest-only routes (redirect to dashboard if already logged in)
Route::middleware('guest')->group(function () {
    Route::get('/login',    [Auth\LoginController::class, 'showForm'])->name('login');
    Route::post('/login',   [Auth\LoginController::class, 'login']);
    Route::get('/register', [Auth\RegisterController::class, 'showForm'])->name('register');
    Route::post('/register',[Auth\RegisterController::class, 'store']);
});

Route::post('/logout', [Auth\LoginController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');

// Authenticated traveler routes
Route::middleware(['auth', 'not-admin'])->group(function () {
    Route::get('/dashboard', [Traveler\DashboardController::class, '__invoke'])->name('dashboard');
    Route::get('/dashboard/report', [Traveler\DashboardController::class, 'downloadReport'])->name('dashboard.report');

    Route::get('/profile',         [Traveler\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile',         [Traveler\ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/setup',   \App\Livewire\Traveler\ProfileBuilder::class)->name('profile.setup');

    Route::get('/settings',              [Traveler\SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings/theme',       [Traveler\SettingsController::class, 'updateTheme'])->name('settings.theme');
    Route::patch('/settings/preferences', [Traveler\SettingsController::class, 'updatePreferences'])->name('settings.preferences');
    Route::patch('/settings/notifications', [Traveler\SettingsController::class, 'updateNotifications'])->name('settings.notifications');

    Route::get('/saved-trips',             \App\Livewire\Traveler\SavedTrips::class)->name('saved-trips');
    Route::get('/trips/import/{code}',    [Traveler\TripImportController::class, 'import'])->name('trips.import');
    Route::get('/trips',                  \App\Livewire\Traveler\TripPlannerWizard::class)->name('trips.index');
    Route::get('/multi-trips',            \App\Livewire\Traveler\MultiTripHub::class)->name('multi-trips.index');
    Route::get('/trips/plan',             \App\Livewire\Traveler\TripPlannerWizard::class)->name('trips.plan');
    Route::get('/trips/plan/ai',          \App\Livewire\Traveler\Llm::class)->name('trips.plan.ai');
    Route::get('/trips/type',             [Traveler\TripController::class, 'type'])->name('trips.type');
    Route::get('/trips/create',           [Traveler\TripController::class, 'create'])->name('trips.create');
    Route::post('/trips',                 [Traveler\TripController::class, 'store'])->name('trips.store');
    Route::get('/trips/{trip}',           [Traveler\TripController::class, 'show'])->name('trips.show');
    Route::get('/trips/{trip}/edit',      [Traveler\TripController::class, 'edit'])->name('trips.edit');
    Route::put('/trips/{trip}',           [Traveler\TripController::class, 'update'])->name('trips.update');
    Route::delete('/trips/{trip}',        [Traveler\TripController::class, 'destroy'])->name('trips.destroy');
    Route::get('/trips/{trip}/budget',    [Traveler\TripController::class, 'budget'])->name('trips.budget');
    Route::get('/trips/{trip}/dashboard', \App\Livewire\Traveler\TripDashboard::class)->name('trips.dashboard');
    Route::post('/trips/{trip}/budget',   [Traveler\TripController::class, 'budgetStore'])->name('trips.budgetStore');
    Route::get('/trips/{trip}/estimate',  [Traveler\TripController::class, 'estimate'])->name('trips.estimate');
    Route::post('/trips/{trip}/estimate', [Traveler\TripController::class, 'applyEstimates'])->name('trips.applyEstimates');

    Route::get('/expenses',              [Traveler\ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('/expenses/create',       [Traveler\ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('/expenses',             [Traveler\ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('/expenses/{expense}/edit',   [Traveler\ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::put('/expenses/{expense}',        [Traveler\ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('/expenses/{expense}',     [Traveler\ExpenseController::class, 'destroy'])->name('expenses.destroy');
    Route::post('/expenses/ocr',             [Traveler\ExpenseController::class, 'ocr'])->name('expenses.ocr');

    Route::get('/attractions',              [Traveler\AttractionController::class, 'index'])->name('attractions.index');
    Route::get('/attractions/{attraction}',[Traveler\AttractionController::class, 'show'])->name('attractions.show');
    Route::get('/destinations',              [Traveler\DestinationController::class, 'index'])->name('destinations.index');
    Route::get('/destinations/{destination}',[Traveler\DestinationController::class, 'show'])->name('destinations.show');
    Route::get('/compare',                   [Traveler\ComparisonController::class, 'index'])->name('compare.index');

    Route::get('/itinerary',             [Traveler\ItineraryController::class, 'index'])->name('itinerary.index');
    Route::get('/moments',               [Traveler\MomentController::class, 'index'])->name('moments.index');
    Route::post('/itinerary',            [Traveler\ItineraryController::class, 'store'])->name('itinerary.store');
    Route::delete('/itinerary/{item}',   [Traveler\ItineraryController::class, 'destroy'])->name('itinerary.destroy');

    Route::get('/reviews',               [Traveler\ReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews',              [Traveler\ReviewController::class, 'store'])->name('reviews.store');
    Route::put('/reviews/{review}',      [Traveler\ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}',   [Traveler\ReviewController::class, 'destroy'])->name('reviews.destroy');
    Route::post('/reviews/{review}/flag', [Traveler\ReviewController::class, 'flag'])->name('reviews.flag');
    Route::post('/reviews/{review}/helpful', [Traveler\ReviewController::class, 'markHelpful'])->name('reviews.helpful');

    Route::get('/alerts',                [Traveler\AlertController::class, 'index'])->name('alerts.index');
    Route::patch('/alerts/read-all',             [Traveler\AlertController::class, 'markAllRead'])->name('alerts.read-all');
    Route::patch('/alerts/{notification}/read', [Traveler\AlertController::class, 'markRead'])->name('alerts.read');

    Route::get('/reports',               [Traveler\ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/download',      [Traveler\ReportController::class, 'download'])->name('reports.download');

    Route::get('/savings',               [Traveler\SavingsGoalController::class, 'index'])->name('savings.index');
    Route::get('/savings/create',        [Traveler\SavingsGoalController::class, 'create'])->name('savings.create');
    Route::post('/savings',              [Traveler\SavingsGoalController::class, 'store'])->name('savings.store');
    Route::get('/savings/{goal}/edit',   [Traveler\SavingsGoalController::class, 'edit'])->name('savings.edit');
    Route::put('/savings/{goal}',        [Traveler\SavingsGoalController::class, 'update'])->name('savings.update');
    Route::delete('/savings/{goal}',     [Traveler\SavingsGoalController::class, 'destroy'])->name('savings.destroy');
});

// Admin routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, '__invoke'])->name('home');
    Route::get('/dashboard', [Admin\DashboardController::class, '__invoke'])->name('dashboard');

    Route::get('/users',             [Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/export',      [Admin\UserController::class, 'export'])->name('users.export');
    Route::get('/users/{user}',      [Admin\UserController::class, 'show'])->name('users.show');
    Route::patch('/users/{user}/ban',[Admin\UserController::class, 'ban'])->name('users.ban');
    Route::delete('/users/{user}',   [Admin\UserController::class, 'destroy'])->name('users.destroy');

    Route::resource('destinations',  Admin\DestinationController::class)->only(['index', 'update', 'destroy']);
    Route::post('/destinations/{destination}/fetch-image', [Admin\DestinationController::class, 'fetchImage'])->name('destinations.fetch-image');
    Route::resource('travel-costs',  Admin\TravelCostController::class)->except(['show']);
    Route::resource('attractions',   Admin\AttractionController::class)->except(['show']);
    Route::post('/attractions/{attraction}/fetch-image', [Admin\AttractionController::class, 'fetchImage'])->name('attractions.fetch-image');

    Route::get('/reviews',                   [Admin\ReviewModerationController::class, 'index'])->name('reviews.index');
    Route::patch('/reviews/{review}/hide',   [Admin\ReviewModerationController::class, 'hide'])->name('reviews.hide');
    Route::patch('/reviews/{review}/show',   [Admin\ReviewModerationController::class, 'show'])->name('reviews.show');
    Route::patch('/reviews/{review}/unflag', [Admin\ReviewModerationController::class, 'unflag'])->name('reviews.unflag');
    Route::delete('/reviews/{review}',       [Admin\ReviewModerationController::class, 'destroy'])->name('reviews.destroy');

    Route::get('/config',            [Admin\ConfigController::class, 'index'])->name('config.index');
    Route::post('/config',           [Admin\ConfigController::class, 'store'])->name('config.store');
    Route::get('/ocr-logs',          [Admin\ConfigController::class, 'ocrLogs'])->name('ocr.index');

    Route::get('/reports',           [Admin\ReportController::class, 'index'])->name('reports.index');

    Route::get('/backup',            [Admin\BackupController::class, 'index'])->name('backup.index');
    Route::post('/backup/download',  [Admin\BackupController::class, 'download'])->name('backup.download');
    Route::post('/backup/restore',   [Admin\BackupController::class, 'restore'])->name('backup.restore');

    Route::get('/profile',           [Admin\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile',           [Admin\ProfileController::class, 'update'])->name('profile.update');

    Route::get('/settings',          [Admin\SettingsController::class, 'edit'])->name('settings.index');
});
