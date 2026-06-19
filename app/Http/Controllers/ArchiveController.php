<?php

namespace App\Http\Controllers;

use App\Models\ExpeditionReport;

class ArchiveController extends Controller
{
    public function index()
    {
        $robot = auth()->user()->robot;

        $reports = ExpeditionReport::where('robot_id', $robot->id)
            ->latest('finished_at')
            ->paginate(10);

        $allReports = ExpeditionReport::where('robot_id', $robot->id)->get();

        $totalReports = $allReports->count();

        $totalXp = $allReports->sum('xp_gained');

        $totalResources = [];

        foreach ($allReports as $report) {
            foreach ($report->resources ?? [] as $item) {
                $resourceName = $item['resource'];
                $amount = $item['amount'];

                $totalResources[$resourceName] =
                    ($totalResources[$resourceName] ?? 0) + $amount;
            }
        }

        arsort($totalResources);    

        return view('archive.index', compact(
            'robot',
            'reports',
            'totalReports',
            'totalXp',
            'totalResources'
        ));
    }
}