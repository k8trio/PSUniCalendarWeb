<?php

use App\Models\ActivityLog;
use App\Models\EventRequest;
use App\Models\PlanningDecisionHistory;
use App\Models\User;
use App\Services\GoogleCalendarEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->planning = User::factory()->create([
        'role' => 'planning_office',
        'email' => 'planning-history@psu.local',
    ]);
});

function planningHistoryEvent(array $attributes = []): EventRequest
{
    $start = now()->addDays(8)->setTime(9, 0);

    return EventRequest::create(array_merge([
        'name' => 'Office for Planning History',
        'email' => 'office-planning-history@psu.local',
        'title' => 'Planning history test event',
        'venue_name' => 'Main Hall',
        'campus' => 'Lingayen Campus',
        'is_university_wide' => false,
        'description' => 'Original event details',
        'start_datetime' => $start,
        'end_datetime' => $start->copy()->addHours(2),
        'status' => 'pending',
        'planning_note' => null,
        'digital_documents' => [],
    ], $attributes));
}

test('Planning Office can undo and redo an approval and its schedule changes', function () {
    $event = planningHistoryEvent(['status' => 'conflict']);
    $start = now()->addDays(12)->setTime(10, 0);

    $this->actingAs($this->planning)
        ->post(route('planning_office.approve', $event->id), [
            'venue_name' => 'Approved Hall',
            'campus' => 'Alaminos Campus',
            'start_datetime' => $start->format('Y-m-d H:i:s'),
            'end_datetime' => $start->copy()->addHours(2)->format('Y-m-d H:i:s'),
            'is_university_wide' => '1',
            'planning_note' => 'Approved with revised schedule.',
        ])
        ->assertSessionHas('success', 'Event approved successfully.');

    expect($event->refresh()->status)->toBe('approved')
        ->and($event->venue_name)->toBe('Approved Hall')
        ->and($event->campus)->toBe('Alaminos Campus');

    $this->post(route('planning_office.decisions.undo'))->assertSessionHas('success');
    expect($event->refresh()->status)->toBe('conflict')
        ->and($event->venue_name)->toBe('Main Hall')
        ->and($event->campus)->toBe('Lingayen Campus')
        ->and($event->planning_note)->toBeNull()
        ->and($event->is_university_wide)->toBeFalse();

    $this->post(route('planning_office.decisions.redo'))->assertSessionHas('success');
    expect($event->refresh()->status)->toBe('approved')
        ->and($event->venue_name)->toBe('Approved Hall')
        ->and($event->campus)->toBe('Alaminos Campus')
        ->and($event->planning_note)->toBe('Approved with revised schedule.');
});

test('undoing an approval removes its Google Calendar event and redo creates a replacement', function () {
    $event = planningHistoryEvent();
    $calendar = Mockery::mock(GoogleCalendarEventService::class);
    $calendar->shouldReceive('isConfigured')->once()->andReturn(true);
    $calendar->shouldReceive('insert')->once()->andReturn('google-event-original');
    $calendar->shouldReceive('deleteForHistory')->once()->with('google-event-original', $event->id);
    $calendar->shouldReceive('insertForHistory')->once()->andReturn('google-event-restored');
    $this->app->instance(GoogleCalendarEventService::class, $calendar);

    $this->actingAs($this->planning)
        ->post(route('planning_office.approve', $event->id))
        ->assertSessionHas('success', 'Event approved and added to Google Calendar.');
    expect($event->refresh()->google_event_id)->toBe('google-event-original');

    $this->post(route('planning_office.decisions.undo'))->assertSessionHas('success');
    expect($event->refresh()->status)->toBe('pending')
        ->and($event->google_event_id)->toBeNull();

    $this->post(route('planning_office.decisions.redo'))->assertSessionHas('success');
    expect($event->refresh()->status)->toBe('approved')
        ->and($event->google_event_id)->toBe('google-event-restored');
});

test('Planning Office can undo and redo reject and return decisions with their notes', function () {
    $rejected = planningHistoryEvent(['planning_note' => 'Previous note']);
    $this->actingAs($this->planning)
        ->post(route('planning_office.reject', $rejected->id), ['planning_note' => 'Rejected by mistake'])
        ->assertSessionHas('success');
    expect($rejected->refresh()->status)->toBe('rejected');

    $this->post(route('planning_office.decisions.undo'))->assertSessionHas('success');
    expect($rejected->refresh()->status)->toBe('pending')
        ->and($rejected->planning_note)->toBe('Previous note');
    $this->post(route('planning_office.decisions.redo'))->assertSessionHas('success');
    expect($rejected->refresh()->status)->toBe('rejected')
        ->and($rejected->planning_note)->toBe('Rejected by mistake');

    $returned = planningHistoryEvent(['title' => 'Returned event', 'status' => 'conflict']);
    $this->post(route('planning_office.return', $returned->id), ['planning_note' => 'Please correct the venue.'])
        ->assertSessionHas('success');
    $this->post(route('planning_office.decisions.undo'))->assertSessionHas('success');
    expect($returned->refresh()->status)->toBe('conflict')
        ->and($returned->planning_note)->toBeNull();
    $this->post(route('planning_office.decisions.redo'))->assertSessionHas('success');
    expect($returned->refresh()->status)->toBe('returned')
        ->and($returned->planning_note)->toBe('Please correct the venue.');

    expect(PlanningDecisionHistory::where('planning_email', $this->planning->email)->count())->toBe(2)
        ->and(ActivityLog::where('email', $this->planning->email)->where('action', 'decision_undone')->count())->toBe(2)
        ->and(ActivityLog::where('email', $this->planning->email)->where('action', 'decision_redone')->count())->toBe(2);
});

test('Planning decision shortcuts are hidden and available on dashboard and review pages', function () {
    $event = planningHistoryEvent();
    $this->actingAs($this->planning)
        ->post(route('planning_office.reject', $event->id));

    foreach ([route('planning_office.dashboard'), route('planning_office.pending')] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('id="planning-undo-command"', false)
            ->assertSee('id="planning-redo-command"', false)
            ->assertSee('planning-decision-history-shortcuts.js')
            ->assertSee('data-command-enabled="true"', false)
            ->assertDontSee('<button type="submit">Undo decision</button>', false);
    }
});

test('decision history is isolated per Planning Office account and rejects stale undo', function () {
    $event = planningHistoryEvent();
    $otherPlanning = User::factory()->create(['role' => 'planning_office']);

    $this->actingAs($this->planning)
        ->post(route('planning_office.reject', $event->id))
        ->assertSessionHas('success', 'Event request rejected.');
    $event->update(['title' => 'Modified after the decision']);

    $this->post(route('planning_office.decisions.undo'))
        ->assertSessionHas('error', 'This event changed after the decision, so it cannot be undone safely.');
    expect($event->refresh()->status)->toBe('rejected')
        ->and($event->title)->toBe('Modified after the decision');

    $this->actingAs($otherPlanning)
        ->post(route('planning_office.decisions.undo'))
        ->assertSessionHas('error', 'There is no Planning Office decision to undo.');

    $this->actingAs(User::factory()->create(['role' => 'office']))
        ->post(route('planning_office.decisions.undo'))
        ->assertRedirect('/planning-office/login');
});
