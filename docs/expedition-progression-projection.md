# Expedition progression projection (Sprint 17.10.5.13)

This is an **expected-value planning tool**, not a playable expedition loop.

Run:

```bash
php artisan expeditions:progression-report --target-level=20 --runs=10000 --seed=42 --strategy=xp_per_minute --output=storage/app/progression-fast.json
php artisan expeditions:progression-report --target-level=20 --runs=10000 --seed=42 --strategy=highest_unlocked --output=storage/app/progression-unlocked.json
```

The projector calls the existing seeded expedition balance simulator once for each location (11 × 10,000 runs by default), then advances through the real `Robot::xpForNextLevel()` formula, assuming zero XP at level 1. Each level selects an eligible route. `xp_per_minute` picks the highest expected XP per base expedition minute; `highest_unlocked` picks the route with the greatest required level, breaking ties by XP/minute. Expected runs may be fractional, because the result is a mathematical projection rather than a sequence of actual game actions.

Outputs per milestone: chosen route, XP threshold, expected expedition count, cumulative expedition time, gross battery cost, and expected integrity damage. The model deliberately excludes charging time, repair affordability, inventory/storage, equipment, RAM speed modifiers, XP rollover randomness, and individual-run state transitions. It **cannot** be used as an estimate of real elapsed time to level 20 or as proof that a player can fund repairs. Future work: event-by-event stateful simulation, battery regeneration, repairs, resource sales and player upgrades.

No gameplay rewards, costs, or rules are changed by this tool.
