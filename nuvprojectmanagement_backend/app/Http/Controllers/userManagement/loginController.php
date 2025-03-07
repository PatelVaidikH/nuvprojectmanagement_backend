<?php

namespace App\Http\Controllers\userManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\CustomHelpers\WebEncryption;
use Illuminate\Support\Facades\Session;

class loginController extends Controller
{
    public function login()
    {
        // $email = 'harshil.h.brahmani@nuv.ac.in';
        // $pass = 'qwedcv';
        // $email = 'aarya.mehta@nuv.ac.in';
        // $pass = 'aarya';
        // $email = 'yogeshc@nuv.ac.in';
        // $pass = 'qwerty';        
        // $user_password = WebEncryption::securePassword($pass, $email);
        // dd($user_password);

        // $users = DB::table('usermaster')->get(); // Fetch all users

        // foreach ($users as $user) {
        //     $email = $user->user_email_address; 
        //     $pass = $email; // Using email as password before hashing

        //     // Generate secure password
        //     $securePassword = WebEncryption::securePassword($pass, $email);

        //     // Update user record with secure password
        //     DB::table('usermaster')
        //         ->where('user_id', $user->user_id)
        //         ->update(['login_password' => $securePassword]);
        // }

        // dd('Password updated successfully');
    }
    public function loginPost(Request $request)
    {

        // return response()->json([
        //     'Data' => $request->all()
        // ], 200);
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
                'error' => 'User not found'
            ], 404);
        }

        $hashedPassword = WebEncryption::securePassword($request->login_password, $userCredentials->user_email_address);
    
        if ($userCredentials->login_password !== $hashedPassword) {
            return response()->json([
                'Status' => 'ERROR',
                'error' => 'Incorrect password'
            ], 401);
        }
        Session::put('user_id', $userCredentials->user_id);
    
        return response()->json([
            'Status' => 'SUCCESS',
            'Data' => 'Login successful',
            'userId' => $userCredentials->user_id,
            'Type' => $userCredentials->user_type=== 'S' ? 'Student' : 'Teacher',
        ], 200);
    }

}
