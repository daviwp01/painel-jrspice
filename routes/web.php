<?php

use App\Http\Controllers\AdminController;

use App\Http\Middleware\EnsureUserIsMaster;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\InternalDashboardController;
use App\Http\Controllers\Admin\DataController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/tracking/email-click/{user}', [AdminController::class, 'trackEmailClick'])->name('tracking.email-click');

Route::middleware(['auth', 'verified', 'throttle:60,1'])->group(function () {
    Route::get('/dashboard', [InternalDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/page/{slug}', [InternalDashboardController::class, 'show'])->name('dashboard.page');
    Route::post('/dashboard/contact/send', [InternalDashboardController::class, 'sendContactEmail'])->name('dashboard.contact.send');
    Route::get('/dashboard/export/prices', [InternalDashboardController::class, 'exportPricesPdf'])->name('dashboard.export.prices');

    // Gestão de Clientes / Exportações
    Route::get('/export-processes', [\App\Http\Controllers\ExportProcessController::class, 'index'])->name('export-processes.index');
    Route::post('/export-processes', [\App\Http\Controllers\ExportProcessController::class, 'store'])->name('export-processes.store');
    Route::put('/export-processes/{exportProcess}', [\App\Http\Controllers\ExportProcessController::class, 'update'])->name('export-processes.update');
    Route::delete('/export-processes/{exportProcess}', [\App\Http\Controllers\ExportProcessController::class, 'destroy'])->name('export-processes.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [\App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password');
});

require __DIR__.'/auth.php';

Route::middleware(['auth', EnsureUserIsMaster::class])->prefix('admin')->group(function () {
    Route::get('/clients', [\App\Http\Controllers\ExportProcessController::class, 'index'])->name('admin.clients.index');
    Route::get('/users', [AdminController::class, 'usersIndex'])->name('admin.users.index');
    Route::get('/activity', [AdminController::class, 'activityIndex'])->name('admin.activity.index');
    Route::post('/activity/clear', [AdminController::class, 'bulkClearActivity'])->name('admin.activity.clear');
    Route::get('/activity/{user}/sessions', [AdminController::class, 'userSessionLogs'])->name('admin.activity.sessions');
    Route::get('/activity/{user}/search-stats', [AdminController::class, 'userSearchStats'])->name('admin.activity.search-stats');
    Route::delete('/activity/{user}/clear', [AdminController::class, 'clearUserActivity'])->name('admin.activity.user.clear');

    Route::get('/users/create', [AdminController::class, 'usersCreate'])->name('admin.users.create');
    Route::post('/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::get('/users/{user}/edit', [AdminController::class, 'usersEdit'])->name('admin.users.edit');
    Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('admin.users.destroy');

    Route::get('/settings', [AdminController::class, 'settingsIndex'])->name('admin.settings.index');
    Route::post('/settings', [AdminController::class, 'updateSettings'])->name('admin.settings.update');
    Route::post('/settings/notify', [AdminController::class, 'notifyUpdate'])->name('admin.settings.notify');
    Route::post('/settings/test-mail', [AdminController::class, 'testMail'])->name('admin.settings.test-mail');
    Route::get('/settings/queue-status', [AdminController::class, 'getQueueStatus'])->name('admin.settings.queue-status');

    Route::patch('/users/{user}/toggle-status', [AdminController::class, 'toggleUserStatus'])->name('admin.users.toggle-status');
    Route::post('/users/bulk-status', [AdminController::class, 'bulkUpdateStatus'])->name('admin.users.bulk-status');

    // Admin Data Routes
    Route::get('/data', [DataController::class, 'index'])->name('admin.data.index');
    Route::post('/data/pages', [DataController::class, 'storePage'])->name('admin.data.pages.store');
    Route::put('/data/pages/{page}', [DataController::class, 'updatePage'])->name('admin.data.pages.update');
    Route::delete('/data/pages/{page}', [DataController::class, 'destroyPage'])->name('admin.data.pages.destroy');

    Route::post('/data/countries', [DataController::class, 'storeCountry'])->name('admin.data.countries.store');
    Route::put('/data/countries/{country}', [DataController::class, 'updateCountry'])->name('admin.data.countries.update');
    Route::delete('/data/countries/{country}', [DataController::class, 'destroyCountry'])->name('admin.data.countries.destroy');

    Route::post('/data/suppliers', [DataController::class, 'storeSupplier'])->name('admin.data.suppliers.store');
    Route::put('/data/suppliers/{supplier}', [DataController::class, 'updateSupplier'])->name('admin.data.suppliers.update');
    Route::delete('/data/suppliers/{supplier}', [DataController::class, 'destroySupplier'])->name('admin.data.suppliers.destroy');

    Route::post('/data/products', [DataController::class, 'storeProduct'])->name('admin.data.products.store');
    Route::put('/data/products/{product}', [DataController::class, 'updateProduct'])->name('admin.data.products.update');
    Route::delete('/data/products/{product}', [DataController::class, 'destroyProduct'])->name('admin.data.products.destroy');
    Route::post('/data/products/clear-harvests', [DataController::class, 'clearAllHarvests'])->name('admin.data.products.clear-harvests');

    Route::post('/data/clients', [DataController::class, 'storeClient'])->name('admin.data.clients.store');
    Route::put('/data/clients/{client}', [DataController::class, 'updateClient'])->name('admin.data.clients.update');
    Route::delete('/data/clients/{client}', [DataController::class, 'destroyClient'])->name('admin.data.clients.destroy');

    Route::post('/data/prices', [DataController::class, 'storePrice'])->name('admin.data.prices.store');
    Route::post('/data/prices/truncate', [DataController::class, 'truncatePrices'])->name('admin.data.prices.truncate');
    Route::post('/data/prices/clear-empty', [DataController::class, 'deleteEmptyPrices'])->name('admin.data.prices.clear-empty');
    Route::put('/data/prices/{price}', [DataController::class, 'updatePrice'])->name('admin.data.prices.update');
    Route::delete('/data/prices/{price}', [DataController::class, 'destroyPrice'])->name('admin.data.prices.destroy');

    // Import Routes
    Route::post('/data/import', [DataController::class, 'importData'])->name('admin.data.import');
    Route::get('/data/import-status/{jobId}', [DataController::class, 'getImportStatus'])->name('admin.data.import-status');
    Route::post('/data/import-cancel', [DataController::class, 'cancelImport'])->name('admin.data.import-cancel');
    Route::get('/data/download-backup', [DataController::class, 'downloadBackup'])->name('admin.data.download-backup');
    Route::get('/data/download-template', [DataController::class, 'downloadTemplate'])->name('admin.data.download-template');
    Route::post('/data/restore-backup', [DataController::class, 'restoreBackup'])->name('admin.data.restore-backup');
    Route::post('/data/create-backup', [DataController::class, 'createManualBackup'])->name('admin.data.create-backup');

    // Default Filter Config
    Route::post('/data/default-filters', [DataController::class, 'saveDefaultFilters'])->name('admin.data.default-filters.save');
});
