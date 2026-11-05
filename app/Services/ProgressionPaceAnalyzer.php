<?php

namespace App\Services;

class ProgressionPaceAnalyzer
{
    /**
     * Derive per-level deltas from cumulative simulation milestones.
     * Only reached levels are reported; a missing level is never fabricated.
     */
    public function analyze(array $report): array
    {
        $previous = [
            'level' => 1,
            'elapsed_minutes' => 0,
            'waiting_minutes' => 0,
            'expeditions' => 0,
            'repairs' => 0,
            'farming_expeditions' => 0,
        ];
        $rows = [];
        foreach ($report['milestones'] as $milestone) {
            $row = [
                'from_level' => $previous['level'],
                'to_level' => $milestone['level'],
                'minutes' => round($milestone['elapsed_minutes'] - $previous['elapsed_minutes'], 2),
                'waiting_minutes' => round($milestone['waiting_minutes'] - $previous['waiting_minutes'], 2),
                'expeditions' => $milestone['expeditions'] - $previous['expeditions'],
                'repairs' => $milestone['repairs'] - $previous['repairs'],
                'farming_expeditions' => $milestone['farming_expeditions'] - $previous['farming_expeditions'],
                'last_route' => $milestone['route'],
            ];
            $rows[] = $row;
            $previous = $milestone;
        }

        $slowest = $rows;
        usort($slowest, static fn (array $a, array $b) => $b['minutes'] <=> $a['minutes']);

        return [
            'parameters' => $report['parameters'],
            'reached_level' => $report['result']['reached_level'],
            'stop_reason' => $report['result']['stop_reason'],
            'levels' => $rows,
            'slowest_intervals' => array_slice($slowest, 0, 5),
            'limitations' => 'Milestones are rounded cumulative values. Last_route is the final expedition of each interval, not the most-used route. No per-route distribution is available. This is deterministic scenario analysis, not actual player telemetry.',
        ];
    }
}
