<?php

use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public calendar filters by university office instead of campus', function () {
    EventRequest::create([
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
    $response->assertDontSee('op@psu.local')
        ->assertDontSee('"office_email"', false);
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
