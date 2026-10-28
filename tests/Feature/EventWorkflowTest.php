<?php

use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('event requests no longer include the SDG field', function () {
    expect(Schema::hasColumn('event_requests', 'sdg_number'))->toBeFalse();
});

test('office add event page presents the event form as a standalone page', function () {
    $office = User::factory()->create(['role' => 'office']);

    $this->actingAs($office)->get('/office/add-event')
        ->assertOk()
        ->assertSee('od-add-event-page')
        ->assertSee('id="venue-request-form"', false)
        ->assertSee('Back to Dashboard');
});

test('office request status page presents request status as a standalone page', function () {
    $office = User::factory()->create(['role' => 'office']);

    $this->actingAs($office)->get('/office/request-status')
        ->assertOk()
        ->assertSee('od-request-status-page')
        ->assertSee('id="request-status"', false)
        ->assertDontSee('id="activity-log"', false)
        ->assertSee('data-nav-section="request-status"', false)
        ->assertSee('data-nav-section="activity-log"', false)
        ->assertSee('Back to Dashboard');
});

test('office recent activity page displays only the activity log', function () {
    $office = User::factory()->create(['role' => 'office']);

    $this->actingAs($office)->get('/office/recent-activity')
        ->assertOk()
        ->assertSee('od-activity-page')
        ->assertSee('id="activity-log"', false)
        ->assertDontSee('id="request-status"', false)
        ->assertSee('Recent Activity')
        ->assertSee('Back to Dashboard');
});

test('office can delete only its own cancelled requests', function () {
    $office = User::factory()->create(['role' => 'office']);
    $otherOffice = User::factory()->create(['role' => 'office']);

    $cancelledRequest = EventRequest::create([
        'name' => $office->name,
        'email' => $office->email,
        'title' => 'Cancelled request',
        'venue_name' => 'Room 101',
        'campus' => 'Lingayen Campus',
        'start_datetime' => now()->addDay()->setTime(9, 0),
        'end_datetime' => now()->addDay()->setTime(10, 0),
        'status' => 'cancelled',
    ]);
    $pendingRequest = EventRequest::create([
        'name' => $office->name,
        'email' => $office->email,
        'title' => 'Pending request',
        'venue_name' => 'Room 102',
        'campus' => 'Lingayen Campus',
        'start_datetime' => now()->addDays(2)->setTime(9, 0),
        'end_datetime' => now()->addDays(2)->setTime(10, 0),
        'status' => 'pending',
    ]);
    $otherRequest = EventRequest::create([
        'name' => $otherOffice->name,
        'email' => $otherOffice->email,
        'title' => 'Another office cancelled request',
        'venue_name' => 'Room 103',
        'campus' => 'Lingayen Campus',
        'start_datetime' => now()->addDays(3)->setTime(9, 0),
        'end_datetime' => now()->addDays(3)->setTime(10, 0),
        'status' => 'cancelled',
    ]);

    $this->actingAs($office)->delete("/office/requests/{$cancelledRequest->id}")
        ->assertRedirect(route('office.request-status'))
        ->assertSessionHas('success', 'Cancelled request deleted.');
    $this->assertDatabaseMissing('event_requests', ['id' => $cancelledRequest->id]);

    $this->delete("/office/requests/{$pendingRequest->id}")->assertNotFound();
    $this->delete("/office/requests/{$otherRequest->id}")->assertNotFound();
    $this->assertDatabaseHas('event_requests', ['id' => $pendingRequest->id]);
    $this->assertDatabaseHas('event_requests', ['id' => $otherRequest->id]);
});

test('office request rejects an overlapping venue and time', function () {
    $division = User::factory()->create([
        'email' => 'lingayen@psu.local',
        'role' => 'office',
        'campus' => 'Lingayen Campus',
    ]);
    $start = now()->addDay()->setTime(10, 0);

    EventRequest::create([
        'name' => 'Existing event owner',
        'email' => 'owner@psu.local',
        'title' => 'Existing approved event',
        'venue_name' => 'Main Auditorium',
        'campus' => 'Lingayen Campus',
        'start_datetime' => $start,
        'end_datetime' => $start->copy()->addHour(),
        'status' => 'approved',
    ]);

    $this->actingAs($division)->post('/office/request-venue', [
        'title' => 'Division seminar',
        'venue_name' => 'Main Auditorium',
        'event_for' => 'Office of the University President; All Vice Presidents; Executive Director, School of Advanced Studies; Executive Director, Open University System; All Campus Executive Directors',
        'attention_to' => 'Office of Student Affairs',
        'campus' => 'Lingayen Campus',
        'start_datetime' => $start->format('Y-m-d\\TH:i'),
        'end_datetime' => $start->copy()->addHour()->format('Y-m-d\\TH:i'),
        'description' => 'An education event',
    ])->assertRedirect()
        ->assertSessionHas('error', 'This venue is already booked during that time. Choose another venue or time before submitting.');

    $this->assertDatabaseMissing('event_requests', ['title' => 'Division seminar']);
    $this->assertDatabaseHas('event_requests', ['title' => 'Existing approved event']);
});

test('office request detects venue conflicts despite case or surrounding spaces', function () {
    $office = User::factory()->create(['role' => 'office', 'campus' => 'Lingayen Campus']);
    $start = now()->addDay()->setTime(10, 0);

    EventRequest::create([
        'name' => 'Existing event owner',
        'email' => 'owner@psu.local',
        'title' => 'Existing booking',
        'venue_name' => 'Main Auditorium',
        'campus' => 'Lingayen Campus',
        'start_datetime' => $start,
        'end_datetime' => $start->copy()->addHour(),
        'status' => 'approved',
    ]);

    $this->actingAs($office)->post('/office/request-venue', [
        'title' => 'Overlapping booking',
        'venue_name' => '  main auditorium  ',
        'campus' => 'Lingayen Campus',
        'start_datetime' => $start->copy()->addMinutes(30)->format('Y-m-d\\TH:i'),
        'end_datetime' => $start->copy()->addHours(2)->format('Y-m-d\\TH:i'),
    ])->assertRedirect()->assertSessionHas('error');

    $this->assertDatabaseMissing('event_requests', ['title' => 'Overlapping booking']);
});

test('conflict suggestions move to the next date and stay within office hours', function () {
    $office = User::factory()->create(['role' => 'office', 'campus' => 'Lingayen Campus']);
    $start = now()->addDays(2)->setTime(9, 0);

    EventRequest::create([
        'name' => 'Existing event owner',
        'email' => 'owner@psu.local',
        'title' => 'Full-day booking',
        'venue_name' => 'Main Auditorium',
        'campus' => 'Lingayen Campus',
        'start_datetime' => $start,
        'end_datetime' => $start->copy()->setTime(17, 0),
        'status' => 'approved',
    ]);

    $query = http_build_query([
        'venue_name' => 'Main Auditorium',
        'campus' => 'Lingayen Campus',
        'start_datetime' => $start->format('Y-m-d\\TH:i'),
        'end_datetime' => $start->copy()->setTime(17, 0)->format('Y-m-d\\TH:i'),
    ]);
    $response = $this->actingAs($office)->get('/office/check-availability?' . $query)->assertOk();

    expect($response->json('state'))->toBe('conflict')
        ->and($response->json('suggestion.start'))->toBe($start->copy()->addDay()->setTime(8, 0)->format('Y-m-d\\TH:i'))
        ->and($response->json('suggestion.end'))->toBe($start->copy()->addDay()->setTime(16, 0)->format('Y-m-d\\TH:i'));
});

test('office requests cannot start or end outside office hours', function () {
    $office = User::factory()->create(['role' => 'office', 'campus' => 'Lingayen Campus']);
    $start = now()->addDay()->setTime(7, 0);

    $this->actingAs($office)->post('/office/request-venue', [
        'title' => 'Early meeting',
        'venue_name' => 'Main Auditorium',
        'campus' => 'Lingayen Campus',
        'start_datetime' => $start->format('Y-m-d\\TH:i'),
        'end_datetime' => $start->copy()->setTime(9, 0)->format('Y-m-d\\TH:i'),
    ])->assertRedirect()
        ->assertSessionHasErrors('start_datetime');

    $this->assertDatabaseMissing('event_requests', ['title' => 'Early meeting']);
});

test('university-wide office requests use all campuses without requiring a campus selection', function () {
    $office = User::factory()->create([
        'email' => 'registrar@psu.local',
        'role' => 'office',
    ]);

    $start = now()->addDay()->setTime(10, 0);

    $this->actingAs($office)->post('/office/request-venue', [
        'title' => 'University seminar',
        'venue_name' => 'University Auditorium',
        'is_university_wide' => '1',
        'start_datetime' => $start->format('Y-m-d\\TH:i'),
        'end_datetime' => $start->copy()->addHour()->format('Y-m-d\\TH:i'),
    ])->assertRedirect();

    $this->assertDatabaseHas('event_requests', [
        'title' => 'University seminar',
        'campus' => 'All Campus',
        'is_university_wide' => true,
    ]);
});

test('public calendar lists only approved events and exposes no editing controls', function () {
    EventRequest::create([
        'name' => 'Division owner',
        'email' => 'division@psu.local',
        'title' => 'Public seminar',
        'venue_name' => 'Science Hall',
        'campus' => 'Alaminos Campus',
        'start_datetime' => now()->addDay(),
        'end_datetime' => now()->addDay()->addHour(),
        'status' => 'approved',
    ]);
    EventRequest::create([
        'name' => 'Division owner',
        'email' => 'division@psu.local',
        'title' => 'Unpublished request',
        'venue_name' => 'Science Hall',
        'campus' => 'Alaminos Campus',
        'start_datetime' => now()->addDays(2),
        'end_datetime' => now()->addDays(2)->addHour(),
        'status' => 'pending',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Public seminar')
        ->assertDontSee('SDG')
        ->assertDontSee('Unpublished request')
        ->assertDontSee('Submit Event Request');
});

test('requester offices cannot access planning office administration', function () {
    $requester = User::factory()->create([
        'email' => 'registrar@psu.local',
        'role' => 'office',
    ]);

    $this->actingAs($requester)
        ->get('/planning-office/manage-venues')
        ->assertRedirect('/planning-office/login');
});