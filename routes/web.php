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

    // Portal do Cliente ("Meus Produtos")
    Route::get('/meus-produtos', [\App\Http\Controllers\ClientDashboardController::class, 'myProducts'])->name('my-products.index');
    Route::get('/meus-produtos/{contract}', [\App\Http\Controllers\ClientDashboardController::class, 'showContract'])->name('my-products.show');
    Route::get('/documentos-contrato/{document}/preview', [\App\Http\Controllers\ClientDashboardController::class, 'previewDocument'])
        ->name('my-products.preview-document');
    Route::get('/documentos-contrato/{document}', [\App\Http\Controllers\ClientDashboardController::class, 'downloadDocument'])->name('my-products.download-document');

    // Avaliações de contratos (cliente)
    Route::post('/meus-produtos/{contract}/avaliacoes', [\App\Http\Controllers\ContractReviewController::class, 'store'])->name('reviews.store');

    // Track & Trace (multi-carrier)
    Route::get('/meus-produtos/{contract}/rastreamento', [\App\Http\Controllers\Api\ContainerTrackingController::class, 'forContract'])
        ->middleware('throttle:30,1')
        ->name('my-products.tracking');
    Route::get('/rastreamento/{container}', [\App\Http\Controllers\Api\ContainerTrackingController::class, 'show'])
        ->middleware('throttle:30,1')
        ->name('tracking.show');
});

// Textos legais públicos: o checkbox de consentimento do formulário de
// cadastro precisa apontar para algo legível antes do login.
Route::get('/privacidade', [\App\Http\Controllers\PublicPolicyController::class, 'privacy'])
    ->name('public.privacy');
Route::get('/termos', [\App\Http\Controllers\PublicPolicyController::class, 'terms'])
    ->name('public.terms');

// Formulário público de cadastro (embed nas landing pages). Passa pela
// aplicação para receber os cabeçalhos de segurança e o token de formulário.
Route::get('/external-form-snippet.html', [\App\Http\Controllers\EmbedFormController::class, 'show'])
    ->name('embed.register.show');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [\App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password');
});

require __DIR__.'/auth.php';

Route::middleware(['auth', EnsureUserIsMaster::class])->prefix('admin')->group(function () {
    Route::get('/clients', [\App\Http\Controllers\ContractController::class, 'index'])->name('admin.clients.index');

    Route::get('/contracts/{contract}', [\App\Http\Controllers\ContractController::class, 'show'])->name('contracts.show');
    Route::post('/contracts/{contract}/documentos', [\App\Http\Controllers\ContractDocumentController::class, 'store'])->name('contracts.documents.store');
    Route::delete('/contracts/documentos/{document}', [\App\Http\Controllers\ContractDocumentController::class, 'destroy'])->name('contracts.documents.destroy');
    Route::get('/contracts/{contract}/rastreamento', [\App\Http\Controllers\Api\ContainerTrackingController::class, 'forContract'])
        ->middleware('throttle:30,1')
        ->name('contracts.tracking');
    Route::post('/contracts', [\App\Http\Controllers\ContractController::class, 'store'])->name('contracts.store');
    Route::put('/contracts/{contract}', [\App\Http\Controllers\ContractController::class, 'update'])->name('contracts.update');
    Route::patch('/contracts/{contract}/status', [\App\Http\Controllers\ContractController::class, 'updateStatus'])->name('contracts.status');
    Route::delete('/contracts/{contract}', [\App\Http\Controllers\ContractController::class, 'destroy'])->name('contracts.destroy');
    Route::post('/contracts/bulk-delete', [\App\Http\Controllers\ContractController::class, 'bulkDestroy'])->name('contracts.bulk-delete');
    Route::post('/contracts/bulk-sync-users', [\App\Http\Controllers\ContractController::class, 'bulkSyncUsers'])->name('contracts.bulk-sync-users');

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

    // Avaliações (Admin)
    Route::get('/reviews', [\App\Http\Controllers\ContractReviewController::class, 'adminIndex'])->name('admin.reviews.index');
    Route::get('/reviews/{review}', [\App\Http\Controllers\ContractReviewController::class, 'adminShow'])->name('admin.reviews.show');
    Route::post('/reviews/{review}/reply', [\App\Http\Controllers\ContractReviewController::class, 'reply'])->name('admin.reviews.reply');
});
