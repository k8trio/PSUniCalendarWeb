<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'title',
        'venue_name',
        'event_for',
        'attention_to',
        'campus',
        'is_university_wide',
        'description',
        'start_datetime',
        'end_datetime',
        'status',
        'planning_note',
        'google_event_id',
        'digital_documents',
        'read_at',
    ];

    protected $casts = [
        'digital_documents' => 'array',
        'is_university_wide' => 'boolean',
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'read_at' => 'datetime',
    ];

    /**
     * Statuses that mean "this office can still edit or cancel this request".
     * "returned" = the Planning Office sent it back for revision.
     */
    public const EDITABLE_STATUSES = ['pending', 'conflict', 'returned'];

    /**
     * Statuses that represent a Planning Office decision the office hasn't seen yet.
     */
    public const NOTIFIABLE_STATUSES = ['approved', 'rejected', 'conflict', 'returned'];

    /**
     * Statuses waiting for a Planning Office decision.
     */
    public const REVIEW_STATUSES = ['pending', 'conflict'];

    /** Event scopes (stored as the is_university_wide flag). */
    public const SCOPE_CAMPUS_SPECIFIC = 'CAMPUS_SPECIFIC';
    public const SCOPE_UNIVERSITY_WIDE = 'UNIVERSITY_WIDE';

    /**
     * Readable status for badges and tables.
     */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'conflict' => 'Conflict review',
            'returned' => 'Returned for revision',
            default => ucfirst($status),
        };
    }

    /**
     * Campus where the event is held (guessed from the venue name for older requests).
     */
    public function venueCampus(): string
    {
        return $this->campus ?: (User::campusFromText($this->venue_name) ?? 'All Campus');
    }

    /**
     * UNIVERSITY_WIDE when the event is open to every campus (or held for "All Campus"),
     * otherwise CAMPUS_SPECIFIC.
     */
    public function scope(): string
    {
        return $this->is_university_wide || $this->venueCampus() === 'All Campus'
            ? self::SCOPE_UNIVERSITY_WIDE
            : self::SCOPE_CAMPUS_SPECIFIC;
    }

    /**
     * Should this event appear on the given office's calendar?
     * Yes if it's the office's own request, if it's for all campuses, or if the
     * office can see events at the venue's campus.
     */
    public function isVisibleTo(User $user): bool
    {
        return $this->email === $user->email
            || $this->is_university_wide
            || $user->canSeeCampusEvent($this->venueCampus());
    }
}
