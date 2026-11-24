<?php

use App\Models\EventRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function officeViewerEvent(array $attributes = []): EventRequest
{
    $start = now()->addDays(6)->setTime(10, 0);

    return EventRequest::create(array_merge([
        'name' => 'Office of the President',
        'email' => 'op@psu.local',
        'title' => 'Parent office approved event',
        'venue_name' => 'Main Hall',
        'campus' => 'Lingayen Campus',
        'is_university_wide' => false,
        'description' => 'Approved parent office event.',
        'start_datetime' => $start,
        'end_datetime' => $start->copy()->addHours(2),
        'status' => 'approved',
        'digital_documents' => [],
    ], $attributes));
}

function officeCalendarViewer(string $email = 'pmlo@psu.local'): User
{
    $parent = User::firstOrCreate(
        ['email' => 'op@psu.local'],
        [
            'name' => 'Office of the President',
            'password' => Hash::make('Office@123'),
            'role' => 'office',
            'campus' => 'University Offices',
        ]
    );

    return User::create([
        'name' => 'Presidential Management and Liaison Office (PMLO)',
        'email' => $email,
        'password' => Hash::make('Office@123'),
        'role' => 'office_viewer',
        'parent_office_id' => $parent->id,
    ]);
}

test('office calendar viewer accounts are seeded for listed sub-offices, excluding campuses, OUS, and SAS', function () {
    $this->seed(DatabaseSeeder::class);

    $viewers = User::where('role', 'office_viewer')->get();
    expect($viewers)->toHaveCount(35)
        ->and($viewers->pluck('email')->all())->not->toContain(
            'alaminos@psu.local',
            'binmaley@psu.local',
            'lingayen@psu.local',
            'ous@psu.local',
            'sas@psu.local',
        );

    $pmlo = User::where('email', 'pmlo@psu.local')->firstOrFail();
    expect($pmlo->role)->toBe('office_viewer')
        ->and($pmlo->parentOffice->email)->toBe('op@psu.local')
        ->and(Hash::check('Office@123', $pmlo->password))->toBeTrue();
});

test('calendar viewer sees parent-office and university-wide approved events', function () {
    Storage::fake('public');
    Storage::disk('public')->put('event-documents/university-wide-memo.pdf', 'wide memo');
    Storage::disk('public')->put('event-documents/another-office-memo.pdf', 'office memo');

    $viewer = officeCalendarViewer();
    $parentEvent = officeViewerEvent();
    $wideEvent = officeViewerEvent([
        'email' => 'ovprei@psu.local',
        'name' => 'OVPREI',
        'title' => 'University-wide event',
        'is_university_wide' => true,
        'digital_documents' => ['event-documents/university-wide-memo.pdf'],
    ]);
    $otherOfficeEvent = officeViewerEvent([
        'email' => 'ovprei@psu.local',
        'name' => 'OVPREI',
        'title' => 'Another office campus-only event',
        'digital_documents' => ['event-documents/another-office-memo.pdf'],
    ]);
    $pendingParentEvent = officeViewerEvent([
        'title' => 'Unapproved parent event',
        'status' => 'pending',
    ]);
    $pendingWideEvent = officeViewerEvent([
        'email' => 'ovprei@psu.local',
        'name' => 'OVPREI',
        'title' => 'Unapproved university-wide event',
        'is_university_wide' => true,
        'status' => 'pending',
    ]);

    $this->post(route('login.submit'), [
        'email' => $viewer->email,
        'password' => 'Office@123',
    ])->assertRedirect(route('office.viewer-calendar'));
    $this->assertAuthenticatedAs($viewer);

    $response = $this->get(route('office.viewer-calendar'))
        ->assertOk()
        ->assertSee($viewer->name)
        ->assertDontSee('Office of the President Calendar')
        ->assertDontSee('Approved events for your parent office and all university-wide events.')
        ->assertSee('Parent office approved event')
        ->assertSee('University-wide event')
        ->assertDontSee('Another office campus-only event')
        ->assertDontSee('Unapproved parent event')
        ->assertDontSee('Unapproved university-wide event')
        ->assertDontSee('Add Event')
        ->assertDontSee('Request Status');

    expect($response->viewData('events')->pluck('title')->all())
        ->toBe([$parentEvent->title, $wideEvent->title]);

    $wideDocumentUrl = $response->viewData('events')->last()['documents'][0]['url'];
    $this->get($wideDocumentUrl)
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename=university-wide-memo.pdf');
    $this->get(route('office.viewer-calendar.documents.show', [
        'event' => $otherOfficeEvent,
        'documentIndex' => 1,
    ]))->assertNotFound();
    $this->get(route('office.viewer-calendar.documents.show', [
        'event' => $pendingWideEvent,
        'documentIndex' => 1,
    ]))->assertNotFound();
    $this->get(route('office.viewer-calendar.documents.show', [
        'event' => $pendingParentEvent,
        'documentIndex' => 1,
    ]))->assertNotFound();
});

test('calendar viewer sees an approved event notified to its parent office', function () {
    Storage::fake('public');
    Storage::disk('public')->put('event-documents/notified-office-memo.pdf', 'notified memo');

    $viewer = officeCalendarViewer();
    $parentOffice = $viewer->parentOffice;
    $event = officeViewerEvent([
    'name' => 'Planning Office',
    'email' => 'planning@psu.local',
    'title' => 'Notified parent office event',
    'venue_name' => 'Alaminos Hall',
    'campus' => 'Alaminos Campus',
    'digital_documents' => ['event-documents/notified-office-memo.pdf'],
    ]);
    $event->recipients()->attach($parentOffice->id);

    $response = $this->actingAs($viewer)
    ->get(route('office.viewer-calendar'))
    ->assertOk()
    ->assertSee('Notified parent office event');

    expect($response->viewData('events')->pluck('title')->all())
    ->toContain('Notified parent office event');

    $this->get(route('office.viewer-calendar.documents.show', [
    'event' => $event,
    'documentIndex' => 1,
    ]))->assertOk()
    ->assertHeader('content-disposition', 'inline; filename=notified-office-memo.pdf');
});

test('calendar viewers cannot submit requests or enter office and Planning dashboards', function () {
    $viewer = officeCalendarViewer();
    $event = officeViewerEvent(['status' => 'pending']);

    $this->actingAs($viewer)
        ->get(route('office.add-event'))
        ->assertRedirect(route('login'));
    $this->assertGuest();

    $this->actingAs($viewer)
        ->post(route('office.request'), [
            'title' => 'Unauthorized viewer submission',
        ])
        ->assertRedirect(route('login'));
    expect(EventRequest::where('title', 'Unauthorized viewer submission')->exists())->toBeFalse()
        ->and($event->refresh()->status)->toBe('pending');
});

test('Planning Office lists and reports do not treat view-only sub-accounts as offices', function () {
    $viewer = officeCalendarViewer();
    $planning = User::factory()->create(['role' => 'planning_office']);

    $this->actingAs($planning)
        ->get(route('planning_office.dashboard'))
        ->assertOk()
        ->assertDontSee($viewer->name)
        ->assertDontSee($viewer->email);

    $this->get(route('planning_office.reports.events.preview'))
        ->assertOk()
        ->assertDontSee($viewer->name);
});
