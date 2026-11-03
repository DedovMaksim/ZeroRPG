# Sprint 17.10.5.17 — Repair and loot economy audit

## Verified mechanics (main)

- `RobotRepairController::store` requires an active `repair_bay`, no in-progress expedition, and **5 units of `scrap_metal` in the robot's inventory**, restoring up to 10 integrity. `robot.scraps` (currency) cannot pay for repair. A repair bay also requires a warehouse and its own construction materials (`config/buildings.php`).
- `MarketController::sell` and `sellAll` convert physical inventory resources into `robot.scraps` and logistics XP, **removing the original resources**. Selling scrap metal therefore removes repair stock.
- `WarehouseController` can deposit or withdraw resources, but repairs only check the **robot inventory**, not warehouse storage. The simulator does not model transfers.
- `ExpeditionLootGenerator` computes free capacity as total robot SSD minus **existing** inventory usage. `ExpeditionBalanceSimulator` resets available capacity to a fixed profile's SSD **for each independent sample**. `StatefulExpeditionCycleSimulator` uses these per-run carried averages and assumes fresh capacity every run, without persistent inventory or unloading.
- `LootPrioritySorter` prioritizes other valuable resources ahead of scrap metal. Priorities: electronics 20, copper 30, scrap metal 40; rarer resources can rank 8–18. A full SSD may exclude scrap metal even when the route generates it.
- Routes from `research_sector` (level 9) onward have **no scrap metal in their configured regular loot**. Resource events may still award it where eligible; inspect the event catalog before asserting zero possible supply. `cargo_hub` (level 7) has a 95% chance for 6–12 scrap metal before storage limits.
- `ExpeditionEventApplier` resource events add directly to persistent inventory and respect current free storage; critical events can abort runs. The cycle simulator uses per-route means and does not apply event effects sequentially.

## Why the level-5 / level-15 stops are not validated softlocks

The current stateful simulator starts with an active repair bay for free, zero scrap metal, no persistent inventory, fixed SSD=32 and CPU=1 for sampling, and no player actions (sell, deposit, withdraw, switch routes to farm metal). It selects routes by XP rather than resource survival. A stop of `insufficient_scrap_metal_for_repair` therefore means **that scenario's policy failed**, not that the actual game is impossible to progress.

The simulated level-15 stop may be especially sensitive to a high-level route that does not yield regular scrap metal and to the absence of a fallback route. A real player can return to scrap-producing routes, subject to battery/integrity/storage constraints.

## Recommended follow-up, without balance changes

1. Add a diagnostic per-location report with Monte Carlo mean **physical scrap metal carried**, damage, XP, abort rate, SSD utilization and loot loss, using multiple SSD profiles.
2. Add an explicit, configurable player policy: reserve scrap metal for repairs, unload/sell other loot to free SSD, withdraw from warehouse, switch to scrap-farming routes when reserves are low. Model the cost of building repair bay or start from a documented already-built-base state.
3. Replace fractional averages with seeded per-expedition event and loot sampling; carry actual inventory, XP, battery, integrity and effects between runs. Include charging while away and critical abort duration.
4. Add invariants/tests for inventory capacity, repair cost, resource identity, and recovery from low scrap metal. Compare baseline and improved policies on identical seeds.
5. Only then decide whether to adjust route rewards, damage or repair price.

**No gameplay tuning is justified by the current report alone.**
