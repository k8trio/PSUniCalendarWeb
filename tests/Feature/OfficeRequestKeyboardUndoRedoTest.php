<?php

use App\Models\EventRequest;
use App\Models\OfficeRequestHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->office = User::factory()->create([
        'role' => 'office',
        'email' => 'office-keyboard@psu.local',
        'campus' => User::UNIVERSITY_OFFICES,
    ]);
});

function keyboardUndoRedoEvent(array $attributes = []): EventRequest
{
    $start = now()->addDays(10)->setTime(9, 0);

    return EventRequest::create(array_merge([
        'name' => 'Office for Keyboard Tests',
        'email' => 'office-keyboard@psu.local',
        'title' => 'Original event',
        'venue_name' => 'Main Hall',
        'campus' => 'Lingayen Campus',
        'is_university_wide' => false,
        'description' => 'Original description',
        'start_datetime' => $start,
        'end_datetime' => $start->copy()->addHours(2),
        'status' => 'pending',
        'digital_documents' => [],
    ], $attributes));
}

function keyboardUndoRedoRequestData(array $overrides = []): array
{
    $start = now()->addDays(20)->setTime(9, 0);

    return array_merge([
        'title' => 'Updated event',
        'venue_name' => 'Other Hall',
        'event_for' => 'University offices',
        'attention_to' => 'Campus officials',
        'campus' => 'Lingayen Campus',
        'is_university_wide' => '0',
        'description' => 'Updated description',
        'start_datetime' => $start->format('Y-m-d\TH:i'),
        'end_datetime' => $start->copy()->addHours(2)->format('Y-m-d\TH:i'),
    ], $overrides);
}

test('office dashboard supports hidden undo and redo keyboard commands', function () {
    $event = keyboardUndoRedoEvent();
    $this->actingAs($this->office)
        ->post(route('office.request'), keyboardUndoRedoRequestData(['edit_id' => $event->id]));

    $this->get(route('office.dashboard'))
        ->assertOk()
        ->assertSee('id="office-undo-command"', false)
        ->assertSee('id="office-redo-command"', false)
        ->assertSee('data-command-enabled="true"', false)
        ->assertSee(asset('js/office-request-history-shortcuts.js'), false)
        ->assertDontSee('<button type="submit">Undo</button>', false)
        ->assertDontSee('<button type="submit">Redo</button>', false);
});

test('office can undo and redo a new event using the command endpoints', function () {
    $this->actingAs($this->office)
        ->post(route('office.request'), keyboardUndoRedoRequestData())
        ->assertRedirect(route('office.request-status'));

    $event = EventRequest::where('email', $this->office->email)->firstOrFail();
    $this->post(route('office.requests.undo'))->assertSessionHas('success');
    expect($event->refresh()->status)->toBe('cancelled');

    $this->post(route('office.requests.redo'))->assertSessionHas('success');
    expect($event->refresh()->status)->toBe('pending');
});

test('office can undo and redo an edit and a cancellation through the command endpoints', function () {
    $event = keyboardUndoRedoEvent(['status' => 'returned', 'planning_note' => 'Revise the venue.']);

    $this->actingAs($this->office)
        ->post(route('office.request'), keyboardUndoRedoRequestData(['edit_id' => $event->id]));
    $this->post(route('office.requests.undo'))->assertSessionHas('success');
    expect($event->refresh()->title)->toBe('Original event')
        ->and($event->status)->toBe('returned');

    $this->post(route('office.requests.redo'))->assertSessionHas('success');
    expect($event->refresh()->title)->toBe('Updated event')
        ->and($event->status)->toBe('pending');

    $this->post(route('office.requests.cancel', $event->id));
    $this->post(route('office.requests.undo'))->assertSessionHas('success');
    expect($event->refresh()->status)->toBe('pending');
    $this->post(route('office.requests.redo'))->assertSessionHas('success');
    expect($event->refresh()->status)->toBe('cancelled');
});

test('undo history stays scoped to its office and rejects requests changed by Planning', function () {
    $event = keyboardUndoRedoEvent();
    $otherOffice = User::factory()->create(['role' => 'office']);

    $this->actingAs($this->office)
        ->post(route('office.request'), keyboardUndoRedoRequestData(['edit_id' => $event->id]));
    $event->update(['status' => 'approved']);

    $this->actingAs($this->office)
        ->post(route('office.requests.undo'))
        ->assertSessionHas('error', 'This request has changed since your last action, so it cannot be undone.');
    expect($event->refresh()->status)->toBe('approved');

    $this->actingAs($otherOffice)
        ->post(route('office.requests.undo'))
        ->assertSessionHas('error', 'There is no Office request action to undo.');

    $this->actingAs(User::factory()->create(['role' => 'planning_office']))
        ->post(route('office.requests.undo'))
        ->assertRedirect('/office/login');
});
