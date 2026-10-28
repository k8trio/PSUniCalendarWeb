<?php

use App\Models\ActivityLog;
use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function dashboardEvent(array $attributes = []): EventRequest
{
    $start = $attributes['start_datetime'] ?? now()->addDays(3)->setTime(9, 0);

    return EventRequest::create(array_merge([
        'name' => 'Office of Student Affairs',
        'email' => 'osa@psu.local',
        'title' => 'Sample event',
        'venue_name' => 'Main Hall',
        'campus' => 'Lingayen Campus',
        'start_datetime' => $start,
        'end_datetime' => $start->copy()->addHours(2),
        'status' => 'approved',
    ], $attributes));
}

beforeEach(function () {
    $this->planning = User::factory()->create(['role' => 'planning_office', 'email' => 'planning@psu.local']);
});

test('planning office search finds offices and event details', function () {
    $office = User::factory()->create([
        'name' => 'Office of the President',
        'email' => 'op@psu.local',
        'role' => 'office',
        'campus' => User::UNIVERSITY_OFFICES,
    ]);
    dashboardEvent([
        'name' => 'Alex Event Organizer',
        'email' => $office->email,
        'title' => 'Planning Symposium',
        'venue_name' => 'Innovation Hall',
    ]);

    $this->actingAs($this->planning)->get('/planning-office/search?q=President')
        ->assertOk()
        ->assertSee('Back to dashboard')
        ->assertSee('Office of the President')
        ->assertSee('Planning Symposium');

    $this->get('/planning-office/search?q=Alex+Event+Organizer')
        ->assertOk()
        ->assertSee('Planning Symposium')
        ->assertSee('Innovation Hall')
        ->assertSee('Alex Event Organizer');

    $this->get('/planning-office/search?q=Planning+Symposium')
        ->assertOk()
        ->assertSee('Planning Symposium')
        ->assertSee(route('planning_office.events.show', ['event' => EventRequest::where('title', 'Planning Symposium')->first(), 'q' => 'Planning Symposium']), false);

    $this->get('/planning-office/search?q=Innovation+Hall')
        ->assertOk()
        ->assertSee('Planning Symposium')
        ->assertSee('Innovation Hall');
});

test('planning office can open searched event details and view its submitted memo', function () {
    Storage::fake('public');
    Storage::disk('public')->put('event-documents/planning-memo.pdf', 'memo file contents');
    $event = dashboardEvent([
        'name' => 'Alex Event Organizer',
        'email' => 'op@psu.local',
        'title' => 'Student Council Forum',
        'venue_name' => 'Innovation Hall',
        'campus' => 'Lingayen Campus',
        'event_for' => 'Student Council',
        'attention_to' => 'University Officials',
        'description' => 'Annual coordination forum.',
        'digital_documents' => ['event-documents/planning-memo.pdf'],
    ]);

    $this->actingAs($this->planning)
        ->get('/planning-office/search?q=Student+Council')
        ->assertOk()
        ->assertSee('Student Council Forum')
        ->assertSee(route('planning_office.events.show', ['event' => $event, 'q' => 'Student Council']), false);

    $this->get(route('planning_office.events.show', ['event' => $event, 'q' => 'Student Council']))
        ->assertOk()
        ->assertSee('Annual coordination forum.')
        ->assertSee('Student Council')
        ->assertSee('University Officials')
        ->assertSee('Memo attachment')
        ->assertSee('Advisory Memo')
        ->assertSee('Back to search results');

    $documentResponse = $this->get(route('planning_office.events.documents.show', ['event' => $event, 'documentIndex' => 1]))
        ->assertOk();
    expect($documentResponse->headers->get('content-disposition'))
        ->toContain('inline')
        ->toContain('planning-memo.pdf');
    Storage::disk('public')->assertExists('event-documents/planning-memo.pdf');
});

test('planning office notifications include a back-to-dashboard link', function () {
    $this->actingAs($this->planning)->get('/planning-office/notifications')
        ->assertOk()
        ->assertSee('Back to dashboard');
});

test('planning office search shows one generic message when there are no results', function () {
    $this->actingAs($this->planning)->get('/planning-office/search?q=NoMatchingResult123')
        ->assertOk()
        ->assertSee('No results found.')
        ->assertDontSee('No offices match this search.')
        ->assertDontSee('No events match this search.')
        ->assertDontSee('<h2 class="od-card__title">Offices</h2>', false)
        ->assertDontSee('<h2 class="od-card__title">Events</h2>', false);
});

test('office account details show approved events scheduled in the academic year', function () {
    $this->travelTo(Carbon::parse('2026-10-27 12:00:00'));
    $office = User::factory()->create([
        'name' => 'Office of the President',
        'email' => 'op@psu.local',
        'role' => 'office',
        'campus' => User::UNIVERSITY_OFFICES,
    ]);

    dashboardEvent([
        'email' => $office->email,
        'title' => 'Academic Year Approved Event',
        'start_datetime' => Carbon::parse('2027-05-28 10:00:00'),
    ]);
    dashboardEvent([
        'email' => $office->email,
        'title' => 'Outside Academic Year Approved Event',
        'start_datetime' => Carbon::parse('2027-05-29 10:00:00'),
    ]);

    $response = $this->actingAs($this->planning)
        ->get("/planning-office/offices/{$office->id}")
        ->assertOk()
        ->assertSee('Approved events · Academic Year 2026-2027')
        ->assertSee('Academic Year Approved Event');

    expect($response->viewData('approvedAcademicYearEvents')->pluck('title')->all())
        ->toBe(['Academic Year Approved Event']);
});

test('dashboard groups event totals by submitting university office', function () {
    dashboardEvent(['title' => 'Lingayen Intrams', 'email' => 'op@psu.local']);
    dashboardEvent(['title' => 'University Meet', 'email' => 'op@psu.local', 'is_university_wide' => true, 'venue_name' => 'Plaza']);
    dashboardEvent(['title' => 'Finished Seminar', 'email' => 'ovprei@psu.local', 'start_datetime' => now()->subDays(5)->setTime(9, 0)]);
    dashboardEvent(['title' => 'Alaminos Request', 'email' => 'ovpaass@psu.local', 'campus' => 'Alaminos Campus', 'venue_name' => 'Alaminos Gym', 'status' => 'pending']);

    $response = $this->actingAs($this->planning)->get('/planning-office')->assertOk()
        ->assertSee('Planning Office Dashboard')
        ->assertSee('Alaminos Request')
        ->assertSee('Campus-specific')
        ->assertSee('Events by office')
        ->assertDontSee('SDG');

    $response->assertSee('function officeAcronym(name)')
        ->assertSee('label: officeAcronym(name)')
        ->assertSee("labels: ['Open conflicts', 'Resolved conflicts']", false)
        ->assertSee('return officeAcronym(this.getLabelForValue(value))')
        ->assertSee('charts.trends.series[name].some');

    expect($response->viewData('charts'))->not->toHaveKey('byCategory');

    $summary = $response->viewData('summary');
    expect($summary['total'])->toBe(3)
        ->and($summary['upcoming'])->toBe(2)
        ->and($summary['completed'])->toBe(1)
        ->and($summary['pending'])->toBe(1);

    $byOffice = array_combine($response->viewData('charts')['byOffice']['labels'], $response->viewData('charts')['byOffice']['values']);
    expect($byOffice['OFFICE OF THE PRESIDENT (OP)'])->toBe(2)
        ->and($byOffice['OFFICE OF THE VICE PRESIDENT FOR RESEARCH, EXTENSION, AND INNOVATION (OVPREI)'])->toBe(1)
        ->and(array_sum($byOffice))->toBe(3);
});

test('overview breaks the numbers down by university office', function () {
    dashboardEvent(['title' => 'Lingayen Intrams', 'email' => 'op@psu.local']);
    dashboardEvent(['title' => 'University Meet', 'email' => 'op@psu.local', 'is_university_wide' => true, 'venue_name' => 'Plaza']);
    dashboardEvent(['title' => 'Alaminos Request', 'email' => 'ovpaass@psu.local', 'campus' => 'Alaminos Campus', 'venue_name' => 'Alaminos Gym', 'status' => 'pending']);

    $response = $this->actingAs($this->planning)->get('/planning-office')->assertOk()
        ->assertSee('Show by office')
        ->assertDontSee('Apply filters');

    $offices = $response->viewData('officeSummaries');
    expect($offices->keys()->all())->toBe(array_values(User::UNIVERSITY_OFFICE_ACCOUNT_LABELS))
        ->and($offices['OFFICE OF THE PRESIDENT (OP)']['total'])->toBe(2)
        ->and($offices['OFFICE OF THE VICE PRESIDENT FOR ACADEMICS AND STUDENT SERVICES (OVPASS)']['pending'])->toBe(1)
        ->and($offices['OFFICE OF THE VICE PRESIDENT FOR QUALITY ASSURANCE (OVPQA)']['total'])->toBe(0)
        ->and($offices->sum('total'))->toBe($response->viewData('summary')['total']);
});

test('overview totals include only events in the current academic year', function () {
    $this->travelTo(Carbon::parse('2026-10-27 12:00:00'));

    dashboardEvent(['title' => 'Academic year start', 'start_datetime' => Carbon::parse('2026-08-10 09:00:00')]);
    dashboardEvent(['title' => 'Academic year end', 'start_datetime' => Carbon::parse('2027-05-28 10:00:00')]);
    dashboardEvent(['title' => 'Before academic year', 'start_datetime' => Carbon::parse('2026-08-09 09:00:00')]);
    dashboardEvent(['title' => 'After academic year', 'start_datetime' => Carbon::parse('2027-05-29 09:00:00')]);
    dashboardEvent(['title' => 'Academic year pending', 'status' => 'pending', 'start_datetime' => Carbon::parse('2027-05-27 09:00:00')]);
    dashboardEvent(['title' => 'Outside academic year pending', 'status' => 'pending', 'start_datetime' => Carbon::parse('2027-05-29 11:00:00')]);

    $response = $this->actingAs($this->planning)->get('/planning-office')->assertOk();

    expect($response->viewData('academicYear')['label'])->toBe('2026-2027')
        ->and($response->viewData('summary')['total'])->toBe(2)
        ->and($response->viewData('summary')['upcoming'])->toBe(1)
        ->and($response->viewData('summary')['completed'])->toBe(1)
        ->and($response->viewData('summary')['pending'])->toBe(1)
        ->and($response->viewData('officeSummaries')->sum('total'))->toBe(2);

    $response->assertSee('Academic Year 2026-2027')
        ->assertSee('Aug 10, 2026')
        ->assertSee('May 28, 2027');
});

test('planning office archive lists the three most recent completed academic years', function () {
    $this->travelTo(Carbon::parse('2026-10-27 12:00:00'));

    $oldestArchived = dashboardEvent([
        'title' => 'Oldest archived event',
        'email' => 'ovpaass@psu.local',
        'start_datetime' => Carbon::parse('2023-09-10 09:00:00'),
    ]);
    $previousYear = dashboardEvent([
        'title' => 'Previous year approved event',
        'email' => 'ovprei@psu.local',
        'start_datetime' => Carbon::parse('2024-09-10 09:00:00'),
    ]);
    dashboardEvent([
        'title' => 'Previous year rejected event',
        'start_datetime' => Carbon::parse('2024-09-11 09:00:00'),
        'status' => 'rejected',
    ]);
    $latestArchived = dashboardEvent([
        'title' => 'Latest archived event',
        'email' => 'op@psu.local',
        'start_datetime' => Carbon::parse('2025-09-10 09:00:00'),
    ]);
    dashboardEvent([
        'title' => 'Current academic year event',
        'start_datetime' => Carbon::parse('2026-09-10 09:00:00'),
    ]);
    dashboardEvent([
        'title' => 'Outside academic year',
        'start_datetime' => Carbon::parse('2025-07-10 09:00:00'),
    ]);
    $septemberConflict = ActivityLog::record('planning@psu.local', 'conflict_resolved', 'Resolved September conflict');
    $septemberConflict->forceFill(['created_at' => Carbon::parse('2024-09-15 12:00:00')])->save();
    $mayConflict = ActivityLog::record('planning@psu.local', 'conflict_resolved', 'Resolved May conflict');
    $mayConflict->forceFill(['created_at' => Carbon::parse('2025-05-15 12:00:00')])->save();
    $outsideYearConflict = ActivityLog::record('planning@psu.local', 'conflict_resolved', 'Outside archive year');
    $outsideYearConflict->forceFill(['created_at' => Carbon::parse('2025-06-15 12:00:00')])->save();

    $response = $this->actingAs($this->planning)->get('/planning-office/archive')->assertOk();
    $academicYears = $response->viewData('academicYears');

    expect($academicYears->pluck('label')->all())->toBe(['2025-2026', '2024-2025', '2023-2024'])
        ->and($response->viewData('selectedYear')['label'])->toBe('2025-2026')
        ->and($response->viewData('events')->pluck('id')->all())->toBe([$latestArchived->id])
        ->and($response->viewData('analytics')['total'])->toBe(1)
        ->and($response->viewData('analytics')['campusSpecific'])->toBe(1)
        ->and($response->viewData('analytics')['offices']['OFFICE OF THE PRESIDENT (OP)'])->toBe(1)
        ->and($response->viewData('analytics')['offices']['OFFICE OF THE VICE PRESIDENT FOR RESEARCH, EXTENSION, AND INNOVATION (OVPREI)'])->toBe(0);

    $response->assertSee('archive-office-chart')
        ->assertSee('archive-monthly-chart')
        ->assertSee('archive-scope-chart')
        ->assertSee('Approved events by office')
        ->assertSee('September 2025')
        ->assertDontSee('Submitting office');
    expect($response->viewData('analytics')['resolvedConflicts'])->toBe(0);

    $previousYearResponse = $this->get('/planning-office/archive?year=2024-2025')
        ->assertOk()
        ->assertSee('Previous year approved event')
        ->assertSee('September 2024')
        ->assertDontSee('Previous year rejected event')
        ->assertDontSee('Current academic year event');

    expect($previousYearResponse->viewData('events')->pluck('id')->all())->toBe([$previousYear->id])
        ->and($previousYearResponse->viewData('analytics')['offices']['OFFICE OF THE VICE PRESIDENT FOR RESEARCH, EXTENSION, AND INNOVATION (OVPREI)'])->toBe(1);
    expect($previousYearResponse->viewData('analytics')['resolvedConflicts'])->toBe(2);

    $this->get('/planning-office/archive?year=2023-2024')
        ->assertOk()
        ->assertSee('Oldest archived event');

    $this->get('/planning-office/archive?year=2022-2023')
        ->assertSessionHasErrors('year');
});

test('office filter keeps requests submitted by that office regardless of event campus', function () {
    dashboardEvent(['title' => 'Lingayen Intrams', 'email' => 'op@psu.local']);
    dashboardEvent(['title' => 'Binmaley Fair', 'email' => 'ovpafm@psu.local', 'campus' => 'Binmaley Campus', 'venue_name' => 'Binmaley Hall']);
    dashboardEvent(['title' => 'University Meet', 'email' => 'ovpafm@psu.local', 'is_university_wide' => true, 'venue_name' => 'Plaza']);

    $office = User::UNIVERSITY_OFFICE_ACCOUNT_LABELS['ovpafm@psu.local'];
    $response = $this->actingAs($this->planning)->get('/planning-office?office=' . urlencode($office))->assertOk();

    expect($response->viewData('summary')['total'])->toBe(2)
        ->and($response->viewData('upcoming')->pluck('title')->all())->toEqualCanonicalizing(['Binmaley Fair', 'University Meet']);
});

test('invalid dashboard filters are rejected', function () {
    $this->actingAs($this->planning)->get('/planning-office?office=Fake+Office')->assertSessionHasErrors('office');
    $this->actingAs($this->planning)->get('/planning-office?from=2026-10-10&to=2026-10-01')->assertSessionHasErrors('to');
});

test('planning office sidebar lists university offices without campus groups', function () {
    User::factory()->create([
        'name' => 'Office of the President',
        'email' => 'op@psu.local',
        'role' => 'office',
        'campus' => User::UNIVERSITY_OFFICES,
    ]);
    User::factory()->create([
        'name' => 'PSU Alaminos Campus',
        'email' => 'alaminos@psu.local',
        'role' => 'office',
        'campus' => 'Alaminos Campus',
    ]);

    $response = $this->actingAs($this->planning)->get('/planning-office')->assertOk();

    $response->assertSee('University Offices')
        ->assertSee('Office of the President')
        ->assertDontSee('PSU Alaminos Campus');
});

test('conflicting submissions are listed as scheduling conflicts', function () {
    dashboardEvent(['title' => 'Approved Booking']);
    dashboardEvent(['title' => 'Clashing Request', 'status' => 'conflict']);

    $response = $this->actingAs($this->planning)->get('/planning-office')->assertOk();

    expect($response->viewData('summary')['conflicts'])->toBe(1)
        ->and($response->viewData('conflicts')->first()['type'])->toBe('Overlaps an approved event');
});

test('planning office can return a request for revision and it is logged', function () {
    $request = dashboardEvent(['status' => 'pending', 'title' => 'Needs Changes']);

    $this->actingAs($this->planning)->post("/planning-office/return/{$request->id}", ['planning_note' => ''])
        ->assertSessionHasErrors('planning_note');

    $this->actingAs($this->planning)->post("/planning-office/return/{$request->id}", ['planning_note' => 'Please attach the program.'])
        ->assertSessionHas('success');

    $request->refresh();
    expect($request->status)->toBe('returned')
        ->and($request->planning_note)->toBe('Please attach the program.')
        ->and(ActivityLog::where('action', 'returned')->where('email', 'planning@psu.local')->exists())->toBeTrue();
});

test('approving a conflict request records a conflict resolution', function () {
    $request = dashboardEvent(['status' => 'conflict', 'title' => 'Was Clashing']);

    $this->actingAs($this->planning)->post("/planning-office/approve/{$request->id}")->assertRedirect();

    expect($request->refresh()->status)->toBe('approved')
        ->and(ActivityLog::where('action', 'conflict_resolved')->exists())->toBeTrue();
});

test('only the planning office can return requests', function () {
    $request = dashboardEvent(['status' => 'pending']);
    $office = User::factory()->create(['role' => 'office']);

    $this->actingAs($office)->post("/planning-office/return/{$request->id}", ['planning_note' => 'x'])
        ->assertRedirect('/planning-office/login');
    expect($request->refresh()->status)->toBe('pending');
});

test('the planning office calendar shows approved events only', function () {
    $approved = dashboardEvent([
        'title' => 'Approved On Calendar',
        'digital_documents' => ['event-documents/signed-advisory.pdf'],
    ]);
    dashboardEvent(['title' => 'Pending Off Calendar', 'status' => 'pending']);
    dashboardEvent(['title' => 'Conflict Off Calendar', 'status' => 'conflict']);

    $response = $this->actingAs($this->planning)->get('/planning-office')->assertOk();
    $calendarEvents = $response->viewData('calendarEvents');

    expect($calendarEvents->pluck('title')->all())->toBe(['Approved On Calendar'])
        ->and($calendarEvents->first()['documents'])->toBe([
            [
                'label' => 'Advisory Memo',
                'url' => route('planning_office.events.documents.show', [
                    'event' => $approved,
                    'documentIndex' => 1,
                ]),
            ],
        ]);
});

test('pending and returned requests never appear on the public calendar', function () {
    dashboardEvent(['title' => 'Public Approved Event']);
    dashboardEvent(['title' => 'Secret Pending Event', 'status' => 'pending']);
    dashboardEvent(['title' => 'Secret Returned Event', 'status' => 'returned']);

    $this->get('/')->assertOk()
        ->assertSee('Public Approved Event')
        ->assertDontSee('Secret Pending Event')
        ->assertDontSee('Secret Returned Event');
});

test('an office can edit a request returned for revision', function () {
    $office = User::factory()->create(['role' => 'office', 'email' => 'osa@psu.local']);
    $request = dashboardEvent(['status' => 'returned']);

    $this->actingAs($office)->get("/office/requests/{$request->id}/edit")
        ->assertSessionHas('info')
        ->assertSessionMissing('error');
    $this->actingAs($office)->get('/office')->assertOk()->assertSee('Returned for revision');
});
