<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class Testing extends Controller
{
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

    //         // Fetch project(s) where guide_number matches
    //         $guideProjects = DB::table('projectmaster')
    //             ->where('guide_number', $guide->guide_id)
    //             ->get();

    //         $result['guide_projects'] = $guideProjects;
    //     }

    //     // 2. Check if user is in projectexternallinktable
    //     $externalLinks = DB::table('projectexternallinktable')
    //         ->where('user_id', $request->user_id)
    //         ->pluck('project_id');
    //     // return response()->json($externalLinks);

    //     if ($externalLinks->isNotEmpty()) {
    //         $result['external'] = true;

    //         $externalProjects = DB::table('projectmaster')
    //             ->whereIn('project_id', $externalLinks)
    //             ->get();

    //         $result['external_projects'] = $externalProjects;
    //     }

    //     return response()->json($result);
    // }

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
                )
                ->get();

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
                )
                ->get();

            $result['external_projects'] = $externalProjects;
        }
        else {
            $result['external_projects'] = [];
        }

        return response()->json($result);
    }

    public function getAllEvaluationDetails()
    {
        $projects = DB::table('projectgroupmaster as pgm')
            ->join('projecttypemaster as ptm', 'pgm.project_type_id', '=', 'ptm.project_type_id')
            ->select('pgm.*', 'ptm.project_type_name')
            ->get();
    
        if ($projects->isEmpty()) {
            return response()->json(['error' => 'No projects found'], 404);
        }
    
        $response = [];
    
        foreach ($projects->groupBy('project_type_id') as $projectTypeId => $groupedProjects) {
            $projectTypeName = $groupedProjects->first()->project_type_name;
    
            // Components (shared for project_type_id)
            $components = DB::table('maxmarkstable as parent')
                ->join('projecttype_maxmark_map as ptmm', 'ptmm.maxmark_id', '=', 'parent.srno')
                ->where('ptmm.project_type_id', $projectTypeId)
                ->whereNull('parent.parent_id')
                ->select('parent.srno', 'parent.max_mark_detail', 'parent.max_mark_value')
                ->get();
    
            $componentData = [];
    
            foreach ($components as $component) {
                $children = DB::table('maxmarkstable as child')
                    ->join('projecttype_maxmark_map as ptmm', 'ptmm.maxmark_id', '=', 'child.srno')
                    ->where('ptmm.project_type_id', $projectTypeId)
                    ->where('child.parent_id', $component->srno)
                    ->select('child.srno', 'child.max_mark_detail', 'child.max_mark_value')
                    ->get();
    
                $subComponents = [];
    
                foreach ($children as $child) {
                    $subComponents[] = [
                        'title' => $child->max_mark_detail,
                        'component_id' => $child->srno,
                        'max_marks' => $child->max_mark_value,
                    ];
                }
    
                $componentData[] = [
                    'title' => $component->max_mark_detail,
                    'component_id' => $component->srno,
                    'sub_components' => $subComponents,
                ];
            }
    
            $projectsData = [];
    
            foreach ($groupedProjects as $project) {
                $groupId = $project->group_id;
    
                $students = DB::table('projectgroupmembertable as pgm')
                    ->join('usermaster as um', 'pgm.user_id', '=', 'um.user_id')
                    ->where('pgm.group_id', $groupId)
                    ->select('pgm.user_id', 'um.user_name', 'um.enrollment_id')
                    ->get();
    
                $grades = [];
    
                foreach ($students as $student) {
                    $gradeRow = [
                        'id' => $student->user_id,
                        'name' => $student->user_name,
                        'enrollment' => $student->enrollment_id,
                    ];
    
                    foreach ($componentData as $comp) {
                        $compTitle = $comp['title'];
                        $gradeRow[$compTitle] = [];
    
                        foreach ($comp['sub_components'] as $sub) {
                            $evaluation = DB::table('projectevaluationtable as pet')
                                ->join('projectgroupmembertable as pgm', 'pgm.member_id', '=', 'pet.member_id')
                                ->where('pgm.user_id', $student->user_id)
                                ->where('pet.project_id', $groupId)
                                ->where('pet.evaluation_component_id', $sub['component_id'])
                                ->select('pet.marks_value', 'pet.member_status')
                                ->first();
    
                            $gradeRow[$compTitle][$sub['title']] = [
                                'marks' => $evaluation->marks_value ?? '',
                                'status' => $evaluation->member_status ?? 'Yet to enter marks',
                            ];
                        }
                    }
    
                    $grades[] = $gradeRow;
                }
    
                $projectsData[] = [
                    'group_id' => $groupId,
                    'project_title' => $project->group_name,
                    'grades' => $grades,
                ];
            }
    
            $response[$projectTypeName] = [
                'components' => $componentData,
                'projects' => $projectsData,
            ];
        }
    
        // return view('report', ['data' => $response]);
        return response()->json([
            'status' => 'success',
            'data' => $response
        ]);
    }
    

    public function submitMarks(Request $request)
    {
        $groupId = $request->groupId;
        \Log::info("Received group ID: ", [$groupId]);
        $components = $request->components;
    
        $rawMembers = DB::table('projectgroupmembertable as pgm')
            ->join('usermaster as um', 'pgm.user_id', '=', 'um.user_id')
            ->where('pgm.group_id', $groupId)
            ->select('pgm.member_id', 'um.enrollment_id')
            ->get();
    
        \Log::info("Raw members:", $rawMembers->toArray());
    
        $members = $rawMembers->mapWithKeys(function ($item) {
            return [(string) $item->enrollment_id => $item->member_id];
        });
    
        \Log::info("Members Map:", $members->toArray());
    
        foreach ($components as $component) {
            foreach ($component['sub_components'] as $sub) {
                $evaluationComponentId = $sub['component_id'];
                $marksData = $sub['marks']; // Expecting [enrollment_id => ['mark' => ..., 'status' => ...]]
                
                \Log::info("Processing component_id: $evaluationComponentId");
                \Log::info("Marks Data:", $marksData);
    
                foreach ($marksData as $enrollment => $markEntry) {
                    if (!isset($members[$enrollment])) {
                        \Log::warning("Enrollment ID not found in members map: $enrollment");
                        continue;
                    }
    
                    $memberId = $members[$enrollment];
    
                    // If frontend sent mark + status as an object
                    if (is_array($markEntry)) {
                        $marksValue = $markEntry['mark'] === "" ? null : $markEntry['mark'];
                        $status = $markEntry['status'] ?? (is_null($marksValue) ? 'Yet to enter marks' : 'Present');
                    } else {
                        // fallback (in case frontend sent raw marks)
                        $marksValue = $markEntry === "" ? null : $markEntry;
                        $status = is_null($marksValue) ? 'Yet to enter marks' : 'Present';
                    }
    
                    \Log::info('Trying to upsert:', [
                        'project_id' => $groupId,
                        'evaluation_component_id' => $evaluationComponentId,
                        'member_id' => $memberId,
                        'marks_value' => $marksValue,
                        'status' => $status
                    ]);
    
                    try {
                        DB::table('projectevaluationtable')->updateOrInsert(
                            [
                                'project_id' => (int)$groupId,
                                'evaluation_component_id' => (int)$evaluationComponentId,
                                'member_id' => (int)$memberId,
                            ],
                            [
                                'marks_value' => $marksValue,
                                'member_status' => $status,
                            ]
                        );
    
                        \Log::info("DB update successful for $enrollment: marks_value = $marksValue, status = $status");
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
    } // move these two functions to Guide Contorller

    // public function getAllEvaluationDetails()
    // {
    //     $projects = DB::table('projectgroupmaster')->get();

    //     if ($projects->isEmpty()) {
    //         return response()->json(['error' => 'No projects found'], 404);
    //     }

    //     $response = [];

    //     foreach ($projects as $project) {
    //         $groupId = $project->group_id;
    //         $projectTypeId = $project->project_type_id;

    //         // Fetch top-level components
    //         $components = DB::table('maxmarkstable as parent')
    //             ->join('projecttype_maxmark_map as ptmm', 'ptmm.maxmark_id', '=', 'parent.srno')
    //             ->where('ptmm.project_type_id', $projectTypeId)
    //             ->whereNull('parent.parent_id')
    //             ->select('parent.srno', 'parent.max_mark_detail', 'parent.max_mark_value')
    //             ->get();

    //         $students = DB::table('projectgroupmembertable as pgm')
    //             ->join('usermaster as um', 'pgm.user_id', '=', 'um.user_id')
    //             ->where('pgm.group_id', $groupId)
    //             ->select('pgm.user_id', 'um.user_name', 'um.enrollment_id')
    //             ->get();

    //         $componentData = [];

    //         foreach ($components as $component) {
    //             $children = DB::table('maxmarkstable as child')
    //                 ->join('projecttype_maxmark_map as ptmm', 'ptmm.maxmark_id', '=', 'child.srno')
    //                 ->where('ptmm.project_type_id', $projectTypeId)
    //                 ->where('child.parent_id', $component->srno)
    //                 ->select('child.srno', 'child.max_mark_detail', 'child.max_mark_value')
    //                 ->get();

    //             $subComponents = [];
    //             $isLocked = true;

    //             foreach ($children as $child) {
    //                 $marksData = DB::table('projectevaluationtable as pet')
    //                     ->join('projectgroupmembertable as pgm', 'pgm.member_id', '=', 'pet.member_id')
    //                     ->join('usermaster as um', 'um.user_id', '=', 'pgm.user_id')
    //                     ->where('pet.project_id', $groupId)
    //                     ->where('pet.evaluation_component_id', $child->srno)
    //                     ->select('um.enrollment_id', 'pet.marks_value')
    //                     ->get();

    //                 $marks = $marksData->pluck('marks_value', 'enrollment_id')->toArray();

    //                 foreach ($students as $student) {
    //                     if (!array_key_exists($student->enrollment_id, $marks)) {
    //                         $isLocked = false;
    //                         break;
    //                     }
    //                 }

    //                 $subComponents[] = [
    //                     'title' => $child->max_mark_detail,
    //                     'component_id' => $child->srno,
    //                     'max_marks' => $child->max_mark_value,
    //                     'marks' => $marks,
    //                 ];
    //             }

    //             $componentData[] = [
    //                 'title' => $component->max_mark_detail,
    //                 'component_id' => $component->srno,
    //                 'locked' => $isLocked,
    //                 'sub_components' => $subComponents,
    //             ];
    //         }

    //         $grades = [];
    //         foreach ($students as $student) {
    //             $gradeRow = [
    //                 'id' => $student->user_id,
    //                 'name' => $student->user_name,
    //                 'enrollment' => $student->enrollment_id,
    //             ];

    //             foreach ($componentData as $comp) {
    //                 $compTitle = $comp['title'];
    //                 $gradeRow[$compTitle] = [];

    //                 foreach ($comp['sub_components'] as $sub) {
    //                     $evaluation = DB::table('projectevaluationtable as pet')
    //                         ->join('projectgroupmembertable as pgm', 'pgm.member_id', '=', 'pet.member_id')
    //                         ->where('pgm.user_id', $student->user_id)
    //                         ->where('pet.project_id', $groupId)
    //                         ->where('pet.evaluation_component_id', $sub['component_id'])
    //                         ->select('pet.marks_value', 'pet.member_status')
    //                         ->first();

    //                     $gradeRow[$compTitle][$sub['title']] = [
    //                         'marks' => $evaluation->marks_value ?? '',
    //                         'status' => $evaluation->member_status ?? 'Yet to enter marks',
    //                     ];
    //                 }
    //             }

    //             $grades[] = $gradeRow;
    //         }

    //         // Grouping by project_type_id
    //         $response[$projectTypeId][] = [
    //             'group_id' => $groupId,
    //             'project_title' => $project->group_name,
    //             'components' => $componentData,
    //             'grades' => $grades,
    //         ];
    //     }

    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $response
    //     ]);
    // }

    // public function getAllEvaluationDetails()
    // {
    //     $projects = DB::table('projectgroupmaster as pgm')
    //         ->join('projecttypemaster as ptm', 'pgm.project_type_id', '=', 'ptm.project_type_id')
    //         ->select('pgm.*', 'ptm.project_type_name')
    //         ->get();

    //     if ($projects->isEmpty()) {
    //         return response()->json(['error' => 'No projects found'], 404);
    //     }

    //     $response = [];

    //     foreach ($projects as $project) {
    //         $groupId = $project->group_id;
    //         $projectTypeId = $project->project_type_id;
    //         $projectTypeName = $project->project_type_name;

    //         $components = DB::table('maxmarkstable as parent')
    //             ->join('projecttype_maxmark_map as ptmm', 'ptmm.maxmark_id', '=', 'parent.srno')
    //             ->where('ptmm.project_type_id', $projectTypeId)
    //             ->whereNull('parent.parent_id')
    //             ->select('parent.srno', 'parent.max_mark_detail', 'parent.max_mark_value')
    //             ->get();

    //         $students = DB::table('projectgroupmembertable as pgm')
    //             ->join('usermaster as um', 'pgm.user_id', '=', 'um.user_id')
    //             ->where('pgm.group_id', $groupId)
    //             ->select('pgm.user_id', 'um.user_name', 'um.enrollment_id')
    //             ->get();

    //         $componentData = [];

    //         foreach ($components as $component) {
    //             $children = DB::table('maxmarkstable as child')
    //                 ->join('projecttype_maxmark_map as ptmm', 'ptmm.maxmark_id', '=', 'child.srno')
    //                 ->where('ptmm.project_type_id', $projectTypeId)
    //                 ->where('child.parent_id', $component->srno)
    //                 ->select('child.srno', 'child.max_mark_detail', 'child.max_mark_value')
    //                 ->get();

    //             $subComponents = [];
    //             $isLocked = true;

    //             foreach ($children as $child) {
    //                 $marksData = DB::table('projectevaluationtable as pet')
    //                     ->join('projectgroupmembertable as pgm', 'pgm.member_id', '=', 'pet.member_id')
    //                     ->join('usermaster as um', 'um.user_id', '=', 'pgm.user_id')
    //                     ->where('pet.project_id', $groupId)
    //                     ->where('pet.evaluation_component_id', $child->srno)
    //                     ->select('um.enrollment_id', 'pet.marks_value')
    //                     ->get();

    //                 $marks = $marksData->pluck('marks_value', 'enrollment_id')->toArray();

    //                 foreach ($students as $student) {
    //                     if (!array_key_exists($student->enrollment_id, $marks)) {
    //                         $isLocked = false;
    //                         break;
    //                     }
    //                 }

    //                 $subComponents[] = [
    //                     'title' => $child->max_mark_detail,
    //                     'component_id' => $child->srno,
    //                     'max_marks' => $child->max_mark_value,
    //                     'marks' => $marks,
    //                 ];
    //             }

    //             $componentData[] = [
    //                 'title' => $component->max_mark_detail,
    //                 'component_id' => $component->srno,
    //                 'locked' => $isLocked,
    //                 'sub_components' => $subComponents,
    //             ];
    //         }

    //         $grades = [];
    //         foreach ($students as $student) {
    //             $gradeRow = [
    //                 'id' => $student->user_id,
    //                 'name' => $student->user_name,
    //                 'enrollment' => $student->enrollment_id,
    //             ];

    //             foreach ($componentData as $comp) {
    //                 $compTitle = $comp['title'];
    //                 $gradeRow[$compTitle] = [];

    //                 foreach ($comp['sub_components'] as $sub) {
    //                     $evaluation = DB::table('projectevaluationtable as pet')
    //                         ->join('projectgroupmembertable as pgm', 'pgm.member_id', '=', 'pet.member_id')
    //                         ->where('pgm.user_id', $student->user_id)
    //                         ->where('pet.project_id', $groupId)
    //                         ->where('pet.evaluation_component_id', $sub['component_id'])
    //                         ->select('pet.marks_value', 'pet.member_status')
    //                         ->first();

    //                     $gradeRow[$compTitle][$sub['title']] = [
    //                         'marks' => $evaluation->marks_value ?? '',
    //                         'status' => $evaluation->member_status ?? 'Yet to enter marks',
    //                     ];
    //                 }
    //             }

    //             $grades[] = $gradeRow;
    //         }

    //         // Grouping by project_type_name instead of ID
    //         $response[$projectTypeName][] = [
    //             'group_id' => $groupId,
    //             'project_title' => $project->group_name,
    //             'components' => $componentData,
    //             'grades' => $grades,
    //         ];
    //     }

    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $response
    //     ]);
    // }


    // {
    //     $groupId = $request->group_id;
    //     $userId = $request->user_id; 
    //     // Step 1: Get the project info for the group
    //     $group = DB::table('projectgroupmaster')
    //         ->where('group_id', $groupId)
    //         ->first();

    //     if (!$group) {
    //         return response()->json(['error' => 'Group not found'], 404);
    //     }
        
    //     $projectTypeId = $group->project_type_id;
        

    //     // Step 2: Get main components and their sub-components with max marks
    //     $components = DB::table('maxmarkstable as mm')
    //             ->leftJoin('projecttype_maxmark_map as ptmm', 'ptmm.maxmark_id', '=', 'mm.srno')
    //             ->where('ptmm.project_type_id', $projectTypeId)
    //             ->whereNull('mm.parent_id') // only top-level components
    //             ->select('mm.srno', 'mm.max_mark_detail', 'mm.max_mark_value')
    //             ->get()
    //             ->map(function ($component) use ($projectTypeId) {
    //                 $children = DB::table('maxmarkstable as mm')
    //                     ->leftJoin('projecttype_maxmark_map as ptmm', 'ptmm.maxmark_id', '=', 'mm.srno')
    //                     ->where('ptmm.project_type_id', $projectTypeId)
    //                     ->where('mm.parent_id', $component->srno)
    //                     ->select('mm.srno', 'mm.max_mark_detail', 'mm.max_mark_value')
    //                     ->get();

    //         return [
    //             'title' => $component->max_mark_detail,
    //             'component_id' => $component->srno,
    //             'sub_components' => $children->map(function ($child) {
    //                 return [
    //                     'title' => $child->max_mark_detail,
    //                     'component_id' => $child->srno,
    //                     'max_marks' => $child->max_mark_value,
    //                     'marks' => [] // to be filled in next step
    //                 ];
    //             }),
    //         ];
    //     });

        

    //     // Step 3: Get students in the group
    //     $students = DB::table('usermaster as s')
    //         ->join('projectgroupmembertable as sgm', 'sgm.user_id', '=', 's.user_id')
    //         ->where('sgm.group_id', $groupId)
    //         ->select('s.user_id', 's.user_name', 's.enrollment_id')
    //         ->get();

        

    //     // Step 4: Fill marks per student for each sub-component
    //     foreach ($components as &$component) {
    //         foreach ($component['sub_components'] as &$sub) {
    //             $marks = DB::table('projectevaluationtable')
    //                 ->where('project_id', $groupId)
    //                 ->where('evaluation_component_id', $sub['component_id'])
    //                 ->get()
    //                 ->pluck('marks_value', 'member_id'); // enrollment => marks

    //             $sub['marks'] = $marks;
    //         }
    //     }
        

    //     // Step 5: Build grades section
    //     $grades = [];
    //     foreach ($students as $student) {
    //         $grade = [
    //             'id' => $student->user_id,
    //             'name' => $student->user_name,
    //             'enrollment' => $student->enrollment_id,
    //             'total' => 0,
    //             'status' => '',
    //         ];

    //         foreach ($components as $component) {
    //             $compTitle = $component['title'];
    //             $grade[$compTitle] = [];

    //             $compTotal = 0;
    //             $isAbsent = true;

    //             foreach ($component['sub_components'] as $sub) {
    //                 $mark = $sub['marks'][$student->enrollment_id] ?? null;
    //                 $grade[$compTitle][$sub['title']] = $mark;

    //                 if (!is_null($mark)) {
    //                     $compTotal += $mark;
    //                     $isAbsent = false;
    //                 }
    //             }

    //             $grade['total'] += $compTotal;
    //         }

    //         if ($grade['total'] == 0) {
    //             $grade['total'] = null;
    //             $grade['status'] = 'Absent';
    //         }

    //         $grades[] = $grade;
    //     }

    //     return response()->json([
    //         'group_id' => $groupId,
    //         'user_id' => $userId,
    //         'components' => $components,
    //         'grades' => $grades,
    //     ]);

    // }

    // {
    //     $groupId = $request->group_id;
    //     $userId = $request->user_id; // Evaluator ID (Guide/External)

    //     // Step 1: Get project and project_type_id
    //     $project = DB::table('projectgroupmaster')
    //         ->where('group_id', $groupId)
    //         ->select('group_id AS project_id', 'project_type_id')
    //         ->first();

    //     if (!$project) {
    //         return response()->json(['error' => 'Project not found'], 404);
    //     }
    //     // return response()->json(['project' => $project]);

    //     // Step 2: Get component IDs for this project_type from mapping table
    //     $componentIDs = DB::table('projecttype_maxmark_map')
    //         ->where('project_type_id', $project->project_type_id)
    //         ->pluck('maxmark_id')
    //         ->toArray();
    //     // return response()->json(['project' => $componentIDs]);

    //     // Step 3: Fetch the actual components from maxmarkstable
    //     $rawComponents = DB::table('maxmarkstable')
    //         ->whereIn('srno', $componentIDs)
    //         ->orderBy('parent_id')
    //         ->get();
    //     // return response()->json(['project' => $rawComponents]);

    //     // Step 4: Organize components into nested structure
    //     $components = [];
    //     $grouped = $rawComponents->groupBy('parent_id');

    //     foreach ($grouped as $parent => $items) {
    //         if ($parent === null) {
    //             foreach ($items as $main) {
    //                 $subItems = $grouped->get($main->component_id, []);
    //                 $subComponents = [];

    //                 foreach ($subItems as $sub) {
    //                     $subComponents[] = [
    //                         'title' => $sub->max_mark_detail,
    //                         'max_marks' => $sub->max_mark_value,
    //                         'component_id' => $sub->srno,
    //                         'marks' => []
    //                     ];
    //                 }

    //                 $components[] = [
    //                     'title' => $main->max_mark_detail,
    //                     'max_marks' => $main->max_mark_value,
    //                     'component_id' => $main->srno,
    //                     'sub_components' => $subComponents
    //                 ];
    //             }
    //         }
    //     }
    //     return response()->json(['project' => $components]);

    //     // Step 5: Get students in this group
    //     $students = DB::table('projectgroupmembertable as pgmt')
    //         ->join('usermaster as sm', 'pgmt.user_id', '=', 'sm.user_id')
    //         ->where('pgmt.group_id', $groupId)
    //         ->select('sm.user_id', 'sm.user_name', 'sm.enrollment_id')
    //         ->get();

    //     // Step 6: Get evaluation data by this evaluator
    //     $evaluations = DB::table('projectevaluationtable')
    //         ->where('project_id', $project->project_id)
    //         ->where('created_by', $userId)
    //         ->get()
    //         ->groupBy('component_id');

    //     // Step 7: Assign marks under each subcomponent
    //     foreach ($components as &$component) {
    //         foreach ($component['sub_components'] as &$sub) {
    //             $sub['marks'] = [];

    //             foreach ($students as $student) {
    //                 $mark = optional($evaluations->get($sub['component_id']))->firstWhere('student_id', $student->user_id);
    //                 $sub['marks'][$student->enrollment_id] = $mark ? (int)$mark->marks : null;
    //             }
    //         }
    //     }

    //     // Step 8: Compute grades per student
    //     $grades = [];
    //     foreach ($students as $student) {
    //         $guideTotal = 0;
    //         $externalTotal = 0;
    //         $hasGuideMarks = false;
    //         $hasExternalMarks = false;

    //         foreach ($components as $component) {
    //             foreach ($component['sub_components'] as $sub) {
    //                 $mark = $sub['marks'][$student->enrollment_id];

    //                 if (!is_null($mark)) {
    //                     if (stripos($sub['title'], 'guide') !== false) {
    //                         $guideTotal += $mark;
    //                         $hasGuideMarks = true;
    //                     } elseif (stripos($sub['title'], 'external') !== false) {
    //                         $externalTotal += $mark;
    //                         $hasExternalMarks = true;
    //                     }
    //                 }
    //             }
    //         }

    //         $grades[] = [
    //             'id' => $student->user_id,
    //             'name' => $student->user_name,
    //             'enrollment' => $student->enrollment_id,
    //             'guide' => $hasGuideMarks ? $guideTotal : '',
    //             'external' => $hasExternalMarks ? $externalTotal : '',
    //             'total' => ($hasGuideMarks || $hasExternalMarks) ? ($guideTotal + $externalTotal) : '',
    //             'status' => (!$hasGuideMarks && !$hasExternalMarks) ? 'Absent' : ''
    //         ];
    //     }

    //     // Step 9: Return response
    //     return response()->json([
    //         'semester' => $components[0]['title'] ?? 'Mid',
    //         'user_id' => $userId,
    //         'grades' => $grades,
    //         'components' => $grouped,
    //         'password' => 'your_secret_password',
    //         'groupId' => $groupId
    //     ]);
    // }

    public function getEvaluationComponentsByProject(Request $request)
    {
        // Step 1: Get all mapped maxmark_ids for the given project_type
        $mappedMaxmarks = DB::table('projecttype_maxmark_map')
            ->where('project_type_id', $request->projectTypeId)
            ->pluck('maxmark_id');

        // Step 2: Fetch all maxmark records using the mapped IDs
        $allComponents = DB::table('maxmarkstable')
            ->whereIn('srno', $mappedMaxmarks)
            ->get();

        // Step 3: Group components hierarchically using parent_id
        $grouped = [];

        foreach ($allComponents as $component) {
            if (is_null($component->parent_id)) {
                // This is a parent (e.g., "Mid Sem", "End Sem")
                $grouped[$component->srno] = [
                    'id' => $component->srno,
                    'title' => $component->max_mark_detail,
                    'max_value' => $component->max_mark_value,
                    'children' => []
                ];
            }
        }

        // Step 4: Attach children under respective parents
        foreach ($allComponents as $component) {
            if (!is_null($component->parent_id) && isset($grouped[$component->parent_id])) {
                $grouped[$component->parent_id]['children'][] = [
                    'id' => $component->srno,
                    'title' => $component->max_mark_detail,
                    'max_value' => $component->max_mark_value,
                ];
            }
        }

        // Step 5: Return re-indexed array
        return array_values($grouped);
    }

}