<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CancerController;
use App\Http\Controllers\BeneficiariesController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\LgaController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\MinistryController;
use App\Http\Controllers\AgentsController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\ProductRequestController;
use App\Http\Controllers\TransactionsController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\HubsController;
use App\Http\Controllers\MSPsController;
use App\Http\Controllers\FarmersController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\CommodityController;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


// Route::middleware(['cors'])->group(function () {
    // Public routes
    Route::post('/signup', [AuthController::class, 'signup2']);
    Route::post('/signin', [AuthController::class, 'signin']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/users/profile', [AuthController::class, 'profile'])->middleware('auth.jwt'); // Use auth.jwt instead of auth:api

    Route::get('/transactions/analytics', [TransactionsController::class, 'analytics']);
    Route::get('/commodities', [CommodityController::class, 'allcommodities']);
    Route::get('/hubs/all-active-hubs', [HubsController::class, 'allActiveHubs']);
    Route::post('/hub-lgas', [LgaController::class, 'getLgasByState']);

Route::post('/membership-application', [MembershipController::class, 'store']);
Route::put('/membership-application/{id}/status', [MembershipController::class, 'updateStatus']);
    Route::get('/price-alert', [ServicesController::class, 'priceAlert']);
    // Protected routes with JWT authentication
    Route::get('/membership-application', [MembershipController::class, 'index']);
    Route::delete('/membership-application/{id}', [MembershipController::class, 'destroy']);
    Route::get('/roles', [RolesController::class, 'index']);

    
    Route::get('/validate-agent/{agentId}', [AgentsController::class, 'validateAgentId']);
    Route::get('/validate-farmer/{phoneNumber}', [FarmersController::class, 'validateFarmer']);
    
    Route::post('/load-services', [ServicesController::class, 'loadServices']);
    Route::get('/load-equipment', [EquipmentController::class, 'searchEquipment2']);

    Route::post('/book-service', [TransactionsController::class, 'bookService']);

    

    Route::middleware(['auth.jwt'])->group(function () {
        Route::get('/user', function () {
            $user = auth()->user();
            return response()->json([
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'email' => $user->email,
                'role' => $user->user_role->roleName,
                'id' => $user->id,
                'state' => $user->state_coordinator ? $user->state_coordinator->state->stateName : null,
                'community' => $user->community_lead ? $user->community_lead->lga_info->lgaName : null,
                'stateId' => $user->state_coordinator ? $user->state_coordinator->stateId : null,
                'communityId' => $user->community_lead ? $user->community_lead->lga : null,
                'message' => 'User authenticated successfully',
            ]);
        });

    
Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
Route::get('/transaction-analysis', [AnalyticsController::class, 'transactionAnalysis']);
Route::get('/farmers-by-state', [AnalyticsController::class, 'farmersByState']);
Route::get('/latest-registered-members', [AnalyticsController::class, 'latestRegisteredMembers']);
        // Application routes
    Route::get('/equipment', [EquipmentController::class, 'index']);
    Route::post('/equipment', [EquipmentController::class, 'store']);
    Route::get('/equipment/search', [EquipmentController::class, 'searchEquipment']);

    

    Route::get('/equipment/categories', [EquipmentController::class, 'equipmentCategories']);

    Route::get('/users', [UsersController::class, 'index']);
    Route::post('/users', [UsersController::class, 'createUser']);

    Route::get('/projects', [ProjectsController::class, 'index']);
    Route::post('/projects', [ProjectsController::class, 'store']);
    Route::put('/projects/{projectId}', [ProjectsController::class, 'update']);
    Route::delete('/projects/{projectId}', [ProjectsController::class, 'destroy']);

    Route::post('/roles', [RolesController::class, 'store']);
    Route::put('/roles/{roleId}', [RolesController::class, 'update']);
    Route::delete('/roles/{roleId}', [RolesController::class, 'destroy']);

    Route::get('/states', [StateController::class, 'index']);
    Route::get('/lgas', [LgaController::class, 'getLgasByState']);
    Route::get('/subhubs', [LgaController::class, 'getSubHubsByHubs']);
    

    Route::get('/hubs/active', [HubsController::class, 'activeHubs']);
    Route::get('/hubs', [HubsController::class, 'index']);
    Route::post('/hubs', [HubsController::class, 'store']);
    Route::put('/hubs/{activeLocationId}', [HubsController::class, 'update']);
    Route::delete('/hubs/{hubId}', [HubsController::class, 'destroy']);

    Route::get('/hubs/state/{stateId}', [HubsController::class, 'hubsInState']);

    Route::get('/farmers', [FarmersController::class, 'index']);
    Route::post('/farmers', [FarmersController::class, 'store']);
    Route::put('/farmers/{farmerId}', [FarmersController::class, 'update']);
    Route::delete('/farmers/{farmerId}', [FarmersController::class, 'destroy']);
    Route::get('/farmers/search-2', [FarmersController::class, 'farmerSearch']);
    

    Route::get('/msps', [MSPsController::class, 'index']);
    Route::post('/msps', [MSPsController::class, 'store']);

     Route::get('/services', [ServicesController::class, 'index']);
    Route::post('/services', [ServicesController::class, 'store']);
    Route::put('/services/{serviceId}', [ServicesController::class, 'update']);
    Route::delete('/services/{serviceId}', [ServicesController::class, 'destroy']);

     Route::get('/commodities', [CommodityController::class, 'index']);
    Route::post('/commodities', [CommodityController::class, 'store']);
    Route::put('/commodities/{commodityId}', [CommodityController::class, 'update']);
    Route::delete('/commodities/{commodityId}', [CommodityController::class, 'destroy']);

    Route::get('/transactions', [TransactionsController::class, 'index']);
    Route::get('/transactions/{transactionId}', [TransactionsController::class, 'show']);
    Route::post('/transactions', [TransactionsController::class, 'store']);
    Route::put('/transactions/{transactionId}/confirm', [TransactionsController::class, 'updatePaymentMethod']);
    Route::put('/transactions/{transactionId}/project-type', [TransactionsController::class, 'updateProjectType']);

    Route::get('/agents', [AgentsController::class, 'index']);
    Route::post('/agents', [AgentsController::class, 'store']);
    Route::put('/agents/{agentId}', [AgentsController::class, 'update']);
    Route::delete('/agents/{agentId}', [AgentsController::class, 'destroy']);

    Route::get('/farmers/search', [FarmersController::class, 'search']);
    // Route::post('/services', [FarmersController::class, 'store']);
    
    });
        Route::get('analytics/total-users', [AnalyticsController::class, 'getTotalBeneficiaries']);
        Route::options('{any}', function () {
            return response()->json([], 200);
        })->where('any', '.*');
       

        // routes/web.php
Route::get('/equipment_proofs/{filename}', function ($filename) {
    $path = storage_path('app/public/equipment_proofs/' . $filename);
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path);
});

