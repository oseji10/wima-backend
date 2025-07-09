<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CancerController;
use App\Http\Controllers\BeneficiariesController;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\LgaController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\MinistryController;
use App\Http\Controllers\CadreController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\ProductRequestController;
use App\Http\Controllers\TransactionsController;
use App\Http\Controllers\JAMBController;
use App\Http\Controllers\ApplicationController;
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
    Route::post('/users/register', [AuthController::class, 'candidateRegister']);
    Route::post('/users/login', [AuthController::class, 'login']);
    Route::post('/users/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/users/profile', [AuthController::class, 'profile'])->middleware('auth.jwt'); // Use auth.jwt instead of auth:api

    Route::post('/verify-jamb', [JAMBController::class, 'verifyJAMB']);
    Route::get('/jamb', [JAMBController::class, 'index']);


    // Protected routes with JWT authentication
    Route::middleware(['auth.jwt'])->group(function () {
        Route::get('/user', function () {
            $user = auth()->user();
            return response()->json([
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'email' => $user->email,
                'role' => $user->role,
                'id' => $user->id,
                'message' => 'User authenticated successfully',
            ]);
        });

        // Application routes
    Route::post('/jamb/upload', [JAMBController::class, 'upload']);
    Route::delete('/jamb/{jambId}', [JAMBController::class, 'destroy']);
    Route::get('/jamb/search', [JambController::class, 'search']);
       
    Route::post('/apply', [ApplicationController::class, 'apply']);
    Route::get('/applications', [ApplicationController::class, 'index']);

    });
        Route::get('analytics/total-users', [AnalyticsController::class, 'getTotalBeneficiaries']);

    Route::options('{any}', function () {
    return response()->json([], 200);
})->where('any', '.*');
