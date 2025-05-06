<?php

namespace App\Http\Controllers\userManagement;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
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
        // vaidik.h.patel@nuv.ac.in
        $email = 'ashish.jani@nuv.ac.in';
        $pass = 'ashish.jani@nuv.ac.in';
        // $email = 'aarya.mehta@nuv.ac.in';
        // $pass = 'aarya';
        // $email = 'yogeshc@nuv.ac.in';
        // $pass = 'qwerty';        
        $user_password = WebEncryption::securePassword($pass, $email);
        dd($user_password);

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
                'error' => 'Invalid Credentials'
            ], 404);
        }

        // If the user is a teacher, get their designation
        $userDesignation = null;
        if ($userCredentials->user_type === 'T') {
            $guideInfo = DB::table('guidemaster')
                ->where('user_id', $userCredentials->user_id)
                ->first();
            
            if ($guideInfo) {
                $userDesignation = $guideInfo->designation;
            }
        }

        $hashedPassword = WebEncryption::securePassword($request->login_password, $userCredentials->user_email_address);
    
        if ($userCredentials->login_password !== $hashedPassword) {
            return response()->json([
                'Status' => 'ERROR',
                'error' => 'Invalid Credentials'
            ], 401);
        }
        Session::put('user_id', $userCredentials->user_id);
        
    
        return response()->json([
            'Status' => 'SUCCESS',
            'Data' => 'Login successful',
            'userId' => $userCredentials->user_id,
            'userName' => $userCredentials->user_name,
            'user_email_address' => $userCredentials->user_email_address,
            'userType' => $userCredentials->user_type === 'S' ? 'Student' :
             ($userCredentials->user_type === 'T' ? 'Teacher' :
             ($userCredentials->user_type === 'A' ? 'Admin' : 'Not a valid user')),
             'userDesignation' => $userDesignation === 'PC' ? 'program_chair':
             ($userDesignation === 'DE' ? 'dean' :
             ($userDesignation === 'G' ? 'guide' : null)),
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        try {
            $user = DB::table('usermaster')
                ->where('user_id', $request->user_id)
                ->first();

            if (!$user) {
                return response()->json([
                    'Status' => 'ERROR',
                    'message' => 'User not found.',
                ], 404);
            }

            // Hash new password using your custom encryption method
            $hashedNewPassword = WebEncryption::securePassword($request->newPassword, $user->user_email_address);

            // Update the password
            DB::table('usermaster')
                ->where('user_id', $request->user_id)
                ->update([
                    'login_password' => $hashedNewPassword,
                    'updated_on' => now(),
                    'updated_by' => $request->user_id,
                ]);

            return response()->json([
                'Status' => 'SUCCESS',
                'message' => 'Password reset successfully.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'Status' => 'ERROR',
                'message' => 'Failed to reset password.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}
