<?php

namespace App\Http\Controllers\projectManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class projectController extends Controller
{
    public function landingPageTest(Request $request)
    {
        // $user_id = $request->session()->get('user_id');
        try {
            $results = DB::select("
                SELECT 
                um.user_id AS user_id,
                um.user_name AS student_name,
                um.user_email_address AS student_email,
                pgm.group_id AS group_id, 
                ptm.project_type_name AS project_type, 
                um.program AS course, 
                pgm.group_name AS title, 
                pgm.project_status AS project_status, 
                gm.guide_name AS guide
                FROM projectgroupmaster pgm
                JOIN projectgroupmembertable pgmt ON pgm.group_id = pgmt.group_id
                JOIN usermaster um ON pgmt.user_id = um.user_id
                LEFT JOIN guidemaster gm ON pgm.guide_number = gm.guide_id  
                LEFT JOIN projecttypemaster ptm ON pgm.project_type_id = ptm.project_type_id
                WHERE um.user_id = ?
                GROUP BY pgm.group_id, ptm.project_type_name, um.program, pgm.group_name, pgm.project_status, gm.guide_name, um.user_id, um.user_name;
            ", [$request['userId']]);

            // return response()->json([
            //     $results
            // ], 200);

            $groupedData = [];

            foreach ($results as $row) {
                if (!isset($groupedData[$row->group_id])) {
                    $groupedData[$row->group_id] = [
                        'group_id' => $row->group_id,
                        'ProjectType' => $row->project_type,
                        'GuideName' => $row->guide,
                        'FinalizedTopic' => $row->title,
                        'teamMembers' => []
                    ];
                }

                $groupedData[$row->group_id]['teamMembers'][] = [
                    'enrollment_id' => $row->enrollment_id,
                    'StudentName' => $row->student_name,
                    'project_lead' => $row->project_lead,
                    'EmailID' => $row->student_email,
                ];
            }

            return response()->json([
                'Status' => 'SUCCESS',
                'Data' => array_values($groupedData) // Reset array keys
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'Status' => 'ERROR',
                'Message' => 'Error retrieving data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function landingPage(Request $request) 
    {
        try {
            $results = DB::select("
                SELECT 
                um.user_id AS user_id,
                um.user_name AS student_name,
                um.user_email_address AS student_email,
                pgm.group_id AS group_id, 
                pgm.group_id_actual AS id, 
                ptm.project_type_name AS type, 
                um.program AS course, 
                pgm.group_name AS title, 
                pgm.project_status AS status, 
                gm.guide_name AS guide
                FROM projectgroupmaster pgm
                JOIN projectgroupmembertable pgmt ON pgm.group_id = pgmt.group_id
                JOIN usermaster um ON pgmt.user_id = um.user_id
                LEFT JOIN guidemaster gm ON pgm.guide_number = gm.guide_id  
                LEFT JOIN projecttypemaster ptm ON pgm.project_type_id = ptm.project_type_id
                WHERE um.user_id = ?
                GROUP BY pgm.group_id, ptm.project_type_name, um.program, pgm.group_name, pgm.project_status, gm.guide_name, um.user_id, um.user_name;", [$request['userId']]);

        // return response()->json($results, 200);
        $formattedData = array_map(function ($row) {
            return [
                "id" => $row->id, 
                "type" => $row->type, 
                "course" => $row->course, 
                "title" => $row->title, 
                "status" => $row->status, 
                "guide" => $row->guide
            ];
        }, $results);

        // Return formatted JSON response
        return response()->json([
            'Status' => 'SUCCESS',
            'Data' => $formattedData
        ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'Status' => 'ERROR',
                'Message' => 'Error retrieving data: ' . $e->getMessage()
            ], 500);}
    }
}
