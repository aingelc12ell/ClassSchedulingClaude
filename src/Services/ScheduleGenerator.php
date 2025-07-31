<?php

namespace App\Services;

use App\Models\TimeSlot;
use App\Models\ClassEntity;

class ScheduleGenerator
{
    private const WORKING_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    private const TIME_SLOTS = [
        '07:00', '08:00', '09:00', '10:00', '11:00', '12:00',
        '13:00', '14:00', '15:00', '16:00', '17:00', '18:00',
    ];
    private const BREAK_TIMES = [
        '10:00-10:15', // Morning break
        '12:00-13:00', // Lunch break
        '15:00-15:15',  // Afternoon break
    ];

    public static function generateSchedule(array $enrollmentData): array
    {
        $result = [
            'schedules' => [],
            'conflicts' => [],
            'warnings' => [],
        ];

        // Validate input data
        $validation = self::validateEnrollmentData($enrollmentData);
        if(!$validation['valid']){
            $result['conflicts'] = $validation['errors'];
            return $result;
        }

        $curriculumId = $enrollmentData['curriculumId'];
        $studentCount = $enrollmentData['studentCount'];
        $preferences = $enrollmentData['preferences'] ?? [];

        $curriculum = DataStore::getCurriculum($curriculumId);
        $subjects = self::getSubjectsForCurriculum($curriculum);

        // Initialize availability tracking
        $availability = self::initializeAvailability();

        // Generate schedules for each subject
        foreach($subjects as $subject){
            $scheduleResult = self::scheduleSubject(
                $subject,
                $studentCount,
                $availability,
                $preferences
            );

            if($scheduleResult['success']){
                $result['schedules'][] = $scheduleResult['schedule'];
                self::markTimeSlots($availability, $scheduleResult['schedule']);
            }
            else{
                $result['conflicts'] = array_merge($result['conflicts'], $scheduleResult['conflicts']);
            }
        }

        // Check for any remaining conflicts
        $result['conflicts'] = array_merge($result['conflicts'], self::detectConflicts($result['schedules']));

        // Generate optimization suggestions
        $result['warnings'] = self::generateOptimizationSuggestions($result['schedules']);

        return $result;
    }

    private static function validateEnrollmentData(array $data): array
    {
        $errors = [];

        if(!isset($data['curriculumId']) || !DataStore::validateCurriculumExists($data['curriculumId'])){
            $errors[] = "Invalid or missing curriculum ID";
        }

        if(!isset($data['studentCount']) || $data['studentCount'] <= 0){
            $errors[] = "Student count must be a positive number";
        }

        if(isset($data['preferences']) && !is_array($data['preferences'])){
            $errors[] = "Preferences must be an array";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    private static function getSubjectsForCurriculum($curriculum): array
    {
        $subjects = [];
        foreach($curriculum->subjectIds as $subjectId){
            $subject = DataStore::getSubject($subjectId);
            if($subject){
                $subjects[] = $subject;
            }
        }
        return $subjects;
    }

    private static function initializeAvailability(): array
    {
        $availability = [];

        foreach(self::WORKING_DAYS as $day){
            $availability[$day] = [];

            for($i = 0; $i < count(self::TIME_SLOTS) - 1; $i++){
                $startTime = self::TIME_SLOTS[$i];
                $endTime = self::TIME_SLOTS[$i + 1];

                // Skip break times
                if(self::isBreakTime($startTime, $endTime)){
                    continue;
                }

                $availability[$day][] = [
                    'startTime' => $startTime,
                    'endTime' => $endTime,
                    'occupiedTeachers' => [],
                    'occupiedRooms' => [],
                    'priority' => self::calculateTimePriority($day, $startTime),
                ];
            }
        }

        return $availability;
    }

    private static function scheduleSubject($subject, $studentCount, &$availability, $preferences): array
    {
        $result = [
            'success' => false,
            'schedule' => null,
            'conflicts' => [],
        ];

        // Find available teacher
        $teacher = self::findAvailableTeacher($subject->id, $preferences);
        if(!$teacher){
            $result['conflicts'][] = "No available teacher for subject: {$subject->title}";
            return $result;
        }

        // Find available room
        $room = self::findAvailableRoom($studentCount, $preferences);
        if(!$room){
            $result['conflicts'][] = "No available room with capacity for {$studentCount} students for subject: {$subject->title}";
            return $result;
        }

        // Generate time slots
        $timeSlots = self::allocateTimeSlots($subject, $availability, $teacher->id, $room->id, $preferences);
        if(empty($timeSlots)){
            $result['conflicts'][] = "Cannot allocate sufficient time slots for subject: {$subject->title}";
            return $result;
        }

        // Create class entity
        $classId = DataStore::getNextId();
        $class = new ClassEntity($classId, '', $subject->id, $teacher->id, $room->id, min($room->capacity, $studentCount + 10));
        $class->code = ClassEntity::generateClassCode($subject->title);
        $class->schedule = $timeSlots;
        $class->term = $preferences['term'] ?? 'Current Term';

        DataStore::addClass($class);

        $result['success'] = true;
        $result['schedule'] = [
            'classId' => $classId,
            'classCode' => $class->code,
            'subject' => [
                'id' => $subject->id,
                'title' => $subject->title,
                'units' => $subject->units,
                'hoursPerWeek' => $subject->hoursPerWeek,
            ],
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'email' => $teacher->email,
            ],
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
                'capacity' => $room->capacity,
                'location' => $room->location,
            ],
            'schedule' => $timeSlots,
            'maxStudents' => $class->maxStudents,
            'totalHours' => array_sum(array_map(function($slot){
                return (strtotime($slot['endTime']) - strtotime($slot['startTime'])) / 3600;
            }, $timeSlots)),
        ];

        return $result;
    }

    private static function findAvailableTeacher($subjectId, $preferences): ?\App\Models\Teacher
    {
        $availableTeachers = DataStore::findTeachersBySubject($subjectId);

        if(empty($availableTeachers)){
            return null;
        }

        // Apply preferences if specified
        if(isset($preferences['preferredTeachers']) && !empty($preferences['preferredTeachers'])){
            $preferredTeachers = array_filter($availableTeachers, function($teacher) use ($preferences){
                return in_array($teacher->id, $preferences['preferredTeachers']);
            });

            if(!empty($preferredTeachers)){
                return reset($preferredTeachers);
            }
        }

        // Return teacher with least assigned hours (simple load balancing)
        usort($availableTeachers, function($a, $b){
            $aClasses = count(DataStore::findClassesByTeacher($a->id));
            $bClasses = count(DataStore::findClassesByTeacher($b->id));
            return $aClasses <=> $bClasses;
        });

        return $availableTeachers[0];
    }

    private static function findAvailableRoom($capacity, $preferences): ?\App\Models\Room
    {
        $availableRooms = DataStore::findRoomsByCapacity($capacity);

        if(empty($availableRooms)){
            return null;
        }

        // Apply preferences if specified
        if(isset($preferences['preferredRooms']) && !empty($preferences['preferredRooms'])){
            $preferredRooms = array_filter($availableRooms, function($room) use ($preferences){
                return in_array($room->id, $preferences['preferredRooms']);
            });

            if(!empty($preferredRooms)){
                return reset($preferredRooms);
            }
        }

        // Sort by capacity (prefer smaller rooms that still fit)
        usort($availableRooms, function($a, $b){
            return $a->capacity <=> $b->capacity;
        });

        return $availableRooms[0];
    }

    private static function allocateTimeSlots($subject, &$availability, $teacherId, $roomId, $preferences): array
    {
        $hoursNeeded = $subject->hoursPerWeek;
        $allocatedSlots = [];
        $maxHoursPerDay = $preferences['maxHoursPerDay'] ?? 3;
        $preferredDays = $preferences['preferredDays'] ?? self::WORKING_DAYS;

        // Sort days by preference
        $sortedDays = array_intersect($preferredDays, self::WORKING_DAYS);
        if(empty($sortedDays)){
            $sortedDays = self::WORKING_DAYS;
        }

        $remainingHours = $hoursNeeded;

        foreach($sortedDays as $day){
            if($remainingHours <= 0) break;

            $hoursForDay = min($maxHoursPerDay, $remainingHours);
            $daySlots = self::findConsecutiveSlots(
                $availability[$day],
                $hoursForDay,
                $teacherId,
                $roomId
            );

            if(!empty($daySlots)){
                $allocatedSlots = array_merge($allocatedSlots, $daySlots);
                $remainingHours -= count($daySlots);
            }
        }

        // If we couldn't allocate all hours, try to distribute across multiple days
        if($remainingHours > 0){
            $allocatedSlots = array_merge(
                $allocatedSlots,
                self::distributeRemainingHours($availability, $remainingHours, $teacherId, $roomId)
            );
        }

        return $allocatedSlots;
    }

    private static function findConsecutiveSlots($daySlots, $hoursNeeded, $teacherId, $roomId): array
    {
        $allocated = [];

        // Sort slots by priority
        usort($daySlots, function($a, $b){
            return $b['priority'] <=> $a['priority'];
        });

        for($i = 0; $i <= count($daySlots) - $hoursNeeded; $i++){
            $canAllocate = true;
            $consecutiveSlots = [];

            // Check if we can allocate consecutive slots
            for($j = $i; $j < $i + $hoursNeeded; $j++){
                if(in_array($teacherId, $daySlots[$j]['occupiedTeachers']) ||
                    in_array($roomId, $daySlots[$j]['occupiedRooms'])){
                    $canAllocate = false;
                    break;
                }
                $consecutiveSlots[] = $daySlots[$j];
            }

            if($canAllocate && self::areConsecutive($consecutiveSlots)){
                foreach($consecutiveSlots as $slot){
                    $allocated[] = [
                        'day' => $daySlots === reset($daySlots) ? array_search($daySlots, self::initializeAvailability()) : 'Unknown',
                        'startTime' => $slot['startTime'],
                        'endTime' => $slot['endTime'],
                    ];
                }
                return $allocated;
            }
        }

        return [];
    }

    private static function distributeRemainingHours($availability, $hoursNeeded, $teacherId, $roomId): array
    {
        $allocated = [];

        foreach(self::WORKING_DAYS as $day){
            if($hoursNeeded <= 0) break;

            foreach($availability[$day] as $slot){
                if($hoursNeeded <= 0) break;

                if(!in_array($teacherId, $slot['occupiedTeachers']) &&
                    !in_array($roomId, $slot['occupiedRooms'])){

                    $allocated[] = [
                        'day' => $day,
                        'startTime' => $slot['startTime'],
                        'endTime' => $slot['endTime'],
                    ];
                    $hoursNeeded--;
                }
            }
        }

        return $allocated;
    }

    private static function markTimeSlots(&$availability, $schedule): void
    {
        foreach($schedule as $slot){
            $day = $slot['day'];
            $startTime = $slot['startTime'];

            if(isset($availability[$day])){
                foreach($availability[$day] as &$availableSlot){
                    if($availableSlot['startTime'] === $startTime){
                        $availableSlot['occupiedTeachers'][] = $schedule['teacher']['id'] ?? 0;
                        $availableSlot['occupiedRooms'][] = $schedule['room']['id'] ?? 0;
                        break;
                    }
                }
            }
        }
    }

    private static function detectConflicts($schedules): array
    {
        $conflicts = [];
        $teacherSchedules = [];
        $roomSchedules = [];

        foreach($schedules as $schedule){
            $teacherId = $schedule['teacher']['id'];
            $roomId = $schedule['room']['id'];

            foreach($schedule['schedule'] as $timeSlot){
                $slotKey = $timeSlot['day'] . '_' . $timeSlot['startTime'] . '_' . $timeSlot['endTime'];

                // Check teacher conflicts
                if(isset($teacherSchedules[$teacherId][$slotKey])){
                    $conflicts[] = "Teacher {$schedule['teacher']['name']} has conflicting schedules on {$timeSlot['day']} at {$timeSlot['startTime']}";
                }
                else{
                    $teacherSchedules[$teacherId][$slotKey] = $schedule['classCode'];
                }

                // Check room conflicts
                if(isset($roomSchedules[$roomId][$slotKey])){
                    $conflicts[] = "Room {$schedule['room']['name']} has conflicting schedules on {$timeSlot['day']} at {$timeSlot['startTime']}";
                }
                else{
                    $roomSchedules[$roomId][$slotKey] = $schedule['classCode'];
                }
            }
        }

        return $conflicts;
    }

    private static function generateOptimizationSuggestions($schedules): array
    {
        $warnings = [];

        // Check for fragmented schedules
        foreach($schedules as $schedule){
            $dailyHours = [];
            foreach($schedule['schedule'] as $slot){
                $dailyHours[$slot['day']] = ($dailyHours[$slot['day']] ?? 0) + 1;
            }

            if(count($dailyHours) > 4){
                $warnings[] = "Class {$schedule['classCode']} is spread across too many days, consider consolidation";
            }

            foreach($dailyHours as $day => $hours){
                if($hours > 4){
                    $warnings[] = "Class {$schedule['classCode']} has {$hours} hours on {$day}, consider redistributing";
                }
            }
        }

        return $warnings;
    }

    private static function isBreakTime($startTime, $endTime): bool
    {
        foreach(self::BREAK_TIMES as $breakTime){
            [$breakStart, $breakEnd] = explode('-', $breakTime);
            if($startTime >= $breakStart && $endTime <= $breakEnd){
                return true;
            }
        }
        return false;
    }

    private static function calculateTimePriority($day, $startTime): int
    {
        $priority = 5; // Base priority

        // Prefer morning slots
        if($startTime >= '08:00' && $startTime <= '11:00'){
            $priority += 3;
        }

        // Prefer mid-week days
        if(in_array($day, ['Tuesday', 'Wednesday', 'Thursday'])){
            $priority += 2;
        }

        // Avoid very early or very late slots
        if($startTime < '07:30' || $startTime > '17:00'){
            $priority -= 2;
        }

        return $priority;
    }

    private static function areConsecutive($slots): bool
    {
        if(count($slots) <= 1) return true;

        for($i = 1; $i < count($slots); $i++){
            if($slots[$i - 1]['endTime'] !== $slots[$i]['startTime']){
                return false;
            }
        }

        return true;
    }

    public static function getAvailableTimeSlots(): array
    {
        return [
            'workingDays' => self::WORKING_DAYS,
            'timeSlots' => self::TIME_SLOTS,
            'breakTimes' => self::BREAK_TIMES,
        ];
    }
}