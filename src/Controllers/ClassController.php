<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\ClassEntity;
use App\Services\DataStore;
use App\Services\ValidationService;
use App\Helpers\ResponseHelper;

class ClassController
{
    /**
     * Get all classes
     */
    public function index(Request $request, Response $response): Response
    {
        $classes = DataStore::getAllClasses();
        $formattedClasses = ResponseHelper::formatCollection($classes);

        return ResponseHelper::success($response, $formattedClasses);
    }

    /**
     * Get a specific class
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $class = DataStore::getClass($id);

        if(!$class){
            return ResponseHelper::notFound($response, 'Class');
        }

        return ResponseHelper::success($response, ResponseHelper::formatModel($class));
    }

    /**
     * Create a new class
     */
    public function create(Request $request, Response $response): Response
    {
        $data = ValidationService::sanitizeInput($request->getParsedBody() ?? []);

        $class = new ClassEntity(
            DataStore::getNextId(),
            $data['code'] ?? '',
            $data['subjectId'] ?? null,
            $data['teacherId'] ?? null,
            $data['roomId'] ?? null,
            $data['maxStudents'] ?? 0
        );

        // Set optional properties
        $class->term = $data['term'] ?? '';
        $class->yearLevel = $data['yearLevel'] ?? 1;
        $class->status = $data['status'] ?? 'active';

        $errors = $class->validate();
        if(!empty($errors)){
            return ResponseHelper::validationError($response, $errors);
        }

        // Validate that subject, teacher, and room exist
        if(!DataStore::getSubject($class->subjectId)){
            return ResponseHelper::validationError($response, ["Subject with ID {$class->subjectId} does not exist"]);
        }

        if(!DataStore::getTeacher($class->teacherId)){
            return ResponseHelper::validationError($response, ["Teacher with ID {$class->teacherId} does not exist"]);
        }

        if(!DataStore::getRoom($class->roomId)){
            return ResponseHelper::validationError($response, ["Room with ID {$class->roomId} does not exist"]);
        }

        // Check if teacher can teach this subject
        $teacher = DataStore::getTeacher($class->teacherId);
        if(!$teacher->canTeach($class->subjectId)){
            return ResponseHelper::validationError($response, ['Teacher is not qualified to teach this subject']);
        }

        // Check for duplicate class code
        $existingClasses = DataStore::getAllClasses();
        foreach($existingClasses as $existingClass){
            if($existingClass->code === $class->code){
                return ResponseHelper::validationError($response, ['Class code already exists']);
            }
        }

        DataStore::addClass($class);

        return ResponseHelper::created(
            $response,
            ResponseHelper::formatModel($class),
            'Class created successfully'
        );
    }

    /**
     * Update an existing class
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = ValidationService::sanitizeInput($request->getParsedBody() ?? []);

        $existingClass = DataStore::getClass($id);
        if(!$existingClass){
            return ResponseHelper::notFound($response, 'Class');
        }

        $class = new ClassEntity(
            $id,
            $data['code'] ?? $existingClass->code,
            $data['subjectId'] ?? $existingClass->subjectId,
            $data['teacherId'] ?? $existingClass->teacherId,
            $data['roomId'] ?? $existingClass->roomId,
            $data['maxStudents'] ?? $existingClass->maxStudents
        );

        // Preserve existing data
        $class->enrolledStudentIds = $existingClass->enrolledStudentIds;
        $class->schedule = $existingClass->schedule;
        $class->term = $data['term'] ?? $existingClass->term;
        $class->yearLevel = $data['yearLevel'] ?? $existingClass->yearLevel;
        $class->status = $data['status'] ?? $existingClass->status;

        $errors = $class->validate();
        if(!empty($errors)){
            return ResponseHelper::validationError($response, $errors);
        }

        // Validate that subject, teacher, and room exist
        if(!DataStore::getSubject($class->subjectId)){
            return ResponseHelper::validationError($response, ["Subject with ID {$class->subjectId} does not exist"]);
        }

        if(!DataStore::getTeacher($class->teacherId)){
            return ResponseHelper::validationError($response, ["Teacher with ID {$class->teacherId} does not exist"]);
        }

        if(!DataStore::getRoom($class->roomId)){
            return ResponseHelper::validationError($response, ["Room with ID {$class->roomId} does not exist"]);
        }

        // Check if teacher can teach this subject
        $teacher = DataStore::getTeacher($class->teacherId);
        if(!$teacher->canTeach($class->subjectId)){
            return ResponseHelper::validationError($response, ['Teacher is not qualified to teach this subject']);
        }

        // Check for duplicate class code (excluding current class)
        $allClasses = DataStore::getAllClasses();
        foreach($allClasses as $otherClass){
            if($otherClass->id !== $id && $otherClass->code === $class->code){
                return ResponseHelper::validationError($response, ['Class code already exists']);
            }
        }

        // Check if reducing maxStudents would exceed current enrollment
        if($class->maxStudents < count($class->enrolledStudentIds)){
            return ResponseHelper::validationError($response, ['Cannot reduce max students below current enrollment count']);
        }

        DataStore::updateClass($id, $class);

        return ResponseHelper::success(
            $response,
            ResponseHelper::formatModel($class),
            'Class updated successfully'
        );
    }

    /**
     * Delete a class
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        if(!DataStore::getClass($id)){
            return ResponseHelper::notFound($response, 'Class');
        }

        DataStore::deleteClass($id);

        return ResponseHelper::success($response, null, 'Class deleted successfully');
    }

    /**
     * Get classes by subject
     */
    public function getBySubject(Request $request, Response $response, array $args): Response
    {
        $subjectId = (int)$args['subjectId'];
        $subject = DataStore::getSubject($subjectId);

        if(!$subject){
            return ResponseHelper::notFound($response, 'Subject');
        }

        $classes = DataStore::getAllClasses();
        $filteredClasses = array_filter($classes, function($class) use ($subjectId){
            return $class->subjectId === $subjectId;
        });

        $formattedClasses = ResponseHelper::formatCollection(array_values($filteredClasses));

        return ResponseHelper::success($response, $formattedClasses);
    }

    /**
     * Get classes by teacher
     */
    public function getByTeacher(Request $request, Response $response, array $args): Response
    {
        $teacherId = (int)$args['teacherId'];
        $teacher = DataStore::getTeacher($teacherId);

        if(!$teacher){
            return ResponseHelper::notFound($response, 'Teacher');
        }

        $classes = DataStore::getAllClasses();
        $filteredClasses = array_filter($classes, function($class) use ($teacherId){
            return $class->teacherId === $teacherId;
        });

        $formattedClasses = ResponseHelper::formatCollection(array_values($filteredClasses));

        return ResponseHelper::success($response, $formattedClasses);
    }

    /**
     * Get classes by room
     */
    public function getByRoom(Request $request, Response $response, array $args): Response
    {
        $roomId = (int)$args['roomId'];
        $room = DataStore::getRoom($roomId);

        if(!$room){
            return ResponseHelper::notFound($response, 'Room');
        }

        $classes = DataStore::getAllClasses();
        $filteredClasses = array_filter($classes, function($class) use ($roomId){
            return $class->roomId === $roomId;
        });

        $formattedClasses = ResponseHelper::formatCollection(array_values($filteredClasses));

        return ResponseHelper::success($response, $formattedClasses);
    }

    /**
     * Enroll students in a class
     */
    public function enrollStudents(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = ValidationService::sanitizeInput($request->getParsedBody() ?? []);

        $class = DataStore::getClass($id);
        if(!$class){
            return ResponseHelper::notFound($response, 'Class');
        }

        $studentIds = $data['studentIds'] ?? [];
        if(!is_array($studentIds) || empty($studentIds)){
            return ResponseHelper::validationError($response, ['Student IDs must be a non-empty array']);
        }

        $enrolledCount = 0;
        $errors = [];

        foreach($studentIds as $studentId){
            $student = DataStore::getStudent($studentId);
            if(!$student){
                $errors[] = "Student with ID {$studentId} does not exist";
                continue;
            }

            if($class->hasStudent($studentId)){
                $errors[] = "Student with ID {$studentId} is already enrolled in this class";
                continue;
            }

            if($class->isFull()){
                $errors[] = "Class is full, cannot enroll more students";
                break;
            }

            if($class->addStudent($studentId)){
                $enrolledCount++;
                // Also update the student's enrolled classes
                $student->enrollInClass($id);
                DataStore::updateStudent($studentId, $student);
            }
        }

        if(!empty($errors) && $enrolledCount === 0){
            return ResponseHelper::validationError($response, $errors);
        }

        DataStore::updateClass($id, $class);

        $message = "Successfully enrolled {$enrolledCount} student(s)";
        if(!empty($errors)){
            $message .= ". Some enrollments failed: " . implode(', ', $errors);
        }

        return ResponseHelper::success(
            $response,
            ResponseHelper::formatModel($class),
            $message
        );
    }

    /**
     * Remove a student from a class
     */
    public function removeStudent(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $studentId = (int)$args['studentId'];

        $class = DataStore::getClass($id);
        if(!$class){
            return ResponseHelper::notFound($response, 'Class');
        }

        if(!$class->hasStudent($studentId)){
            return ResponseHelper::error(
                $response,
                'Student is not enrolled in this class',
                'Invalid Operation',
                400
            );
        }

        $class->removeStudent($studentId);
        DataStore::updateClass($id, $class);

        // Also update the student's enrolled classes
        $student = DataStore::getStudent($studentId);
        if($student){
            $student->dropClass($id);
            DataStore::updateStudent($studentId, $student);
        }

        return ResponseHelper::success(
            $response,
            ResponseHelper::formatModel($class),
            'Student removed from class successfully'
        );
    }

    /**
     * Search classes
     */
    public function search(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $searchTerm = $queryParams['q'] ?? '';
        $subjectId = isset($queryParams['subjectId']) ? (int)$queryParams['subjectId'] : null;
        $teacherId = isset($queryParams['teacherId']) ? (int)$queryParams['teacherId'] : null;
        $roomId = isset($queryParams['roomId']) ? (int)$queryParams['roomId'] : null;
        $status = $queryParams['status'] ?? '';
        $term = $queryParams['term'] ?? '';
        $yearLevel = isset($queryParams['yearLevel']) ? (int)$queryParams['yearLevel'] : null;
        $minStudents = isset($queryParams['minStudents']) ? (int)$queryParams['minStudents'] : null;
        $maxStudents = isset($queryParams['maxStudents']) ? (int)$queryParams['maxStudents'] : null;

        $classes = DataStore::getAllClasses();
        $filteredClasses = array_filter($classes, function($class) use ($searchTerm, $subjectId, $teacherId, $roomId, $status, $term, $yearLevel, $minStudents, $maxStudents){
            // Text search in class code
            if(!empty($searchTerm)){
                $searchLower = strtolower($searchTerm);
                $codeMatch = strpos(strtolower($class->code), $searchLower) !== false;
                if(!$codeMatch){
                    return false;
                }
            }

            // Subject filter
            if($subjectId !== null && $class->subjectId !== $subjectId){
                return false;
            }

            // Teacher filter
            if($teacherId !== null && $class->teacherId !== $teacherId){
                return false;
            }

            // Room filter
            if($roomId !== null && $class->roomId !== $roomId){
                return false;
            }

            // Status filter
            if(!empty($status) && $class->status !== $status){
                return false;
            }

            // Term filter
            if(!empty($term) && $class->term !== $term){
                return false;
            }

            // Year level filter
            if($yearLevel !== null && $class->yearLevel !== $yearLevel){
                return false;
            }

            // Enrolled students count filter
            $enrolledCount = count($class->enrolledStudentIds);
            if($minStudents !== null && $enrolledCount < $minStudents){
                return false;
            }

            if($maxStudents !== null && $enrolledCount > $maxStudents){
                return false;
            }

            return true;
        });

        $formattedClasses = ResponseHelper::formatCollection(array_values($filteredClasses));

        return ResponseHelper::success($response, [
            'classes' => $formattedClasses,
            'total' => count($filteredClasses),
            'filters' => [
                'searchTerm' => $searchTerm,
                'subjectId' => $subjectId,
                'teacherId' => $teacherId,
                'roomId' => $roomId,
                'status' => $status,
                'term' => $term,
                'yearLevel' => $yearLevel,
                'minStudents' => $minStudents,
                'maxStudents' => $maxStudents,
            ],
        ]);
    }

    /**
     * Get class statistics
     */
    public function stats(Request $request, Response $response): Response
    {
        $classes = DataStore::getAllClasses();

        $stats = [
            'total' => count($classes),
            'totalEnrollments' => array_sum(array_map(fn($c) => count($c->enrolledStudentIds), $classes)),
            'totalCapacity' => array_sum(array_map(fn($c) => $c->maxStudents, $classes)),
            'averageEnrollment' => count($classes) > 0 ? round(array_sum(array_map(fn($c) => count($c->enrolledStudentIds), $classes)) / count($classes), 2) : 0,
            'averageCapacity' => count($classes) > 0 ? round(array_sum(array_map(fn($c) => $c->maxStudents, $classes)) / count($classes), 2) : 0,
            'utilizationRate' => 0,
            'distribution' => [
                'byStatus' => [],
                'byTerm' => [],
                'byYearLevel' => [],
                'byCapacityRange' => [],
                'byUtilization' => [],
            ],
        ];

        // Calculate utilization rate
        if($stats['totalCapacity'] > 0){
            $stats['utilizationRate'] = round(($stats['totalEnrollments'] / $stats['totalCapacity']) * 100, 2);
        }

        // Calculate distribution by status
        $statusCounts = [];
        foreach($classes as $class){
            $status = $class->status;
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }
        $stats['distribution']['byStatus'] = $statusCounts;

        // Calculate distribution by term
        $termCounts = [];
        foreach($classes as $class){
            $term = $class->term ?: 'Unknown';
            $termCounts[$term] = ($termCounts[$term] ?? 0) + 1;
        }
        $stats['distribution']['byTerm'] = $termCounts;

        // Calculate distribution by year level
        $yearLevelCounts = [];
        foreach($classes as $class){
            $yearLevel = $class->yearLevel;
            $yearLevelCounts[$yearLevel] = ($yearLevelCounts[$yearLevel] ?? 0) + 1;
        }
        ksort($yearLevelCounts);
        $stats['distribution']['byYearLevel'] = $yearLevelCounts;

        // Calculate distribution by capacity ranges
        $capacityRanges = [
            '1-20' => 0,
            '21-40' => 0,
            '41-60' => 0,
            '61-100' => 0,
            '100+' => 0,
        ];

        foreach($classes as $class){
            if($class->maxStudents <= 20){
                $capacityRanges['1-20']++;
            }
            elseif($class->maxStudents <= 40){
                $capacityRanges['21-40']++;
            }
            elseif($class->maxStudents <= 60){
                $capacityRanges['41-60']++;
            }
            elseif($class->maxStudents <= 100){
                $capacityRanges['61-100']++;
            }
            else{
                $capacityRanges['100+']++;
            }
        }
        $stats['distribution']['byCapacityRange'] = $capacityRanges;

        // Calculate distribution by utilization ranges
        $utilizationRanges = [
            '0-25%' => 0,
            '26-50%' => 0,
            '51-75%' => 0,
            '76-100%' => 0,
            'Overbooked' => 0,
        ];

        foreach($classes as $class){
            $utilization = $class->maxStudents > 0 ? (count($class->enrolledStudentIds) / $class->maxStudents) * 100 : 0;

            if($utilization > 100){
                $utilizationRanges['Overbooked']++;
            }
            elseif($utilization > 75){
                $utilizationRanges['76-100%']++;
            }
            elseif($utilization > 50){
                $utilizationRanges['51-75%']++;
            }
            elseif($utilization > 25){
                $utilizationRanges['26-50%']++;
            }
            else{
                $utilizationRanges['0-25%']++;
            }
        }
        $stats['distribution']['byUtilization'] = $utilizationRanges;

        return ResponseHelper::success($response, $stats);
    }
}