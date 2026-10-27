<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'campus', 'contact_person', 'contact_number'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * PSU campuses, in display order (alphabetical). Single source for every campus
     * dropdown, filter, sidebar group and chart.
     */
    public const CAMPUSES = [
        'OFFICE OF THE PRESIDENT (OP)',
        'OFFICE OF THE VICE PRESIDENT FOR ACADEMICS AND STUDENT SERVICES (OVPASS)',
        'OFFICE OF THE VICE PRESIDENT FOR RESEARCH, EXTENSION, AND INNOVATION (OVPREI)',
        'OFFICE OF THE VICE PRESIDENT FOR ADMINISRATION AND FINANCE MANAGEMENT (OVPAFM)',
        'OFFICE OF THE VICE PRESIDENT FOR QUALITY ASSURANCE (OVPQA)',
        'OFFICE OF THE VICE PRESIDENT FOR LOCAL & INTERNATIONAL AFFAIRS (OVPLIA)',
    ];

    /**
     * Campuses with their own office account; the Planning Office pages (sidebar,
     * campus cards, chart, filters) show only these.
     */
    public const MAIN_CAMPUSES = ['Alaminos Campus', 'Binmaley Campus', 'Lingayen Campus'];

    /**
     * Group for university-level offices (OP, the VP offices, SAS, OUS) that aren't tied to one campus.
     */
    public const UNIVERSITY_OFFICES = 'University Offices';

    /**
     * Groups an office account can be listed under in the Planning Office sidebar.
     */
    public const OFFICE_GROUPS = [self::UNIVERSITY_OFFICES, ...self::MAIN_CAMPUSES];

    /**
     * Is this account tied to one campus? University Offices (and offices with no campus set)
     * are university-wide.
     */
    public function isCampusOffice(): bool
    {
        return in_array($this->campus, self::CAMPUSES, true);
    }

    /**
     * Can this account see an event held at the given campus?
     * University-wide offices see everything; a campus office sees its own campus
     * and university-wide ("All Campus") events only.
     */
    public function canSeeCampusEvent(?string $eventCampus): bool
    {
        if (! $this->isCampusOffice()) {
            return true;
        }

        return $eventCampus === null
            || $eventCampus === 'All Campus'
            || $eventCampus === $this->campus;
    }

    /**
     * Guess a campus from free text such as a venue name ("Lingayen Gym" → "Lingayen Campus").
     */
    public static function campusFromText(?string $text): ?string
    {
        $text = strtolower($text ?? '');
        if ($text === '') {
            return null;
        }

        foreach (self::CAMPUSES as $campus) {
            $short = strtolower(str_replace(' Campus', '', $campus));
            $aliases = [$short, str_replace(' ', '', $short)];
            if ($short === 'santa maria') {
                $aliases[] = 'sta. maria';
                $aliases[] = 'sta maria';
            }

            foreach ($aliases as $alias) {
                if (str_contains($text, $alias)) {
                    return $campus;
                }
            }
        }

        return null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
