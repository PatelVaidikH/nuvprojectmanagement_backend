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

    // public function fetchProjectsByUserId(Request $request)
    // {
    //     $result = [
    //         'guide' => false,
    //         'external' => false,
    //     ];

    //     // 1. Check if user is a guide
    //     $guide = DB::table('guidemaster')->where('user_id', $request->user_id)->first();

    //     if ($guide) {
    //         $result['guide'] = true;

    //         $guideProjects = DB::table('projectmaster as pm')
    //             ->join('projectgroupmaster as pgm', 'pm.project_group_id', '=', 'pgm.group_id')
    //             ->leftJoin('projecttypemaster as ptm', 'pgm.project_type_id', '=', 'ptm.project_type_id')
    //             ->leftJoin('guidemaster as gm', 'pm.guide_number', '=', 'gm.guide_id')
    //             ->leftJoin('projectgroupmembertable as pgmt', function ($join) {
    //                 $join->on('pgm.group_id', '=', 'pgmt.group_id')
    //                     ->where('pgmt.is_team_lead', '=', 'Y');
    //             })
    //             ->leftJoin('usermaster as um', 'pgmt.user_id', '=', 'um.user_id')
    //             ->where('pm.guide_number', $guide->guide_id)
    //             ->select(
    //                 'pm.project_id',
    //                 DB::raw('CASE WHEN ptm.project_type_id = 5 THEN um.user_name ELSE pm.project_name END AS title'),
    //                 'pgm.group_id',
    //                 'ptm.project_type_name as type',
    //                 'pgm.group_id_actual as id',
    //                 'gm.guide_name as guide',
    //                 'pm.project_status as status',
    //                 'um.program as course',
    //             )
    //             ->get();

    //         $result['guide_projects'] = $guideProjects;
    //     }

    //     // 2. Check for external projects
    //     $externalLinks = DB::table('projectexternallinktable')
    //         ->where('user_id', $request->user_id)
    //         ->pluck('project_id');

    //     if ($externalLinks->isNotEmpty()) {
    //         $result['external'] = true;

    //         $externalProjects = DB::table('projectmaster as pm')
    //             ->join('projectgroupmaster as pgm', 'pm.project_group_id', '=', 'pgm.group_id')
    //             ->leftJoin('projecttypemaster as ptm', 'pgm.project_type_id', '=', 'ptm.project_type_id')
    //             ->leftJoin('guidemaster as gm', 'pm.guide_number', '=', 'gm.guide_id')
    //             ->leftJoin('projectgroupmembertable as pgmt', function ($join) {
    //                 $join->on('pgm.group_id', '=', 'pgmt.group_id')
    //                     ->where('pgmt.is_team_lead', '=', 'Y');
    //             })
    //             ->leftJoin('usermaster as um', 'pgmt.user_id', '=', 'um.user_id')
    //             ->whereIn('pm.project_id', $externalLinks)
    //             ->select(
    //                 'pm.project_id',
    //                 DB::raw('CASE WHEN ptm.project_type_id = 5 THEN um.user_name ELSE pm.project_name END AS title'),
    //                 'pgm.group_id',
    //                 'ptm.project_type_name as type',
    //                 'pgm.group_id_actual as id',
    //                 'gm.guide_name as guide',
    //                 'pm.project_status as status',
    //                 'um.program as course',
    //             )
    //             ->get();

    //         $result['external_projects'] = $externalProjects;
    //     }
    //     else {
    //         $result['external_projects'] = [];
    //     }

    //     return response()->json($result);
    // }

    //defaulter
    public function getEvaluationDetails(Request $request)
    {
        $groupId = $request->group_id;
        $userId = $request->user_id;
    
        $project = DB::table('projectgroupmaster')
            ->where('group_id', $groupId)
            ->first();
    
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }
    
        $projectTypeId = $project->project_type_id;
    
        $components = DB::table('maxmarkstable as parent')
            ->join('projecttype_maxmark_map as ptmm', 'ptmm.maxmark_id', '=', 'parent.srno')
            ->where('ptmm.project_type_id', $projectTypeId)
            ->whereNull('parent.parent_id')
            ->select('parent.srno', 'parent.max_mark_detail', 'parent.max_mark_value')
            ->get();
    
        $students = DB::table('projectgroupmembertable as pgm')
            ->join('usermaster as um', 'pgm.user_id', '=', 'um.user_id')
            ->where('pgm.group_id', $groupId)
            ->select('pgm.user_id', 'um.user_name', 'um.enrollment_id')
            ->get();
    
        $componetData = [];
        foreach ($components as $component) {
            $children = DB::table('maxmarkstable as child')
                ->join('projecttype_maxmark_map as ptmm', 'ptmm.maxmark_id', '=', 'child.srno')
                ->where('ptmm.project_type_id', $projectTypeId)
                ->where('child.parent_id', $component->srno)
                ->select('child.srno', 'child.max_mark_detail', 'child.max_mark_value', 'child.marks_by')
                ->get();
    
            $subComponents = [];
            $isLocked = true;
    
            foreach ($children as $child) {
                $marksData = DB::table('projectevaluationtable as pet')
                    ->join('projectgroupmembertable as pgm', 'pgm.member_id', '=', 'pet.member_id')
                    ->join('usermaster as um', 'um.user_id', '=', 'pgm.user_id')
                    ->where('pet.project_id', $groupId)
                    ->where('pet.evaluation_component_id', $child->srno)
                    ->select('um.enrollment_id', 'pet.marks_value')
                    ->get();
    
                $marks = $marksData->pluck('marks_value', 'enrollment_id')->toArray();
    
                foreach ($students as $student) {
                    if (!array_key_exists($student->enrollment_id, $marks)) {
                        $isLocked = false;
                        break;
                    }
                }
    
                $subComponents[] = [
                    'title' => $child->max_mark_detail,
                    'component_id' => $child->srno,
                    'role' => $child->marks_by,
                    'max_marks' => $child->max_mark_value,
                    'marks' => $marks,
                ];
            }
    
            $componetData[] = [
                'title' => $component->max_mark_detail,
                'component_id' => $component->srno,
                'locked' => $isLocked,
                'sub_components' => $subComponents,
            ];
        }
    
        $grades = [];
        foreach ($students as $student) {
            $gradeRow = [
                'id' => $student->user_id,
                'name' => $student->user_name,
                'enrollment' => $student->enrollment_id,
            ];
    
            foreach ($componetData as $comp) {
                $compTitle = $comp['title'];
                $gradeRow[$compTitle] = [];
    
                $totalMarks = 0;
    
                foreach ($comp['sub_components'] as $sub) {
                    $evaluation = DB::table('projectevaluationtable as pet')
                        ->join('projectgroupmembertable as pgm', 'pgm.member_id', '=', 'pet.member_id')
                        ->where('pgm.user_id', $student->user_id)
                        ->where('pet.project_id', $groupId)
                        ->where('pet.evaluation_component_id', $sub['component_id'])
                        ->select('pet.marks_value', 'pet.member_status')
                        ->first();
    
                    $marks = $evaluation->marks_value ?? '';
                    $status = $evaluation->member_status ?? 'yet to enter marks';
    
                    if (is_numeric($marks)) {
                        $totalMarks += $marks;
                    }
    
                    $gradeRow[$compTitle][$sub['title']] = [
                        'marks' => $marks,
                        'status' => $status,
                    ];
                }
    
                // Add total marks for the main component
                $gradeRow[$compTitle]['Total'] = $totalMarks;
            }
    
            $grades[] = $gradeRow;
        }
    
        return response()->json([
            'Status' => 'Success',
            'Data' => [
                'group_id' => $groupId,
                'project_title' => $project->group_name,
                'user_id' => $userId,
                'components' => $componetData,
                'grades' => $grades
            ]
        ]);
    }

    //role based
    // public function getEvaluationDetails(Request $request)
    // {
    //     $groupId = $request->group_id;
    //     $userId = $request->user_id;
    //     $role = strtoupper($request->role); // Expect 'G' for Guide or 'E' for External

    //     if (!in_array($role, ['G', 'E'])) {
    //         return response()->json(['error' => 'Invalid role'], 400);
    //     }

    //     $project = DB::table('projectgroupmaster')
    //         ->where('group_id', $groupId)
    //         ->first();

    //     if (!$project) {
    //         return response()->json(['error' => 'Project not found'], 404);
    //     }

    //     $projectTypeId = $project->project_type_id;

    //     $components = DB::table('maxmarkstable as parent')
    //         ->join('projecttype_maxmark_map as ptmm', 'ptmm.maxmark_id', '=', 'parent.srno')
    //         ->where('ptmm.project_type_id', $projectTypeId)
    //         ->whereNull('parent.parent_id')
    //         ->select('parent.srno', 'parent.max_mark_detail', 'parent.max_mark_value')
    //         ->get();

    //     $students = DB::table('projectgroupmembertable as pgm')
    //         ->join('usermaster as um', 'pgm.user_id', '=', 'um.user_id')
    //         ->where('pgm.group_id', $groupId)
    //         ->select('pgm.user_id', 'um.user_name', 'um.enrollment_id')
    //         ->get();

    //     $componetData = [];
    //     foreach ($components as $component) {
    //         $children = DB::table('maxmarkstable as child')
    //             ->join('projecttype_maxmark_map as ptmm', 'ptmm.maxmark_id', '=', 'child.srno')
    //             ->where('ptmm.project_type_id', $projectTypeId)
    //             ->where('child.parent_id', $component->srno)
    //             ->where('child.marks_by', $role) // 🔒 Role-based filter
    //             ->select('child.srno', 'child.max_mark_detail', 'child.max_mark_value', 'child.marks_by')
    //             ->get();

    //         $subComponents = [];
    //         $isLocked = true;

    //         foreach ($children as $child) {
    //             $marksData = DB::table('projectevaluationtable as pet')
    //                 ->join('projectgroupmembertable as pgm', 'pgm.member_id', '=', 'pet.member_id')
    //                 ->join('usermaster as um', 'um.user_id', '=', 'pgm.user_id')
    //                 ->where('pet.project_id', $groupId)
    //                 ->where('pet.evaluation_component_id', $child->srno)
    //                 ->select('um.enrollment_id', 'pet.marks_value')
    //                 ->get();

    //             $marks = $marksData->pluck('marks_value', 'enrollment_id')->toArray();

    //             foreach ($students as $student) {
    //                 if (!array_key_exists($student->enrollment_id, $marks)) {
    //                     $isLocked = false;
    //                     break;
    //                 }
    //             }

    //             $subComponents[] = [
    //                 'title' => $child->max_mark_detail,
    //                 'component_id' => $child->srno,
    //                 'role' => $child->marks_by,
    //                 'max_marks' => $child->max_mark_value,
    //                 'marks' => $marks,
    //             ];
    //         }

    //         // If there are subcomponents for the current role, include the parent
    //         if (!empty($subComponents)) {
    //             $componetData[] = [
    //                 'title' => $component->max_mark_detail,
    //                 'component_id' => $component->srno,
    //                 'locked' => $isLocked,
    //                 'sub_components' => $subComponents,
    //             ];
    //         }
    //     }

    //     $grades = [];
    //     foreach ($students as $student) {
    //         $gradeRow = [
    //             'id' => $student->user_id,
    //             'name' => $student->user_name,
    //             'enrollment' => $student->enrollment_id,
    //         ];

    //         foreach ($componetData as $comp) {
    //             $compTitle = $comp['title'];
    //             $gradeRow[$compTitle] = [];

    //             $totalMarks = 0;

    //             foreach ($comp['sub_components'] as $sub) {
    //                 $evaluation = DB::table('projectevaluationtable as pet')
    //                     ->join('projectgroupmembertable as pgm', 'pgm.member_id', '=', 'pet.member_id')
    //                     ->where('pgm.user_id', $student->user_id)
    //                     ->where('pet.project_id', $groupId)
    //                     ->where('pet.evaluation_component_id', $sub['component_id'])
    //                     ->select('pet.marks_value', 'pet.member_status')
    //                     ->first();

    //                 $marks = $evaluation->marks_value ?? '';
    //                 $status = $evaluation->member_status ?? 'yet to enter marks';

    //                 if (is_numeric($marks)) {
    //                     $totalMarks += $marks;
    //                 }

    //                 $gradeRow[$compTitle][$sub['title']] = [
    //                     'marks' => $marks,
    //                     'status' => $status,
    //                 ];
    //             }

    //             $gradeRow[$compTitle]['Total'] = $totalMarks;
    //         }

    //         $grades[] = $gradeRow;
    //     }

    //     return response()->json([
    //         'Status' => 'Success',
    //         'Data' => [
    //             'group_id' => $groupId,
    //             'project_title' => $project->group_name,
    //             'user_id' => $userId,
    //             'components' => $componetData,
    //             'grades' => $grades
    //         ]
    //     ]);
    // }

    

    // public function submitMarks(Request $request)
    // {
    //     $groupId = $request->groupId;
    //     $components = $request->components;
    
    //     $rawMembers = DB::table('projectgroupmembertable as pgm')
    //         ->join('usermaster as um', 'pgm.user_id', '=', 'um.user_id')
    //         ->where('pgm.group_id', $groupId)
    //         ->select('pgm.member_id', 'um.enrollment_id')
    //         ->get();
    
    
    //     $members = $rawMembers->mapWithKeys(function ($item) {
    //         return [(string) $item->enrollment_id => $item->member_id];
    //     });
    
    
    //     foreach ($components as $component) {
    //         foreach ($component['sub_components'] as $sub) {
    //             $evaluationComponentId = $sub['component_id'];
    //             $marksData = $sub['marks']; // Expecting [enrollment_id => ['mark' => ..., 'status' => ...]]
                
    
    //             foreach ($marksData as $enrollment => $markEntry) {
    //                 if (!isset($members[$enrollment])) {
    //                     continue;
    //                 }
    
    //                 $memberId = $members[$enrollment];
    
    //                 // If frontend sent mark + status as an object
    //                 if (is_array($markEntry)) {
    //                     $marksValue = $markEntry['mark'] === "" ? null : $markEntry['mark'];
    //                     $status = $markEntry['status'] ?? (is_null($marksValue) ? 'yet to enter marks' : 'Present');
    //                 } else {
    //                     // fallback (in case frontend sent raw marks)
    //                     $marksValue = $markEntry === "" ? null : $markEntry;
    //                     $status = is_null($marksValue) ? 'yet to enter marks' : 'Present';
    //                 }

    
    //                 try {
    //                     DB::table('projectevaluationtable')->updateOrInsert(
    //                         [
    //                             'project_id' => (int)$groupId,
    //                             'evaluation_component_id' => (int)$evaluationComponentId,
    //                             'member_id' => (int)$memberId,
    //                         ],
    //                         [
    //                             'marks_value' => $marksValue,
    //                             'member_status' => $status,
    //                         ]
    //                     );

    //                 } catch (\Exception $e) {
    //                     \Log::error("DB update failed for $enrollment: " . $e->getMessage());
    //                 }
    //             }
    //         }
    //     }
    
    //     return response()->json([
    //         'Status' => 'Success',
    //         'Message' => 'Marks submitted successfully.',
    //     ]);
    // }
    public function submitMarks(Request $request)
    {
        $groupId = $request->groupId;
        $components = $request->components;
        $userRole = $request->userRole; 
        $userId = $request->user_id; // Get the current user's ID for created_by/updated_by
        $currentTime = now(); // Get the current timestamp for created_on/updated_on

        $rawMembers = DB::table('projectgroupmembertable as pgm')
            ->join('usermaster as um', 'pgm.user_id', '=', 'um.user_id')
            ->where('pgm.group_id', $groupId)
            ->select('pgm.member_id', 'um.enrollment_id')
            ->get();

        $members = $rawMembers->mapWithKeys(function ($item) {
            return [(string) $item->enrollment_id => $item->member_id];
        });

        foreach ($components as $component) {
            foreach ($component['sub_components'] as $sub) {
                $evaluationComponentId = $sub['component_id'];
                $marksData = $sub['marks']; // Expecting [enrollment_id => ['mark' => ..., 'status' => ...]]
                
                foreach ($marksData as $enrollment => $markEntry) {
                    if (!isset($members[$enrollment])) {
                        continue;
                    }

                    $memberId = $members[$enrollment];

                    // If frontend sent mark + status as an object
                    if (is_array($markEntry)) {
                        $marksValue = $markEntry['mark'] === "" ? null : $markEntry['mark'];
                        $status = $markEntry['status'] ?? (is_null($marksValue) ? 'yet to enter marks' : 'Present');
                    } else {
                        // fallback (in case frontend sent raw marks)
                        $marksValue = $markEntry === "" ? null : $markEntry;
                        $status = is_null($marksValue) ? 'yet to enter marks' : 'Present';
                    }

                    try {
                        // Check if the record already exists
                        $exists = DB::table('projectevaluationtable')
                            ->where([
                                'project_id' => (int)$groupId,
                                'evaluation_component_id' => (int)$evaluationComponentId,
                                'member_id' => (int)$memberId,
                            ])
                            ->exists();

                        if ($exists) {
                            // Update existing record with updated_by and updated_on
                            DB::table('projectevaluationtable')
                                ->where([
                                    'project_id' => (int)$groupId,
                                    'evaluation_component_id' => (int)$evaluationComponentId,
                                    'member_id' => (int)$memberId,
                                ])
                                ->update([
                                    'marks_value' => $marksValue,
                                    'member_status' => $status,
                                    'updated_by' => $userId,
                                    'updated_on' => $currentTime,
                                ]);
                        } else {
                            // Insert new record with created_by and created_on
                            DB::table('projectevaluationtable')
                                ->insert([
                                    'project_id' => (int)$groupId,
                                    'evaluation_component_id' => (int)$evaluationComponentId,
                                    'member_id' => (int)$memberId,
                                    'marks_value' => $marksValue,
                                    'member_status' => $status,
                                    'marks_by' => $userRole,
                                    'created_by' => $userId,
                                    'created_on' => $currentTime,
                                ]);
                        }
                    } catch (\Exception $e) {
                        \Log::error("DB update failed for $enrollment: " . $e->getMessage());
                    }
                }
            }
        }

        return response()->json([
            'Status' => 'Success',
            'Message' => 'Marks submitted successfully.',
        ]);
    }

    public function fetchProjectsByUserId(Request $request)
    {
        $result = [
            'guide' => false,
            'external' => false,
        ];

        // 1. Check if user is a guide
        $guide = DB::table('guidemaster')->where('user_id', $request->user_id)->first();

        if ($guide) {
            $result['guide'] = true;

            $guideProjects = DB::table('projectmaster as pm')
                ->join('projectgroupmaster as pgm', 'pm.project_group_id', '=', 'pgm.group_id')
                ->leftJoin('projecttypemaster as ptm', 'pgm.project_type_id', '=', 'ptm.project_type_id')
                ->leftJoin('guidemaster as gm', 'pm.guide_number', '=', 'gm.guide_id')
                ->leftJoin('projectgroupmembertable as pgmt', function ($join) {
                    $join->on('pgm.group_id', '=', 'pgmt.group_id')
                        ->where('pgmt.is_team_lead', '=', 'Y');
                })
                ->leftJoin('usermaster as um', 'pgmt.user_id', '=', 'um.user_id')
                ->where('pm.guide_number', $guide->guide_id)
                ->select(
                    'pm.project_id',
                    DB::raw('CASE WHEN ptm.project_type_id = 5 THEN um.user_name ELSE pm.project_name END AS title'),
                    'pgm.group_id',
                    'ptm.project_type_name as type',
                    'pgm.group_id_actual as id',
                    'gm.guide_name as guide',
                    'pm.project_status as status',
                    'um.program as course',
                    'ptm.project_type_id'
                )
                ->get();

            // Enhance projects with evaluation status information
            foreach ($guideProjects as $project) {
                $project->components = $this->getEvaluationStatusForProject($project->group_id, $project->project_type_id);
            }

            $result['guide_projects'] = $guideProjects;
        }

        // 2. Check for external projects
        $externalLinks = DB::table('projectexternallinktable')
            ->where('user_id', $request->user_id)
            ->pluck('project_id');

        if ($externalLinks->isNotEmpty()) {
            $result['external'] = true;

            $externalProjects = DB::table('projectmaster as pm')
                ->join('projectgroupmaster as pgm', 'pm.project_group_id', '=', 'pgm.group_id')
                ->leftJoin('projecttypemaster as ptm', 'pgm.project_type_id', '=', 'ptm.project_type_id')
                ->leftJoin('guidemaster as gm', 'pm.guide_number', '=', 'gm.guide_id')
                ->leftJoin('projectgroupmembertable as pgmt', function ($join) {
                    $join->on('pgm.group_id', '=', 'pgmt.group_id')
                        ->where('pgmt.is_team_lead', '=', 'Y');
                })
                ->leftJoin('usermaster as um', 'pgmt.user_id', '=', 'um.user_id')
                ->whereIn('pm.project_id', $externalLinks)
                ->select(
                    'pm.project_id',
                    DB::raw('CASE WHEN ptm.project_type_id = 5 THEN um.user_name ELSE pm.project_name END AS title'),
                    'pgm.group_id',
                    'ptm.project_type_name as type',
                    'pgm.group_id_actual as id',
                    'gm.guide_name as guide',
                    'pm.project_status as status',
                    'um.program as course',
                    'ptm.project_type_id'
                )
                ->get();

            // Enhance external projects with evaluation status information
            foreach ($externalProjects as $project) {
                $project->components = $this->getEvaluationStatusForProject($project->group_id, $project->project_type_id);
            }

            $result['external_projects'] = $externalProjects;
        }
        else {
            $result['external_projects'] = [];
        }

        return response()->json($result);
    }

    //accordian
    // public function fetchProjectsByUserId(Request $request)
    // {
    //     $result = [
    //         'guide' => false,
    //         'external' => false,
    //         'guide_projects' => [],
    //         'external_projects' => [],
    //     ];

    //     // 1. Check if user is a guide
    //     $guide = DB::table('guidemaster')->where('user_id', $request->user_id)->first();

    //     if ($guide) {
    //         $result['guide'] = true;

    //         $guideProjects = DB::table('projectmaster as pm')
    //             ->join('projectgroupmaster as pgm', 'pm.project_group_id', '=', 'pgm.group_id')
    //             ->leftJoin('projecttypemaster as ptm', 'pgm.project_type_id', '=', 'ptm.project_type_id')
    //             ->leftJoin('guidemaster as gm', 'pm.guide_number', '=', 'gm.guide_id')
    //             ->leftJoin('projectgroupmembertable as pgmt', function ($join) {
    //                 $join->on('pgm.group_id', '=', 'pgmt.group_id')
    //                     ->where('pgmt.is_team_lead', '=', 'Y');
    //             })
    //             ->leftJoin('usermaster as um', 'pgmt.user_id', '=', 'um.user_id')
    //             ->where('pm.guide_number', $guide->guide_id)
    //             ->select(
    //                 'pm.project_id',
    //                 DB::raw('CASE WHEN ptm.project_type_id = 5 THEN um.user_name ELSE pm.project_name END AS title'),
    //                 'pgm.group_id',
    //                 'ptm.project_type_name as type',
    //                 'pgm.group_id_actual as id',
    //                 'gm.guide_name as guide',
    //                 'pm.project_status as status',
    //                 'um.program as course',
    //                 'ptm.project_type_id'
    //             )
    //             ->get();

    //         foreach ($guideProjects as $project) {
    //             $project->components = $this->getEvaluationStatusForProject($project->group_id, $project->project_type_id);
    //         }

    //         $result['guide_projects'] = $guideProjects->groupBy('type');
    //     }

    //     // 2. External projects
    //     $externalLinks = DB::table('projectexternallinktable')
    //         ->where('user_id', $request->user_id)
    //         ->pluck('project_id');

    //     if ($externalLinks->isNotEmpty()) {
    //         $result['external'] = true;

    //         $externalProjects = DB::table('projectmaster as pm')
    //             ->join('projectgroupmaster as pgm', 'pm.project_group_id', '=', 'pgm.group_id')
    //             ->leftJoin('projecttypemaster as ptm', 'pgm.project_type_id', '=', 'ptm.project_type_id')
    //             ->leftJoin('guidemaster as gm', 'pm.guide_number', '=', 'gm.guide_id')
    //             ->leftJoin('projectgroupmembertable as pgmt', function ($join) {
    //                 $join->on('pgm.group_id', '=', 'pgmt.group_id')
    //                     ->where('pgmt.is_team_lead', '=', 'Y');
    //             })
    //             ->leftJoin('usermaster as um', 'pgmt.user_id', '=', 'um.user_id')
    //             ->whereIn('pm.project_id', $externalLinks)
    //             ->select(
    //                 'pm.project_id',
    //                 DB::raw('CASE WHEN ptm.project_type_id = 5 THEN um.user_name ELSE pm.project_name END AS title'),
    //                 'pgm.group_id',
    //                 'ptm.project_type_name as type',
    //                 'pgm.group_id_actual as id',
    //                 'gm.guide_name as guide',
    //                 'pm.project_status as status',
    //                 'um.program as course',
    //                 'ptm.project_type_id'
    //             )
    //             ->get();

    //         foreach ($externalProjects as $project) {
    //             $project->components = $this->getEvaluationStatusForProject($project->group_id, $project->project_type_id);
    //         }

    //         $result['external_projects'] = $externalProjects->groupBy('type');
    //     }

    //     return response()->json($result);
    // }

    // New helper method to check evaluation status for a project
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
}
