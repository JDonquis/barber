<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PlatformSettingController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\AppointmentAvailabilityController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BarberController;
use App\Http\Controllers\BarberLiquidationController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ClientPortalController;
use App\Http\Controllers\ClosingController;
use App\Http\Controllers\CompanyPwaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanPaymentController;
use App\Http\Controllers\PasswordResetRequestController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrLoginController;
use App\Http\Controllers\RetentionController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SubscriptionPaymentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return auth()->user()->isSuperAdmin()
        ? redirect()->route('admin.companies.index')
        : redirect()->route('dashboard');
});

Route::get('/qr-login/{token}', [QrLoginController::class, 'consume'])
    ->middleware('throttle:20,1')
    ->name('qr-login.consume');

Route::get('/reservar/{company:slug}/manifest.webmanifest', [CompanyPwaController::class, 'manifest'])->name('booking.manifest');
Route::get('/reservar/{company:slug}/icon-{size}.png', [CompanyPwaController::class, 'icon'])
    ->whereIn('size', ['192', '512'])
    ->name('booking.icon');
Route::get('/reservar/{company:slug}/acceso/{token}', [ClientPortalController::class, 'access'])->name('booking.access');
Route::get('/reservar/{company:slug}/mi-cuenta', [ClientPortalController::class, 'account'])->name('booking.account');
Route::post('/reservar/{company:slug}/recordatorios', [ClientPortalController::class, 'updateReminders'])->name('booking.reminders.update');
Route::delete('/reservar/{company:slug}/recordatorios', [ClientPortalController::class, 'disableReminders'])->name('booking.reminders.destroy');
Route::post('/reservar/{company:slug}/push', [ClientPortalController::class, 'subscribePush'])->name('booking.push.subscribe');
Route::delete('/reservar/{company:slug}/push', [ClientPortalController::class, 'unsubscribePush'])->name('booking.push.destroy');
Route::get('/reservar/{company:slug}/slots', [BookingController::class, 'slots'])->name('booking.slots');
Route::get('/reservar/{company:slug}/dias', [BookingController::class, 'days'])->name('booking.days');
Route::post('/reservar/{company:slug}', [BookingController::class, 'store'])->name('booking.store');
Route::get('/reservar/{company:slug}', [BookingController::class, 'show'])->name('booking.show');

Route::middleware(['auth', 'superadmin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
    Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
    Route::post('/companies/{company}/toggle', [CompanyController::class, 'toggle'])->name('companies.toggle');

    Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments/{payment}/approve', [AdminPaymentController::class, 'approve'])->name('payments.approve');
    Route::post('/payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->name('payments.reject');

    Route::get('/settings', [PlatformSettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [PlatformSettingController::class, 'update'])->name('settings.update');

    Route::get('/profile', [AdminProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile/password', [AdminProfileController::class, 'updatePassword'])->name('profile.password.update');
});

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Ventas (disponible para administradores y barberos)
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::middleware('can-register-sales')->group(function () {
        Route::get('/sales/service/create', [SaleController::class, 'createService'])->name('sales.create-service');
        Route::post('/sales/service', [SaleController::class, 'storeService'])->name('sales.store-service');
    });
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::get('/sales/{sale}/print', [SaleController::class, 'print'])->name('sales.print');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Administración
    Route::middleware('role:admin')->group(function () {
        Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');

        // Venta de productos: solo administradores
        Route::get('/sales/product/create', [SaleController::class, 'createProduct'])->name('sales.create-product');
        Route::post('/sales/product', [SaleController::class, 'storeProduct'])->name('sales.store-product');

        Route::resource('services', ServiceController::class)->except(['show']);
        Route::post('/services/{service}/toggle', [ServiceController::class, 'toggleActive'])->name('services.toggle');
        Route::post('/services/{service}/price', [ServiceController::class, 'updatePrice'])->name('services.update-price');
        Route::resource('products', ProductController::class)->except(['show']);
        Route::post('/products/{product}/toggle', [ProductController::class, 'toggleActive'])->name('products.toggle');
        Route::resource('barbers', BarberController::class)->except(['show']);
        Route::post('/barbers/{barber}/toggle', [BarberController::class, 'toggleActive'])->name('barbers.toggle');

        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');

        Route::resource('users', UserController::class)->except(['show']);
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

        Route::get('/password-requests', [PasswordResetRequestController::class, 'index'])->name('password-requests.index');
        Route::post('/password-requests/{passwordResetRequest}/resolve', [PasswordResetRequestController::class, 'resolve'])->name('password-requests.resolve');
        Route::delete('/password-requests/{passwordResetRequest}', [PasswordResetRequestController::class, 'destroy'])->name('password-requests.destroy');

        Route::get('/products/{product}/stock', [StockMovementController::class, 'index'])->name('products.stock.index');
        Route::post('/products/{product}/stock', [StockMovementController::class, 'store'])->name('products.stock.store');

        Route::resource('expenses', ExpenseController::class)->except(['show']);

        Route::resource('loans', LoanController::class);
        Route::post('/loans/{loan}/payments', [LoanPaymentController::class, 'store'])->name('loans.payments.store');
        Route::delete('/loans/{loan}/payments/{payment}', [LoanPaymentController::class, 'destroy'])->name('loans.payments.destroy');

        Route::resource('retentions', RetentionController::class)->except(['show']);
        Route::post('/retentions/{retention}/toggle', [RetentionController::class, 'toggleActive'])->name('retentions.toggle');

        Route::get('/closings', [ClosingController::class, 'index'])->name('closings.index');
        Route::post('/closings', [ClosingController::class, 'generate'])->name('closings.generate');
        Route::get('/closings/{closing}', [ClosingController::class, 'show'])->name('closings.show');
        Route::post('/closings/{closing}/close', [ClosingController::class, 'close'])->name('closings.close');
        Route::post('/closings/{closing}/reopen', [ClosingController::class, 'reopen'])->name('closings.reopen');
        Route::get('/closings/{closing}/print', [ClosingController::class, 'print'])->name('closings.print');

        Route::get('/liquidations', [BarberLiquidationController::class, 'index'])->name('liquidations.index');
        Route::post('/liquidations', [BarberLiquidationController::class, 'store'])->name('liquidations.store');
        Route::get('/liquidations/{liquidation}', [BarberLiquidationController::class, 'show'])->name('liquidations.show');
        Route::post('/liquidations/{liquidation}/close', [BarberLiquidationController::class, 'close'])->name('liquidations.close');
        Route::post('/liquidations/{liquidation}/reopen', [BarberLiquidationController::class, 'reopen'])->name('liquidations.reopen');
        Route::get('/liquidations/{liquidation}/print', [BarberLiquidationController::class, 'print'])->name('liquidations.print');

        Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.status');
        Route::get('/appointments/availability', [AppointmentAvailabilityController::class, 'index'])->name('appointments.availability');
        Route::post('/appointments/availability', [AppointmentAvailabilityController::class, 'storeSchedule'])->name('appointments.schedules.store');
        Route::post('/appointments/availability/sync', [AppointmentAvailabilityController::class, 'syncSchedules'])->name('appointments.schedules.sync');
        Route::post('/appointments/availability/service-durations', [AppointmentAvailabilityController::class, 'updateServiceDurations'])->name('appointments.services.durations.update');
        Route::delete('/appointments/availability/{schedule}', [AppointmentAvailabilityController::class, 'destroySchedule'])->name('appointments.schedules.destroy');
        Route::post('/appointments/days-off', [AppointmentAvailabilityController::class, 'storeDayOff'])->name('appointments.days-off.store');
        Route::delete('/appointments/days-off/{dayOff}', [AppointmentAvailabilityController::class, 'destroyDayOff'])->name('appointments.days-off.destroy');

        Route::get('/payments', [SubscriptionPaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments', [SubscriptionPaymentController::class, 'store'])->name('payments.store');

        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/qr-login', [QrLoginController::class, 'regenerate'])->name('settings.qr-login.regenerate');
        Route::post('/exchange-rate/refresh', [ExchangeRateController::class, 'refresh'])->name('exchange-rate.refresh');
        Route::post('/exchange-rate/clear-manual', [ExchangeRateController::class, 'clearManual'])->name('exchange-rate.clear-manual');
    });
});

require __DIR__.'/auth.php';
