<?php

namespace App\Providers;

use App\Models\EventRequest;
use App\Models\User;
use App\Services\PlanningDecisionHistoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Planning Office sidebar: university offices + pending review count
        View::composer('layouts.planning-office', function ($view) {
            $offices = User::where('role', 'office')->orderBy('name')->get(['id', 'name', 'campus']);

            // Show the main campus accounts at the end of the University Offices group.
            $officesByGroup = collect(User::SIDEBAR_OFFICE_GROUPS)
                ->mapWithKeys(fn ($group) => [
                    $group => $offices
                        ->whereNotIn('campus', User::MAIN_CAMPUSES)
                        ->concat($offices->whereIn('campus', User::MAIN_CAMPUSES))
                        ->values(),
                ]);

            $view->with([
                'sidebarOfficeGroups' => $officesByGroup,
                'sidebarNotificationCount' => EventRequest::whereIn('status', ['pending', 'conflict'])->count(),
                'planningDecisionHistoryControls' => app(PlanningDecisionHistoryService::class)->controlsFor(Auth::user()),
            ]);
        });
    }
}
