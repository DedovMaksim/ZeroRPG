<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function constructionProjects(): HasMany
    {
        return $this->hasMany(ConstructionProject::class);
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }
}