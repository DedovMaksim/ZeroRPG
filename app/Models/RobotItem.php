<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RobotItem extends Model
{
    protected $fillable = ['robot_id', 'item_id', 'equipped'];

    protected $casts = ['equipped' => 'boolean'];

    public function robot(): BelongsTo
    {
        return $this->belongsTo(Robot::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
