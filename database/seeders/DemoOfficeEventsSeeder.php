<?php

namespace Database\Seeders;

use App\Models\EventRequest;
use App\Models\FavoriteVenue;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoOfficeSeeder extends Seeder
{
    public function run(): void
    {
        // --------------------------------------------------
        // CHANGE THIS TO THE OFFICE EMAIL YOU ARE USING
        // --------------------------------------------------
        $officeEmail = 'studentaffairs@psu.edu';

        // ----- USERS -----
        $users = [
            [
                'name' => 'Planning Office Admin',
                'email' => 'admin@psu.edu',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'campus' => 'Lingayen Campus',
                'contact_person' => 'Planning Office',
                'contact_number' => '09171234567',
            ],
            [
                'name' => 'Office of Student Affairs',
                'email' => $officeEmail,
                'password' => Hash::make('password123'),
                'role' => 'user',
                'campus' => 'Lingayen Campus',
                'contact_person' => 'Student Affairs Coordinator',
                'contact_number' => '09181234567',
            ],
            [
                'name' => 'College of Engineering',
                'email' => 'eng@psu.edu',
                'password' => Hash::make('password123'),
                'role' => 'user',
                'campus' => 'Lingayen Campus',
                'contact_person' => 'Dean Office',
                'contact_number' => '09191234567',
            ],
            [
                'name' => 'Campus Student Council',
                'email' => 'studentcouncil@psu.edu',
                'password' => Hash::make('password123'),
                'role' => 'user',
                'campus' => 'Alaminos Campus',
                'contact_person' => 'Council President',
                'contact_number' => '09201234567',
            ],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                array_merge($userData, [
                    'email_verified_at' => now(),
                    'remember_token' => null,
                ])
            );
        }

        // ----- VENUES -----
        $venues = [
            'Main Auditorium',
            'Student Center Hall',
            'Research Building Lobby',
            'Open Field',
            'Computer Lab Room 101',
            'Science Hall',
            'Sports Gym',
        ];

        foreach ($venues as $venueName) {
            Venue::firstOrCreate(['name' => $venueName]);
        }

        // ----- FAVORITE VENUES -----
        $favoriteVenues = [
            ['email' => $officeEmail, 'venue_name' => 'Main Auditorium', 'campus' => 'Lingayen Campus'],
            ['email' => $officeEmail, 'venue_name' => 'Open Field', 'campus' => 'Lingayen Campus'],
            ['email' => 'eng@psu.edu', 'venue_name' => 'Research Building Lobby', 'campus' => 'Lingayen Campus'],
            ['email' => 'studentcouncil@psu.edu', 'venue_name' => 'Student Center Hall', 'campus' => 'Alaminos Campus'],
        ];

        foreach ($favoriteVenues as $fav) {
            FavoriteVenue::firstOrCreate(
                ['email' => $fav['email'], 'venue_name' => $fav['venue_name']],
                ['campus' => $fav['campus']]
            );
        }

        // ----- EVENT REQUESTS -----
        $requests = [
            [
                'name' => 'Office of Student Affairs',
                'email' => $officeEmail,
                'title' => 'Campus Sustainability Week Launch',
                'venue_name' => 'Main Auditorium',
                'campus' => 'Lingayen Campus',
                'is_university_wide' => true,
                'description' => 'Launch event for the Sustainability Week campaign.',
                'start_datetime' => now()->addDays(5)->setTime(9, 0, 0),
                'end_datetime' => now()->addDays(5)->setTime(12, 0, 0),
                'digital_documents' => ['sustainability-launch-poster.png', 'proposal.pdf'],
                'status' => 'approved',
                'planning_note' => 'Approved and added to the live calendar.',
                'read_at' => now(),
                'google_event_id' => 'evt_001',
            ],
            [
                'name' => 'Office of Student Affairs',
                'email' => $officeEmail,
                'title' => 'Volunteer Day',
                'venue_name' => 'Open Field',
                'campus' => 'Lingayen Campus',
                'is_university_wide' => true,
                'description' => 'Volunteer cleanup and community outreach activity.',
                'start_datetime' => now()->addDays(18)->setTime(7, 30, 0),
                'end_datetime' => now()->addDays(18)->setTime(11, 30, 0),
                'digital_documents' => ['volunteer-day-plan.pdf'],
                'status' => 'pending',
                'planning_note' => 'Awaiting final approval and venue confirmation.',
                'read_at' => null,
                'google_event_id' => null,
            ],
            [
                'name' => 'Office of Student Affairs',
                'email' => $officeEmail,
                'title' => 'Cultural Showcase',
                'venue_name' => 'Student Center Hall',
                'campus' => 'Lingayen Campus',
                'is_university_wide' => false,
                'description' => 'Culture and arts showcase for the campus community.',
                'start_datetime' => now()->addDays(27)->setTime(11, 0, 0),
                'end_datetime' => now()->addDays(27)->setTime(14, 0, 0),
                'digital_documents' => ['culture-showcase.jpg'],
                'status' => 'rejected',
                'planning_note' => 'Rejected due to duplicate schedule.',
                'read_at' => now()->subHours(5),
                'google_event_id' => null,
            ],
            [
                'name' => 'Office of Student Affairs',
                'email' => $officeEmail,
                'title' => 'Community Outreach Program',
                'venue_name' => 'Open Field',
                'campus' => 'Lingayen Campus',
                'is_university_wide' => false,
                'description' => 'Community outreach activity with local stakeholders.',
                'start_datetime' => now()->addDays(40)->setTime(8, 0, 0),
                'end_datetime' => now()->addDays(40)->setTime(12, 0, 0),
                'digital_documents' => ['outreach-plan.pdf'],
                'status' => 'approved',
                'planning_note' => 'Approved and included in upcoming events.',
                'read_at' => now()->subHours(6),
                'google_event_id' => 'evt_005',
            ],
            [
                'name' => 'Office of Student Affairs',
                'email' => $officeEmail,
                'title' => 'Wellness and Mental Health Day',
                'venue_name' => 'Main Auditorium',
                'campus' => 'Lingayen Campus',
                'is_university_wide' => true,
                'description' => 'Awareness day devoted to wellness and student support.',
                'start_datetime' => now()->addDays(48)->setTime(9, 0, 0),
                'end_datetime' => now()->addDays(48)->setTime(13, 0, 0),
                'digital_documents' => ['wellness.pdf'],
                'status' => 'pending',
                'planning_note' => 'Awaiting final funding confirmation.',
                'read_at' => null,
                'google_event_id' => null,
            ],
            [
                'name' => 'Office of Student Affairs',
                'email' => $officeEmail,
                'title' => 'Leadership Summit',
                'venue_name' => 'Student Center Hall',
                'campus' => 'Lingayen Campus',
                'is_university_wide' => false,
                'description' => 'Summit for student leaders and organization advisers.',
                'start_datetime' => now()->addDays(55)->setTime(10, 0, 0),
                'end_datetime' => now()->addDays(55)->setTime(13, 0, 0),
                'digital_documents' => ['leadership-summit.pdf'],
                'status' => 'approved',
                'planning_note' => 'Approved for the upcoming academic cycle.',
                'read_at' => now()->subHours(2),
                'google_event_id' => 'evt_006',
            ],
            [
                'name' => 'College of Engineering',
                'email' => 'eng@psu.edu',
                'title' => 'Research Innovation Expo',
                'venue_name' => 'Research Building Lobby',
                'campus' => 'Lingayen Campus',
                'is_university_wide' => false,
                'description' => 'Engineering showcase and innovation expo.',
                'start_datetime' => now()->addDays(63)->setTime(8, 0, 0),
                'end_datetime' => now()->addDays(63)->setTime(15, 0, 0),
                'digital_documents' => ['engineering-expo.pdf'],
                'status' => 'approved',
                'planning_note' => 'Good example for analytics and live calendar.',
                'read_at' => now()->subHours(1),
                'google_event_id' => 'evt_007',
            ],
            [
                'name' => 'Campus Student Council',
                'email' => 'studentcouncil@psu.edu',
                'title' => 'Arts and Culture Showcase',
                'venue_name' => 'Student Center Hall',
                'campus' => 'Alaminos Campus',
                'is_university_wide' => false,
                'description' => 'Showcase of bands, dance, and local culture.',
                'start_datetime' => now()->addDays(70)->setTime(11, 0, 0),
                'end_datetime' => now()->addDays(70)->setTime(14, 0, 0),
                'digital_documents' => ['culture-showcase.jpg'],
                'status' => 'approved',
                'planning_note' => 'Approved and visible to the campus community.',
                'read_at' => now()->subHours(3),
                'google_event_id' => 'evt_008',
            ],
            [
                'name' => 'Office of Student Affairs',
                'email' => $officeEmail,
                'title' => 'Mental Health Awareness Week',
                'venue_name' => 'Computer Lab Room 101',
                'campus' => 'Lingayen Campus',
                'is_university_wide' => false,
                'description' => 'Awareness sessions and supportive campus activities.',
                'start_datetime' => now()->addDays(80)->setTime(9, 30, 0),
                'end_datetime' => now()->addDays(80)->setTime(12, 30, 0),
                'digital_documents' => ['mental-health-week.pdf'],
                'status' => 'rejected',
                'planning_note' => 'Rejected due to schedule conflict.',
                'read_at' => now()->subHours(4),
                'google_event_id' => null,
            ],
        ];

        foreach ($requests as $request) {
            EventRequest::firstOrCreate(
                [
                    'email' => $request['email'],
                    'title' => $request['title'],
                    'start_datetime' => $request['start_datetime'],
                ],
                $request
            );
        }
    }
}