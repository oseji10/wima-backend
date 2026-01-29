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
use App\Models\EquipmentBooking;
use App\Models\Farmers;
use App\Models\Hubs;
use App\Services\ZohoBooks;
use App\Models\Lgas;
use App\Models\Services;
use App\Models\State;
use App\Models\User;
// use App\Models\MSPs;

use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use App\Imports\BulkMspFarmerImport;
use PhpOffice\PhpSpreadsheet\IOFactory;


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
    $role = (int) $user->role;   // 🔥 FIX: Normalize role for both servers

    $perPage = $request->query('per_page', 10);
    $search = $request->query('search');
    $state = $request->query('state');
    $lga = $request->query('lga');
    $project = $request->query('projectId');

    $query = Transactions::with(
                'transaction_list',
                'transaction_commodity.commodities',
                'farmer_info',
                'msp_info.users',
                'hub_info.states',
                'hub_info.lgas',
                'active_states',
                'projects'
            )
            ->orderBy('transactionId', 'desc');

    $state_coordinators = StateCoordinators::where('userId', $user->id)->first();
    $community_lead = CommunityLead::where('userId', $user->id)->first();

    /*
    |--------------------------------------------------------------------------
    | ROLE-BASED FILTERS
    |--------------------------------------------------------------------------
    */

    if ($role === 4 && $state_coordinators) {
        // State Coordinator → filter by assigned state
        $query->whereHas('hub_info', function($q) use ($state_coordinators) {
            $q->where('state', $state_coordinators->stateId);
        });

        if ($lga) {
            $query->whereHas('hub_info', function($q) use ($lga) {
                $q->where('lga', $lga);
            });
        }

    } elseif ($role === 5 && $community_lead) {
        // Community Lead → filter by assigned LGA
        $query->whereHas('hub_info', function($q) use ($community_lead) {
            $q->where('lga', $community_lead->lga);
        });

    } elseif (in_array($role, [1, 3])) {
        // Admin + National Coordinator
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

    /*
    |--------------------------------------------------------------------------
    | PROJECT FILTER
    |--------------------------------------------------------------------------
    */
    if ($project) {
        $query->where('project', $project);
    }

    /*
    |--------------------------------------------------------------------------
    | SEARCH FUNCTIONALITY (with role restrictions)
    |--------------------------------------------------------------------------
    */
    if ($search) {
        $query->where(function($q) use ($search, $role, $state_coordinators, $community_lead) {

            // Apply restricted search for role 4 or 5
            if ($role === 4 && $state_coordinators) {
                $q->whereHas('hub_info', function($hq) use ($state_coordinators) {
                    $hq->where('state', $state_coordinators->stateId);
                });
            } elseif ($role === 5 && $community_lead) {
                $q->whereHas('hub_info', function($hq) use ($community_lead) {
                    $hq->where('lga', $community_lead->lga);
                });
            }

            // Search logic
            $q->where(function($sq) use ($search) {
                $sq->where('transactionReference', 'like', "%$search%")
                   ->orWhere('transactionId', 'like', "%$search%")
                   ->orWhereHas('farmer_info', function($fq) use ($search) {
                       $fq->where('farmerFirstName', 'like', "%$search%")
                          ->orWhere('farmerLastName', 'like', "%$search%")
                          ->orWhere('farmerOtherNames', 'like', "%$search%")
                          ->orWhere('phoneNumber', 'like', "%$search%")
                          ->orWhere('farmerId', 'like', "%$search%")
                          ->orWhereRaw("CONCAT(farmerFirstName, ' ', farmerLastName) LIKE ?", ["%{$search}%"]);
                   })
                   ->orWhereHas('msp_info.users', function($mq) use ($search) {
                       $mq->where('firstName', 'like', "%$search%")
                          ->orWhere('lastName', 'like', "%$search%")
                          ->orWhere('phoneNumber', 'like', "%$search%")
                          ->orWhereRaw("CONCAT(firstName, ' ', lastName) LIKE ?", ["%{$search}%"]);
                   });
            });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | FINAL DATA
    |--------------------------------------------------------------------------
    */
    $transactions = $query->paginate($perPage);

    return response()->json($transactions);
}

public function analytics(Request $request)
{
    // Get unique MSPs with their state and project (for global counts)
    $uniqueMsps = MSPs::select(
            'msps.mspId',
            'msps.gender',
            'states.stateName as state',
            'projects.projectName as projectName'
        )
        ->join('hubs', 'msps.hub', '=', 'hubs.hubId')
        ->join('states', 'hubs.state', '=', 'states.stateId')
        ->join('projects', 'msps.project', '=', 'projects.projectId')
        ->distinct()
        ->get();

    // Get unique MSP counts from transactions by state and project
    $transactionMsps = \DB::table('transactions')
        ->select(
            'states.stateName as state',
            'projects.projectName as projectName',
            'msps.gender',
            \DB::raw('COUNT(DISTINCT msps.mspId) as count')
        )
        ->join('hubs', 'transactions.hub', '=', 'hubs.hubId')
        ->join('states', 'hubs.state', '=', 'states.stateId')
        ->join('msps', 'transactions.msp', '=', 'msps.mspId')
        ->join('projects', 'transactions.project', '=', 'projects.projectId')
        ->groupBy('states.stateName', 'projects.projectName', 'msps.gender')
        ->get();

    $genderCounts = [
        'Male'   => $uniqueMsps->whereIn('gender', ['Male', 'male'])->count(),
        'Female' => $uniqueMsps->whereIn('gender', ['Female', 'female'])->count(),
        'StateProject' => []
    ];

    // Process the grouped results
    foreach ($transactionMsps as $record) {
        $state = $record->state;
        $project = $record->projectName;
        
        if (!isset($genderCounts['StateProject'][$state])) {
            $genderCounts['StateProject'][$state] = [];
        }
        if (!isset($genderCounts['StateProject'][$state][$project])) {
            $genderCounts['StateProject'][$state][$project] = [
                'Male' => 0,
                'Female' => 0,
                'Total' => 0
            ];
        }
        
        if (in_array($record->gender, ['Male', 'male'])) {
            $genderCounts['StateProject'][$state][$project]['Male'] += $record->count;
        } else {
            $genderCounts['StateProject'][$state][$project]['Female'] += $record->count;
        }
        
        $genderCounts['StateProject'][$state][$project]['Total'] += $record->count;
    }

    $data = Transactions::select(
            'states.stateName as state',
            'lgas.lgaName as lga',
            'transactions.created_at',
            'services.serviceName as service',
            'projects.projectName as projectName',
            \DB::raw("COUNT(DISTINCT CASE WHEN msps.gender IN ('Female','female') THEN msps.mspId END) as FemaleCount"),
            \DB::raw("COUNT(DISTINCT CASE WHEN msps.gender IN ('Male','male') THEN msps.mspId END) as MaleCount"),
            \DB::raw("SUM(totalCost) as totalCost")
        )
        ->join('hubs', 'transactions.hub', '=', 'hubs.hubId')
        ->join('states', 'hubs.state', '=', 'states.stateId')
        ->join('lgas', 'hubs.lga', '=', 'lgas.lgaId')
        ->join('transaction_list', 'transactions.transactionReference', '=', 'transaction_list.transactionReference')
        ->join('services', 'transaction_list.serviceId', '=', 'services.serviceId')
        ->join('farmers', 'transactions.farmer', '=', 'farmers.farmerId')
        ->join('msps', 'transactions.msp', '=', 'msps.mspId')
        ->join('projects', 'transactions.project', '=', 'projects.projectId')
        ->groupBy('states.stateName', 'lgas.lgaName', 'transactions.created_at', 'services.serviceName', 'projects.projectName')
        ->get()
        ->map(function($transaction) {
            return [
                'MonthYear' => $transaction->created_at->format('M-y'),
                'State'     => $transaction->state,
                'LGA'       => $transaction->lga,
                'Activity'  => $transaction->service,
                'Male'      => $transaction->MaleCount,
                'Female'    => $transaction->FemaleCount,
                'Amount'    => $transaction->totalCost,
                'Project'   => $transaction->projectName,
            ];
        });

    return response()->json([
        'genderCounts' => $genderCounts,
        'transactions' => $data
    ]);
}


    public function show($transactionId)
    {
        $transaction = Transactions::find($transactionId);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }
        return response()->json($transaction);
    }

   public function store(Request $request, ZohoBooks $zoho)
{
    // Validate
    $validator = Validator::make($request->all(), [
        'farmer' => ['required', 'string', 'exists:farmers,farmerId'],
        'hub' => ['required', 'integer'],
        'projectId' => ['required', 'string', 'exists:projects,projectId'],
        // 'transactionType' => ['required', 'in:Service,Product'],
        'totalCost' => ['required', 'numeric', 'min:0'],
        'transaction_commodity' => ['array'],
        'transaction_commodity.*' => ['integer', 'exists:commodities,commodityId'],
        // 'quantity' => ['required', 'integer', 'min:1'], // Change this to
'services' => ['required', 'array', 'min:1'],
'services.*.serviceId' => ['required', 'string', 'exists:services,serviceId'],
'services.*.quantity' => ['required', 'integer', 'min:1'],
'services.*.equipmentId' => ['required', 'string', 'exists:equipment,equipmentId'],// You need this for invoice
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    try {
        return DB::transaction(function () use ($request, $zoho) {

            $transactionReference = strtoupper(Str::random(2)) . mt_rand(1000000000, 9999999999);

            // Fetch farmer
            $farmer = Farmers::where('farmerId', $request->farmer)->first();

            // Fetch hub
            $hub = Hubs::where('lga', $request->hub)->first();

            // Fetch LGA + State
            $lga = Lgas::find($hub->lga);
            $state = State::find($lga->stateId);

            // Fetch service (IMPORTANT)


            // Create transaction
            $transaction = Transactions::create([
                'farmer' => $request->farmer,
                'hub' => $hub->hubId,
                'project' => $request->projectId,
                'transactionType' => $request->transactionType,
                'transactionStatus' => "PENDING",
                'totalCost' => $request->totalCost,
                'transactionReference' => $transactionReference,
            ]);

            // Insert commodity list
            if ($request->transaction_commodity) {
                $commodityData = array_map(function ($id) use ($transactionReference) {
                    return [
                        'transactionReference' => $transactionReference,
                        'commodityId' => $id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }, $request->transaction_commodity);

                TransactionCommodity::insert($commodityData);
            }

            // -----------------------------------
            // CREATE ZOHO CUSTOMER
            // -----------------------------------
            $contactName = $farmer->farmerFirstName . ' ' . $farmer->farmerLastName;

            $customerPayload = [
                "contact_name" => $contactName.' - '.$farmer->farmerId.' - '.$transactionReference,
                "company_name" => $farmer->company ?? '',
                "billing_address" => [
                    "city" => $lga->lgaName ?? "N/A",
                    "state" => $state->stateName ?? "N/A",
                    "country" => "Nigeria",
                ],
                "email" => $request->email ?? "",
                "phone" => $farmer->phoneNumber,
                "contact_type" => "customer",
                "portal_status" => "enabled",
                "contact_persons" => [
                    [
                        "first_name" => $farmer->farmerFirstName,
                        "last_name" => $farmer->farmerLastName,
                        "email" => $request->email ?? "",
                        "phone" => $farmer->phoneNumber,
                        "is_primary_contact" => true
                    ]
                ]
            ];

            $zohoCustomer = $zoho->createCustomer($customerPayload);

            if (!isset($zohoCustomer['contact']['contact_id'])) {
                throw new \Exception("Zoho Customer creation failed");
            }

            $zohoCustomerId = $zohoCustomer['contact']['contact_id'];

            $farmer->update(['zohoCustomerId' => $zohoCustomerId]);

            // -----------------------------------
            // CREATE ZOHO INVOICE
            // -----------------------------------
            $lineItems = [];
foreach ($request->services as $serviceData) {
    $service = Services::find($serviceData['serviceId']);
    if ($service) {
        $lineItems[] = [
            "item_id" => $service->zohoId,
            "description" =>
                $service->serviceName . ' for ' .
                $farmer->farmerFirstName . ' ' . $farmer->farmerLastName .
                ' at ' . $lga->lgaName,
            "rate" => $service->cost,
            "quantity" => $serviceData['quantity'],
        ];
    }
}

$invoicePayload = [
    "customer_id" => $zohoCustomerId,
    "date" => now()->toDateString(),
    "due_date" => now()->addDays(7)->toDateString(),
    "send" => "true",
    "email" => "true",
    "line_items" => $lineItems
];

            $zohoInvoice = $zoho->createInvoice($invoicePayload);

            if (!isset($zohoInvoice['invoice']['invoice_id'])) {
                throw new \Exception("Zoho Invoice creation failed");
            }

            $zohoInvoiceId = $zohoInvoice['invoice']['invoice_id'];

            $zoho->markInvoiceAsSent($zohoInvoiceId);

            $transaction->update(['zohoInvoiceId' => $zohoInvoiceId]);

            return response()->json([
                'message' => 'Transaction created successfully',
                'data' => $transaction,
            ], 201);
        });

    } catch (\Exception $e) {
        \Log::error("Error creating transaction: ".$e->getMessage());
        return response()->json([
            'error' => $e->getMessage(),
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


public function updatePaymentMethod(Request $request, $transactionId, ZohoBooks $zoho)
{
    $transaction = Transactions::with('farmer_info')->find($transactionId);
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

    $payload = [
        "customer_id" => $transaction->farmer_info->zohoCustomerId,
        "payment_mode" => $paymentMethod,
        "amount" => floatval($transaction->totalCost),
        "date" => now()->toDateString(),
        "invoices" => [
            [
                "invoice_id" => $transaction->zohoInvoiceId,
                "amount_applied" => floatval($transaction->totalCost)
            ]
        ]
    ];

    $zohoInvoice = $zoho->recordPayment($payload);

    return response()->json([
        'message' => 'Payment method updated successfully',
        'transactionId' => $transaction->transactionId,
        'paymentMethod' => $transaction->paymentMethod,
        'transactionStatus' => $transaction->transactionStatus,
        'zohoInvoice' => $zohoInvoice
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



//     public function bookService(Request $request)
// {
//     // Define validation rules
//     $validator = Validator::make($request->all(), [
//         'phoneNumber' => ['required', 'string', 'size:11'],
//         'agentId' => ['nullable', 'string', 'exists:agents,agentId'],
//         'name' => ['required', 'string', 'max:255'],
//         'email' => ['nullable', 'email', 'max:255'],
//         'stateId' => ['required', 'integer', 'exists:states,stateId'],
//         'lgaId' => ['required', 'integer', 'exists:hubs,hubId'], // Assuming lgaId is hubId
//         'serviceId' => ['required', 'string', 'exists:services,serviceId'],
//         'equipmentId' => ['required', 'string', 'exists:equipment,equipmentId'],
//         'quantity' => ['required', 'integer', 'min:1'],
//         'gender' => ['nullable', 'string', 'in:Male,Female'],
//         'age' => ['nullable', 'integer', 'min:0'],
//         'bookingDate' => ['nullable', 'date'],
//         // 'projectId' => ['nullable', 'string', 'exists:projects,projectId'], // Optional, will default if not provided
//     ]);

//     // Check for validation errors
//     if ($validator->fails()) {
//         return response()->json([
//             'errors' => $validator->errors(),
//         ], 422);
//     }

//     try {
//         // Start a database transaction
//         $transactionResult = DB::transaction(function () use ($request) {
//             // Generate a unique transaction reference
//             $transactionReference = strtoupper(Str::random(2)) . mt_rand(1000000000, 9999999999);
//             $farmerId = strtoupper(Str::random(2)) . mt_rand(100000000, 999999999);

//             $hub = \App\Models\Hubs::where('hubId', $request->lgaId)->first();
//             // Handle farmer: find or create based on phoneNumber
//             $farmer = \App\Models\Farmers::where('phoneNumber', $request->phoneNumber)->first();
//             if (!$farmer) {
//                 $farmer = \App\Models\Farmers::create([
//                     'phoneNumber' => $request->phoneNumber,
//                     'name' => $request->name,
//                     'email' => $request->email,
//                     'farmerId' => $farmerId,
//                     'farmerFirstName' => explode(' ', $request->name)[0],
//                     'farmerLastName' => isset(explode(' ', $request->name)[1]) ? explode(' ', $request->name)[1] : '',
//                     'farmerOtherNames' => isset(explode(' ', $request->name)[2]) ? explode(' ', $request->name)[2] : '',
//                     'gender' => $request->gender ?? 'Not Specified',
//                     'status' => 'active',
//                     'hub' => $hub->hubId,
//                     'ageBracket' => $request->age,
//                     // Add other default fields as needed, e.g., status: 'active'
//                 ]);
//             }

//             // Get MSP if agentId provided
//             $mspId = $request->agentId ? $request->agentId : null;

//             // Get hub (lgaId is hubId)
//             $hub = \App\Models\Hubs::where('hubId', $request->lgaId)->first();
//             if (!$hub) {
//                 throw new \Exception('Hub not found');
//             }

//             // Default projectId if not provided
//             // $projectId = $request->projectId ?? \App\Models\Project::first()->projectId ?? null;
//             // if (!$projectId) {
//             //     throw new \Exception('No default project available');
//             // }

//             // Fetch service to calculate totalCost (assuming service has 'cost' field)
//             $service = \App\Models\Services::where('serviceId', $request->serviceId)
//                 // ->where('hubId', $request->lgaId) // Ensure it's for the hub
//                 ->first();
//             if (!$service) {
//                 throw new \Exception('Service not available for the selected hub');
//             }
//             $totalCost = $service->cost * $request->quantity; // Assuming price per unit

//             $isAvailable = EquipmentBooking::where('equipmentId', $request->equipmentId)
//                 ->where('bookingDate', $request->bookingDate)
//                 ->whereIn('status', ['reserved', 'booked'])
//                 ->doesntExist();

//             if (!$isAvailable) {
//                 return response()->json(['error' => 'Equipment not available on selected date.'], 422);
//             }

//             // Create the transaction
//             $transaction = Transactions::create([
//                 // 'msp' => $mspId,
//                 'farmer' => $farmer->farmerId,
//                 'hub' => $hub->hubId,
//                 // 'project' => $projectId,
//                 'transactionType' => 'Service',
//                 'paymentMethod' => 'Cash', // Default, or add to request if needed
//                 'transactionStatus' => 'PENDING',
//                 'totalCost' => $totalCost,
//                 'transactionReference' => $transactionReference,
//                 // transaction_commodity can be added if needed, e.g., based on service
//             ]);

//             $booking = EquipmentBooking::create([
//                 'transactionId' => $transaction->transactionId,
//                 'equipmentId' => $request->equipmentId,
//                 'bookingDate' => $request->bookingDate,
//                 'status' => 'reserved',
//             ]);

//             // Load related data for response
//             $transaction->load([
//                 'msp_info' => fn($query) => $query->select('mspId', 'name'),
//                 'farmer_info' => fn($query) => $query->select('farmerId', 'farmerFirstName', 'phoneNumber'),
//                 'hub_info' => fn($query) => $query->select('hubId', 'lga'),
//                 // 'projects' => fn($query) => $query->select('projectId', 'projectName'),
//                 // 'transaction_commodity' => fn($query) => $query->select('transactionReference', 'commodityId')->with(['commodity' => fn($q) => $q->select('id', 'name')]),
//             ]);

//             return $transaction;
//         });

//         // Send email notifications after successful transaction creation
//         $this->sendBookingNotifications($transactionResult, $request);

//         return response()->json([
//             'message' => 'Booking successful! Transaction created.',
//             'data' => $transactionResult,
//         ], 201);
//     } catch (\Exception $e) {
//         \Log::error('Error creating transaction: ' . $e->getMessage());
//         return response()->json([
//             'error' => $e->getMessage(),
//         ], 500);
//     }
// }




public function bookServiceAndZoho(Request $request, ZohoBooks $zoho)
{
    // -----------------------------
    // 1. Validate Request
    // -----------------------------
    $validator = Validator::make($request->all(), [
        'phoneNumber' => ['required', 'string', 'size:11'],
        'agentId' => ['nullable', 'string', 'exists:agents,agentId'],
        'name' => ['required', 'string', 'max:255'],
        'email' => ['nullable', 'email', 'max:255'],
        'stateId' => ['required', 'integer', 'exists:states,stateId'],
        'lgaId' => ['required', 'integer', 'exists:hubs,hubId'],
        'serviceId' => ['required', 'string', 'exists:services,serviceId'],
        'equipmentId' => ['required', 'string', 'exists:equipment,equipmentId'],
        'quantity' => ['required', 'integer', 'min:1'],
        'gender' => ['nullable', 'string', 'in:Male,Female'],
        'age' => ['nullable', 'integer', 'min:0'],
        'bookingDate' => ['nullable', 'date'],
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    DB::beginTransaction();  // <<===== START TRANSACTION

    try {

        // -----------------------------------
        // Generate IDs
        // -----------------------------------
        $farmerId = strtoupper(Str::random(10));
        $transactionReference = strtoupper(Str::random(2)) . mt_rand(1000000000, 9999999999);

        // -----------------------------------
        // Fetch Hub & Service
        // -----------------------------------
        $hub = Hubs::where('hubId', $request->lgaId)->firstOrFail();
        $service = Services::where('serviceId', $request->serviceId)->firstOrFail();

        $totalCost = $service->cost * $request->quantity;

        // -----------------------------------
        // Create Farmer
        // -----------------------------------
        $farmer = Farmers::create([
            'phoneNumber' => $request->phoneNumber,
            'name' => $request->name,
            'email' => $request->email,
            'farmerId' => $farmerId,
            'farmerFirstName' => explode(' ', $request->name)[0],
            'farmerLastName' => explode(' ', $request->name)[1] ?? '',
            'farmerOtherNames' => explode(' ', $request->name)[2] ?? '',
            'gender' => $request->gender ?? 'Not Specified',
            'status' => 'active',
            'hub' => $hub->hubId,
            'ageBracket' => $request->age,
        ]);

        // -----------------------------------
        // Create Transaction
        // -----------------------------------
        $transaction = Transactions::create([
            'farmer' => $farmer->farmerId,
            'hub' => $hub->hubId,
            'transactionType' => 'Service',
            'paymentMethod' => 'Cash',
            'transactionStatus' => 'PENDING',
            'totalCost' => $totalCost,
            'transactionReference' => $transactionReference,
        ]);

        // -----------------------------------
        // Create Booking
        // -----------------------------------
        EquipmentBooking::create([
            'transactionId' => $transaction->transactionId,
            'equipmentId' => $request->equipmentId,
            'bookingDate' => $request->bookingDate,
            'status' => 'reserved',
        ]);

        // Fetch LGA & State
        $lga = Lgas::where('lgaId', $hub->lga)->first();
        $state = State::where('stateId', $hub->state)->first();

        $contactName = $request->name ?: 'Default Customer';

        // -----------------------------------
        // Prepare Zoho Customer Payload
        // -----------------------------------
        $customerPayload = [
            "contact_name" => $contactName . ' - ' . $farmerId . ' - ' . $transactionReference,
            "company_name" => $farmer->company ?? '',
            "billing_address" => [
                "city" => $lga->lgaName ?? "N/A",
                "state" => $state->stateName ?? "N/A",
                "country" => "Nigeria"
            ],
            "email" => $request->email ?? "",
            "phone" => $farmer->phoneNumber,
            "contact_type" => "customer",
            "portal_status" => "enabled",
            "contact_persons" => [
                [
                    "first_name" => $farmer->farmerFirstName,
                    "last_name"  => $farmer->farmerLastName,
                    "email"      => $request->email ?? "",
                    "phone"      => $farmer->phoneNumber,
                    "is_primary_contact" => true
                ]
            ]
        ];

        // -----------------------------------
        // Call Zoho: Create Customer
        // -----------------------------------
        $zohoCustomer = $zoho->createCustomer($customerPayload);

        if (!isset($zohoCustomer['contact']['contact_id'])) {
            throw new \Exception("Zoho Customer creation failed");
        }

        $zohoCustomerId = $zohoCustomer['contact']['contact_id'];

        // Update farmer with Zoho ID
        $farmer->update([
            'zohoCustomerId' => $zohoCustomerId
        ]);

        // -----------------------------------
        // Create Zoho Invoice
        // -----------------------------------
        $invoicePayload = [
            "customer_id" => $zohoCustomerId,
            "date" => now()->toDateString(),
            "due_date" => now()->addDays(7)->toDateString(),
            "send" => "true",
            "email" => "true",  // auto-email invoice
            "line_items" => [
                [
                    "item_id" => $service->zohoId,
                    "description" => $service->serviceName . ' for ' . $farmer->farmerFirstName . ' ' . $farmer->farmerLastName . ' at ' . $lga->lgaName,
                    "rate" => $service->cost,
                    "quantity" => $request->quantity
                ]
            ]
        ];

        $zohoInvoice = $zoho->createInvoice($invoicePayload);

        if (!isset($zohoInvoice['invoice']['invoice_id'])) {
            throw new \Exception("Zoho Invoice creation failed");
        }

        $zohoInvoiceId = $zohoInvoice['invoice']['invoice_id'];
        $zoho->markInvoiceAsSent($zohoInvoiceId);
        // Update transaction with Zoho invoice ID
        $transaction->update([
            'zohoInvoiceId' => $zohoInvoiceId
        ]);

        DB::commit();  // <<===== SUCCESS

        return response()->json([
            "message" => "Farmer, transaction, customer & invoice created successfully",
            "farmer" => $farmer,
            "transaction" => $transaction,
            "zoho_customer" => $zohoCustomer,
            "zoho_invoice" => $zohoInvoice
        ]);

    } catch (\Exception $e) {

        DB::rollBack(); // <<===== ROLLBACK EVERYTHING

        return response()->json([
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
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


public function checkEquipmentAvailability(Request $request)
    {
        // $validator = Validator::make(['equipmentId' => $equipmentId, 'bookingDate' => $bookingDate], [
        //     'equipmentId' => 'required|exists:equipment,equipmentId', // Assuming equipments table
        //     'bookingDate' => 'required|date|after_or_equal:today',
        // ]);

        // if ($validator->fails()) {
        //     return response()->json(['available' => false, 'errors' => $validator->errors()], 422);
        // }

        $isBooked = EquipmentBooking::where('equipmentId', $request->equipmentId)
            ->where('bookingDate', $request->bookingDate)
            ->where('status', 'reserved')
            ->orWhere('status', 'booked') // Exclude cancelled/completed if needed
            ->first();

        return response()->json(['available' => !$isBooked, 'status' => $isBooked->status ?? null]);
    }

 



public function uploadBulk(Request $request)
{
    $request->validate([
        'file'       => 'required|file|mimes:xls,xlsx|max:10240',
        'projectId'  => 'required|integer|exists:projects,projectId',
        // stateId & hubId no longer required from payload
    ]);

    $projectId = $request->projectId;
    $addedBy   = auth()->id();

    $file = $request->file('file');
    $filePath = $file->getRealPath();

    try {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet   = $spreadsheet->getActiveSheet();

        $highestRow    = $worksheet->getHighestRow();
        if ($highestRow <= 1) {
            return response()->json(['error' => 'No data rows found'], 422);
        }

        $dataArray = $worksheet->toArray(null, true, true, true);
        $dataArray = array_filter($dataArray, fn($row) => !empty(array_filter($row)));

        if (count($dataArray) <= 1) {
            return response()->json(['error' => 'No valid data rows after filtering'], 422);
        }

        $rows    = collect($dataArray);
        $headers = array_values($rows->first());

        $requiredColumns = [
            'mspFirstName', 'mspLastName',
            'farmerFirstName', 'farmerSurname', 'farmerPhone', 'farmerGender', 'ageBracket',
            'serviceCode', 'serviceName', 'serviceLabel', 'quantity',
            'state', 'lga', 'serviceDate',  'hubId' // ← added
        ];

        $missing = array_diff($requiredColumns, $headers);
        if ($missing) {
            return response()->json([
                'error' => 'Missing required columns: ' . implode(', ', $missing),
                'found' => $headers,
            ], 422);
        }

        $dataRows = $rows->slice(1);

        DB::beginTransaction();

        $processed = 0;

        $groupedRows = $dataRows->groupBy(function ($rowData) use ($headers) {
            $row = array_combine($headers, array_values($rowData));
            return trim($row['mspFirstName']) . '|' .
                   trim($row['mspLastName']) . '|' .
                   trim($row['farmerSurname']) . '|' .
                   trim($row['farmerFirstName']) . '|' .
                   trim($row['farmerPhone']) . '|' .
                   ($row['serviceDate'] ?? '');
        });

        foreach ($groupedRows as $groupKey => $group) {
            $firstRow  = array_combine($headers, array_values($group->first()));
            $rowNumber = $group->keys()->first() + 2; // more accurate row number

            // ── Resolve Hub from Excel state + lga ───────────────────────────────
            $stateName = trim($firstRow['state'] ?? '');
            $lgaName   = trim($firstRow['lga'] ?? '');
            $lgaId = trim($firstRow['hubId'] ?? '');

            // $hub = Hubs::whereHas('lga', function ($q) use ($lgaName) {
            //         $q->where('lga', $lgaName); // adjust column name if needed
            //     })
            //     ->whereHas('state', function ($q) use ($stateName) {
            //         $q->where('state', $stateName);
            //     })
            //     ->first();

                $hub = Hubs::where('hubId', $lgaId)->first();

            if (!$hub) {
                throw new \Exception("Hub not found for state '$stateName' / LGA '$lgaName' in row $rowNumber");
            }

            // ── Parse serviceDate (Excel serial number) ──────────────────────────
         $excelDate = $firstRow['serviceDate'] ?? null;

if (!$excelDate) {
    throw new \Exception("Missing serviceDate in row $rowNumber");
}

try {
    if (is_numeric($excelDate)) {
        $serviceDate = Carbon::instance(
            Date::excelToDateTimeObject($excelDate)
        );
    } else {
        $serviceDate = Carbon::parse($excelDate);
    }
} catch (\Throwable $e) {
    throw new \Exception("Invalid serviceDate format in row $rowNumber");
}

            // ── MSP handling (unchanged logic) ───────────────────────────────────
            $mspFirstName  = trim($firstRow['mspFirstName']);
            $mspLastName   = trim($firstRow['mspLastName']);
            $mspOtherNames = trim($firstRow['mspOtherNames'] ?? '');
            $mspGender     = trim($firstRow['mspGender'] ?? 'Unknown');

            $existingUser = User::where('firstName', $mspFirstName)
                ->where('lastName', $mspLastName)
                ->first();

            $mspId = null;

            if ($existingUser) {
                $msp = MSPs::where('userId', $existingUser->id)->first();
                if ($msp) $mspId = $msp->mspId;
            }

            if (!$mspId) {
                $user = User::create([
                    'firstName'   => $mspFirstName,
                    'lastName'    => $mspLastName,
                    'otherNames'  => $mspOtherNames,
                    'email'       => Str::uuid() . '@wimanigeria.com',
                    'password'    => Hash::make('password'),
                    'role'        => 2,
                ]);

                do {
                    $mspId = Str::upper(Str::random(10));
                } while (MSPs::where('mspId', $mspId)->exists());

                MSPs::create([
                    'mspId'     => $mspId,
                    'hub'       => $hub->hubId,
                    'gender'    => $mspGender,
                    'userId'    => $user->id,
                    'project'   => $projectId,
                    'addedBy'   => $addedBy,
                    // 'stateId'   => $hub->state_id ?? $stateId, // fallback if needed
                ]);
            }

            // ── Farmer handling (unchanged) ──────────────────────────────────────
            $rawPhone = trim($firstRow['farmerPhone']);
            $phoneNumber = ltrim($rawPhone, "'");
            $phoneNumber = preg_replace('/\D/', '', $phoneNumber);

            if (strlen($phoneNumber) < 10 || strlen($phoneNumber) > 11) {
                throw new \Exception("Invalid phone in row $rowNumber: $rawPhone");
            }

            $farmer = Farmers::where('farmerFirstName', trim($firstRow['farmerFirstName']))
                ->where('farmerLastName', trim($firstRow['farmerSurname']))
                ->where('phoneNumber', $phoneNumber)
                ->where('hub', $hub->hubId)
                ->first();

            if (!$farmer) {
                do {
                    $farmerId = Str::upper(Str::random(10));
                } while (Farmers::where('farmerId', $farmerId)->exists());

                $farmer = Farmers::create([
                    'farmerId'         => $farmerId,
                    'farmerFirstName'  => trim($firstRow['farmerFirstName']),
                    'farmerLastName'   => trim($firstRow['farmerSurname']),
                    'farmerOtherNames' => trim($firstRow['farmerOtherNames'] ?? ''),
                    'phoneNumber'      => $phoneNumber,
                    'gender'           => trim($firstRow['farmerGender']),
                    'mspId'            => $mspId,
                    'ageBracket'       => trim($firstRow['ageBracket']),
                    'hub'              => $hub->hubId,
                    'project'          => $projectId,
                ]);
            }

            // ── Transaction items with service lookup ────────────────────────────
            $totalCost = 0;
            $transactionItems = [];

            foreach ($group as $itemData) {
                $item = array_combine($headers, array_values($itemData));

                $serviceCode = (int) trim($item['serviceCode'] ?? 0);
                if ($serviceCode <= 0) {
                    throw new \Exception("Invalid serviceCode in row $rowNumber");
                }

                $service = Services::where('serviceCategoryId', $serviceCode)->first(); // ← adjust column if not 'code'

                if (!$service) {
                    throw new \Exception("Service not found for code $serviceCode in row $rowNumber");
                }

                $quantity  = (int) ($item['quantity'] ?? 1);
                $itemTotal = (float) ($item['totalCost'] ?? 0);
                $unitPrice = $quantity > 0 ? round($itemTotal / $quantity, 2) : 0;

                $totalCost += $itemTotal;

                $transactionItems[] = [
                    'serviceId'       => $service->serviceId, // or $service->id
                    'quantity'        => $quantity,
                    // 'unitCost'     => $unitPrice,   // uncomment if column exists
                    // 'total'        => $itemTotal,
                ];
            }

            // ── Create Transaction with correct date ─────────────────────────────
            $transactionData = [
                'farmer'              => $farmer->farmerId,
                'msp'                 => $mspId,
                'project'             => $projectId,
                'hub'                 => $hub->hubId,
                'transactionType'     => 'Service',
                'totalCost'           => $totalCost,
                'transactionStatus'   => 'PAID',
                'transactionReference'=> strtoupper(Str::random(2)) . mt_rand(1000000000, 9999999999),
                'paymentMethod'       => 'Cash',
                'addedBy'             => $addedBy,
                // 'created_at'          => $serviceDate,
                // 'updated_at'          => $serviceDate,
            ];

            // if ($serviceDate) {
            //     $transactionData['created_at'] = $serviceDate . ' 00:00:00';
            //     $transactionData['updated_at'] = $serviceDate . ' 00:00:00';
            // }

            $transaction = new Transactions($transactionData);
$transaction->timestamps = false;
$transaction->created_at = $serviceDate;
$transaction->updated_at = $serviceDate;
$transaction->save();

            // ── TransactionList entries ──────────────────────────────────────────
            foreach ($transactionItems as $item) {
               $transaction_list =  TransactionList::create([
                    // 'transaction_id'       => $transaction->id,               // preferred over reference
                    'transactionReference' => $transaction->transactionReference, // only if needed
                    'serviceId'            => $item['serviceId'],
                    'quantity'             => $item['quantity'],
                    // 'created_at'           => $transaction->created_at,
                    // 'updated_at'           => $transaction->updated_at,
                    
                ]);

                $transaction_list->timestamps = false;
$transaction_list->created_at = $serviceDate;
$transaction_list->updated_at = $serviceDate;
$transaction_list->save();
            }

            $processed += $group->count();
        }

        DB::commit();

        return response()->json([
            'message'        => 'Bulk upload completed successfully',
            'processed_rows' => $processed,
        ], 200);

    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json([
            'error'   => 'Upload failed',
            'message' => $e->getMessage(),
            'line'    => $e->getLine(),
            'row'     => $rowNumber ?? null,
        ], 422);
    }
}
}
