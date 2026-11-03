# Expedition balance report — Sprint 17.10.5.7

Run from the project root:

```bash
php artisan expeditions:balance-report --runs=10000 --seed=42 --ssd=32 --cpu=1 --output=storage/app/expedition-balance.json
```

The command simulates every configured route using the same profile and random seed.
It prints a comparison table and optionally saves detailed per-route JSON with
loot and event counts. No database changes are required.

Metrics: XP/run and XP/minute, carried scrap value/run and per minute,
lost value/run due to capacity, aborted expeditions (%), average integrity loss,
and base/effective battery cost. The simulator models event battery deltas
separately; battery cost is **not** net energy consumption. Time per route
is **base duration**, before RAM reductions. Results are Monte Carlo estimates,
not measured gameplay statistics.

Use a second run with --ssd=8 to compare capacity losses and --cpu=17 to
study higher-CPU loot generation. Do not change balancing numbers until
the results have been reviewed.
