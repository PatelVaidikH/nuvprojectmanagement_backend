<?php

namespace App\Http\Controllers\guideManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class guideController extends Controller
{
    public function guideDashboard(Request $request)
    {
        return response()->json(
            $request->all()
        );
    }
}
