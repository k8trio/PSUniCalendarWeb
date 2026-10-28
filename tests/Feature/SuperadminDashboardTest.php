<?php

use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('planning office dashboard renders for authenticated office users', function () {
    $user = User::factory()->create([
        'email' => 'planning-office@example.com',
        'role' => 'planning_office',
    ]);

    $response = $this->actingAs($user)->get('/planning-office');

    $response->assertStatus(200);
    $response->assertSee('Planning Office Dashboard')
        ->assertSee('Analytics');
});

test('planning office dashboard keeps the live master calendar visible', function () {
    $user = User::factory()->create([
        'email' => 'planning-office-no-calendar@example.com',
        'role' => 'planning_office',
    ]);

    $response = $this->actingAs($user)->get('/planning-office');

    $response->assertStatus(200);
    $response->assertSee('Master Live Calendar');
    $this->get('/superadmin/calendar')->assertNotFound();
});

test('planning office dashboard shows office event totals and upcoming events summary', function () {
    $user = User::factory()->create([
        'email' => 'planning-office-summary@example.com',
        'role' => 'planning_office',
    ]);

    EventRequest::create([
        'name' => 'Admin One',
        'email' => 'admin1@psu.local',
        'title' => 'Alaminos Event',
        'venue_name' => 'Alaminos Hall',
        'campus' => 'Alaminos Campus',
        'description' => 'Alaminos sample',
        'start_datetime' => now()->addDay()->format('Y-m-d H:i:s'),
        'end_datetime' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
        'status' => 'approved',
    ]);

    EventRequest::create([
        'name' => 'Admin Two',
        'email' => 'admin2@psu.local',
        'title' => 'Lingayen Event',
        'venue_name' => 'Lingayen Hall',
        'campus' => 'Lingayen Campus',
        'description' => 'Lingayen sample',
        'start_datetime' => now()->addDays(2)->format('Y-m-d H:i:s'),
        'end_datetime' => now()->addDays(2)->addHour()->format('Y-m-d H:i:s'),
        'status' => 'approved',
    ]);

    EventRequest::create([
        'name' => 'Admin Three',
        'email' => 'admin3@psu.local',
        'title' => 'University Event',
        'venue_name' => 'University Plaza',
        'campus' => 'All Campus',
        'description' => 'University-wide sample',
        'start_datetime' => now()->addDays(3)->format('Y-m-d H:i:s'),
        'end_datetime' => now()->addDays(3)->addHour()->format('Y-m-d H:i:s'),
        'status' => 'approved',
    ]);

    EventRequest::create([
        'name' => 'Admin Four',
        'email' => 'admin4@psu.local',
        'title' => 'Past Event',
        'venue_name' => 'Binmaley Hall',
        'campus' => 'Binmaley Campus',
        'description' => 'Past sample',
        'start_datetime' => now()->subDays(2)->format('Y-m-d H:i:s'),
        'end_datetime' => now()->subDays(2)->addHour()->format('Y-m-d H:i:s'),
        'status' => 'approved',
    ]);

    $response = $this->actingAs($user)->get('/planning-office');

    $response->assertStatus(200);
    $response->assertSee('Alaminos Campus');
    $response->assertSee('Lingayen Campus');
    $response->assertSee('Binmaley Campus');
    $response->assertSee('OFFICE OF THE PRESIDENT (OP)');
    $response->assertSee('Upcoming events');
    $response->assertSee('Office event trends');
    $response->assertSee('1', false);
});

test('planning office can review pending requests with campus metadata', function () {
    $user = User::factory()->create([
        'email' => 'planning-office-review@example.com',
        'role' => 'planning_office',
    ]);

    EventRequest::create([
        'name' => 'Admin User',
        'email' => 'admin@psu.local',
        'title' => 'Campus Event',
        'venue_name' => 'Alaminos Hall',
        'campus' => 'Alaminos Campus',
        'description' => 'Sample request',
        'start_datetime' => now()->addDay()->format('Y-m-d H:i:s'),
        'end_datetime' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->get('/planning-office/pending-approvals');

    $response->assertStatus(200);
    $response->assertSee('Request review');
    $response->assertSee('Alaminos Campus');
    $response->assertSee('Save and approve');
    $response->assertSee('Reject request');
});

test('office request submission stores the campus from which the request came', function () {
    $user = User::factory()->create([
        'email' => 'admin@psu.local',
        'role' => 'office',
        'campus' => 'Lingayen Campus',
    ]);

    $response = $this->actingAs($user)->post('/office/request-venue', [
        'title' => 'Campus Meeting',
        'venue_name' => 'Lingayen Hall',
        'campus' => 'Lingayen Campus',
        'start_datetime' => now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i'),
        'end_datetime' => now()->addDay()->setTime(11, 0)->format('Y-m-d\TH:i'),
        'description' => 'Request from Lingayen',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('event_requests', [
        'email' => 'admin@psu.local',
        'campus' => 'Lingayen Campus',
        'status' => 'pending',
    ]);
});
