<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventApprovalController;
use App\Http\Controllers\PlanningOfficeAuthController;
use App\Http\Controllers\PlanningOfficeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\OfficeAuthController;
use App\Http\Controllers\OfficeController;

Route::redirect('/superadmin/login', '/planning-office/login');
Route::redirect('/superadmin', '/planning-office');
Route::redirect('/superadmin/pending-approvals', '/planning-office/pending-approvals');
Route::redirect('/superadmin/manage-venues', '/planning-office/manage-venues');

Route::get('/office/login', [OfficeAuthController::class, 'showLogin'])->name('office.login');
Route::post('/office/login', [OfficeAuthController::class, 'login'])->name('office.login.submit');
Route::post('/office/logout', [OfficeAuthController::class, 'logout'])->name('office.logout');

Route::middleware(['auth', 'role:office'])->prefix('office')->group(function () {
    Route::get('/', [OfficeController::class, 'dashboard'])->name('office.dashboard');
    Route::get('/add-event', [OfficeController::class, 'addEvent'])->name('office.add-event');
    Route::get('/request-status', [OfficeController::class, 'requestStatus'])->name('office.request-status');
    Route::get('/recent-activity', [OfficeController::class, 'recentActivity'])->name('office.recent-activity');
    Route::get('/recent-activity', [OfficeController::class, 'recentActivity'])->name('office.recent-activity');
    Route::get('/calendar', [OfficeController::class, 'calendar'])->name('office.calendar');
    Route::post('/request-venue', [OfficeController::class, 'requestVenue'])->name('office.request');
    Route::get('/check-availability', [OfficeController::class, 'checkAvailability'])->name('office.availability');

    // Quick actions / shortcuts
    Route::get('/requests/{id}/duplicate', [OfficeController::class, 'duplicateRequest'])->name('office.requests.duplicate');
    Route::get('/requests/{id}/edit', [OfficeController::class, 'editRequestForm'])->name('office.requests.edit');
    Route::post('/requests/{id}/cancel', [OfficeController::class, 'cancelRequest'])->name('office.requests.cancel');
    Route::delete('/requests/{id}', [OfficeController::class, 'deleteCancelledRequest'])->name('office.requests.delete');
    Route::delete('/requests/{id}', [OfficeController::class, 'deleteCancelledRequest'])->name('office.requests.delete');
    Route::post('/favorite-venues', [OfficeController::class, 'toggleFavoriteVenue'])->name('office.favorite-venues.toggle');

    // Notifications
    Route::post('/notifications/read', [OfficeController::class, 'markNotificationsRead'])->name('office.notifications.read');

    // Reporting
    Route::get('/requests/export', [OfficeController::class, 'exportCsv'])->name('office.requests.export');

    // Profile
    Route::patch('/profile', [OfficeController::class, 'updateProfile'])->name('office.profile.update');
    Route::patch('/change-password', [OfficeController::class, 'changePassword'])->name('office.password.update');
});

Route::get('/planning-office/login', [PlanningOfficeAuthController::class, 'showLogin'])->name('planning_office.login');
Route::post('/planning-office/login', [PlanningOfficeAuthController::class, 'login'])->name('planning_office.login.submit');
Route::post('/planning-office/logout', [PlanningOfficeAuthController::class, 'logout'])->name('planning_office.logout');

Route::middleware(['auth', 'role:planning_office'])->prefix('planning-office')->group(function () {
    Route::get('/', [PlanningOfficeController::class, 'dashboard'])->name('planning_office.dashboard');
    Route::get('/calendar', [PlanningOfficeController::class, 'dashboard'])->name('planning_office.calendar');
    Route::get('/pending-approvals', [PlanningOfficeController::class, 'pendingApprovals'])->name('planning_office.pending');
    Route::get('/search', [PlanningOfficeController::class, 'search'])->name('planning_office.search');
    Route::post('/approve/{id}', [EventApprovalController::class, 'approve'])->name('planning_office.approve');
    Route::post('/reject/{id}', [EventApprovalController::class, 'reject'])->name('planning_office.reject');
    Route::post('/return/{id}', [EventApprovalController::class, 'returnForRevision'])->name('planning_office.return');
    Route::get('/manage-venues', [PlanningOfficeController::class, 'manageVenues'])->name('planning_office.venues');
    Route::post('/manage-venues', [PlanningOfficeController::class, 'storeVenue'])->name('planning_office.venues.store');
    Route::put('/manage-venues/{venue}', [PlanningOfficeController::class, 'updateVenue'])->name('planning_office.venues.update');
    Route::delete('/manage-venues/{venue}', [PlanningOfficeController::class, 'destroyVenue'])->name('planning_office.venues.destroy');
    Route::get('/manage-venues/{venue}', [PlanningOfficeController::class, 'venueEvents'])->name('planning_office.venues.events');

    // Offices (grouped by campus in the sidebar)
    Route::get('/offices/{office}', [PlanningOfficeController::class, 'showOffice'])->name('planning_office.offices.show');
    Route::patch('/offices/{office}/campus', [PlanningOfficeController::class, 'updateOfficeCampus'])->name('planning_office.offices.campus');

    // System
    Route::get('/notifications', [PlanningOfficeController::class, 'notifications'])->name('planning_office.notifications');
    Route::get('/settings', [PlanningOfficeController::class, 'settings'])->name('planning_office.settings');
    Route::patch('/settings/profile', [PlanningOfficeController::class, 'updateSettingsProfile'])->name('planning_office.settings.profile');
    Route::patch('/settings/password', [PlanningOfficeController::class, 'updateSettingsPassword'])->name('planning_office.settings.password');
});

Route::get('/', [UserController::class, 'index'])->name('user.calendar');

Route::domain('superadmin.unicalendar.test')->group(function () {
    Route::redirect('/', '/planning-office');
    Route::redirect('/login', '/planning-office/login');
});
