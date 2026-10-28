<?php

namespace App\Http\Controllers;

use App\Models\EventRequest;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        $events = EventRequest::where('status', 'approved')
            ->orderBy('start_datetime', 'asc')
            ->get()
            ->map(function ($event) {
                $campus = $event->campus ?: (\App\Models\User::campusFromText($event->venue_name) ?? 'All Campus');

                return [
                    'title' => $event->title,
                    'start' => $event->start_datetime,
                    'end' => $event->end_datetime,
                    'description' => $event->description,
                    'venue' => $event->venue_name,
                    'campus' => $campus,
                    'university_wide' => (bool) $event->is_university_wide,
                    'office' => $event->name,
                    'office_filter' => User::UNIVERSITY_OFFICE_ACCOUNT_LABELS[$event->email] ?? 'Other offices',
                ];
            });

        return view('user.calendar', compact('events'));
    }
}
