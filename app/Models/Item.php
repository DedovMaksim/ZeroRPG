<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'name', 'slug', 'type', 'description', 'cpu_bonus', 'ram_bonus',
        'ssd_bonus', 'loot_chance_bonus', 'rare_loot_chance_bonus',
        'event_chance_bonus', 'ambush_chance_reduction',
        'battery_cost_reduction', 'expedition_speed_bonus', 'price',
    ];

    public function robotItems(): HasMany
    {
        return $this->hasMany(RobotItem::class);
    }

    public function bonuses(): array
    {
        return array_filter([
            'CPU' => $this->cpu_bonus,
            'RAM' => $this->ram_bonus,
            'SSD' => $this->ssd_bonus,
            'Шанс добычи' => $this->loot_chance_bonus,
            'Редкая добыча' => $this->rare_loot_chance_bonus,
            'События' => $this->event_chance_bonus,
            'Защита от засад' => $this->ambush_chance_reduction,
            'Экономия батареи' => $this->battery_cost_reduction,
            'Скорость экспедиции' => $this->expedition_speed_bonus,
        ], fn ($value) => (int) $value !== 0);
    }
}
