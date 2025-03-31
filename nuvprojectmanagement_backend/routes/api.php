<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\userManagement\loginController;
use App\Http\Controllers\projectManagement\projectController;
use App\Http\Controllers\guideManagement\guideController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get("/login", [loginController::class,"login"]);
Route::post("/loginPost", [loginController::class,"loginPost"]);

Route::post('/landingPage', [projectController::class, 'landingPage']);
// Route::get('/logsList', [projectController::class, 'logsList']);
Route::post('/logsList', [projectController::class, 'logsList']);
Route::post('/projectDetails', [projectController::class, 'projectDetails']);
Route::post('/addNewLog', [projectController::class, 'addNewLog']);
Route::post('/viewLogDetail', [projectController::class, 'viewLogDetail']);


Route::post('/guideDashboard', [guideController::class, 'guideDashboard']);
Route::post('/groupInfoEvaluation', [guideController::class, 'groupInfoEvaluation']);
Route::post('/submitMidSemesterGrades', [guideController::class, 'submitMidSemesterGrades']);
Route::post('/aprroveMidSemesterGrades', [guideController::class, 'aprroveMidSemesterGrades']);