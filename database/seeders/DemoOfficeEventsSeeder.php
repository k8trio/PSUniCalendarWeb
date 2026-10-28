<?php

namespace Database\Seeders;

use App\Models\EventRequest;
use Illuminate\Database\Seeder;

class DemoOfficeEventsSeeder extends Seeder
{
    public function run(): void
    {
        $officeEmail = 'op@psu.local';

        $events = [
            [
                'name' => 'Office of the President (OP)',
                'email' => $officeEmail,
                'title' => 'Campus Leadership Forum',
                'venue_name' => 'Main Auditorium',
                'campus' => 'University Offices',
                'is_university_wide' => true,
                'description' => 'Leadership forum for university-wide planning and coordination.',
                'start_datetime' => now()->addDays(5)->setTime(9, 0, 0),
                'end_datetime' => now()->addDays(5)->setTime(12, 0, 0),
                'digital_documents' => ['forum-flyer.pdf'],
                'status' => 'approved',
                'planning_note' => 'Approved and visible in the live calendar.',
                'read_at' => now(),
                'google_event_id' => 'demo-op-1',
            ],
            [
                'name' => 'Office of the President (OP)',
                'email' => $officeEmail,
                'title' => 'Office Planning Review',
                'venue_name' => 'Planning Hall',
                'campus' => 'University Offices',
                'is_university_wide' => false,
                'description' => 'Internal review and planning session for the office.',
                'start_datetime' => now()->addDays(15)->setTime(10, 0, 0),
                'end_datetime' => now()->addDays(15)->setTime(12, 0, 0),
                'digital_documents' => ['planning-review.pdf'],
                'status' => 'pending',
                'planning_note' => 'Pending review by Planning Office.',
                'read_at' => null,
                'google_event_id' => null,
            ],
            [
                'name' => 'Office of the President (OP)',
                'email' => $officeEmail,
                'title' => 'University Community Outreach',
                'venue_name' => 'Open Field',
                'campus' => 'University Offices',
                'is_university_wide' => true,
                'description' => 'Community service and outreach initiative.',
                'start_datetime' => now()->addDays(30)->setTime(8, 0, 0),
                'end_datetime' => now()->addDays(30)->setTime(11, 30, 0),
                'digital_documents' => ['outreach-brief.pdf'],
                'status' => 'rejected',
                'planning_note' => 'Rejected due to schedule conflict.',
                'read_at' => now()->subHours(3),
                'google_event_id' => null,
            ],
        ];

        foreach ($events as $event) {
            EventRequest::firstOrCreate(
                [
                    'email' => $event['email'],
                    'title' => $event['title'],
                    'start_datetime' => $event['start_datetime'],
                ],
                $event
            );
        }
    }
}