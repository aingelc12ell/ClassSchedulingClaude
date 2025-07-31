<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\DataStore;
use App\Services\ScheduleGenerator;
use App\Services\ValidationService;
use App\Helpers\ResponseHelper;

class ScheduleController
{
    /**
     * Generate schedule for enrollment request
     */
    public function generate(Request $request, Response $response): Response
    {
        $data = ValidationService::sanitizeInput($request->getParsedBody() ?? []);

        // Validate enrollment request
        $validationErrors = ValidationService::validateEnrollmentRequest($data);
        if(!empty($validationErrors)){
            return ResponseHelper::validationError($response, $validationErrors);
        }

        // Validate curriculum integrity
        $curriculumErrors = ValidationService::validateCurriculumIntegrity($data['curriculumId']);
        if(!empty($curriculumErrors)){
            return ResponseHelper::error($response, $curriculumErrors, 'Curriculum Validation Failed', 422);
        }

        // Validate resource availability
        $resourceErrors = ValidationService::validateResourceAvailability($data);
        if(!empty($resourceErrors)){
            return ResponseHelper::error($response, $resourceErrors, 'Insufficient Resources', 409);
        }

        // Generate schedule
        $scheduleResult = ScheduleGenerator::generateSchedule($data);

        // Validate for conflicts
        if(!empty($scheduleResult['schedules'])){
            $conflicts = ValidationService::validateScheduleConflicts($scheduleResult['schedules']);
            if(!empty($conflicts)){
                $scheduleResult['conflicts'] = array_merge($scheduleResult['conflicts'] ?? [], $conflicts);
            }
        }

        return ResponseHelper::scheduleResponse($response, $scheduleResult);
    }

    /**
     * Get all generated schedules
     */
    public function index(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $page = max(1, (int)($queryParams['page'] ?? 1));
        $limit = min(100, max(1, (int)($queryParams['limit'] ?? 10)));
        $term = $queryParams['term'] ?? null;
        $teacherId = isset($queryParams['teacherId']) ? (int)$queryParams['teacherId'] : null;
        $roomId = isset($queryParams['roomId']) ? (int)$queryParams['roomId'] : null;

        // Validate pagination parameters
        $paginationErrors = ValidationService::validatePagination($queryParams);
        if(!empty($paginationErrors)){
            return ResponseHelper::validationError($response, $paginationErrors);
        }

        $classes = DataStore::getAllClasses();

        // Apply filters
        if($term !== null){
            $classes = array_filter($classes, fn($class) => $class->term === $term);
        }

        if($teacherId !== null){
            $classes = array_filter($classes, fn($class) => $class->teacherId === $teacherId);
        }

        if($roomId !== null){
            $classes = array_filter($classes, fn($class) => $class->roomId === $roomId);
        }

        $total = count($classes);
        $classes = array_slice($classes, ($page - 1) * $limit, $limit);

        // Format schedules with full details
        $schedules = [];
        foreach($classes as $class){
            $subject = DataStore::getSubject($class->subjectId);
            $teacher = DataStore::getTeacher($class->teacherId);
            $room = DataStore::getRoom($class->roomId);

            if($subject && $teacher && $room){
                $schedules[] = [
                    'classId' => $class->id,
                    'classCode' => $class->code,
                    'subject' => ResponseHelper::formatModel($subject),
                    'teacher' => ResponseHelper::formatModel($teacher),
                    'room' => ResponseHelper::formatModel($room),
                    'schedule' => $class->schedule,
                    'maxStudents' => $class->maxStudents,
                    'enrolledStudents' => count($class->enrolledStudentIds),
                    'availableSlots' => $class->getAvailableSlots(),
                    'status' => $class->status,
                    'term' => $class->term,
                ];
            }
        }

        return ResponseHelper::paginated($response, $schedules, $page, $limit, $total);
    }

    /**
     * Get schedule for a specific class
     */
    public function getClassSchedule(Request $request, Response $response, array $args): Response
    {
        $classId = (int)$args['classId'];
        $class = DataStore::getClass($classId);

        if(!$class){
            return ResponseHelper::notFound($response, 'Class');
        }

        $subject = DataStore::getSubject($class->subjectId);
        $teacher = DataStore::getTeacher($class->teacherId);
        $room = DataStore::getRoom($class->roomId);

        $schedule = [
            'classId' => $class->id,
            'classCode' => $class->code,
            'subject' => $subject ? ResponseHelper::formatModel($subject) : null,
            'teacher' => $teacher ? ResponseHelper::formatModel($teacher) : null,
            'room' => $room ? ResponseHelper::formatModel($room) : null,
            'schedule' => $class->schedule,
            'maxStudents' => $class->maxStudents,
            'enrolledStudents' => $class->enrolledStudentIds,
            'enrolledCount' => count($class->enrolledStudentIds),
            'availableSlots' => $class->getAvailableSlots(),
            'status' => $class->status,
            'term' => $class->term,
            'totalHours' => array_sum(array_map(function($slot){
                return (strtotime($slot['endTime']) - strtotime($slot['startTime'])) / 3600;
            }, $class->schedule)),
        ];

        return ResponseHelper::success($response, $schedule);
    }

    /**
     * Get teacher's schedule
     */
    public function getTeacherSchedule(Request $request, Response $response, array $args): Response
    {
        $teacherId = (int)$args['teacherId'];
        $teacher = DataStore::getTeacher($teacherId);

        if(!$teacher){
            return ResponseHelper::notFound($response, 'Teacher');
        }

        $classes = DataStore::findClassesByTeacher($teacherId);
        $schedule = [];
        $totalHours = 0;

        foreach($classes as $class){
            $subject = DataStore::getSubject($class->subjectId);
            $room = DataStore::getRoom($class->roomId);

            foreach($class->schedule as $timeSlot){
                $schedule[] = [
                    'classId' => $class->id,
                    'classCode' => $class->code,
                    'subject' => $subject ? $subject->title : 'Unknown',
                    'room' => $room ? $room->name : 'Unknown',
                    'day' => $timeSlot['day'],
                    'startTime' => $timeSlot['startTime'],
                    'endTime' => $timeSlot['endTime'],
                    'duration' => (strtotime($timeSlot['endTime']) - strtotime($timeSlot['startTime'])) / 3600,
                ];

                $totalHours += (strtotime($timeSlot['endTime']) - strtotime($timeSlot['startTime'])) / 3600;
            }
        }

        // Sort by day and time
        usort($schedule, function($a, $b){
            $dayOrder = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7];

            if($dayOrder[$a['day']] !== $dayOrder[$b['day']]){
                return $dayOrder[$a['day']] <=> $dayOrder[$b['day']];
            }

            return strcmp($a['startTime'], $b['startTime']);
        });

        return ResponseHelper::success($response, [
            'teacher' => ResponseHelper::formatModel($teacher),
            'schedule' => $schedule,
            'summary' => [
                'totalClasses' => count($classes),
                'totalHours' => round($totalHours, 2),
                'averageHoursPerDay' => round($totalHours / 5, 2), // Assuming 5 working days
                'utilizationRate' => round(($totalHours / $teacher->maxHoursPerWeek) * 100, 2),
            ],
        ]);
    }

    /**
     * Get room schedule
     */
    public function getRoomSchedule(Request $request, Response $response, array $args): Response
    {
        $roomId = (int)$args['roomId'];
        $room = DataStore::getRoom($roomId);

        if(!$room){
            return ResponseHelper::notFound($response, 'Room');
        }

        $classes = DataStore::findClassesByRoom($roomId);
        $schedule = [];
        $totalHours = 0;

        foreach($classes as $class){
            $subject = DataStore::getSubject($class->subjectId);
            $teacher = DataStore::getTeacher($class->teacherId);

            foreach($class->schedule as $timeSlot){
                $schedule[] = [
                    'classId' => $class->id,
                    'classCode' => $class->code,
                    'subject' => $subject ? $subject->title : 'Unknown',
                    'teacher' => $teacher ? $teacher->name : 'Unknown',
                    'day' => $timeSlot['day'],
                    'startTime' => $timeSlot['startTime'],
                    'endTime' => $timeSlot['endTime'],
                    'duration' => (strtotime($timeSlot['endTime']) - strtotime($timeSlot['startTime'])) / 3600,
                    'enrolledStudents' => count($class->enrolledStudentIds),
                ];

                $totalHours += (strtotime($timeSlot['endTime']) - strtotime($timeSlot['startTime'])) / 3600;
            }
        }

        // Sort by day and time
        usort($schedule, function($a, $b){
            $dayOrder = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7];

            if($dayOrder[$a['day']] !== $dayOrder[$b['day']]){
                return $dayOrder[$a['day']] <=> $dayOrder[$b['day']];
            }

            return strcmp($a['startTime'], $b['startTime']);
        });

        return ResponseHelper::success($response, [
            'room' => ResponseHelper::formatModel($room),
            'schedule' => $schedule,
            'summary' => [
                'totalClasses' => count($classes),
                'totalHours' => round($totalHours, 2),
                'utilizationRate' => round(($totalHours / 45) * 100, 2), // Assuming 45 available hours per week
                'averageClassSize' => count($classes) > 0 ? round(array_sum(array_map(fn($s) => $s['enrolledStudents'], $schedule)) / count($schedule), 1) : 0,
            ],
        ]);
    }

    /**
     * Get curriculum schedule
     */
    public function getCurriculumSchedule(Request $request, Response $response, array $args): Response
    {
        $curriculumId = (int)$args['curriculumId'];
        $curriculum = DataStore::getCurriculum($curriculumId);

        if(!$curriculum){
            return ResponseHelper::notFound($response, 'Curriculum');
        }

        $schedule = [];
        $totalHours = 0;
        $conflicts = [];

        foreach($curriculum->subjectIds as $subjectId){
            $classes = DataStore::findClassesBySubject($subjectId);
            $subject = DataStore::getSubject($subjectId);

            foreach($classes as $class){
                $teacher = DataStore::getTeacher($class->teacherId);
                $room = DataStore::getRoom($class->roomId);

                $classSchedule = [
                    'classId' => $class->id,
                    'classCode' => $class->code,
                    'subject' => $subject ? ResponseHelper::formatModel($subject) : null,
                    'teacher' => $teacher ? ResponseHelper::formatModel($teacher) : null,
                    'room' => $room ? ResponseHelper::formatModel($room) : null,
                    'schedule' => $class->schedule,
                    'enrolledStudents' => count($class->enrolledStudentIds),
                ];

                $schedule[] = $classSchedule;

                // Calculate total hours
                foreach($class->schedule as $timeSlot){
                    $totalHours += (strtotime($timeSlot['endTime']) - strtotime($timeSlot['startTime'])) / 3600;
                }
            }
        }

        // Check for schedule conflicts within the curriculum
        $scheduleConflicts = ValidationService::validateScheduleConflicts($schedule);

        return ResponseHelper::success($response, [
            'curriculum' => ResponseHelper::formatModel($curriculum),
            'schedule' => $schedule,
            'summary' => [
                'totalSubjects' => count($curriculum->subjectIds),
                'totalClasses' => count($schedule),
                'totalHours' => round($totalHours, 2),
                'conflicts' => count($scheduleConflicts),
            ],
            'conflicts' => $scheduleConflicts,
        ]);
    }

    /**
     * Validate schedule for conflicts
     */
    public function validateSchedule(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        if(!isset($data['schedules']) || !is_array($data['schedules'])){
            return ResponseHelper::validationError($response, ['Schedules array is required']);
        }

        $conflicts = ValidationService::validateScheduleConflicts($data['schedules']);
        $businessRuleViolations = ValidationService::validateBusinessRules($data);

        $allIssues = array_merge($conflicts, $businessRuleViolations);

        return ResponseHelper::success($response, [
            'valid' => empty($allIssues),
            'conflicts' => $conflicts,
            'businessRuleViolations' => $businessRuleViolations,
            'totalIssues' => count($allIssues),
        ]);
    }

    /**
     * Get available time slots
     */
    public function getAvailableTimeSlots(Request $request, Response $response): Response
    {
        $timeSlots = ScheduleGenerator::getAvailableTimeSlots();

        return ResponseHelper::success($response, $timeSlots);
    }

    /**
     * Get schedule optimization suggestions
     */
    public function getOptimizationSuggestions(Request $request, Response $response): Response
    {
        $classes = DataStore::getAllClasses();
        $suggestions = [];

        // Analyze room utilization
        $roomUtilization = [];
        foreach($classes as $class){
            $roomId = $class->roomId;
            if(!isset($roomUtilization[$roomId])){
                $roomUtilization[$roomId] = ['hours' => 0, 'classes' => 0];
            }

            foreach($class->schedule as $slot){
                $roomUtilization[$roomId]['hours'] += (strtotime($slot['endTime']) - strtotime($slot['startTime'])) / 3600;
            }
            $roomUtilization[$roomId]['classes']++;
        }

        foreach($roomUtilization as $roomId => $usage){
            $room = DataStore::getRoom($roomId);
            if($room){
                $utilizationRate = ($usage['hours'] / 45) * 100; // 45 hours per week assumption

                if($utilizationRate < 30){
                    $suggestions[] = [
                        'type' => 'underutilized_room',
                        'message' => "Room {$room->name} is underutilized ({$utilizationRate}%)",
                        'recommendation' => 'Consider consolidating classes or using this room for additional activities',
                    ];
                }
                elseif($utilizationRate > 90){
                    $suggestions[] = [
                        'type' => 'overutilized_room',
                        'message' => "Room {$room->name} is overutilized ({$utilizationRate}%)",
                        'recommendation' => 'Consider finding alternative rooms or rescheduling some classes',
                    ];
                }
            }
        }

        return ResponseHelper::success($response, [
            'suggestions' => $suggestions,
            'totalSuggestions' => count($suggestions),
        ]);
    }
}