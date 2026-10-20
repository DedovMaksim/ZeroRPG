# Sprint 17.8 — Expedition economy baseline

This document audits **configured base values**, not observed production results.
Assumptions: no CPU/equipment bonuses, no SSD restrictions, no event rewards, and no guaranteed-fallback loot. Expected quantities are calculated as probability × midpoint of the configured amount interval.

| Location | Difficulty | Route battery cost | Base XP range | Base expected scrap | Base expected copper | Base expected electronics |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| drone_dump | 1 | 80% | 1–3 | 4.75 | 0.70 | 0.10 |
| abandoned_factory | 2 | 90% | 2–4 | 2.275 | 2.625 | 0.20 |
| old_substation | 3 | 100% | 3–5 | 0.60 | 2.10 | 0.825 |

The actual loot generator guarantees a fallback item when no item succeeds its roll, so these expected amounts are **lower bounds on pre-bonus loot expectations**, not exact full-system averages. Inventory capacity can lower received loot. XP bonuses, level-ups and event effects also change realized rewards.

## Event slot probabilities

Current slot trigger chances are 20%, 35%, and 50% for difficulty 1–3, with up to 1, 2, and 3 slots respectively. Before early termination, the expected triggered slot counts are 0.20, 0.70, and 1.50. These are **not** expected successfully applied event counts when an early critical event occurs.

Category weights are positive 40, negative 30, neutral 30, but selection is conditional on which categories still have eligible events. Duplicate keys are disabled, so subsequent slot distributions change after each selection.

A critical EMP is eligible **only** in old_substation (difficulty >=3). It belongs to the negative category with weight 3, while hostile_drones, electrical_surge, and memory_fault have weights 18, 20, and 12. Thus, when all these events remain available, a negative-category draw selects EMP with probability 3/(18+20+12+3) = 3/53, or about 5.66%. Its per-slot unconditional probability at the start is 50% × 30% × (3/53) ≈ **0.849%**. This is a *first-slot* calculation, not the whole-expedition abort probability; no-repeats and early termination alter later slots.

## Risks and design decisions

1. **Route accessibility:** the old_substation costs 100% of base battery, leaving no margin for negative battery events without battery capacity/cost bonuses.
2. **Reward timing:** event effects are applied at expedition completion, although logs are timestamped within the route; battery recharge and temporary-effect expiry can therefore differ from a real-time simulation.
3. **Critical abort rewards:** normal loot and route XP are skipped after an EMP, but **resources or XP granted by earlier random events are currently retained**. Do not describe this as losing *all* expedition rewards.
4. **Economy units:** resource prices and storage size must be included before comparing routes in scraps per energy unit.
5. **Simulation:** run seeded Monte Carlo sampling against a pure, side-effect-free selection model or test fixture before changing live probability settings. Track abort rate, integrity loss, battery delta, route XP, loot value, storage overflow, and profit per battery spent, by location and robot level.

## Suggested acceptance criteria

- No route is permanently inaccessible to a level-appropriate robot without a clear progression requirement.
- Category selection tests and critical abort tests remain green.
- A simulation report explicitly distinguishes theoretical estimates from observed sample statistics.
- Balance changes are isolated in configuration and justified by comparative before/after results.
