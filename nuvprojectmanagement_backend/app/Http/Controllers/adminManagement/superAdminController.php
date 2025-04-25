<?php

namespace App\Http\Controllers\adminManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class superAdminController extends Controller
{
    //
    public function getGuideWiseProjectReport()
    {
        $result = DB::table('guidemaster as gm')
            ->leftJoin('projectgroupmaster as pg', 'gm.guide_id', '=', 'pg.guide_number')
            ->select(
                'gm.guide_id as id',
                'gm.guide_name as Guide name',
                DB::raw('SUM(CASE WHEN pg.project_type_id = 2 THEN 1 ELSE 0 END) as `Minor Project II`'),
                DB::raw('SUM(CASE WHEN pg.project_type_id = 4 THEN 1 ELSE 0 END) as `Major Project II`'),
                DB::raw('SUM(CASE WHEN pg.project_type_id = 5 THEN 1 ELSE 0 END) as `Industry Internship`'),
                DB::raw('SUM(CASE WHEN pg.project_type_id = 7 THEN 1 ELSE 0 END) as `Dissertation II`')
            )
            ->groupBy('gm.guide_id', 'gm.guide_name')
            ->get();

        return response()->json([
            'Status' => 'Success',
            'Data' => $result
        ]);
    }

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
    
    //     foreach ($projects->groupBy('project_type_id') as $projectTypeId => $groupedProjects) {
    //         $projectTypeName = $groupedProjects->first()->project_type_name;
    
    //         // Components (shared for project_type_id)
    //         $components = DB::table('maxmarkstable as parent')
    //             ->join('projecttype_maxmark_map as ptmm', 'ptmm.maxmark_id', '=', 'parent.srno')
    //             ->where('ptmm.project_type_id', $projectTypeId)
    //             ->whereNull('parent.parent_id')
    //             ->select('parent.srno', 'parent.max_mark_detail', 'parent.max_mark_value')
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
    
    //             foreach ($children as $child) {
    //                 $subComponents[] = [
    //                     'title' => $child->max_mark_detail,
    //                     'component_id' => $child->srno,
    //                     'max_marks' => $child->max_mark_value,
    //                 ];
    //             }
    
    //             $componentData[] = [
    //                 'title' => $component->max_mark_detail,
    //                 'component_id' => $component->srno,
    //                 'sub_components' => $subComponents,
    //             ];
    //         }
    
    //         $projectsData = [];
    
    //         foreach ($groupedProjects as $project) {
    //             $groupId = $project->group_id;
    
    //             $students = DB::table('projectgroupmembertable as pgm')
    //                 ->join('usermaster as um', 'pgm.user_id', '=', 'um.user_id')
    //                 ->where('pgm.group_id', $groupId)
    //                 ->select('pgm.user_id', 'um.user_name', 'um.enrollment_id')
    //                 ->get();
    
    //             $grades = [];
    
    //             foreach ($students as $student) {
    //                 $gradeRow = [
    //                     'id' => $student->user_id,
    //                     'name' => $student->user_name,
    //                     'enrollment' => $student->enrollment_id,
    //                 ];
    
    //                 foreach ($componentData as $comp) {
    //                     $compTitle = $comp['title'];
    //                     $gradeRow[$compTitle] = [];
    
    //                     foreach ($comp['sub_components'] as $sub) {
    //                         $evaluation = DB::table('projectevaluationtable as pet')
    //                             ->join('projectgroupmembertable as pgm', 'pgm.member_id', '=', 'pet.member_id')
    //                             ->where('pgm.user_id', $student->user_id)
    //                             ->where('pet.project_id', $groupId)
    //                             ->where('pet.evaluation_component_id', $sub['component_id'])
    //                             ->select('pet.marks_value', 'pet.member_status')
    //                             ->first();
    
    //                         $gradeRow[$compTitle][$sub['title']] = [
    //                             'marks' => $evaluation->marks_value ?? '',
    //                             'status' => $evaluation->member_status ?? 'Yet to enter marks',
    //                         ];
    //                     }
    //                 }
    
    //                 $grades[] = $gradeRow;
    //             }
    
    //             $projectsData[] = [
    //                 'group_id' => $groupId,
    //                 'project_title' => $project->group_name,
    //                 'grades' => $grades,
    //             ];
    //         }
    
    //         $response[$projectTypeName] = [
    //             'components' => $componentData,
    //             'projects' => $projectsData,
    //         ];
    //     }
    
    //     // return view('report', ['data' => $response]);
    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $response
    //     ]);
    // }
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

            // Fetch components and subcomponents
            $components = DB::table('maxmarkstable as parent')
                ->join('projecttype_maxmark_map as ptmm', 'ptmm.maxmark_id', '=', 'parent.srno')
                ->where('ptmm.project_type_id', $projectTypeId)
                ->whereNull('parent.parent_id')
                ->select('parent.srno', 'parent.max_mark_detail', 'parent.max_mark_value')
                ->get();

            $columnHeaders = ['Title', 'Name', 'Enrollment'];
            $flattenedSubComponents = [];

            foreach ($components as $component) {
                $children = DB::table('maxmarkstable as child')
                    ->join('projecttype_maxmark_map as ptmm', 'ptmm.maxmark_id', '=', 'child.srno')
                    ->where('ptmm.project_type_id', $projectTypeId)
                    ->where('child.parent_id', $component->srno)
                    ->select('child.srno', 'child.max_mark_detail', 'child.max_mark_value')
                    ->get();

                foreach ($children as $child) {
                    $title = "{$child->max_mark_detail}_{$child->max_mark_value}";
                    $flattenedSubComponents[] = [
                        'key_marks' => "$title Marks",
                        'key_status' => "$title Status",
                        'component_id' => $child->srno,
                    ];
                    $columnHeaders[] = "$title Marks";
                    $columnHeaders[] = "$title Status";
                }
            }

            // Build rows for each project and each student
            $rows = [];

            foreach ($groupedProjects as $project) {
                $groupId = $project->group_id;

                $students = DB::table('projectgroupmembertable as pgm')
                    ->join('usermaster as um', 'pgm.user_id', '=', 'um.user_id')
                    ->where('pgm.group_id', $groupId)
                    ->select('pgm.user_id', 'pgm.member_id', 'um.user_name', 'um.enrollment_id')
                    ->get();

                foreach ($students as $student) {
                    $row = [
                        'id' => $student->user_id,
                        'Title' => $project->group_name,
                        'Name' => $student->user_name,
                        'Enrollment' => $student->enrollment_id,
                    ];

                    foreach ($flattenedSubComponents as $sub) {
                        $evaluation = DB::table('projectevaluationtable as pet')
                            ->where('pet.project_id', $groupId)
                            ->where('pet.evaluation_component_id', $sub['component_id'])
                            ->where('pet.member_id', $student->member_id)
                            ->select('pet.marks_value', 'pet.member_status')
                            ->first();

                        $row[$sub['key_marks']] = $evaluation->marks_value ?? '';
                        $row[$sub['key_status']] = $evaluation->member_status ?? 'Yet to enter marks';
                    }

                    $rows[] = $row;
                }
            }

            $response[$projectTypeName] = [
                'tableHeader' => $projectTypeName,
                'columnHeaders' => $columnHeaders,
                'rows' => $rows,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $response
        ]);
    }

}
