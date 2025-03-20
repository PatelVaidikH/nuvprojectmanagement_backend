<?php

namespace App\Http\Controllers\projectManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;

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
                pgm.group_id_actual AS id, 
                pm.project_id AS project_id, 
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
                LEFT JOIN projectmaster pm ON pm.project_group_id = pgm.group_id
                WHERE um.user_id = ?
                GROUP BY pgm.group_id, pgm.group_id_actual, pm.project_id, ptm.project_type_name, 
                        um.program, pgm.group_name, pgm.project_status, gm.guide_name, 
                        um.user_id, um.user_name, um.user_email_address;", 
                [$request->input('userId')]
            );

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
                pm.project_id AS project_id, 
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
                LEFT JOIN projectmaster pm ON pm.project_group_id = pgm.group_id
                WHERE um.user_id = ?
                GROUP BY pgm.group_id, pgm.group_id_actual, pm.project_id, ptm.project_type_name, 
                        um.program, pgm.group_name, pgm.project_status, gm.guide_name, 
                        um.user_id, um.user_name, um.user_email_address;", 
                [$request->input('userId')]
            );

        // return response()->json($results, 200);
        $formattedData = array_map(function ($row) {
            return [
                "project_id" => $row->project_id,
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
    
    public function logsList(Request $request)
    {
        // Fetch data using joins based on project_id
        $submissionData = DB::table('projectlogsmaster as plm')
            ->leftJoin('projectsubmissionmaster as psm', 'plm.project_log_id', '=', 'psm.project_log_id')
            ->leftJoin('usermaster as creator', 'plm.created_by', '=', 'creator.user_id')
            ->leftJoin('usermaster as guide', 'psm.guide_approval_by', '=', 'guide.user_id')
            ->leftJoin('usermaster as first_signer', 'psm.first_sign_by', '=', 'first_signer.user_id')
            ->leftJoin('usermaster as second_signer', 'psm.second_sign_by', '=', 'second_signer.user_id')
            ->leftJoin('usermaster as rejecter', 'psm.rejected_by', '=', 'rejecter.user_id')
            ->leftJoin('usermaster as canceller', 'psm.canceled_by', '=', 'canceller.user_id')
            ->where('plm.project_id', $request->input('project_id'))
            ->select(
                'plm.created_on as log_created_on',
                'plm.project_log_id',
                'creator.user_name as log_created_by',
                'psm.first_sign_on',
                'first_signer.user_name as first_sign_by',
                'psm.second_sign_on',
                'second_signer.user_name as second_sign_by'
            )
            ->get(); // Fetch all records
    
        // Check if data exists
        if ($submissionData->isEmpty()) {
            return response()->json(['Status' => 'ERROR', 'Message' => 'No data found'], 404);
        }
    
        // Prepare response
        $response = [];
    
        foreach ($submissionData as $data) {
            $response[] = [
                'date' => date('d/m/Y', strtotime($data->log_created_on)),
                'log_id' => $data->project_log_id,
    
                'submitted' => [
                    'status' => !empty($data->log_created_on),
                    'date' => $data->log_created_on ? date('d/m/Y', strtotime($data->log_created_on)) : null,
                    'by' => $data->log_created_by ?? null,
                ],
    
                'firstSign' => [
                    'status' => !empty($data->first_sign_on),
                    'date' => $data->first_sign_on ? date('d/m/Y', strtotime($data->first_sign_on)) : null,
                    'by' => $data->first_sign_by ?? null,
                ],
    
                'secondSign' => [
                    'status' => !empty($data->second_sign_on),
                    'date' => $data->second_sign_on ? date('d/m/Y', strtotime($data->second_sign_on)) : null,
                    'by' => $data->second_sign_by ?? null,
                ],
            ];
        }
    
        return response()->json([
            'Status' => 'SUCCESS',
            'Data' => $response
        ]);
    }
    
    public function projectDetails(Request $request) 
    {
        try {
            $results = DB::select("
                SELECT 
                    pgm.group_id AS group_id, 
                    pgm.group_id_actual AS id, 
                    pm.project_id AS project_id, 
                    ptm.project_type_name AS type, 
                    pgm.group_name AS title, 
                    pgm.project_status AS status, 
                    gm.guide_name AS guide,
                    um.program AS course
                FROM projectgroupmaster pgm
                LEFT JOIN guidemaster gm ON pgm.guide_number = gm.guide_id  
                LEFT JOIN projecttypemaster ptm ON pgm.project_type_id = ptm.project_type_id
                LEFT JOIN projectmaster pm ON pm.project_group_id = pgm.group_id
                LEFT JOIN projectgroupmembertable pgmt ON pgm.group_id = pgmt.group_id
                LEFT JOIN usermaster um ON pgmt.user_id = um.user_id
                WHERE pm.project_id = ?
                GROUP BY pgm.group_id, pgm.group_id_actual, pm.project_id, ptm.project_type_name, 
                        um.program, pgm.group_name, pgm.project_status, gm.guide_name
                LIMIT 1;",
                [$request->input('project_id')]
            );
        // return response()->json($results, 200);
        $formattedData = array_map(function ($row) {
            return [
                "project_id" => $row->project_id,
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

    public function addNewLog(Request $request)
    {
        \Log::info('Received request:', $request->all());
        $data = $request->json()->all();

        $logId = DB::table('projectlogsmaster')->insertGetId([
            'project_id' => $data['project_id'],
        'previous_meeting_outcome' => $data['previous_meeting_outcome'] ?? null,
        'discussion_points' => $data['discussion_points'] ?? null,
        'expected_outcome' => $data['expected_outcome'] ?? null,
        'plan_for_next_meeting' => $data['plan_for_next_meeting'] ?? null,
        'log_submission_status' => 'submitted',
        'created_by' => $data['created_by'] ?? null,
        'created_on' => Carbon::now(),
        ]);

        DB::table('projectsubmissionmaster')->insert([
            'project_id' => $request->project_id,
            'project_log_id' => $logId,
        ]);

        $submissionData = DB::table('projectlogsmaster as plm')
            ->leftJoin('projectsubmissionmaster as psm', 'plm.project_log_id', '=', 'psm.project_log_id')
            ->leftJoin('usermaster as creator', 'plm.created_by', '=', 'creator.user_id')
            ->leftJoin('usermaster as guide', 'psm.guide_approval_by', '=', 'guide.user_id')
            ->leftJoin('usermaster as first_signer', 'psm.first_sign_by', '=', 'first_signer.user_id')
            ->leftJoin('usermaster as second_signer', 'psm.second_sign_by', '=', 'second_signer.user_id')
            ->leftJoin('usermaster as rejecter', 'psm.rejected_by', '=', 'rejecter.user_id')
            ->leftJoin('usermaster as canceller', 'psm.canceled_by', '=', 'canceller.user_id')
            ->where('plm.project_log_id', $logId)
            ->select(
                'plm.created_on as log_created_on',
                'creator.user_name as log_created_by',
                'psm.first_sign_on',
                'first_signer.user_name as first_sign_by',
                'psm.second_sign_on',
                'second_signer.user_name as second_sign_by'
            )
            ->get(); // Fetch all records
    
        // Check if data exists
        if ($submissionData->isEmpty()) {
            return response()->json(['Status' => 'ERROR', 'Message' => 'No data found'], 404);
        }
    
        // Prepare response
        $response = [];
    
        foreach ($submissionData as $data) {
            $response[] = [
                'date' => date('d/m/Y', strtotime($data->log_created_on)),
                'log_id' => $logId,
    
                'submitted' => [
                    'status' => !empty($data->log_created_on),
                    'date' => $data->log_created_on ? date('d/m/Y', strtotime($data->log_created_on)) : null,
                    'by' => $data->log_created_by ?? null,
                ],
    
                'firstSign' => [
                    'status' => !empty($data->first_sign_on),
                    'date' => $data->first_sign_on ? date('d/m/Y', strtotime($data->first_sign_on)) : null,
                    'by' => $data->first_sign_by ?? null,
                ],
    
                'secondSign' => [
                    'status' => !empty($data->second_sign_on),
                    'date' => $data->second_sign_on ? date('d/m/Y', strtotime($data->second_sign_on)) : null,
                    'by' => $data->second_sign_by ?? null,
                ],
            ];
        }
    
        return response()->json([
            'Status' => 'SUCCESS',
            'Data' => $response
        ]);
    }
    
    public function viewLogDetail(Request $request)
    {
        $logDetail = DB::table('projectlogsmaster as plm')
            ->leftJoin('projectsubmissionmaster as psm', 'plm.project_log_id', '=', 'psm.project_log_id')
            ->leftJoin('usermaster as creator', 'plm.created_by', '=', 'creator.user_id')
            ->leftJoin('usermaster as guide', 'psm.guide_approval_by', '=', 'guide.user_id')
            ->leftJoin('usermaster as first_signer', 'psm.first_sign_by', '=', 'first_signer.user_id')
            ->leftJoin('usermaster as second_signer', 'psm.second_sign_by', '=', 'second_signer.user_id')
            ->where('plm.project_log_id', $request->input('log_id'))
            ->select(
                'plm.project_log_id',
                'plm.created_on as date',
                'plm.previous_meeting_outcome',
                'plm.discussion_points',
                'plm.expected_outcome',
                'plm.plan_for_next_meeting',
                'plm.log_submission_status',
                'creator.user_name as submitted_by',
                'psm.first_sign_on',
                'first_signer.user_name as first_sign_by',
                'psm.second_sign_on',
                'second_signer.user_name as second_sign_by'
            )
            ->first();
    
        if (!$logDetail) {
            return response()->json(['Status' => 'ERROR', 'Message' => 'Log not found'], 404);
        }
    
        return response()->json([
            'Status' => 'SUCCESS',
            'Data' => [
                'date' => Carbon::parse($logDetail->date)->format('d/m/Y'),
                'outcomeOfPreviousMeeting' => $logDetail->previous_meeting_outcome,
                'discussionActivityPoints' => $logDetail->discussion_points,
                'expectedOutcome' => $logDetail->expected_outcome,
                'planForNextMeeting' => $logDetail->plan_for_next_meeting,
                'submitted' => [
                    'status' => $logDetail->log_submission_status === 'submitted',
                    'date' => $logDetail->date ? Carbon::parse($logDetail->date)->format('d/m/Y') : null,
                    'by' => $logDetail->submitted_by ?? null,
                ],
                'firstSign' => [
                    'status' => !empty($logDetail->first_sign_on),
                    'date' => $logDetail->first_sign_on ? Carbon::parse($logDetail->first_sign_on)->format('d/m/Y') : null,
                    'by' => $logDetail->first_sign_by ?? null,
                ],
                'secondSign' => [
                    'status' => !empty($logDetail->second_sign_on),
                    'date' => $logDetail->second_sign_on ? Carbon::parse($logDetail->second_sign_on)->format('d/m/Y') : null,
                    'by' => $logDetail->second_sign_by ?? null,
                ],
            ],
        ]);
    }
}
