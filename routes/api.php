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
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\AssetManagementController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\GoTractApplicationController;



use App\Http\Controllers\SecurityController;
use App\Http\Controllers\SafeguardingController;


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
    Route::post('/load-services2', [ServicesController::class, 'loadServices2']);
    Route::get('/load-equipment', [EquipmentController::class, 'searchEquipment1']);
    Route::get('/load-equipment2', [EquipmentController::class, 'searchEquipment2']);

    Route::post('/book-service', [TransactionsController::class, 'bookServiceAndZoho']);
    Route::get('/check-equipment-availability/{equipmentId}/{bookingDate}', [TransactionsController::class, 'checkEquipmentAvailability']);


    Route::post('/farmers/register', [FarmersController::class, 'register']);
    

    Route::post('/msps/register', [MSPsController::class, 'register']);
    
    Route::get('/validate-msp/{phoneNumber}', [MSPsController::class, 'validateMSP']);
    
    Route::post('/verify-cl-subcl', [MSPsController::class, 'verifyCLSubCL']);

    Route::post('gotract/applications', [GoTractApplicationController::class, 'store']);
    Route::get('gotract/oversight', [GoTractApplicationController::class, 'oversight']);

    

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

     Route::get('gotract/applications', [GoTractApplicationController::class, 'index']);
    Route::patch('gotract/applications/bulk-status', [GoTractApplicationController::class, 'bulkStatus']);
    Route::get('gotract/applications/{application}', [GoTractApplicationController::class, 'show']);
    Route::patch('gotract/applications/{application}/status', [GoTractApplicationController::class, 'updateStatus']);
    Route::get('gotract/stats', [GoTractApplicationController::class, 'stats']);

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
        Route::put('/users/{id}', [UsersController::class, 'update']);
        Route::delete('/users/{id}', [UsersController::class, 'destroy']);

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
        Route::post('/transactions/upload-bulk', [TransactionsController::class, 'uploadBulk']);

        Route::get('/agents', [AgentsController::class, 'index']);
        Route::post('/agents', [AgentsController::class, 'store']);
        Route::put('/agents/{agentId}', [AgentsController::class, 'update']);
        Route::delete('/agents/{agentId}', [AgentsController::class, 'destroy']);

        Route::get('/farmers/search', [FarmersController::class, 'search']);
        // Route::post('/services', [FarmersController::class, 'store']);




// ---- Fleet level ----
Route::get('asset-management/dashboard', [AssetManagementController::class, 'fleetDashboard']);
Route::get('asset-management/alerts',    [AssetManagementController::class, 'serviceAlerts']);
Route::get('asset-management/incidents', [AssetManagementController::class, 'incidentIndex']);

// ---- Per asset overview ----
Route::get('equipment/{equipmentId}/overview', [AssetManagementController::class, 'assetOverview']);

// ---- Lifecycle ----
Route::get('equipment/{equipmentId}/lifecycle',  [AssetManagementController::class, 'lifecycleIndex']);
Route::post('equipment/{equipmentId}/lifecycle', [AssetManagementController::class, 'lifecycleStore']);

// ---- Movements / deployment logs ----
Route::get('equipment/{equipmentId}/movements',  [AssetManagementController::class, 'movementIndex']);
Route::post('equipment/{equipmentId}/movements', [AssetManagementController::class, 'movementStore']);
Route::put('movements/{id}/receive',             [AssetManagementController::class, 'movementReceive']);

// ---- Utilization & uptime ----
Route::get('equipment/{equipmentId}/utilization',  [AssetManagementController::class, 'utilizationIndex']);
Route::post('equipment/{equipmentId}/utilization', [AssetManagementController::class, 'utilizationStore']);

// ---- Maintenance schedules ----
Route::get('equipment/{equipmentId}/schedules',  [AssetManagementController::class, 'scheduleIndex']);
Route::post('equipment/{equipmentId}/schedules', [AssetManagementController::class, 'scheduleStore']);
Route::put('schedules/{id}',                      [AssetManagementController::class, 'scheduleUpdate']);
Route::delete('schedules/{id}',                   [AssetManagementController::class, 'scheduleDestroy']);
Route::post('schedules/{id}/serviced',            [AssetManagementController::class, 'scheduleMarkServiced']);

// ---- Incidents / breakdowns ----
Route::post('equipment/{equipmentId}/incidents', [AssetManagementController::class, 'incidentStore']);
Route::put('incidents/{id}',                      [AssetManagementController::class, 'incidentUpdate']);

// ---- Compliance logs ----
Route::get('equipment/{equipmentId}/compliance',  [AssetManagementController::class, 'complianceIndex']);
Route::post('equipment/{equipmentId}/compliance', [AssetManagementController::class, 'complianceStore']);
Route::put('compliance/{id}',                      [AssetManagementController::class, 'complianceUpdate']);





// ---- Treasury dashboard ----
Route::get('finance/dashboard', [FinanceController::class, 'dashboard']);

// ---- Sharing scheme (the variables) ----
Route::get('finance/scheme',          [FinanceController::class, 'schemeShow']);
Route::put('finance/scheme',          [FinanceController::class, 'schemeUpdate']);
Route::get('finance/scheme/preview',  [FinanceController::class, 'schemePreview']);

// ---- Service catalogue ----
Route::get('finance/services',        [FinanceController::class, 'serviceIndex']);
Route::post('finance/services',       [FinanceController::class, 'serviceStore']);
Route::put('finance/services/{id}',   [FinanceController::class, 'serviceUpdate']);
Route::delete('finance/services/{id}',[FinanceController::class, 'serviceDestroy']);

// ---- Revenue tracking & sharing ----
Route::get('finance/revenue',                 [FinanceController::class, 'revenueIndex']);
Route::post('finance/revenue',                [FinanceController::class, 'revenueStore']);
Route::put('finance/revenue/{id}',            [FinanceController::class, 'revenueUpdate']);
Route::delete('finance/revenue/{id}',         [FinanceController::class, 'revenueDestroy']);
Route::get('finance/hubs/{hubId}/projection', [FinanceController::class, 'revenueProjection']);

// ---- Invoicing & receivables ----
Route::get('finance/invoices',                  [FinanceController::class, 'invoiceIndex']);
Route::post('finance/invoices',                 [FinanceController::class, 'invoiceStore']);
Route::get('finance/invoices/{id}',             [FinanceController::class, 'invoiceShow']);
Route::put('finance/invoices/{id}',             [FinanceController::class, 'invoiceUpdate']);
Route::post('finance/invoices/{id}/payments',   [FinanceController::class, 'invoiceRecordPayment']);
Route::put('finance/invoices/{id}/void',        [FinanceController::class, 'invoiceVoid']);

// ---- Donor / grant / investment tracking ----
Route::get('finance/funding',                       [FinanceController::class, 'fundingIndex']);
Route::post('finance/funding',                      [FinanceController::class, 'fundingStore']);
Route::get('finance/funding/{id}',                  [FinanceController::class, 'fundingShow']);
Route::put('finance/funding/{id}',                  [FinanceController::class, 'fundingUpdate']);
Route::post('finance/funding/{id}/transactions',    [FinanceController::class, 'fundingTransactionStore']);


// ---- Dashboards ----
Route::get('me/dashboard',        [MonitoringController::class, 'executiveDashboard']);
Route::get('me/donor-dashboard',  [MonitoringController::class, 'donorDashboard']);

// ---- KPI configuration ----
Route::get('me/indicators',            [MonitoringController::class, 'indicatorIndex']);
Route::post('me/indicators',           [MonitoringController::class, 'indicatorStore']);
Route::put('me/indicators/{id}',       [MonitoringController::class, 'indicatorUpdate']);
Route::delete('me/indicators/{id}',    [MonitoringController::class, 'indicatorDestroy']);
Route::get('me/indicators/{id}/trend', [MonitoringController::class, 'indicatorTrend']);
Route::post('me/indicators/{id}/values', [MonitoringController::class, 'indicatorValueStore']);

// ---- Field data collection forms ----
Route::get('me/forms',          [MonitoringController::class, 'formIndex']);
Route::post('me/forms',         [MonitoringController::class, 'formStore']);
Route::get('me/forms/{id}',     [MonitoringController::class, 'formShow']);
Route::put('me/forms/{id}',     [MonitoringController::class, 'formUpdate']);

// ---- Submissions ----
Route::get('me/submissions',       [MonitoringController::class, 'submissionIndex']);
Route::post('me/submissions',      [MonitoringController::class, 'submissionStore']);
Route::get('me/submissions/{id}',  [MonitoringController::class, 'submissionShow']);



// ---- Dashboard ----
Route::get('hr/dashboard', [HrController::class, 'dashboard']);

// ---- Roles (read-only; sourced from the existing `roles` table) ----
Route::get('hr/roles', [HrController::class, 'roleIndex']);

// ---- Staff (= users + employment profile). No user creation/deletion here. ----
Route::get('hr/staff',      [HrController::class, 'staffIndex']);
Route::get('hr/staff/{id}', [HrController::class, 'staffShow']);
Route::put('hr/staff/{id}', [HrController::class, 'staffUpsert']); // upserts employment profile + safe user fields
Route::get('hr/staff/{id}/leave-balance', [HrController::class, 'leaveBalance']);

// ---- Performance (linked to M&E KPIs) ----
Route::get('hr/reviews',         [HrController::class, 'reviewIndex']);
Route::post('hr/reviews',        [HrController::class, 'reviewStore']);
Route::get('hr/reviews/{id}',    [HrController::class, 'reviewShow']);
Route::put('hr/reviews/{id}',    [HrController::class, 'reviewUpdate']);
Route::delete('hr/reviews/{id}', [HrController::class, 'reviewDestroy']);

// ---- Leave ----
Route::get('hr/leave-types',      [HrController::class, 'leaveTypeIndex']);
Route::post('hr/leave-types',     [HrController::class, 'leaveTypeStore']);
Route::put('hr/leave-types/{id}', [HrController::class, 'leaveTypeUpdate']);
Route::get('hr/leave',            [HrController::class, 'leaveIndex']);
Route::post('hr/leave',           [HrController::class, 'leaveStore']);
Route::put('hr/leave/{id}/decide', [HrController::class, 'leaveDecide']);

// ---- Compliance ----
Route::get('hr/compliance',         [HrController::class, 'complianceIndex']);
Route::post('hr/compliance',        [HrController::class, 'complianceStore']);
Route::put('hr/compliance/{id}',    [HrController::class, 'complianceUpdate']);
Route::delete('hr/compliance/{id}', [HrController::class, 'complianceDestroy']);




// ---- Dashboard, locations & risk mapping ----
Route::get('security/dashboard',  [SecurityController::class, 'dashboard']);
Route::get('security/locations',  [SecurityController::class, 'locations']);
Route::get('security/risk-map',   [SecurityController::class, 'riskMap']);

// ---- Incidents ----
Route::get('security/incidents',                 [SecurityController::class, 'incidentIndex']);
Route::post('security/incidents',                [SecurityController::class, 'incidentStore']);
Route::get('security/incidents/{id}',            [SecurityController::class, 'incidentShow']);
Route::put('security/incidents/{id}',            [SecurityController::class, 'incidentUpdate']);
Route::post('security/incidents/{id}/actions',   [SecurityController::class, 'incidentActionStore']);
Route::delete('security/incidents/{id}',         [SecurityController::class, 'incidentDestroy']);

// ---- Security vendor register ----
Route::get('security/vendors',         [SecurityController::class, 'vendorIndex']);
Route::post('security/vendors',        [SecurityController::class, 'vendorStore']);
Route::put('security/vendors/{id}',    [SecurityController::class, 'vendorUpdate']);
Route::delete('security/vendors/{id}', [SecurityController::class, 'vendorDestroy']);

// =========================================================================
//  Safeguarding (gender-based incidents) — CONFIDENTIAL, officer-gated.
//  Access control and audit logging are enforced inside the controller.
// =========================================================================
Route::get('safeguarding/access',          [SafeguardingController::class, 'access']);

// Officer roster (admin only)
Route::get('safeguarding/officers',           [SafeguardingController::class, 'officerIndex']);
Route::post('safeguarding/officers',          [SafeguardingController::class, 'officerStore']);
Route::delete('safeguarding/officers/{user}', [SafeguardingController::class, 'officerDestroy']);

// Cases (read/manage = officers only; create = any authenticated user / intake)
Route::get('safeguarding/cases',              [SafeguardingController::class, 'caseIndex']);
Route::post('safeguarding/cases',             [SafeguardingController::class, 'caseStore']);
Route::get('safeguarding/cases/{id}',         [SafeguardingController::class, 'caseShow']);
Route::put('safeguarding/cases/{id}',         [SafeguardingController::class, 'caseUpdate']);
Route::post('safeguarding/cases/{id}/actions', [SafeguardingController::class, 'caseActionStore']);
Route::get('safeguarding/cases/{id}/audit',   [SafeguardingController::class, 'auditIndex']);


        });
        Route::get('/videos', [VideoController::class, 'index']);
        Route::get('/videos/categories', [VideoController::class, 'categories']);
        Route::get('/videos/{id}', [VideoController::class, 'show']);

        Route::get('/photos', [PhotoController::class, 'index']);
        Route::get('/photos/categories', [PhotoController::class, 'categories']);
        Route::get('/photos/{id}', [PhotoController::class, 'show']);

        Route::post('/photos', [PhotoController::class, 'store']);
        Route::post('/videos', [VideoController::class, 'store']);

    Route::post('/zohotest', [TransactionsController::class, 'zohotest']);

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

