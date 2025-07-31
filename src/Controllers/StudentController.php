<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Student;
use App\Services\DataStore;
use App\Services\ValidationService;
use App\Helpers\ResponseHelper;

class StudentController
{
    /**
     * Get all students
     */
    public function index(Request $request, Response $response): Response
    {
        $students = DataStore::getAllStudents();
        $formattedStudents = ResponseHelper::formatCollection($students);

        return ResponseHelper::success($response, $formattedStudents);
    }

    /**
     * Get a specific student
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $student = DataStore::getStudent($id);

        if(!$student){
            return ResponseHelper::notFound($response, 'Student');
        }

        return ResponseHelper::success($response, ResponseHelper::formatModel($student));
    }

    /**
     * Create a new student
     */
    public function create(Request $request, Response $response): Response
    {
        $data = ValidationService::sanitizeInput($request->getParsedBody() ?? []);

        $student = new Student(
            DataStore::getNextId(),
            $data['name'] ?? '',
            $data['email'] ?? '',
            $data['studentNumber'] ?? '',
            $data['curriculumId'] ?? null,
            $data['yearLevel'] ?? 1
        );

        $errors = $student->validate();
        if(!empty($errors)){
            return ResponseHelper::validationError($response, $errors);
        }

        // Validate that curriculum ID exists if provided
        if($student->curriculumId && !DataStore::getCurriculum($student->curriculumId)){
            return ResponseHelper::validationError($response, ["Curriculum with ID {$student->curriculumId} does not exist"]);
        }

        // Check for duplicate student number
        if(!empty($student->studentNumber)){
            $existingStudents = DataStore::getAllStudents();
            foreach($existingStudents as $existingStudent){
                if($existingStudent->studentNumber === $student->studentNumber){
                    return ResponseHelper::validationError($response, ['Student number already exists']);
                }
            }
        }

        DataStore::addStudent($student);

        return ResponseHelper::created(
            $response,
            ResponseHelper::formatModel($student),
            'Student created successfully'
        );
    }

    /**
     * Update an existing student
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = ValidationService::sanitizeInput($request->getParsedBody() ?? []);

        $existingStudent = DataStore::getStudent($id);
        if(!$existingStudent){
            return ResponseHelper::notFound($response, 'Student');
        }

        $student = new Student(
            $id,
            $data['name'] ?? $existingStudent->name,
            $data['email'] ?? $existingStudent->email,
            $data['studentNumber'] ?? $existingStudent->studentNumber,
            $data['curriculumId'] ?? $existingStudent->curriculumId,
            $data['yearLevel'] ?? $existingStudent->yearLevel
        );

        // Preserve enrolled classes
        $student->enrolledClassIds = $existingStudent->enrolledClassIds;

        $errors = $student->validate();
        if(!empty($errors)){
            return ResponseHelper::validationError($response, $errors);
        }

        // Validate that curriculum ID exists if provided
        if($student->curriculumId && !DataStore::getCurriculum($student->curriculumId)){
            return ResponseHelper::validationError($response, ["Curriculum with ID {$student->curriculumId} does not exist"]);
        }

        // Check for duplicate student number (excluding current student)
        if(!empty($student->studentNumber)){
            $allStudents = DataStore::getAllStudents();
            foreach($allStudents as $otherStudent){
                if($otherStudent->id !== $id && $otherStudent->studentNumber === $student->studentNumber){
                    return ResponseHelper::validationError($response, ['Student number already exists']);
                }
            }
        }

        DataStore::updateStudent($id, $student);

        return ResponseHelper::success(
            $response,
            ResponseHelper::formatModel($student),
            'Student updated successfully'
        );
    }

    /**
     * Delete a student
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        if(!DataStore::getStudent($id)){
            return ResponseHelper::notFound($response, 'Student');
        }

        DataStore::deleteStudent($id);

        return ResponseHelper::success($response, null, 'Student deleted successfully');
    }

    /**
     * Get students by curriculum
     */
    public function getByCurriculum(Request $request, Response $response, array $args): Response
    {
        $curriculumId = (int)$args['curriculumId'];
        $curriculum = DataStore::getCurriculum($curriculumId);

        if(!$curriculum){
            return ResponseHelper::notFound($response, 'Curriculum');
        }

        $students = DataStore::getAllStudents();
        $filteredStudents = array_filter($students, function($student) use ($curriculumId){
            return $student->curriculumId === $curriculumId;
        });

        $formattedStudents = ResponseHelper::formatCollection(array_values($filteredStudents));

        return ResponseHelper::success($response, $formattedStudents);
    }

    /**
     * Enroll student in a class
     */
    public function enrollInClass(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = ValidationService::sanitizeInput($request->getParsedBody() ?? []);

        $student = DataStore::getStudent($id);
        if(!$student){
            return ResponseHelper::notFound($response, 'Student');
        }

        $classId = $data['classId'] ?? null;
        if(!$classId){
            return ResponseHelper::validationError($response, ['Class ID is required']);
        }

        $class = DataStore::getClass($classId);
        if(!$class){
            return ResponseHelper::validationError($response, ["Class with ID {$classId} does not exist"]);
        }

        if($student->isEnrolledInClass($classId)){
            return ResponseHelper::error(
                $response,
                'Student is already enrolled in this class',
                'Duplicate Enrollment',
                409
            );
        }

        $student->enrollInClass($classId);
        DataStore::updateStudent($id, $student);

        return ResponseHelper::success(
            $response,
            ResponseHelper::formatModel($student),
            'Student enrolled in class successfully'
        );
    }

    /**
     * Drop student from a class
     */
    public function dropClass(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $classId = (int)$args['classId'];

        $student = DataStore::getStudent($id);
        if(!$student){
            return ResponseHelper::notFound($response, 'Student');
        }

        if(!$student->isEnrolledInClass($classId)){
            return ResponseHelper::error(
                $response,
                'Student is not enrolled in this class',
                'Invalid Operation',
                400
            );
        }

        $student->dropClass($classId);
        DataStore::updateStudent($id, $student);

        return ResponseHelper::success(
            $response,
            ResponseHelper::formatModel($student),
            'Student dropped from class successfully'
        );
    }

    /**
     * Search students
     */
    public function search(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $searchTerm = $queryParams['q'] ?? '';
        $curriculumId = isset($queryParams['curriculumId']) ? (int)$queryParams['curriculumId'] : null;
        $yearLevel = isset($queryParams['yearLevel']) ? (int)$queryParams['yearLevel'] : null;
        $minClasses = isset($queryParams['minClasses']) ? (int)$queryParams['minClasses'] : null;
        $maxClasses = isset($queryParams['maxClasses']) ? (int)$queryParams['maxClasses'] : null;

        $students = DataStore::getAllStudents();
        $filteredStudents = array_filter($students, function($student) use ($searchTerm, $curriculumId, $yearLevel, $minClasses, $maxClasses){
            // Text search in name, email, and student number
            if(!empty($searchTerm)){
                $searchLower = strtolower($searchTerm);
                $nameMatch = strpos(strtolower($student->name), $searchLower) !== false;
                $emailMatch = strpos(strtolower($student->email), $searchLower) !== false;
                $numberMatch = strpos(strtolower($student->studentNumber), $searchLower) !== false;
                if(!$nameMatch && !$emailMatch && !$numberMatch){
                    return false;
                }
            }

            // Curriculum filter
            if($curriculumId !== null && $student->curriculumId !== $curriculumId){
                return false;
            }

            // Year level filter
            if($yearLevel !== null && $student->yearLevel !== $yearLevel){
                return false;
            }

            // Enrolled classes count filter
            $classCount = count($student->enrolledClassIds);
            if($minClasses !== null && $classCount < $minClasses){
                return false;
            }

            if($maxClasses !== null && $classCount > $maxClasses){
                return false;
            }

            return true;
        });

        $formattedStudents = ResponseHelper::formatCollection(array_values($filteredStudents));

        return ResponseHelper::success($response, [
            'students' => $formattedStudents,
            'total' => count($filteredStudents),
            'filters' => [
                'searchTerm' => $searchTerm,
                'curriculumId' => $curriculumId,
                'yearLevel' => $yearLevel,
                'minClasses' => $minClasses,
                'maxClasses' => $maxClasses,
            ],
        ]);
    }

    /**
     * Get student statistics
     */
    public function stats(Request $request, Response $response): Response
    {
        $students = DataStore::getAllStudents();

        $stats = [
            'total' => count($students),
            'totalEnrollments' => array_sum(array_map(fn($s) => count($s->enrolledClassIds), $students)),
            'averageEnrollments' => count($students) > 0 ? round(array_sum(array_map(fn($s) => count($s->enrolledClassIds), $students)) / count($students), 2) : 0,
            'distribution' => [
                'byYearLevel' => [],
                'byCurriculum' => [],
                'byEnrollmentCount' => [],
            ],
        ];

        // Calculate distribution by year level
        $yearLevelCounts = [];
        foreach($students as $student){
            $yearLevel = $student->yearLevel;
            $yearLevelCounts[$yearLevel] = ($yearLevelCounts[$yearLevel] ?? 0) + 1;
        }
        ksort($yearLevelCounts);
        $stats['distribution']['byYearLevel'] = $yearLevelCounts;

        // Calculate distribution by curriculum
        $curriculumCounts = [];
        foreach($students as $student){
            $curriculumId = $student->curriculumId ?? 'None';
            $curriculumCounts[$curriculumId] = ($curriculumCounts[$curriculumId] ?? 0) + 1;
        }
        $stats['distribution']['byCurriculum'] = $curriculumCounts;

        // Calculate distribution by enrollment count
        $enrollmentRanges = [
            '0' => 0,
            '1-3' => 0,
            '4-6' => 0,
            '7-9' => 0,
            '10+' => 0,
        ];

        foreach($students as $student){
            $count = count($student->enrolledClassIds);
            if($count === 0){
                $enrollmentRanges['0']++;
            }
            elseif($count <= 3){
                $enrollmentRanges['1-3']++;
            }
            elseif($count <= 6){
                $enrollmentRanges['4-6']++;
            }
            elseif($count <= 9){
                $enrollmentRanges['7-9']++;
            }
            else{
                $enrollmentRanges['10+']++;
            }
        }
        $stats['distribution']['byEnrollmentCount'] = $enrollmentRanges;

        return ResponseHelper::success($response, $stats);
    }
}