<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transactions;
use App\Models\TransactionCommodity;
use App\Models\TransactionProducts;
use App\Models\PendingTransactions;
use App\Models\MSPs;
use App\Models\TransactionList;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\Models\StateCoordinators;
use App\Models\CommunityLead;
use Illuminate\Support\Facades\Auth;


class TransactionsController extends Controller
{
    // public function index()
    // {
    //     $transactions = Transactions::with('transaction_list', 'transaction_commodity.commodities', 'farmer_info', 'msp_info.users', 'hub_info', 'active_states')
    //     ->orderBy('created_at', 'desc')
    //     ->get();
    //     return response()->json($transactions);
    // }


public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->query('per_page', 10);
        $search = $request->query('search');
        $state = $request->query('state');
        $lga = $request->query('lga');
        $project = $request->query('projectId');

        $query = Transactions::with('transaction_list', 'transaction_commodity.commodities', 'farmer_info', 'msp_info.users', 'hub_info.states', 'hub_info.lgas', 'active_states', 'projects')
                            ->orderBy('transactionId', 'desc');

        $state_coordinators = StateCoordinators::where('userId', $user->id)->first();
        $community_lead = CommunityLead::where('userId', $user->id)->first();

        // Role-based filtering
        if ($user->role === 4) {
            // State coordinators see only their state's records
            $query->whereHas('hub_info', function($q) use ($state_coordinators) {
                $q->where('state', $state_coordinators->stateId);
            });

            if ($lga) {
                $query->whereHas('hub_info', function($q) use ($lga) {
                    $q->where('lga', $lga);
                });
            }
        } elseif ($user->role === 5) {
            // Community leads see only their community's records
            $query->whereHas('hub_info', function($q) use ($community_lead) {
                $q->where('lga', $community_lead->lga);
            });
        } elseif ($user->role === 1 || $user->role === 3) {
            // Admin and National Coordinator can filter by state and LGA
            if ($state) {
                $query->whereHas('hub_info', function($q) use ($state) {
                    $q->where('state', $state);
                });
            }
            if ($lga) {
                $query->whereHas('hub_info', function($q) use ($lga) {
                    $q->where('lga', $lga);
                });
            }
        }

        // Project filtering for all roles
        if ($project) {
            $query->where('project', $project);
        }

        // Search functionality with role-based restrictions
        if ($search) {
            $query->where(function($q) use ($search, $user, $state_coordinators, $community_lead) {
                // Apply state or LGA restriction for search
                if ($user->role === 4) {
                    $q->whereHas('hub_info', function($hq) use ($state_coordinators) {
                        $hq->where('state', $state_coordinators->stateId);
                    });
                } elseif ($user->role === 5) {
                    $q->whereHas('hub_info', function($hq) use ($community_lead) {
                        $hq->where('lga', $community_lead->lga);
                    });
                }
                // Search conditions
                $q->where(function($sq) use ($search) {
                    $sq->where('farmerFirstName', 'like', "%$search%")
                       ->orWhere('farmerLastName', 'like', "%$search%")
                       ->orWhere('farmerOtherNames', 'like', "%$search%")
                       ->orWhereRaw("CONCAT(farmerFirstName, ' ', farmerLastName, ' ', farmerOtherNames) LIKE ?", ["%{$search}%"])
                       ->orWhere('phoneNumber', 'like', "%$search%");
                });
            });
        }


        $transactions = $query->paginate($perPage);
        
        return response()->json($transactions);
    }


public function analytics(Request $request)
{
//    return $data2 = TransactionList::select('transaction_list.transactionReference', 'services.serviceName as service', 'services.measuringUnit as measure', 'transaction_list.quantity', 'transaction_list.unitCost as cost', \DB::raw("SUM(transaction_list.unitCost*transaction_list.quantity) as totalCost"), 'transaction_list.created_at')
//    ->join('services', 'transaction_list.serviceId', '=', 'services.serviceId')
//     ->groupBy('transaction_list.transactionReference', 'services.serviceName', 'services.measuringUnit', 'transaction_list.quantity', 'transaction_list.unitCost', 'transaction_list.created_at')
//    ->get();
$uniqueMsps = MSPs::select('msps.mspId', 'msps.gender', 'states.stateName as state')
    // ->join('transactions', 'transactions.msp', '=', 'msps.mspId')
    ->join('hubs', 'msps.hub', '=', 'hubs.hubId')
    ->join('states', 'hubs.state', '=', 'states.stateId')
    ->distinct()
    ->get();

$genderCounts = [
    'Male'   => $uniqueMsps->whereIn('gender', ['Male','male'])->count(),
    'Female' => $uniqueMsps->whereIn('gender', ['Female','female'])->count(),
    'State' => $uniqueMsps->groupBy('state')->map(function($group) {
        return [
            'Male'   => $group->whereIn('gender', ['Male','male'])->count(),
            'Female' => $group->whereIn('gender', ['Female','female'])->count(),
            'Total'  => $group->count(),
        ];
    })->toArray()
];
    $data = Transactions::select(
            'states.stateName as state',
            'lgas.lgaName as lga',
            'transactions.created_at',
            'services.serviceName as service',
           \DB::raw("COUNT(DISTINCT CASE WHEN msps.gender IN ('Female','female') THEN msps.mspId END) as FemaleCount"),
        \DB::raw("COUNT(DISTINCT CASE WHEN msps.gender IN ('Male','male') THEN msps.mspId END) as MaleCount"),
        \DB::raw("SUM(totalCost) as totalCost")
        )
        ->join('hubs', 'transactions.hub', '=', 'hubs.hubId')
        ->join('states', 'hubs.state', '=', 'states.stateId') // join to states table
        ->join('lgas', 'hubs.lga', '=', 'lgas.lgaId') // join to lgas table
        ->join('transaction_list', 'transactions.transactionReference', '=', 'transaction_list.transactionReference')
        ->join('services', 'transaction_list.serviceId', '=', 'services.serviceId') // join to services table
        ->join('farmers', 'transactions.farmer', '=', 'farmers.farmerId')
        ->join('msps', 'transactions.msp', '=', 'msps.mspId')
        ->groupBy('states.stateName', 'lgas.lgaName', 'transactions.created_at', 'services.serviceName', 'farmers.gender')
        ->get()
        ->map(function($transaction) {
            return [
                'MonthYear' => $transaction->created_at->format('M-y'),
                'State'     => $transaction->state,  // now you get the name, not just ID
                'LGA'       => $transaction->lga,
                'Activity'  => $transaction->service,
                'Male'      => $transaction->MaleCount,
                'Female'    => $transaction->FemaleCount,
                'Amount'    => $transaction->totalCost,
            ];
        });

    // return response()->json($data);
    return response()->json([
    'genderCounts' => $genderCounts,   // only once, global
    'transactions' => $data // detailed breakdown
]);

    // $data = Transactions::with([
    //     'farmer_info',
    //     'msp_info',
    //     'hub_info.states',
    //     'hub_info.lgas',
    //     'transaction_list.services'
    // ])
    // // ->whereYear('created_at', 2025) // Optional: Filter by year for debugging
    // ->get();

    // $data = Transactions::with([
    //     'farmer_info',
    //     'msp_info',
    //     'hub_info.states',
    //     'hub_info.lgas',
    //     'transaction_list.services'
    // ])
    // // ->whereYear('created_at', 2025) // Optional: Filter by year for debugging
    // ->get();

    // \Log::info('Total transactions fetched: ' . $data->count());

    // $result = $data
    //     ->groupBy(function ($transaction) {
    //         return Carbon::parse($transaction->created_at)->format('M-y');
    //     })
    //     ->flatMap(function ($transactions, $monthYear) {
    //         return $transactions->groupBy(function ($t) {
    //             $state = optional(optional($t->hub_info)->states)->stateName ?? 'Unknown';
    //             $lga = optional(optional($t->hub_info)->lgas)->lgaName ?? 'Unknown';
    //             $service = optional(optional($t->transaction_list)->services)->serviceName ?? 'Unknown';
    //             $key = $state . '-' . $lga . '-' . $service;
    //             \Log::info('Grouping key: ' . $key);
    //             return $key;
    //         })->map(function ($group, $key) use ($monthYear) {
    //             $maleCount = $group->filter(function ($t) {
    //                 return optional($t->farmer_info)->gender === 'Male';
    //             })->count();
    //             $femaleCount = $group->filter(function ($t) {
    //                 return optional($t->farmer_info)->gender === 'Female';
    //             })->count();

    //             $amount = $group->sum('totalCost');

    //             [$state, $lga, $activity] = explode('-', $key);

    //             return [
    //                 "MonthYear" => $monthYear,
    //                 "State"     => $state,
    //                 "LGA"       => $lga,
    //                 "Activity"  => $activity,
    //                 "Male"      => $maleCount,
    //                 "Female"    => $femaleCount,
    //                 "Amount"    => $amount,
    //             ];
    //         });
    //     })
    //     ->values();

    // \Log::info('Final result count: ' . $result->count());
    // return response()->json($result);
}


//    public function analytics(Request $request)
// {
//     // return  $data = Transactions::with(['farmer_info', 'msp_info', 'hub_info.states', 'hub_info.lgas', 'transaction_list.services'])->get();
// $data = Transactions::with([
//         'farmer_info',
//         'msp_info',
//         'hub_info.states',
//         'hub_info.lgas',
//         'transaction_list.services'
//     ])
//     ->get()
//     ->groupBy(function ($transaction) {
//         // Group by month-year
//         return Carbon::parse($transaction->created_at)->format('M-y');
//     })
//     ->flatMap(function ($transactions, $monthYear) {
//         return $transactions->groupBy(function ($t) {
//             return $t->hub_info->states->stateName . '-' . $t->hub_info->lgas->lgaName . '-' . $t->transaction_list->services->serviceName;
//         })->map(function ($group, $key) use ($monthYear) {

//             $maleCount = $group->where('farmer_info.gender', 'Male')->count();
//             $femaleCount = $group->where('farmer_info.gender', 'Female')->count();

//             $amount = $group->sum('totalCost');

//             [$state, $lga, $activity] = explode('-', $key);

//             return [
//                 "MonthYear" => $monthYear,
//                 "State"     => $state,
//                 "LGA"       => $lga,
//                 "Activity"  => $activity,
//                 "Male"      => $maleCount,
//                 "Female"    => $femaleCount,
//                 "Amount"    => $amount, // keep numeric for charts
//             ];
//         });
//     })
//     ->values();        return response()->json($data);
    
// }
    
    
    public function show($transactionId)
    {
        $transaction = Transactions::find($transactionId);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }
        return response()->json($transaction);
    }

   public function store(Request $request)
    {
        // Define validation rules
        $validator = Validator::make($request->all(), [
            // 'msp' => ['required', 'string', 'exists:msps,mspId'],
            'farmer' => ['required', 'string', 'exists:farmers,farmerId'],
            'hub' => ['required', 'integer'],
            'projectId' => ['required', 'string', 'exists:projects,projectId'],
            'transactionType' => ['required', 'in:Service,Product'],
            // 'paymentMethod' => ['required', 'in:Cash,Bank Transfer'],
            // 'transactionStatus' => ['required', 'in:Paid,Pending'],
            'totalCost' => ['required', 'numeric', 'min:0'],
            'transaction_commodity' => ['array'],
            'transaction_commodity.*' => ['integer', 'exists:commodities,commodityId'],
        ]);

        
        // Check for validation errors
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }
        
        try {
            // Start a database transaction
            return DB::transaction(function () use ($request) {
                // Generate a unique transaction reference
                // $transactionReference = Str::uuid()->toString();
                 $transactionReference = strtoupper(Str::random(2)) . mt_rand(1000000000, 9999999999);
                
                $hub = \App\Models\Hubs::where('lga', $request->hub)->first();
                // Create the transaction
                $transaction = Transactions::create([
                    // 'transactionId' => $transactionId,
                    // 'msp' => $request->msp,
                    'farmer' => $request->farmer,
                    'hub' => $hub->hubId,
                    'project' => $request->projectId,
                    'transactionType' => $request->transactionType,
                    // 'paymentMethod' => $request->paymentMethod,
                    'transactionStatus' => "PENDING",
                    'totalCost' => $request->totalCost,
                    'transactionReference' => $transactionReference,
                ]);

                // Attach commodities to the transaction (if provided)
                if (!empty($request->transaction_commodity)) {
                    $commodityData = array_map(function ($commodityId) use ($transactionReference) {
                        return [
                            'transactionReference' => $transactionReference,
                            'commodityId' => $commodityId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }, $request->transaction_commodity);
                    TransactionCommodity::insert($commodityData);
                }

                // Load related data for response
                $transaction->load([
                    // 'msp_info' => fn($query) => $query->select('mspId'),
                    'farmer_info' => fn($query) => $query->select('farmerId'),
                    'hub_info' => fn($query) => $query->select('hubId'),
                    'projects' => fn($query) => $query->select('projectId', 'projectName'),
                    // 'transaction_commodity' => fn($query) => $query->select('transactionReference', 'commodityId')->with(['commodity' => fn($q) => $q->select('id', 'name')]),
                ]);

                return response()->json([
                    'message' => 'Transaction created successfully',
                    'data' => $transaction,
                ], 201);
            });
        } catch (\Exception $e) {
            \Log::error('Error creating transaction: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to create transaction',
            ], 500);
        }
    }

    
    
    public function update(Request $request, $transactionId)
    {
        $transaction = Transactions::find($transactionId);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        $data = $request->all();
        $transaction->update($data);

        return response()->json([
            'message' => 'Transaction updated successfully',
            'transactionId' => $transaction->transactionId,
            'transactionName' => $transaction->transactionName], 201); // HTTP status code 201: Created

    }
    
    public function destroy($transactionId)
    {
        $transaction = Transactions::find($transactionId);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        $transaction->delete();
        return response()->json(['message' => 'Transaction deleted successfully']);
    }
    


        public function updatePaymentMethod(Request $request, $transactionId)
    {
        $transaction = Transactions::find($transactionId);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        $paymentMethod = $request->input('paymentMethod');
        if (!$paymentMethod) {
            return response()->json(['message' => 'Payment method is required'], 400);
        }

        $transaction->paymentMethod = $paymentMethod;
        $transaction->transactionStatus = "PAID";
        $transaction->save();

        return response()->json([
            'message' => 'Payment method updated successfully',
            'transactionId' => $transaction->transactionId,
            'paymentMethod' => $transaction->paymentMethod,
            'transactionStatus' => $transaction->transactionStatus
        ], 200);
    }


           public function updateProjectType(Request $request, $transactionId)
    {
        $transaction = Transactions::find($transactionId);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        $projectId = $request->input('projectId');
        if (!$projectId) {
            return response()->json(['message' => 'Project ID is required'], 400);
        }

        $transaction->project = $projectId;
        $transaction->save();

        return response()->json([
            'message' => 'Project type updated successfully',
            'transactionId' => $transaction->transactionId,
            'projectType' => $transaction->projectType
        ], 200);
    }


    // public function store(Request $request)
    // {
    //     // Define validation rules
    //     $validator = Validator::make($request->all(), [
    //         // 'msp' => ['required', 'string', 'exists:msps,mspId'],
    //         'farmer' => ['required', 'string', 'exists:farmers,farmerId'],
    //         'hub' => ['required', 'integer'],
    //         'projectId' => ['required', 'string', 'exists:projects,projectId'],
    //         'transactionType' => ['required', 'in:Service,Product'],
    //         // 'paymentMethod' => ['required', 'in:Cash,Bank Transfer'],
    //         // 'transactionStatus' => ['required', 'in:Paid,Pending'],
    //         'totalCost' => ['required', 'numeric', 'min:0'],
    //         'transaction_commodity' => ['array'],
    //         'transaction_commodity.*' => ['integer', 'exists:commodities,commodityId'],
    //     ]);

        
    //     // Check for validation errors
    //     if ($validator->fails()) {
    //         return response()->json([
    //             'errors' => $validator->errors(),
    //         ], 422);
    //     }
        
    //     try {
    //         // Start a database transaction
    //         return DB::transaction(function () use ($request) {
    //             // Generate a unique transaction reference
    //             // $transactionReference = Str::uuid()->toString();
    //              $transactionReference = strtoupper(Str::random(2)) . mt_rand(1000000000, 9999999999);
                
    //             $hub = \App\Models\Hubs::where('lga', $request->hub)->first();
    //             // Create the transaction
    //             $transaction = Transactions::create([
    //                 // 'transactionId' => $transactionId,
    //                 // 'msp' => $request->msp,
    //                 'farmer' => $request->farmer,
    //                 'hub' => $hub->hubId,
    //                 'project' => $request->projectId,
    //                 'transactionType' => $request->transactionType,
    //                 // 'paymentMethod' => $request->paymentMethod,
    //                 'transactionStatus' => "PENDING",
    //                 'totalCost' => $request->totalCost,
    //                 'transactionReference' => $transactionReference,
    //             ]);

          

    //             // Load related data for response
    //             $transaction->load([
    //                 // 'msp_info' => fn($query) => $query->select('mspId'),
    //                 'farmer_info' => fn($query) => $query->select('farmerId'),
    //                 'hub_info' => fn($query) => $query->select('hubId'),
    //                 'projects' => fn($query) => $query->select('projectId', 'projectName'),
    //                 // 'transaction_commodity' => fn($query) => $query->select('transactionReference', 'commodityId')->with(['commodity' => fn($q) => $q->select('id', 'name')]),
    //             ]);

    //             return response()->json([
    //                 'message' => 'Transaction created successfully',
    //                 'data' => $transaction,
    //             ], 201);
    //         });
    //     } catch (\Exception $e) {
    //         \Log::error('Error creating transaction: ' . $e->getMessage());
    //         return response()->json([
    //             'error' => 'Failed to create transaction',
    //         ], 500);
    //     }
    // }


    public function bookService(Request $request)
{
    // Define validation rules
    $validator = Validator::make($request->all(), [
        'phoneNumber' => ['required', 'string', 'size:11'],
        'agentId' => ['nullable', 'string', 'exists:agents,agentId'],
        'name' => ['required', 'string', 'max:255'],
        'email' => ['nullable', 'email', 'max:255'],
        'stateId' => ['required', 'integer', 'exists:states,stateId'],
        'lgaId' => ['required', 'integer', 'exists:hubs,hubId'], // Assuming lgaId is hubId
        'serviceId' => ['required', 'string', 'exists:services,serviceId'],
        'equipmentId' => ['required', 'string', 'exists:equipment,equipmentId'],
        'quantity' => ['required', 'integer', 'min:1'],
        // 'projectId' => ['nullable', 'string', 'exists:projects,projectId'], // Optional, will default if not provided
    ]);

    // Check for validation errors
    if ($validator->fails()) {
        return response()->json([
            'errors' => $validator->errors(),
        ], 422);
    }

    try {
        // Start a database transaction
        $transactionResult = DB::transaction(function () use ($request) {
            // Generate a unique transaction reference
            $transactionReference = strtoupper(Str::random(2)) . mt_rand(1000000000, 9999999999);

            // Handle farmer: find or create based on phoneNumber
            $farmer = \App\Models\Farmers::where('phoneNumber', $request->phoneNumber)->first();
            if (!$farmer) {
                $farmer = \App\Models\Farmers::create([
                    'phoneNumber' => $request->phoneNumber,
                    'name' => $request->name,
                    'email' => $request->email,
                    // Add other default fields as needed, e.g., status: 'active'
                ]);
            }

            // Get MSP if agentId provided
            $mspId = $request->agentId ? $request->agentId : null;

            // Get hub (lgaId is hubId)
            $hub = \App\Models\Hubs::where('hubId', $request->lgaId)->first();
            if (!$hub) {
                throw new \Exception('Hub not found');
            }

            // Default projectId if not provided
            // $projectId = $request->projectId ?? \App\Models\Project::first()->projectId ?? null;
            // if (!$projectId) {
            //     throw new \Exception('No default project available');
            // }

            // Fetch service to calculate totalCost (assuming service has 'cost' field)
            $service = \App\Models\Services::where('serviceId', $request->serviceId)
                // ->where('hubId', $request->lgaId) // Ensure it's for the hub
                ->first();
            if (!$service) {
                throw new \Exception('Service not available for the selected hub');
            }
            $totalCost = $service->cost * $request->quantity; // Assuming price per unit

            // Create the transaction
            $transaction = Transactions::create([
                // 'msp' => $mspId,
                'farmer' => $farmer->farmerId,
                'hub' => $hub->hubId,
                // 'project' => $projectId,
                'transactionType' => 'Service',
                'paymentMethod' => 'Cash', // Default, or add to request if needed
                'transactionStatus' => 'PENDING',
                'totalCost' => $totalCost,
                'transactionReference' => $transactionReference,
                // transaction_commodity can be added if needed, e.g., based on service
            ]);

            // Load related data for response
            $transaction->load([
                'msp_info' => fn($query) => $query->select('mspId', 'name'),
                'farmer_info' => fn($query) => $query->select('farmerId', 'farmerFirstName', 'phoneNumber'),
                'hub_info' => fn($query) => $query->select('hubId', 'lga'),
                // 'projects' => fn($query) => $query->select('projectId', 'projectName'),
                // 'transaction_commodity' => fn($query) => $query->select('transactionReference', 'commodityId')->with(['commodity' => fn($q) => $q->select('id', 'name')]),
            ]);

            return $transaction;
        });

        // Send email notifications after successful transaction creation
        $this->sendBookingNotifications($transactionResult, $request);

        return response()->json([
            'message' => 'Booking successful! Transaction created.',
            'data' => $transactionResult,
        ], 201);
    } catch (\Exception $e) {
        \Log::error('Error creating transaction: ' . $e->getMessage());
        return response()->json([
            'error' => $e->getMessage(),
        ], 500);
    }
}

/**
 * Send email notifications to national coordinator, state coordinator, and customer
 */
private function sendBookingNotifications($transaction, $request)
{
    // Fetch national coordinators (roleId 3)
    $nationalCoordinators = \App\Models\User::where('role', 3)->pluck('email')->toArray();

    // Fetch state coordinators (roleId 4) for the specific state from StateCordinators model
    $stateCoordinators = \App\Models\StateCoordinators::where('stateId', $request->stateId)
        ->join('users', 'state_coordinators.userId', '=', 'users.id') // Assuming table name 'state_cordinators' and field 'user_id'
        ->where('users.role', 4)
        ->pluck('users.email')
        ->toArray();

    // Collect all coordinator emails
    $coordinatorEmails = array_merge($nationalCoordinators, $stateCoordinators);
    $coordinatorEmails = array_unique(array_filter($coordinatorEmails)); // Remove duplicates and nulls

    // Customer email (farmer)
    $farmer = \App\Models\Farmers::where('farmerId', $transaction->farmer)->first();
    $customerEmail = $farmer->email;

    // Fetch service for email data
    $service = \App\Models\Services::where('serviceId', $request->serviceId)->first();

    // Prepare email data
    $emailData = [
        'transactionReference' => $transaction->transactionReference,
        'farmerName' => $farmer->name,
        'serviceName' => $service ? $service->serviceName : 'Service',
        'totalCost' => $transaction->totalCost,
        'quantity' => $request->quantity,
        'hubName' => $transaction->hub_info->lga ?? 'Hub',
    ];

    // Send to coordinators
    if (!empty($coordinatorEmails)) {
        \Illuminate\Support\Facades\Mail::to($coordinatorEmails)->send(new \App\Mail\BookingNotification($emailData));
    }

    // Send to customer if email provided
    if ($customerEmail) {
        \Illuminate\Support\Facades\Mail::to($customerEmail)->send(new \App\Mail\BookingCustomerNotification($emailData));
    }
}


}
