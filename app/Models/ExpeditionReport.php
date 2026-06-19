<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpeditionReport extends Model
{
    protected $fillable = [
        'robot_id',
        'location_id',
        'location_name',
        'resources',
        'xp_gained',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'resources' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}