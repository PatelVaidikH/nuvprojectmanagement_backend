<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Testing;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/report', [Testing::class, 'getAllEvaluationDetails'])->name('report');

