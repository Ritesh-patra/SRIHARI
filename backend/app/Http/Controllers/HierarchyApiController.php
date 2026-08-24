<?php

namespace App\Http\Controllers;

use App\Models\Circle;
use App\Models\Division;
use App\Models\Dtr;
use App\Models\Feeder;
use App\Models\Substation;
use App\Models\Zone;
use Illuminate\Http\Request;

class HierarchyApiController extends Controller
{
    public function circles(Request $request)
    {
        return Circle::where('region_id', $request->integer('region_id'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function divisions(Request $request)
    {
        return Division::where('circle_id', $request->integer('circle_id'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function zones(Request $request)
    {
        return Zone::where('division_id', $request->integer('division_id'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function substations(Request $request)
    {
        return Substation::where('zone_id', $request->integer('zone_id'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function feeders(Request $request)
    {
        return Feeder::where('substation_id', $request->integer('substation_id'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    public function dtrs(Request $request)
    {
        return Dtr::where('feeder_id', $request->integer('feeder_id'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'capacity_kva']);
    }
}
