<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventApprovalController;
use App\Http\Controllers\PlanningOfficeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\PlanningDecisionHistoryController;
use App\Http\Controllers\StaffAuthController;

Route::redirect('/superadmin/login', '/planning-office/login');
Route::redirect('/superadmin', '/planning-office');
Route::redirect('/superadmin/pending-approvals', '/planning-office/pending-approvals');
Route::redirect('/superadmin/manage-venues', '/planning-office/manage-venues');

Route::get('/login', [StaffAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [StaffAuthController::class, 'login'])->name('login.submit');
Route::get('/office/login', [StaffAuthController::class, 'redirectToLogin'])->name('office.login');
Route::post('/office/login', [StaffAuthController::class, 'login'])->name('office.login.submit');
Route::post('/office/logout', [StaffAuthController::class, 'logout'])->name('office.logout');

Route::middleware(['auth', 'role:office'])->prefix('office')->group(function () {
    Route::get('/', [OfficeController::class, 'dashboard'])->name('office.dashboard');
    Route::get('/add-event', [OfficeController::class, 'addEvent'])->name('office.add-event');
    Route::get('/request-status', [OfficeController::class, 'requestStatus'])->name('office.request-status');
    Route::get('/recent-activity', [OfficeController::class, 'recentActivity'])->name('office.recent-activity');
    Route::get('/calendar', [OfficeController::class, 'calendar'])->name('office.calendar');
    Route::get('/requests/detailed-report', [OfficeController::class, 'detailedReport'])->name('office.requests.report.preview');
    Route::get('/requests/detailed-report.pdf', [OfficeController::class, 'downloadDetailedReport'])->name('office.requests.report');
    Route::get('/events/{event}/documents/{documentIndex}', [OfficeController::class, 'eventDocument'])->whereNumber('documentIndex')->name('office.events.documents.show');
    Route::post('/request-venue', [OfficeController::class, 'requestVenue'])->name('office.request');
    Route::post('/requests/undo', [OfficeController::class, 'undoRequest'])->name('office.requests.undo');
    Route::post('/requests/redo', [OfficeController::class, 'redoRequest'])->name('office.requests.redo');
    Route::get('/check-availability', [OfficeController::class, 'checkAvailability'])->name('office.availability');

    // Quick actions / shortcuts
    Route::get('/requests/{id}/duplicate', [OfficeController::class, 'duplicateRequest'])->name('office.requests.duplicate');
    Route::get('/requests/{id}/edit', [OfficeController::class, 'editRequestForm'])->name('office.requests.edit');
    Route::post('/requests/{id}/cancel', [OfficeController::class, 'cancelRequest'])->name('office.requests.cancel');
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

Route::get('/planning-office/login', [StaffAuthController::class, 'redirectToLogin'])->name('planning_office.login');
Route::post('/planning-office/login', [StaffAuthController::class, 'login'])->name('planning_office.login.submit');
Route::post('/planning-office/logout', [StaffAuthController::class, 'logout'])->name('planning_office.logout');

Route::middleware(['auth', 'role:planning_office'])->prefix('planning-office')->group(function () {
    Route::get('/', [PlanningOfficeController::class, 'dashboard'])->name('planning_office.dashboard');
    Route::get('/calendar', [PlanningOfficeController::class, 'dashboard'])->name('planning_office.calendar');
    Route::get('/reports/detailed-events', [PlanningOfficeController::class, 'detailedReport'])->name('planning_office.reports.events.preview');
    Route::get('/reports/detailed-events.pdf', [PlanningOfficeController::class, 'downloadDetailedReport'])->name('planning_office.reports.events');
    Route::get('/archive', [PlanningOfficeController::class, 'archive'])->name('planning_office.archive');
    Route::get('/pending-approvals', [PlanningOfficeController::class, 'pendingApprovals'])->name('planning_office.pending');
    Route::get('/search', [PlanningOfficeController::class, 'search'])->name('planning_office.search');
    Route::get('/events/{event}', [PlanningOfficeController::class, 'showEvent'])->name('planning_office.events.show');
    Route::get('/events/{event}/documents/{documentIndex}', [PlanningOfficeController::class, 'eventDocument'])->whereNumber('documentIndex')->name('planning_office.events.documents.show');
    Route::post('/approve/{id}', [EventApprovalController::class, 'approve'])->name('planning_office.approve');
    Route::post('/reject/{id}', [EventApprovalController::class, 'reject'])->name('planning_office.reject');
    Route::post('/return/{id}', [EventApprovalController::class, 'returnForRevision'])->name('planning_office.return');
    Route::post('/decisions/undo', [PlanningDecisionHistoryController::class, 'undo'])->name('planning_office.decisions.undo');
    Route::post('/decisions/redo', [PlanningDecisionHistoryController::class, 'redo'])->name('planning_office.decisions.redo');
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

Route::get('/events/{event}/documents/{documentIndex}', [UserController::class, 'eventDocument'])
    ->whereNumber('documentIndex')
    ->name('user.events.documents.show');

Route::get('/', function () {
    if (Auth::check()) {
        if (Auth::user()->role === 'office') {
            return redirect()->route('office.dashboard');
        }

        if (Auth::user()->role === 'planning_office') {
            return redirect()->route('planning_office.dashboard');
        }
    }

    return redirect()->route('login');
})->name('user.calendar');

Route::domain('superadmin.unicalendar.test')->group(function () {
    Route::redirect('/', '/planning-office');
    Route::get('/login', [StaffAuthController::class, 'showLogin']);
});