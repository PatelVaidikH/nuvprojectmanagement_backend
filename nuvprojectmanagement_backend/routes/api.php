<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\userManagement\loginController;
use App\Http\Controllers\projectManagement\projectController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get("/login", [loginController::class,"login"]);
Route::post("/loginPost", [loginController::class,"loginPost"]);

Route::post('/landingPage', [projectController::class, 'landingPage']);