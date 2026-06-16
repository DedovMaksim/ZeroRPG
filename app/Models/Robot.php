<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Robot extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'version',
        'cpu',
        'ram',
        'ssd',
        'battery',
        'integrity',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}