<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Teacher;
use App\Services\DataStore;
use App\Services\ValidationService;
use App\Helpers\ResponseHelper;

class TeacherController
{
    /**
     * Get all teachers
     */
    public function index(Request $request, Response $response): Response
    {
        $teachers = DataStore::getAllTeachers();
        $formattedTeachers = ResponseHelper::formatCollection($teachers);

        return ResponseHelper::success($response, $formattedTeachers);
    }

    /**
     * Get a specific teacher
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $teacher = DataStore::getTeacher($id);

        if(!$teacher){
            return ResponseHelper::notFound($response, 'Teacher');
        }

        return ResponseHelper::success($response, ResponseHelper::formatModel($teacher));
    }

    /**
     * Create a new teacher
     */
    public function create(Request $request, Response $response): Response
    {
        $data = ValidationService::sanitizeInput($request->getParsedBody() ?? []);

        $teacher = new Teacher(
            DataStore::getNextId(),
            $data['name'] ?? '',
            $data['email'] ?? '',
            $data['subjectIds'] ?? [],
            $data['maxHoursPerWeek'] ?? 40
        );

        $errors = $teacher->validate();
        if(!empty($errors)){
            return ResponseHelper::validationError($response, $errors);
        }

        // Validate that all subject IDs exist
        foreach($teacher->subjectIds as $subjectId){
            if(!DataStore::getSubject($subjectId)){
                return ResponseHelper::validationError($response, ["Subject with ID {$subjectId} does not exist"]);
            }
        }

        DataStore::addTeacher($teacher);

        return ResponseHelper::created(
            $response,
            ResponseHelper::formatModel($teacher),
            'Teacher created successfully'
        );
    }

    /**
     * Update an existing teacher
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = ValidationService::sanitizeInput($request->getParsedBody() ?? []);

        $existingTeacher = DataStore::getTeacher($id);
        if(!$existingTeacher){
            return ResponseHelper::notFound($response, 'Teacher');
        }

        $teacher = new Teacher(
            $id,
            $data['name'] ?? $existingTeacher->name,
            $data['email'] ?? $existingTeacher->email,
            $data['subjectIds'] ?? $existingTeacher->subjectIds,
            $data['maxHoursPerWeek'] ?? $existingTeacher->maxHoursPerWeek
        );

        $errors = $teacher->validate();
        if(!empty($errors)){
            return ResponseHelper::validationError($response, $errors);
        }

        // Validate that all subject IDs exist
        foreach($teacher->subjectIds as $subjectId){
            if(!DataStore::getSubject($subjectId)){
                return ResponseHelper::validationError($response, ["Subject with ID {$subjectId} does not exist"]);
            }
        }

        DataStore::updateTeacher($id, $teacher);

        return ResponseHelper::success(
            $response,
            ResponseHelper::formatModel($teacher),
            'Teacher updated successfully'
        );
    }

    /**
     * Delete a teacher
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        if(!DataStore::getTeacher($id)){
            return ResponseHelper::notFound($response, 'Teacher');
        }

        // Check if teacher is being used in any class
        $classes = DataStore::findClassesByTeacher($id);
        if(!empty($classes)){
            return ResponseHelper::error(
                $response,
                "Cannot delete teacher. They are currently assigned to active classes",
                'Constraint Violation',
                409
            );
        }

        DataStore::deleteTeacher($id);

        return ResponseHelper::success($response, null, 'Teacher deleted successfully');
    }

    /**
     * Get teachers by subject
     */
    public function getBySubject(Request $request, Response $response, array $args): Response
    {
        $subjectId = (int)$args['subjectId'];
        $subject = DataStore::getSubject($subjectId);

        if(!$subject){
            return ResponseHelper::notFound($response, 'Subject');
        }

        $teachers = DataStore::getAllTeachers();
        $filteredTeachers = array_filter($teachers, function($teacher) use ($subjectId){
            return $teacher->canTeach($subjectId);
        });

        $formattedTeachers = ResponseHelper::formatCollection(array_values($filteredTeachers));

        return ResponseHelper::success($response, $formattedTeachers);
    }

    /**
     * Add subjects to a teacher
     */
    public function addSubjects(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = ValidationService::sanitizeInput($request->getParsedBody() ?? []);

        $teacher = DataStore::getTeacher($id);
        if(!$teacher){
            return ResponseHelper::notFound($response, 'Teacher');
        }

        $subjectIds = $data['subjectIds'] ?? [];
        if(!is_array($subjectIds) || empty($subjectIds)){
            return ResponseHelper::validationError($response, ['Subject IDs must be a non-empty array']);
        }

        // Validate that all subject IDs exist
        foreach($subjectIds as $subjectId){
            if(!DataStore::getSubject($subjectId)){
                return ResponseHelper::validationError($response, ["Subject with ID {$subjectId} does not exist"]);
            }
        }

        // Add subjects to teacher
        foreach($subjectIds as $subjectId){
            $teacher->addSubject($subjectId);
        }

        DataStore::updateTeacher($id, $teacher);

        return ResponseHelper::success(
            $response,
            ResponseHelper::formatModel($teacher),
            'Subjects added to teacher successfully'
        );
    }

    /**
     * Remove a subject from a teacher
     */
    public function removeSubject(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $subjectId = (int)$args['subjectId'];

        $teacher = DataStore::getTeacher($id);
        if(!$teacher){
            return ResponseHelper::notFound($response, 'Teacher');
        }

        if(!$teacher->canTeach($subjectId)){
            return ResponseHelper::error(
                $response,
                'Teacher is not assigned to this subject',
                'Invalid Operation',
                400
            );
        }

        // Check if removing this subject would leave teacher with no subjects
        if(count($teacher->subjectIds) <= 1){
            return ResponseHelper::error(
                $response,
                'Cannot remove subject. Teacher must be able to teach at least one subject',
                'Constraint Violation',
                409
            );
        }

        $teacher->removeSubject($subjectId);
        DataStore::updateTeacher($id, $teacher);

        return ResponseHelper::success(
            $response,
            ResponseHelper::formatModel($teacher),
            'Subject removed from teacher successfully'
        );
    }

    /**
     * Search teachers
     */
    public function search(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $searchTerm = $queryParams['q'] ?? '';
        $subjectId = isset($queryParams['subjectId']) ? (int)$queryParams['subjectId'] : null;
        $minHours = isset($queryParams['minHours']) ? (int)$queryParams['minHours'] : null;
        $maxHours = isset($queryParams['maxHours']) ? (int)$queryParams['maxHours'] : null;

        $teachers = DataStore::getAllTeachers();
        $filteredTeachers = array_filter($teachers, function($teacher) use ($searchTerm, $subjectId, $minHours, $maxHours){
            // Text search in name and email
            if(!empty($searchTerm)){
                $searchLower = strtolower($searchTerm);
                $nameMatch = strpos(strtolower($teacher->name), $searchLower) !== false;
                $emailMatch = strpos(strtolower($teacher->email), $searchLower) !== false;
                if(!$nameMatch && !$emailMatch){
                    return false;
                }
            }

            // Subject filter
            if($subjectId !== null && !$teacher->canTeach($subjectId)){
                return false;
            }

            // Hours range filter
            if($minHours !== null && $teacher->maxHoursPerWeek < $minHours){
                return false;
            }

            if($maxHours !== null && $teacher->maxHoursPerWeek > $maxHours){
                return false;
            }

            return true;
        });

        $formattedTeachers = ResponseHelper::formatCollection(array_values($filteredTeachers));

        return ResponseHelper::success($response, [
            'teachers' => $formattedTeachers,
            'total' => count($filteredTeachers),
            'filters' => [
                'searchTerm' => $searchTerm,
                'subjectId' => $subjectId,
                'minHours' => $minHours,
                'maxHours' => $maxHours,
            ],
        ]);
    }

    /**
     * Get teacher statistics
     */
    public function stats(Request $request, Response $response): Response
    {
        $teachers = DataStore::getAllTeachers();

        $stats = [
            'total' => count($teachers),
            'totalMaxHours' => array_sum(array_map(fn($t) => $t->maxHoursPerWeek, $teachers)),
            'averageMaxHours' => count($teachers) > 0 ? round(array_sum(array_map(fn($t) => $t->maxHoursPerWeek, $teachers)) / count($teachers), 2) : 0,
            'distribution' => [
                'byMaxHours' => [],
                'bySubjectCount' => [],
                'bySubjects' => [],
            ],
        ];

        // Calculate distribution by max hours ranges
        $hoursRanges = [
            '1-20' => 0,
            '21-30' => 0,
            '31-40' => 0,
            '41-50' => 0,
            '50+' => 0,
        ];

        foreach($teachers as $teacher){
            if($teacher->maxHoursPerWeek <= 20){
                $hoursRanges['1-20']++;
            }
            elseif($teacher->maxHoursPerWeek <= 30){
                $hoursRanges['21-30']++;
            }
            elseif($teacher->maxHoursPerWeek <= 40){
                $hoursRanges['31-40']++;
            }
            elseif($teacher->maxHoursPerWeek <= 50){
                $hoursRanges['41-50']++;
            }
            else{
                $hoursRanges['50+']++;
            }
        }
        $stats['distribution']['byMaxHours'] = $hoursRanges;

        // Calculate distribution by number of subjects
        $subjectCountDistribution = [];
        foreach($teachers as $teacher){
            $count = count($teacher->subjectIds);
            $subjectCountDistribution[$count] = ($subjectCountDistribution[$count] ?? 0) + 1;
        }
        ksort($subjectCountDistribution);
        $stats['distribution']['bySubjectCount'] = $subjectCountDistribution;

        // Calculate subject popularity among teachers
        $subjectCounts = [];
        foreach($teachers as $teacher){
            foreach($teacher->subjectIds as $subjectId){
                $subjectCounts[$subjectId] = ($subjectCounts[$subjectId] ?? 0) + 1;
            }
        }
        arsort($subjectCounts);
        $stats['distribution']['bySubjects'] = $subjectCounts;

        return ResponseHelper::success($response, $stats);
    }
}