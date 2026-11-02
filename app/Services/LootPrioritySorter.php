<?php

namespace App\Services;

class LootPrioritySorter
{
    /** Sort discovered resources before loading them into SSD. */
    public function sort(array $loot): array
    {
        $definitions = config('resources', []);
        $byName = [];
        foreach ($definitions as $slug => $definition) {
            $byName[$definition['name']] = $slug;
        }

        $priorities = config('loot_priorities', []);
        usort($loot, static function (array $a, array $b) use ($definitions, $byName, $priorities): int {
            $aKey = $byName[$a['resource']] ?? $a['resource'];
            $bKey = $byName[$b['resource']] ?? $b['resource'];
            $aRank = $priorities[$aKey] ?? 100;
            $bRank = $priorities[$bKey] ?? 100;
            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }

            $aDef = $definitions[$aKey] ?? [];
            $bDef = $definitions[$bKey] ?? [];
            $aDensity = ($aDef['scrap_value'] ?? 0) / max(1, $aDef['storage_size'] ?? 1);
            $bDensity = ($bDef['scrap_value'] ?? 0) / max(1, $bDef['storage_size'] ?? 1);

            return ($bDensity <=> $aDensity) ?: strcmp($aKey, $bKey);
        });

        return $loot;
    }
}
