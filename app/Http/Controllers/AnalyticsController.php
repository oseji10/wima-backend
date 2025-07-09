<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cancer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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



        
}
