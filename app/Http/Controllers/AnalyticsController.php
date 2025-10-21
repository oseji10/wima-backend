<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Hub;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use App\Models\StateCoordinators;
use App\Models\Farmers;
use App\Models\MSPs;
use App\Models\Agents;
// use App\Models\Investors;
use App\Models\Equipment;
use App\Models\Commodity;
use App\Models\Transactions;



class AnalyticsController extends Controller
{
    public function getTotalBeneficiaries()
{
    $user = auth()->user();

    // Get total beneficiaries enrolled by this user
    $total = Beneficiary::where('enrolledBy', $user->id)->count();

    // Define date ranges
    $oneWeekAgo = now()->subWeek();
    $twoWeeksAgo = $oneWeekAgo->copy()->subWeek();

    // Last week's count
    $lastWeekCount = Beneficiary::where('enrolledBy', $user->id)
        ->where('created_at', '>=', $oneWeekAgo)
        ->count();

    // Previous week's count
    $previousWeekCount = Beneficiary::where('enrolledBy', $user->id)
        ->whereBetween('created_at', [$twoWeeksAgo, $oneWeekAgo])
        ->count();

    // Calculate percentage change
    if ($previousWeekCount == 0) {
        $percentageChange = $lastWeekCount > 0 ? 100 : 0;
        $trend = $lastWeekCount > 0 ? 'increase' : 'no change';
    } else {
        $percentageChange = (($lastWeekCount - $previousWeekCount) / $previousWeekCount) * 100;
        if ($percentageChange > 0) {
            $trend = 'increase';
        } elseif ($percentageChange < 0) {
            $trend = 'decrease';
        } else {
            $trend = 'no change';
        }
    }

    return response()->json([
        'total' => $total,
        'last_week' => $lastWeekCount,
        'previous_week' => $previousWeekCount,
        'percentage_change' => round($percentageChange, 2),
        'trend' => $trend
    ]);
}


public function dashboard()
{
    $user = Auth::user();

    // State coordinator record (if any)
    $stateCoordinator = StateCoordinators::where('userId', $user->id)->first();

    // Hub coordinator record (if any) - adjust model/column names to match your app
    // $hubCoordinator = HubCoordinators::where('userId', $user->id)->first();

    // ---------- FARMERS ----------
    $farmersQuery = Farmers::query(); // start with a query builder

    // If user is a state coordinator -> filter by hubs in that state
    if ($user->role === 4 && $stateCoordinator) {
        $farmersQuery->whereHas('hubs', function ($q) use ($stateCoordinator) {
            $q->where('state', $stateCoordinator->stateId);
        });
    }

    // If user is a hub coordinator -> filter by that hub id
    if ($user->role === 5 && $hubCoordinator) {
        $farmersQuery->where('hubId', $hubCoordinator->hubId);
    } elseif ($user->role === 5 && isset($user->hubId)) {
        // fallback if hub_id is stored on users table
        $farmersQuery->where('hubId', $user->hubId);
    }

    // now compute the count (this will respect any filters applied above)
    $farmersPerHub = $farmersQuery->count();

    // ---------- MSPs ----------
    $mspsQuery = MSPs::query();

    if ($user->role === 4 && $stateCoordinator) {
        $mspsQuery->whereHas('hubs', function ($q) use ($stateCoordinator) {
            $q->where('state', $stateCoordinator->stateId);
        });
    }

    if ($user->role === 5 && $hubCoordinator) {
        $mspsQuery->where('hubId', $hubCoordinator->hubId);
    } elseif ($user->role === 5 && isset($user->hubId)) {
        $mspsQuery->where('hubId', $user->hubId);
    }

    $mspsPerHub = $mspsQuery->count();


     // ---------- Equipment ----------
    $equipmentQuery = Equipment::query();

    if ($user->role === 4 && $stateCoordinator) {
        $equipmentQuery->whereHas('hubs', function ($q) use ($stateCoordinator) {
            $q->where('state', $stateCoordinator->stateId);
        });
    }

    if ($user->role === 5 && $hubCoordinator) {
        $equipmentQuery->where('hubId', $hubCoordinator->hubId);
    } elseif ($user->role === 5 && isset($user->hubId)) {
        $equipmentQuery->where('hubId', $user->hubId);
    }

    $equipmentsPerHub = $equipmentQuery->count();




    // ---------- GLOBAL TOTALS ----------
    $totalFarmers = Farmers::count();
    $totalMSPs = MSPs::count();
    $totalAgents = Agents::count();
    $totalEquipment = Equipment::count();
    $totalCommodities = Commodity::count();
    $totalAgents = Agents::count();
    $totalTransactions = Transactions::sum('totalCost');

    return response()->json([
        'farmersInMyHub'   => $farmersPerHub,
        'mspsInMyHub'      => $mspsPerHub,
        'equipmentInMyHub' => $equipmentsPerHub,
        'totalFarmers'    => $totalFarmers,
        'totalMSPs'       => $totalMSPs,
        'totalAgents'     => $totalAgents,
        'totalEquipment'  => $totalEquipment,
        'totalCommodities'=> $totalCommodities,
        'totalAgents'=> $totalAgents,
        'totalTransactions'=> $totalTransactions,
    ]);
}


        
}
