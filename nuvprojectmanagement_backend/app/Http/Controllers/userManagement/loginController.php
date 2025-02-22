<?php

namespace App\Http\Controllers\userManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\CustomHelpers\WebEncryption;

class loginController extends Controller
{
    public function loginPost(Request $request)
    {
        $request->validate([
            'user_email_address' => 'required|email',
            'login_password' => 'required'
        ]);
    
        $userCredentials = DB::table('usermaster')
        ->where('user_email_address', $request->user_email_address)
        ->first();
    
        if (!$userCredentials) {
            return response()->json([
                'Status' => 'ERROR',
                'Message' => 'User not found'
            ], 404);
        }

        $hashedPassword = WebEncryption::securePassword($request->login_password, $userCredentials->user_email_address);
    
        if ($userCredentials->login_password !== $hashedPassword) {
            return response()->json([
                'Status' => 'ERROR',
                'Message' => 'Incorrect password'
            ], 401);
        }
    
        return response()->json([
            'Status' => 'SUCCESS',
            'Message' => 'Login successful'
        ], 200);
    }

}
