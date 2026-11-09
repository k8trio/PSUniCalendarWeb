<?php

namespace App\Http\Controllers;

use App\Services\GoogleCalendarEventService;
use App\Services\PlanningDecisionHistoryService;
use DomainException;
use Illuminate\Support\Facades\Auth;

class PlanningDecisionHistoryController extends Controller
{
    public function undo(PlanningDecisionHistoryService $history, GoogleCalendarEventService $calendar)
    {
        try {
            $history->undo(Auth::user(), $calendar);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Your last Planning Office decision was undone.');
    }

    public function redo(PlanningDecisionHistoryService $history, GoogleCalendarEventService $calendar)
    {
        try {
            $history->redo(Auth::user(), $calendar);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Your Planning Office decision was redone.');
    }
}
