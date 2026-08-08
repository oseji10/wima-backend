<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::get('/equipment_proofs/{filename}', function ($filename) {
    $path = storage_path('app/public/equipment_proofs/' . $filename);
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path);
});

Route::get('/student_proofs/{filename}', function ($filename) {
    $path = storage_path('app/public/student_proofs/' . $filename);
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path);
});


Route::get('/company_details/{filename}', function ($filename) {
    $path = storage_path('app/public/company_details/' . $filename);
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path);
});


Route::get('/skills_assessment/{filename}', function ($filename) {
    $path = storage_path('app/public/skills_assessment/' . $filename);
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path);
});

Route::get('/means_of_identification/{filename}', function ($filename) {
    $path = storage_path('app/public/means_of_identification/' . $filename);
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path);
});

Route::get('/identifications/{filename}', function ($filename) {
    $path = storage_path('app/public/identifications/' . $filename);
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path);
});

Route::get('/cac_documents/{filename}', function ($filename) {
    $path = storage_path('app/public/cac_documents/' . $filename);
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path);
});

Route::get('/gotract/{filename}', function ($filename) {
    $path = storage_path('app/public/gotract/' . $filename);
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path);
});

