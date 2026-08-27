<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, '__invoke'])->name('dashboard');

    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::delete('/users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/destinations', [Admin\DestinationController::class, 'index'])->name('destinations.index');
    Route::post('/destinations', [Admin\DestinationController::class, 'store'])->name('destinations.store');
    Route::put('/destinations/{dest}', [Admin\DestinationController::class, 'update'])->name('destinations.update');
    Route::delete('/destinations/{dest}', [Admin\DestinationController::class, 'destroy'])->name('destinations.destroy');

    Route::get('/attractions', [Admin\AttractionController::class, 'index'])->name('attractions.index');
    Route::post('/attractions', [Admin\AttractionController::class, 'store'])->name('attractions.store');
    Route::put('/attractions/{attr}', [Admin\AttractionController::class, 'update'])->name('attractions.update');
    Route::delete('/attractions/{attr}', [Admin\AttractionController::class, 'destroy'])->name('attractions.destroy');

    Route::get('/reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('/reviews/{review}', [Admin\ReviewController::class, 'updateStatus'])->name('reviews.updateStatus');
    Route::delete('/reviews/{review}', [Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::get('/integrations', [Admin\IntegrationController::class, 'index'])->name('integrations.index');
    Route::post('/integrations/klook', [Admin\IntegrationController::class, 'saveKlook'])->name('integrations.klook');
    Route::post('/integrations/test', [Admin\IntegrationController::class, 'testKlook'])->name('integrations.test');

    Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/download', [Admin\ReportController::class, 'download'])->name('reports.download');
});
