<?php

use App\Http\Controllers\Api\EventFlowController as API;
use App\Http\Controllers\Api\CatalogController as Catalog;
use App\Models\Task;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function () {
    Route::get('/services', [API::class, 'publicServices']);
    Route::get('/event-types', [API::class, 'publicEventTypes']);
    Route::post('/request-quote', [API::class, 'requestQuote'])->middleware('throttle:10,1');
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [API::class, 'login'])->middleware('throttle:10,1');
    Route::post('/register', [API::class, 'register'])->middleware('throttle:5,1');
    Route::middleware('auth:sanctum')->post('/logout', [API::class, 'logout']);
});

Route::middleware(['auth:sanctum', 'role:super_admin,manager,finance,staff'])->group(function () {
    Route::get('/dashboard', [API::class, 'dashboard']);
    Route::get('/events', [API::class, 'events']);
    Route::post('/events', [API::class, 'createEvent']);
    Route::get('/events/{event}', [API::class, 'showEvent']);
    Route::put('/events/{event}', [API::class, 'updateEvent']);
    Route::post('/events/{event}/services', [API::class, 'attachServices']);
    Route::post('/events/{event}/staff', [API::class, 'attachStaff']);
    Route::post('/events/{event}/vendors', [API::class, 'attachVendors']);
    Route::get('/events/{event}/messages', [API::class, 'eventMessages']);
    Route::post('/events/{event}/messages', [API::class, 'createMessage']);

    Route::get('/customers', [API::class, 'customers']);
    Route::post('/customers', [API::class, 'createCustomer']);
    Route::put('/customers/{customer}', [API::class, 'updateCustomer']);
    Route::delete('/customers/{customer}', [API::class, 'deleteCustomer']);
    Route::get('/services', [API::class, 'services']);
    Route::post('/services', [Catalog::class, 'createService'])->middleware('role:super_admin,manager');
    Route::put('/services/{service}', [Catalog::class, 'updateService'])->middleware('role:super_admin,manager');
    Route::get('/event-types', [API::class, 'publicEventTypes']);
    Route::post('/event-types', [Catalog::class, 'createEventType'])->middleware('role:super_admin,manager');
    Route::put('/event-types/{eventType}', [Catalog::class, 'updateEventType'])->middleware('role:super_admin,manager');
    Route::get('/staff', [API::class, 'staff']);
    Route::post('/staff', [Catalog::class, 'createStaff'])->middleware('role:super_admin,manager');
    Route::put('/staff/{staff}', [Catalog::class, 'updateStaff'])->middleware('role:super_admin,manager');
    Route::delete('/staff/{staff}', [Catalog::class, 'deleteStaff'])->middleware('role:super_admin,manager');
    Route::get('/vendors', [API::class, 'vendors']);
    Route::post('/vendors', [Catalog::class, 'createVendor'])->middleware('role:super_admin,manager');
    Route::put('/vendors/{vendor}', [Catalog::class, 'updateVendor'])->middleware('role:super_admin,manager');
    Route::delete('/vendors/{vendor}', [Catalog::class, 'deleteVendor'])->middleware('role:super_admin,manager');
    Route::get('/equipment', [API::class, 'equipment']);
    Route::post('/equipment', [Catalog::class, 'createEquipment'])->middleware('role:super_admin,manager');
    Route::put('/equipment/{equipment}', [Catalog::class, 'updateEquipment'])->middleware('role:super_admin,manager');
    Route::delete('/equipment/{equipment}', [Catalog::class, 'deleteEquipment'])->middleware('role:super_admin,manager');
    Route::post('/equipment/reservations', [API::class, 'reserveEquipment']);
    Route::get('/invoices', [Catalog::class, 'invoices'])->middleware('role:super_admin,manager,finance');
    Route::get('/invoices/{invoice}', [Catalog::class, 'showInvoice'])->middleware('role:super_admin,manager,finance');
    Route::get('/documents', [Catalog::class, 'documents'])->middleware('role:super_admin,manager,finance,staff');
    Route::post('/documents', [Catalog::class, 'uploadDocument'])->middleware('role:super_admin,manager,finance,staff');
    Route::delete('/documents/{document}', [Catalog::class, 'deleteDocument'])->middleware('role:super_admin,manager,finance');
    Route::get('/activity-logs', [Catalog::class, 'activityLog'])->middleware('role:super_admin,manager');
    Route::get('/notifications', [Catalog::class, 'notifications']);
    Route::patch('/notifications/read-all', [Catalog::class, 'markAllNotificationsRead']);
    Route::patch('/notifications/{notification}/read', [Catalog::class, 'markNotificationRead']);
    Route::get('/tasks', fn() => Task::with(['event', 'assignedStaff'])->paginate(100));
    Route::post('/tasks', [API::class, 'createTask']);
    Route::put('/tasks/{task}', [API::class, 'updateTask']);

    Route::middleware('role:super_admin,manager,finance')->group(function () {
        Route::get('/quotations', [API::class, 'quotations']);
        Route::post('/quotations', [API::class, 'createQuotation']);
        Route::get('/payments', [API::class, 'payments']);
        Route::post('/payments', [API::class, 'createPayment']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/portal/dashboard', [API::class, 'portalDashboard'])->middleware('role:customer');
    Route::post('/quotations/{quotation}/accept', [API::class, 'acceptQuotation'])->middleware('role:customer');
    Route::post('/quotations/{quotation}/reject', [API::class, 'rejectQuotation'])->middleware('role:customer');
});
