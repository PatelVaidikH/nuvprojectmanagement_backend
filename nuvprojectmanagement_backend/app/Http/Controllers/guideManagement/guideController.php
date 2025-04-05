<?php

namespace App\Http\Controllers\guideManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Helpers\CustomHelpers\WebEncryption;

class guideController extends Controller
{
    public function guideDashboard(Request $request)
    {
        try {
            $results = DB::select("
                SELECT 
                    pm.project_id,
                    pm.project_name AS title,
                    pgm.group_id,
                    ptm.project_type_name AS type,
                    pgm.group_id_actual AS id,
                    gm.guide_name AS guide,
                    pm.project_status AS status,
                    um.program AS course,
                    CASE 
                        WHEN ptm.project_type_id = 5 THEN um.user_name 
                        ELSE pm.project_name 
                    END AS title
                FROM projectmaster pm
                JOIN projectgroupmaster pgm ON pm.project_group_id = pgm.group_id
                LEFT JOIN projecttypemaster ptm ON pgm.project_type_id = ptm.project_type_id
                LEFT JOIN guidemaster gm ON pm.guide_number = gm.guide_id
                LEFT JOIN projectgroupmembertable pgmt ON pgm.group_id = pgmt.group_id AND pgmt.is_team_lead = 'Y'
                LEFT JOIN usermaster um ON pgmt.user_id = um.user_id
                WHERE gm.user_id = ?;", 
                [$request->input('userId')]
            );

            return response()->json([
                'Status' => 'SUCCESS',
                'Data' => $results
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'Status' => 'ERROR',
                'Message' => 'Error retrieving data: ' . $e->getMessage()
            ], 500);}
    }

    public function groupInfoEvaluation(Request $request)
    {
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
                        gm.guide_name AS guide,
                        pgmt.mid_sem_locked AS isMidSemLocked,
                        pgmt.end_sem_locked AS isEndSemLocked
                    FROM projectgroupmaster pgm
                    JOIN projectgroupmembertable pgmt ON pgm.group_id = pgmt.group_id
                    JOIN usermaster um ON pgmt.user_id = um.user_id
                    LEFT JOIN guidemaster gm ON pgm.guide_number = gm.guide_id  
                    LEFT JOIN projecttypemaster ptm ON pgm.project_type_id = ptm.project_type_id
                    LEFT JOIN projectmaster pm ON pm.project_group_id = pgm.group_id
                    WHERE pm.project_id = ?
                    GROUP BY pgm.group_id, pgm.group_id_actual, pm.project_id, ptm.project_type_name, 
                            um.program, pgm.group_name, pgm.project_status, gm.guide_name, 
                            um.user_id, um.user_name, um.user_email_address, 
                            pgmt.mid_sem_locked, pgmt.end_sem_locked;", 
                    [$request->input('project_id')]
                );


            // return response()->json([
            //     $results
            // ], 200);

            $groupedData = [];

            foreach ($results as $row) {
                if (!isset($groupedData[$row->group_id])) {
                    $groupedData[$row->group_id] = [
                        'id' => strval($row->group_id),
                        'title' => $row->title,
                        'type' => $row->type,
                        'course' => $row->course,
                        'isMidSemLocked' => boolval($row->isMidSemLocked),
                        'isEndSemLocked' => boolval($row->isEndSemLocked),
                    ];
                }

                $groupedData[$row->group_id]['students'][] = [
                    'id' => $row->user_id,
                    'name' => $row->student_name,
                    'enrollment' => $row->enrollment_id,
                ];
            }

            return response()->json([
                'Status' => 'SUCCESS',
                'Data' => reset($groupedData)
            ],200);

        } catch (\Exception $e) {
            return response()->json([
                'Status' => 'ERROR',
                'Message' => 'Error retrieving data: ' . $e->getMessage()
            ], 500);}

    }

    public function submitMidSemesterGrades(Request $request) 
    {
        \Log::info('Request found:', (array) $request->all()); // Debugging

        $userCredentials = DB::table('usermaster as um')
            ->leftJoin('guidemaster as gm', 'um.user_id', '=', 'gm.user_id')
            ->where('um.user_id', $request->user_id)
            ->select('um.user_email_address', 'gm.approval_password')
            ->first();

        if (!$userCredentials) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $hashedPassword = WebEncryption::securePassword($request->password, $userCredentials->user_email_address);


        if ($hashedPassword !== $userCredentials->approval_password) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            DB::beginTransaction();

            foreach ($request->grades as $grade) {

                DB::table('projectgroupmembertable')
                    ->where('group_id', intval($request->groupId))  // Common where condition
                    ->where('user_id', $grade['id'])
                    ->update([
                        'guide_marks_30' => $grade['guide'] ?? null,
                        'jury_marks_30' => $grade['external'] ?? null,
                        'sum_60' => $grade['total'] ?? null,
                        'evaluation_status' => $grade['status'] ?? 'Present',
                        'mid_sem_locked' => 1,
                        'marks_created_on' => now(),
                        'marks_created_by' => $request->user_id,
                        'marks_updated_on' => now(),
                        'marks_updated_by' => $request->user_id,
                    ]);
            }

            DB::commit();
            return response()->json(['message' => 'Mid-Semester Grades submitted successfully'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Database transaction failed:', ['error' => $e->getMessage()]); // Debugging
            return response()->json(['error' => 'Failed to submit grades', 'details' => $e->getMessage()], 500);
        }
    }



    // public function aprroveMidSemesterGrades(Request $request)
    // {
    //     // Check password (replace 'your_secret_password' with actual stored password logic)
    //     // $user_id = $request->session()->get('user_id'); 
    //     // return response()->json($user_id->user_id, 200);

    //     $userCredentials = DB::table('usermaster as um')
    //         ->leftJoin('guidemaster as gm', 'um.user_id', '=', 'gm.user_id')
    //         ->where('um.user_id', 4)
    //         ->select('um.user_email_address', 'gm.approval_password')
    //         ->first();
    //         return response()->json($userCredentials, 200);

    //     $hashedPassword = WebEncryption::securePassword($request->password, $userCredentials->user_email_address);

    //     if ($hashedPassword !== $userCredentials->approval_password) {
    //         return response()->json(['message' => 'Unauthorized'], 401);
    //     }
    //     try {
    //         DB::beginTransaction();

    //         foreach ($request->grades as $grade) {
    //             DB::table('projectgroupmembertable')
    //                 ->where('group_id', $request->groupId)  // Common where condition
    //                 ->where('user_id', $grade['id'])  // Per-student condition
    //                 ->updateOrInsert(
    //                     ['user_id' => $grade['id'], 'group_id' => $request->groupId], // Ensures it checks both conditions
    //                     [
    //                         'guide_marks_30' => $grade['guide'] ?? null,
    //                         'jury_marks_30' => $grade['external'] ?? null,
    //                         'sum_60' => $grade['total'] ?? null,
    //                         'evaluation_status' => $grade['status'] ?? 'Present',
    //                         'mid_sem_locked' => 1,
    //                         'marks_updated_on' => now(),
    //                         'marks_updated_by' => $user_id,
    //                     ]
    //                 );
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Grades submitted successfully'
    //         ], 200);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'error' => 'Failed to submit grades',
    //             'details' => $e->getMessage()
    //         ], 500);
    //     }


    //     return response()->json(['message' => 'Grades submitted successfully'], 200);
    // }

    public function viewMidSemMarks(Request $request)
    {
        $grades = DB::table('projectgroupmembertable')
                ->join('usermaster', 'usermaster.user_id', '=', 'projectgroupmembertable.user_id')
                ->where('group_id', $request->groupId)
                ->select(
                    'usermaster.user_id',
                    'usermaster.user_name',
                    'usermaster.enrollment_id',
                    'projectgroupmembertable.group_id',
                    'projectgroupmembertable.guide_marks_30',
                    'projectgroupmembertable.jury_marks_30',
                    'projectgroupmembertable.sum_60',
                    'projectgroupmembertable.evaluation_status'
                )
                ->get();
            
        return response()->json([
            'grades' => $grades,
            'groupId' => $request->groupId
        ]);
    }
}
