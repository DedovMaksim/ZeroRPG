<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConstructionProject extends Model
{
    protected $fillable = [
        'base_id',
        'key',
        'name',
        'description',
        'status',
    ];

    public function base(): BelongsTo
    {
        return $this->belongsTo(Base::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ConstructionRequirement::class);
    }

    public function isCompleted(): bool
    {
        return $this->requirements->every(function ($requirement) {
            return $requirement->delivered_amount >= $requirement->required_amount;
        });
    }
}