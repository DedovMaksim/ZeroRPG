<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstructionRequirement extends Model
{
    protected $fillable = [
        'construction_project_id',
        'resource_id',
        'required_amount',
        'delivered_amount',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ConstructionProject::class, 'construction_project_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function remainingAmount(): int
    {
        return max(0, $this->required_amount - $this->delivered_amount);
    }
}