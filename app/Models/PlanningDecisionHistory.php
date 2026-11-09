<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanningDecisionHistory extends Model
{
    protected $fillable = [
        'planning_email',
        'event_request_id',
        'decision',
        'before_data',
        'after_data',
        'is_undone',
    ];

    protected $casts = [
        'before_data' => 'array',
        'after_data' => 'array',
        'is_undone' => 'boolean',
    ];
}
