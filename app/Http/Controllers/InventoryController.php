<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index()
    {
        $robot = auth()->user()->robot;

        $warehouse = $robot->base
            ->buildings()
            ->where('key', 'warehouse')
            ->where('status', 'active')
            ->first();

        return view('inventory', [
            'robot' => $robot,
            'warehouse' => $warehouse,
        ]);
    }
}