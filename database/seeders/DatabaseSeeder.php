<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@psu.edu'],
            [
                'name' => 'Planning Office',
                'email_verified_at' => now(),
                'password' => Hash::make('Admin@123'),
                'role' => 'planning_office',
                'remember_token' => Str::random(10),
            ]
        );

        $user->forceFill([
            'name' => 'Planning Office',
            'email_verified_at' => now(),
            'password' => Hash::make('Admin@123'),
            'role' => 'planning_office',
            'remember_token' => Str::random(10),
        ])->save();

        $officeAccounts = [
            ['email' => 'op@psu.local', 'name' => 'Office of the President (OP)', 'campus' => 'University Offices'],
            ['email' => 'ovpaass@psu.local', 'name' => 'Office of the Vice President for Academic Affairs and Student Services (OVPAASS)', 'campus' => 'University Offices'],
            ['email' => 'ovpafm@psu.local', 'name' => 'Office of the Vice President for Administration and Finance Management (OVPAFM)', 'campus' => 'University Offices'],
            ['email' => 'ovprei@psu.local', 'name' => 'Office of the Vice President for Research, Extension and Innovation (OVPREI)', 'campus' => 'University Offices'],
            ['email' => 'ovpqa@psu.local', 'name' => 'Office of the Vice President for Quality Assurance (OVPQA)', 'campus' => 'University Offices'],
            ['email' => 'ovplia@psu.local', 'name' => 'Office of the Vice President for Local and International Affairs (OVPLIA)', 'campus' => 'University Offices'],
            ['email' => 'sas@psu.local', 'name' => 'Student Affairs and Services (SAS)', 'campus' => 'University Offices'],
            ['email' => 'ous@psu.local', 'name' => 'Open University System (OUS)', 'campus' => 'University Offices'],
        ];

        foreach ($officeAccounts as $account) {
            $officeUser = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('Office@123'),
                    'role' => 'office',
                    'campus' => $account['campus'],
                    'remember_token' => Str::random(10),
                ]
            );

            $officeUser->forceFill([
                'name' => $account['name'],
                'email_verified_at' => now(),
                'password' => Hash::make('Office@123'),
                'role' => 'office',
                'campus' => $account['campus'],
                'remember_token' => Str::random(10),
            ])->save();
        }

        $this->call([
            DemoOfficeEventsSeeder::class,
        ]);
    }
}