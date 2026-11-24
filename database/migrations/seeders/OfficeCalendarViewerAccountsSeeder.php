<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OfficeCalendarViewerAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['email' => 'pmlo@psu.local', 'name' => 'Presidential Management and Liaison Office (PMLO)', 'parent' => 'op@psu.local'],
            ['email' => 'ictmo@psu.local', 'name' => 'Information Communication Technology Management Office (ICTMO)', 'parent' => 'op@psu.local'],
            ['email' => 'lso@psu.local', 'name' => 'Legal Services Office (LSO)', 'parent' => 'op@psu.local'],
            ['email' => 'gad@psu.local', 'name' => 'Gender and Development (GAD)', 'parent' => 'op@psu.local'],
            ['email' => 'ci@psu.local', 'name' => 'CI', 'parent' => 'ovpaass@psu.local'],
            ['email' => 'osaas@psu.local', 'name' => 'Office of Student Affairs and Auxiliary Services (OSAAS)', 'parent' => 'ovpaass@psu.local'],
            ['email' => 'eteaap@psu.local', 'name' => 'Expanded Tertiary Education Equivalency and Accreditation Program (ETEEAP)', 'parent' => 'ovpaass@psu.local'],
            ['email' => 'internship@psu.local', 'name' => 'Internship', 'parent' => 'ovpaass@psu.local'],
            ['email' => 'culture-arts@psu.local', 'name' => 'Culture and Arts', 'parent' => 'ovpaass@psu.local'],
            ['email' => 'nstp-pe-sports@psu.local', 'name' => 'NSTP, PE, Sports', 'parent' => 'ovpaass@psu.local'],
            ['email' => 'research-development@psu.local', 'name' => 'Research and Development (R&D)', 'parent' => 'ovprei@psu.local'],
            ['email' => 'eso@psu.local', 'name' => 'Extension Services Office (ESO)', 'parent' => 'ovprei@psu.local'],
            ['email' => 'iso@psu.local', 'name' => 'Innovation Support Office (ISO)', 'parent' => 'ovprei@psu.local'],
            ['email' => 'fo@psu.local', 'name' => 'Finance Office (FO)', 'parent' => 'ovpafm@psu.local'],
            ['email' => 'opas@psu.local', 'name' => 'Production and Auxiliary Services Office (OPAS)', 'parent' => 'ovpafm@psu.local'],
            ['email' => 'glh@psu.local', 'name' => 'Golden Lion Hotel (GLH)', 'parent' => 'ovpafm@psu.local'],
            ['email' => 'supply-office@psu.local', 'name' => 'Supply Office (SO)', 'parent' => 'ovpafm@psu.local'],
            ['email' => 'records-management@psu.local', 'name' => 'Records Management Office (RMO)', 'parent' => 'ovpafm@psu.local'],
            ['email' => 'upo@psu.local', 'name' => 'University Planning Office (UPO)', 'parent' => 'ovpafm@psu.local'],
            ['email' => 'ppfmo@psu.local', 'name' => 'Physical Plant and Facilities and Management Office (PPFMO)', 'parent' => 'ovpafm@psu.local'],
            ['email' => 'gso@psu.local', 'name' => 'General Services Office (GSO)', 'parent' => 'ovpafm@psu.local'],
            ['email' => 'pso@psu.local', 'name' => 'Procurement Services Office (PSO)', 'parent' => 'ovpafm@psu.local'],
            ['email' => 'hrmdo@psu.local', 'name' => 'Human Resource Management and Development Office (HRMDO)', 'parent' => 'ovpafm@psu.local'],
            ['email' => 'iao@psu.local', 'name' => 'International Accreditation Office (IAO)', 'parent' => 'ovpqa@psu.local'],
            ['email' => 'lao@psu.local', 'name' => 'Local Accreditation Office (LAO)', 'parent' => 'ovpqa@psu.local'],
            ['email' => 'monitoring-evaluation@psu.local', 'name' => 'Monitoring and Evaluation (M&E)', 'parent' => 'ovpqa@psu.local'],
            ['email' => 'risk-management@psu.local', 'name' => 'Risk Management Office (RMO)', 'parent' => 'ovpqa@psu.local'],
            ['email' => 'sfto@psu.local', 'name' => 'Sustainability and Futures Thinking Office (SFTO)', 'parent' => 'ovpqa@psu.local'],
            ['email' => 'udcc@psu.local', 'name' => 'University Document Control Custodian (UDCC)', 'parent' => 'ovpqa@psu.local'],
            ['email' => 'iro@psu.local', 'name' => 'International Relations Office (IRO)', 'parent' => 'ovplia@psu.local'],
            ['email' => 'student-faculty-mobility@psu.local', 'name' => 'Student and Faculty Mobility', 'parent' => 'ovplia@psu.local'],
            ['email' => 'gced@psu.local', 'name' => 'Global Citizenship Education (GCEd)', 'parent' => 'ovplia@psu.local'],
            ['email' => 'special-projects@psu.local', 'name' => 'Special Projects (SP)', 'parent' => 'ovplia@psu.local'],
            ['email' => 'cpd@psu.local', 'name' => 'Continuing Professional Development (CPD)', 'parent' => 'ovplia@psu.local'],
            ['email' => 'prio@psu.local', 'name' => 'Public Relations Information Office (PRIO)', 'parent' => 'ovplia@psu.local'],
        ];

        foreach ($accounts as $account) {
            $parentOffice = User::where('email', $account['parent'])
                ->where('role', 'office')
                ->firstOrFail();

            User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('Office@123'),
                    'role' => 'office_viewer',
                    'parent_office_id' => $parentOffice->id,
                    'remember_token' => Str::random(10),
                ]
            );
        }
    }
}
