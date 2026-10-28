<?php

use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('public calendar filters by university office instead of campus', function () {
    $event = EventRequest::create([
        'name' => 'Office contact',
        'email' => 'op@psu.local',
        'title' => 'President event',
        'venue_name' => 'Alaminos Hall',
        'campus' => 'Alaminos Campus',
        'start_datetime' => now()->addDay(),
        'end_datetime' => now()->addDay()->addHour(),
        'status' => 'approved',
    ]);

    $response = $this->get('/')->assertOk()
        ->assertSee('id="office-filter"', false)
        ->assertSee('value="OFFICE OF THE PRESIDENT (OP)"', false)
        ->assertSee('OFFICE OF THE PRESIDENT (OP)')
        ->assertDontSee('<option value="Alaminos Campus">', false);

    expect($response->viewData('events')->first()['office_filter'])
        ->toBe('OFFICE OF THE PRESIDENT (OP)');
    expect($response->viewData('events')->first()['documents'])->toBe([]);
    $response->assertDontSee('op@psu.local')
        ->assertDontSee('"office_email"', false);
});

test('public calendar exposes memo links only for approved events', function () {
    Storage::fake('public');
    Storage::disk('public')->put('event-documents/public-advisory.pdf', 'signed memo');

    $approvedEvent = EventRequest::create([
        'name' => 'University Planning Office',
        'email' => 'planning@psu.local',
        'title' => 'Published event with memo',
        'venue_name' => 'Main Hall',
        'campus' => 'Lingayen Campus',
        'start_datetime' => now()->addDay(),
        'end_datetime' => now()->addDay()->addHour(),
        'status' => 'approved',
        'digital_documents' => ['event-documents/public-advisory.pdf'],
    ]);
    $pendingEvent = EventRequest::create([
        'name' => 'University Planning Office',
        'email' => 'planning@psu.local',
        'title' => 'Unpublished event with memo',
        'venue_name' => 'Main Hall',
        'campus' => 'Lingayen Campus',
        'start_datetime' => now()->addDays(2),
        'end_datetime' => now()->addDays(2)->addHour(),
        'status' => 'pending',
        'digital_documents' => ['event-documents/public-advisory.pdf'],
    ]);

    $response = $this->get('/')->assertOk();
    $events = $response->viewData('events');

    expect($events->pluck('title')->all())->toBe(['Published event with memo'])
        ->and($events->first()['documents'])->toBe([
            [
                'label' => 'Advisory Memo',
                'url' => route('user.events.documents.show', ['event' => $approvedEvent, 'documentIndex' => 1]),
            ],
        ]);

    $this->get(route('user.events.documents.show', ['event' => $approvedEvent, 'documentIndex' => 1]))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename=public-advisory.pdf');
    $this->get(route('user.events.documents.show', ['event' => $pendingEvent, 'documentIndex' => 1]))
        ->assertNotFound();
});

test('office dashboard calendar filters by university office instead of campus', function () {
    $user = User::factory()->create([
        'role' => 'office',
        'campus' => User::UNIVERSITY_OFFICES,
    ]);

    $this->actingAs($user)->get('/office')
        ->assertOk()
        ->assertSee('<span>Office:</span>', false)
        ->assertSee('id="office-filter"', false)
        ->assertSee('value="OFFICE OF THE PRESIDENT (OP)"', false)
        ->assertDontSee('<option value="Alaminos Campus">', false);
});

test('office calendar event details include accessible advisory memo attachments', function () {
    Storage::fake('public');
    Storage::disk('public')->put('event-documents/signed-advisory.pdf', 'signed memo');

    $office = User::factory()->create([
        'role' => 'office',
        'campus' => 'Lingayen Campus',
    ]);
    $visibleEvent = EventRequest::create([
        'name' => 'University Planning Office',
        'email' => 'planning@psu.local',
        'title' => 'Signed planning event',
        'venue_name' => 'Main Hall',
        'campus' => 'Lingayen Campus',
        'start_datetime' => now()->addDay(),
        'end_datetime' => now()->addDay()->addHour(),
        'status' => 'approved',
        'digital_documents' => ['event-documents/signed-advisory.pdf'],
    ]);
    $hiddenEvent = EventRequest::create([
        'name' => 'Another Office',
        'email' => 'another@psu.local',
        'title' => 'Hidden campus event',
        'venue_name' => 'Alaminos Hall',
        'campus' => 'Alaminos Campus',
        'start_datetime' => now()->addDays(2),
        'end_datetime' => now()->addDays(2)->addHour(),
        'status' => 'approved',
        'digital_documents' => ['event-documents/signed-advisory.pdf'],
    ]);

    $response = $this->actingAs($office)->get('/office')->assertOk();
    $events = $response->viewData('events');

    expect($events->firstWhere('title', 'Signed planning event')['documents'])->toBe([
        [
            'label' => 'Advisory Memo',
            'url' => route('office.events.documents.show', ['event' => $visibleEvent, 'documentIndex' => 1]),
        ],
    ])->and($events->pluck('title')->all())->not->toContain('Hidden campus event');

    $this->get(route('office.events.documents.show', ['event' => $visibleEvent, 'documentIndex' => 1]))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename=signed-advisory.pdf');
    $this->get(route('office.events.documents.show', ['event' => $hiddenEvent, 'documentIndex' => 1]))
        ->assertNotFound();
});
