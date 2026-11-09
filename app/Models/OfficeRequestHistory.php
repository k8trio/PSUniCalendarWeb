<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeRequestHistory extends Model
{
    protected $fillable = [
        'office_email',
        'event_request_id',
        'action',
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
