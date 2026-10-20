<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RobotEffectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;
use Tests\TestCase;

class RobotEffectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_positive_and_negative_effects_modify_ram_without_changing_base_stat(): void
    {
        $robot = User::factory()->create()->robot;
        $base = $robot->ram;
        $effects = app(RobotEffectService::class);

        $effects->apply($robot, 'mechanic_chip', 'ram', 2, 60);
        $effects->apply($robot, 'memory_fault', 'ram', -1, 60);

        $this->assertSame($base + 1, $robot->stats()->ram($robot));
        $this->assertSame($base, $robot->fresh()->ram);
    }

    public function test_expired_effects_do_not_change_stats(): void
    {
        Date::setTestNow('2026-10-20 12:00:00');
        try {
            $robot = User::factory()->create()->robot;
            app(RobotEffectService::class)->apply($robot, 'chip', 'ram', 2, 60);

            $this->assertSame($robot->ram + 2, $robot->stats()->ram($robot));
            Date::setTestNow('2026-10-20 13:01:00');
            $this->assertSame($robot->ram, $robot->stats()->ram($robot));
        } finally {
            Date::setTestNow();
        }
    }

    public function test_same_effect_refreshes_instead_of_stacking(): void
    {
        $robot = User::factory()->create()->robot;
        $effects = app(RobotEffectService::class);

        $effects->apply($robot, 'chip', 'ram', 2, 60);
        $effects->apply($robot, 'chip', 'ram', 2, 60);

        $this->assertSame(1, $robot->effects()->count());
        $this->assertSame($robot->ram + 2, $robot->stats()->ram($robot));
    }

    public function test_modifiers_respect_safe_minimums(): void
    {
        $robot = User::factory()->create()->robot;
        app(RobotEffectService::class)->apply($robot, 'ram_fault', 'ram', -100, 60);

        $this->assertSame(1, $robot->stats()->ram($robot));
    }

    public function test_all_configured_stats_can_receive_modifiers(): void
    {
        $robot = User::factory()->create()->robot;
        $effects = app(RobotEffectService::class);

        foreach (array_keys(config('robot_effects.stats')) as $stat) {
            $effects->apply($robot, 'test_'.$stat, $stat, 1, 60);
            $this->assertSame(1, $effects->modifier($robot, $stat), $stat);
        }
    }

    public function test_effects_are_isolated_by_robot(): void
    {
        $first = User::factory()->create()->robot;
        $second = User::factory()->create()->robot;

        app(RobotEffectService::class)->apply($first, 'chip', 'ram', 2, 60);

        $this->assertSame($first->ram + 2, $first->stats()->ram($first));
        $this->assertSame($second->ram, $second->stats()->ram($second));
    }

    public function test_unknown_stats_are_rejected(): void
    {
        $robot = User::factory()->create()->robot;
        $this->expectException(InvalidArgumentException::class);

        app(RobotEffectService::class)->apply($robot, 'invalid', 'scraps', 100, 60);
    }
}
