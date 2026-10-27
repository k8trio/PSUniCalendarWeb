<?php

namespace App\Providers;

use App\Models\EventRequest;
use App\Models\User;
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

            // Campus groups are omitted from this Superadmin navigation.
            $officesByGroup = collect(User::SIDEBAR_OFFICE_GROUPS)
                ->mapWithKeys(fn ($group) => [$group => $offices->where('campus', $group)->values()]);

            $view->with([
                'sidebarOfficeGroups' => $officesByGroup,
                'sidebarNotificationCount' => EventRequest::whereIn('status', ['pending', 'conflict'])->count(),
            ]);
        });
    }
}
