# Stateful expedition cycle — Sprint 17.10.5.16

Run `php artisan expeditions:stateful-report --target-level=20 --runs=10000 --seed=42 --strategy=xp_per_cycle_minute --output=storage/app/stateful.json`.

The report models **persistent state between runs**: XP rollover, level-ups, maximum battery/integrity growth, base recharge at 4 units/minute (including during expeditions), recharge waiting time, cumulative integrity damage, and repairs consuming 5 units of **scrap_metal** per 10 integrity. Repair bay is assumed active. It stops on a repair-material shortage or expedition safety limit. The GitHub Actions workflow runs all three strategies and uploads JSON artifacts.

**Accuracy warning:** This is still a deterministic *approximation*, not a faithful stochastic simulation. Per-run XP, damage, and scrap-metal loot are fractional Monte Carlo averages from `ExpeditionBalanceSimulator`. Critical aborts and event timing are not individually sampled. Charging is approximated continuously; the game floors charge increments to integer units and has equipment/effects. The simulator uses fixed SSD=32 and CPU=1 for reward estimates, base expedition durations, and no upgrades, market transactions, inventory capacity progression, or actual repair-bay construction cost. Repairs are instant. Real damage may exceed the modeled average and cause earlier failure. Do not treat projected completion times as observed gameplay.

Next: extract a shared per-run stochastic event engine from production rules (or instrument production runs), account for per-run resource inventory, abort time, equipment and effect modifiers, and validate against gameplay fixtures before adjusting balance.
