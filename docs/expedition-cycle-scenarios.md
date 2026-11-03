# Expedition cycle scenario report (Sprint 17.10.5.15)

This is an **expected-value scenario model**, not a full stateful gameplay simulator.

Run three route-selection strategies with identical assumptions:

```sh
php artisan expeditions:cycle-report --target-level=20 --runs=10000 --seed=42 --strategy=xp_per_minute --battery-per-minute=1 --scraps-per-integrity=1 --output=storage/app/cycle-fast.json
php artisan expeditions:cycle-report --target-level=20 --runs=10000 --seed=42 --strategy=highest_unlocked --battery-per-minute=1 --scraps-per-integrity=1 --output=storage/app/cycle-unlocked.json
php artisan expeditions:cycle-report --target-level=20 --runs=10000 --seed=42 --strategy=xp_per_cycle_minute --battery-per-minute=1 --scraps-per-integrity=1 --output=storage/app/cycle-optimal.json
```

**Important:** battery-per-minute and scraps-per-integrity are scenario inputs, **not verified game mechanics**. Before balancing gameplay, compare them with the actual recharge and repair services. Scrap earnings assume immediate sale of all carried loot at catalog values; repairs assume instantaneous conversion of scraps into integrity. Negative cumulative scrap surplus flags a possible deficit, not a modeled stop. No actual gameplay changes are made.

A route is eligible only when its required level and base battery cost fit the robot's current maximum battery (100 + 5 per level). All outputs use stationary average rewards, fractional expected runs, no XP carryover, no dynamic upgrades, no event battery deltas, no charging during expeditions, no inventory carryover and no per-run state. The model does not enforce integrity limits or affordability, so it is **not a real elapsed-time prediction**.

Next milestone: audit actual battery and repair mechanics, then implement a stateful discrete-event simulation with charging, repairs, XP rollover, resource inventory and failed runs.
