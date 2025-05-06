<?php

namespace App\Http\Controllers\projectManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
                um.enrollment_id AS enrollment_id,
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
                WHERE pm.project_id = ?
                GROUP BY pgm.group_id, pgm.group_id_actual, pm.project_id, ptm.project_type_name, 
                        um.program, pgm.group_name, pgm.project_status, gm.guide_name, 
                        um.user_id, um.user_name, um.user_email_address;", 
                [$request->input('project_id')]
            );

            // return response()->json([
            //     $results
            // ], 200);

            $groupedData = [];

            foreach ($results as $row) {
                if (!isset($groupedData[$row->group_id])) {
                    $groupedData[$row->group_id] = [
                        'group_id' => $row->group_id,
                        'ProjectType' => $row->type,
                        'GuideName' => $row->guide,
                        'FinalizedTopic' => $row->title,
                        'teamMembers' => []
                    ];
                }

                $groupedData[$row->group_id]['teamMembers'][] = [
                    'enrollment_id' => $row->enrollment_id,
                    'StudentName' => $row->student_name,
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
                ptm.project_type_id AS project_type_id, 
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
                        ptm.project_type_id, um.program, pgm.group_name, pgm.project_status, gm.guide_name, 
                        um.user_id, um.user_name, um.user_email_address;", 
                [$request->input('userId')]
            );

            $formattedData = array_map(function ($row) {
                // Get evaluation components status
                $components = $this->getEvaluationStatusForProject($row->group_id, $row->project_type_id);
                
                return [
                    "project_id" => $row->project_id,
                    "id" => $row->id, 
                    "type" => $row->type, 
                    "course" => $row->course, 
                    "title" => $row->title, 
                    "status" => $row->status, 
                    "guide" => $row->guide,
                    "components" => $components // Add components with evaluation status
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
            ], 500);
        }
    }

    // Helper method to check evaluation status for a project
    private function getEvaluationStatusForProject($groupId, $projectTypeId)
    {
        // Get the parent components for this project type
        $components = DB::table('maxmarkstable as parent')
            ->join('projecttype_maxmark_map as ptmm', 'ptmm.maxmark_id', '=', 'parent.srno')
            ->where('ptmm.project_type_id', $projectTypeId)
            ->whereNull('parent.parent_id')
            ->select('parent.srno', 'parent.max_mark_detail')
            ->get();

        // Get all students in the group
        $students = DB::table('projectgroupmembertable as pgm')
            ->where('pgm.group_id', $groupId)
            ->select('pgm.member_id')
            ->get();
            
        $result = [];
        
        foreach ($components as $component) {
            // Get subcomponents
            $subComponents = DB::table('maxmarkstable as child')
                ->join('projecttype_maxmark_map as ptmm', 'ptmm.maxmark_id', '=', 'child.srno')
                ->where('ptmm.project_type_id', $projectTypeId)
                ->where('child.parent_id', $component->srno)
                ->select('child.srno')
                ->get()
                ->pluck('srno');
                
            // Count how many evaluations have been done
            $evaluationsDone = DB::table('projectevaluationtable')
                ->where('project_id', $groupId)
                ->whereIn('evaluation_component_id', $subComponents)
                ->whereIn('member_id', $students->pluck('member_id'))
                ->count();
                
            // Total needed evaluations: students count × subcomponents count
            $totalNeeded = $students->count() * $subComponents->count();
            
            // Evaluation is done if all marks have been entered
            $evaluationDone = ($totalNeeded > 0 && $evaluationsDone == $totalNeeded);
            
            $result[] = [
                'title' => $component->max_mark_detail,
                'evaluation_done' => $evaluationDone
            ];
        }
        
        return $result;
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
                'psm.guide_approval_on',
                'guide.user_name as guide_approval_by',
                'psm.first_sign_on',
                'first_signer.user_name as first_sign_by',
                'psm.second_sign_on',
                'second_signer.user_name as second_sign_by'
            )
            ->orderBy('plm.created_on', 'desc')
            ->get(); // Fetch all records
    
        // Check if data exists
        if ($submissionData->isEmpty()) {
            return response()->json([]);
        }
    
        // Prepare response
        $logsList = [];
    
        foreach ($submissionData as $data) {
            $logsList[] = [
                'date' => date('d/m/Y', strtotime($data->log_created_on)),
                'log_id' => $data->project_log_id,
    
                'submitted' => [
                    'status' => !empty($data->log_created_on),
                    'date' => $data->log_created_on ? date('d/m/Y', strtotime($data->log_created_on)) : null,
                    'by' => $data->log_created_by ?? null,
                ],

                'guideApproval' => [
                'status' => !empty($data->guide_approval_on),
                'date' => $data->guide_approval_on ? date('d/m/Y', strtotime($data->guide_approval_on)) : null,
                'by' => $data->guide_approval_by ?? null,
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

        $documents = DB::table('projectdocumenttable')
        ->where('project_id', $request->input('project_id'))
        ->select('document_id', 'document_title', 'document_name_user', 'document_path', 'created_on')
        ->orderBy('created_on', 'desc')
        ->get();

        $documentsList = $documents->map(function ($doc) {
            return [
                'document_id' => $doc->document_id,
                'title' => $doc->document_title,
                'name' => $doc->document_name_user,
                'path' => $doc->document_path,
                'uploadedOn' => $doc->created_on ? date('d/m/Y', strtotime($doc->created_on)) : null,
            ];
        });
    
        return response()->json([
            'Status' => 'SUCCESS',
            'Data' => [
                'logsList' => $logsList,
                'documentsList' => $documentsList
            ]
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
        // $data = $request->json()->all();

        $logId = DB::table('projectlogsmaster')->insertGetId([
        'project_id' => $request->project_id,
        'previous_meeting_outcome' => $request->previous_meeting_outcome,
        'discussion_points' => $request->discussion_points,
        'expected_outcome' => $request->expected_outcome,
        'plan_for_next_meeting' => $request->plan_for_next_meeting,
        'log_submission_status' => 'submitted',
        'created_by' => $request->created_by,
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

    public function addNewFile(Request $request)
    {
        try {
            $file = $request->file('file');

            // Generate unique name for the file
            $originalName = $file->getClientOriginalName();
            $uniqueName = Str::uuid() . '_' . $originalName;

            // Store the file directly to the public path instead of using 'public' disk
            $publicPath = public_path('project_documents');
            
            // Make sure the directory exists
            if (!file_exists($publicPath)) {
                mkdir($publicPath, 0777, true);
            }
            
            // Move the uploaded file
            $file->move($publicPath, $uniqueName);
            $filePath = 'project_documents/' . $uniqueName;

            // Save metadata to DB
            DB::table('projectdocumenttable')->insert([
                'document_title' => $request->document_title,
                'document_name_system' => $uniqueName,
                'document_name_user' => $originalName,
                'document_path' => $filePath, // Path accessible from public directory
                'created_on' => Carbon::now(),
                'created_by' => $request->created_by,
                'project_id' => $request->project_id,
                'active_status' => 1,
            ]);

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'File uploaded successfully.',
                'path' => $filePath,
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('File upload error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'ERROR',
                'message' => 'File upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function viewDocument(Request $request)
    {
        try {
            // Fetch document from database
            $document = DB::table('projectdocumenttable')
                ->where('document_id', $request->input('document_id'))
                ->where('active_status', 1)
                ->first();
            
            if (!$document) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Document not found',
                ], 404);
            }
            
            // Check if file exists in filesystem
            $filePath = public_path($document->document_path);
            if (!file_exists($filePath)) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'File not found on server',
                ], 404);
            }
            
            // Get file extension
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            
            // Determine content type based on extension
            $contentType = $this->getContentType($extension);
            
            // Stream file for viewing
            return response()->file($filePath, ['Content-Type' => $contentType]);
            
        } catch (\Exception $e) {
            Log::error('Error viewing document: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Failed to view document: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function getContentType($extension)
    {
        $contentTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            'html' => 'text/html',
            'htm' => 'text/html',
        ];
        
        return $contentTypes[strtolower($extension)] ?? 'application/octet-stream';
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
                'psm.guide_approval_on',
                'guide.user_name as guide_approval_by',
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
                'approved' => [
                    'status' => !empty($logDetail->guide_approval_on),
                    'date' => $logDetail->guide_approval_on ? Carbon::parse($logDetail->guide_approval_on)->format('d/m/Y') : null,
                    'by' => $logDetail->guide_approval_by ?? null,
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
