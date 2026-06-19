<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Base extends Model
{
    protected $fillable = [
        'robot_id',
        'name',
        'level',
        'status',
    ];

    public function robot(): BelongsTo
    {
        return $this->belongsTo(Robot::class);
    }
}