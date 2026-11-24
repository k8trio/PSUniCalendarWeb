<?php

use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->planning = User::factory()->create([
        'role' => 'planning_office',
        'email' => 'planning@psu.local',
    ]);
});

test('planning office can upload a supporting document with an event', function () {
    Storage::fake('public');
    $start = now()->addDays(5)->setTime(9, 0);

    $this->actingAs($this->planning)
        ->followingRedirects()
        ->post(route('planning_office.events.store'), [
            'title' => 'Document Upload Forum',
            'venue_name' => 'University Convention Center',
            'campus' => 'Lingayen Campus',
            'is_university_wide' => '0',
            'start_datetime' => $start->format('Y-m-d\TH:i'),
            'end_datetime' => $start->copy()->addHours(2)->format('Y-m-d\TH:i'),
            'digital_documents' => [
                UploadedFile::fake()->create('supporting-document.pdf', 50, 'application/pdf'),
            ],
        ])
        ->assertOk()
        ->assertSee('Event added successfully');

    $event = EventRequest::where('title', 'Document Upload Forum')->firstOrFail();
    expect($event->digital_documents)->toHaveCount(1);
    Storage::disk('public')->assertExists($event->digital_documents[0]);
});

test('planning office gets a useful message when a document upload fails', function () {
    $file = UploadedFile::fake()->create('supporting-document.pdf', 50, 'application/pdf');
    $failedUpload = new UploadedFile(
        $file->getPathname(),
        'supporting-document.pdf',
        'application/pdf',
        UPLOAD_ERR_INI_SIZE,
        true,
    );
    $start = now()->addDays(5)->setTime(9, 0);

    $this->actingAs($this->planning)
        ->from(route('planning_office.add-event'))
        ->followingRedirects()
        ->post(route('planning_office.events.store'), [
            'title' => 'Failed Document Upload',
            'venue_name' => 'University Convention Center',
            'campus' => 'Lingayen Campus',
            'is_university_wide' => '0',
            'start_datetime' => $start->format('Y-m-d\TH:i'),
            'end_datetime' => $start->copy()->addHours(2)->format('Y-m-d\TH:i'),
            'digital_documents' => [$failedUpload],
        ])
        ->assertOk()
        ->assertSee('The document exceeds the server upload limit of 25M.')
        ->assertSee('application limit is 10 MB per file.');

    $this->assertDatabaseMissing('event_requests', ['title' => 'Failed Document Upload']);
});
