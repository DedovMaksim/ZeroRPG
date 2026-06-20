<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Building extends Model
{
    protected $fillable = [
        'base_id',
        'key',
        'name',
        'level',
        'status',
        'capacity',
    ];

    public function base(): BelongsTo
    {
        return $this->belongsTo(Base::class);
    }
}