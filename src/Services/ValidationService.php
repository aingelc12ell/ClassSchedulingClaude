<?php

namespace App\Services;

class ValidationService
{
    /**
     * Validate enrollment request data
     */
    public static function validateEnrollmentRequest(array $data): array
    {
        $errors = [];

        // Required fields
        $requiredFields = ['curriculumId', 'studentCount'];
        foreach($requiredFields as $field){
            if(!isset($data[$field])){
                $errors[] = ucfirst($field) . " is required";
            }
        }

        // Validate curriculum exists
        if(isset($data['curriculumId']) && !DataStore::validateCurriculumExists($data['curriculumId'])){
            $errors[] = "Curriculum with ID {$data['curriculumId']} does not exist";
        }

        // Validate student count
        if(isset($data['studentCount'])){
            if(!is_numeric($data['studentCount']) || $data['studentCount'] <= 0){
                $errors[] = "Student count must be a positive number";
            }
            elseif($data['studentCount'] > 500){
                $errors[] = "Student count cannot exceed 500";
            }
        }

        // Validate preferences if provided
        if(isset($data['preferences'])){
            $preferenceErrors = self::validatePreferences($data['preferences']);
            $errors = array_merge($errors, $preferenceErrors);
        }

        return $errors;
    }

    /**
     * Validate scheduling preferences
     */
    public static function validatePreferences(array $preferences): array
    {
        $errors = [];

        // Validate preferred days
        if(isset($preferences['preferredDays'])){
            if(!is_array($preferences['preferredDays'])){
                $errors[] = "Preferred days must be an array";
            }
            else{
                $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                foreach($preferences['preferredDays'] as $day){
                    if(!in_array($day, $validDays)){
                        $errors[] = "Invalid day: {$day}";
                    }
                }
            }
        }

        // Validate preferred teachers
        if(isset($preferences['preferredTeachers'])){
            if(!is_array($preferences['preferredTeachers'])){
                $errors[] = "Preferred teachers must be an array";
            }
            else{
                foreach($preferences['preferredTeachers'] as $teacherId){
                    if(!DataStore::validateTeacherExists($teacherId)){
                        $errors[] = "Teacher with ID {$teacherId} does not exist";
                    }
                }
            }
        }

        // Validate preferred rooms
        if(isset($preferences['preferredRooms'])){
            if(!is_array($preferences['preferredRooms'])){
                $errors[] = "Preferred rooms must be an array";
            }
            else{
                foreach($preferences['preferredRooms'] as $roomId){
                    if(!DataStore::validateRoomExists($roomId)){
                        $errors[] = "Room with ID {$roomId} does not exist";
                    }
                }
            }
        }

        // Validate max hours per day
        if(isset($preferences['maxHoursPerDay'])){
            if(!is_numeric($preferences['maxHoursPerDay']) || $preferences['maxHoursPerDay'] <= 0){
                $errors[] = "Max hours per day must be a positive number";
            }
            elseif($preferences['maxHoursPerDay'] > 8){
                $errors[] = "Max hours per day cannot exceed 8";
            }
        }

        // Validate time preferences
        if(isset($preferences['preferredTimeSlots'])){
            if(!is_array($preferences['preferredTimeSlots'])){
                $errors[] = "Preferred time slots must be an array";
            }
            else{
                foreach($preferences['preferredTimeSlots'] as $timeSlot){
                    $timeErrors = self::validateTimeSlot($timeSlot);
                    $errors = array_merge($errors, $timeErrors);
                }
            }
        }

        return $errors;
    }

    /**
     * Validate time slot format
     */
    public static function validateTimeSlot(array $timeSlot): array
    {
        $errors = [];

        $requiredFields = ['day', 'startTime', 'endTime'];
        foreach($requiredFields as $field){
            if(!isset($timeSlot[$field])){
                $errors[] = "Time slot must include {$field}";
            }
        }

        if(isset($timeSlot['day'])){
            $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            if(!in_array($timeSlot['day'], $validDays)){
                $errors[] = "Invalid day in time slot: {$timeSlot['day']}";
            }
        }

        if(isset($timeSlot['startTime']) && !self::isValidTimeFormat($timeSlot['startTime'])){
            $errors[] = "Invalid start time format: {$timeSlot['startTime']}. Use HH:MM format";
        }

        if(isset($timeSlot['endTime']) && !self::isValidTimeFormat($timeSlot['endTime'])){
            $errors[] = "Invalid end time format: {$timeSlot['endTime']}. Use HH:MM format";
        }

        if(isset($timeSlot['startTime']) && isset($timeSlot['endTime'])){
            if(strtotime($timeSlot['startTime']) >= strtotime($timeSlot['endTime'])){
                $errors[] = "End time must be after start time";
            }
        }

        return $errors;
    }

    /**
     * Validate class assignment data
     */
    public static function validateClassAssignment(array $data): array
    {
        $errors = [];

        // Required fields
        $requiredFields = ['subjectId', 'teacherId', 'roomId', 'maxStudents'];
        foreach($requiredFields as $field){
            if(!isset($data[$field])){
                $errors[] = ucfirst($field) . " is required";
            }
        }

        // Validate references exist
        if(isset($data['subjectId']) && !DataStore::validateSubjectExists($data['subjectId'])){
            $errors[] = "Subject with ID {$data['subjectId']} does not exist";
        }

        if(isset($data['teacherId']) && !DataStore::validateTeacherExists($data['teacherId'])){
            $errors[] = "Teacher with ID {$data['teacherId']} does not exist";
        }

        if(isset($data['roomId']) && !DataStore::validateRoomExists($data['roomId'])){
            $errors[] = "Room with ID {$data['roomId']} does not exist";
        }

        // Validate teacher can teach subject
        if(isset($data['subjectId']) && isset($data['teacherId'])){
            $teacher = DataStore::getTeacher($data['teacherId']);
            if($teacher && !$teacher->canTeach($data['subjectId'])){
                $errors[] = "Teacher is not qualified to teach this subject";
            }
        }

        // Validate room capacity vs max students
        if(isset($data['roomId']) && isset($data['maxStudents'])){
            $room = DataStore::getRoom($data['roomId']);
            if($room && $data['maxStudents'] > $room->capacity){
                $errors[] = "Max students ({$data['maxStudents']}) exceeds room capacity ({$room->capacity})";
            }
        }

        return $errors;
    }

    /**
     * Validate curriculum integrity
     */
    public static function validateCurriculumIntegrity(int $curriculumId): array
    {
        $errors = [];
        $curriculum = DataStore::getCurriculum($curriculumId);

        if(!$curriculum){
            $errors[] = "Curriculum not found";
            return $errors;
        }

        // Check if all subjects exist
        foreach($curriculum->subjectIds as $subjectId){
            if(!DataStore::validateSubjectExists($subjectId)){
                $errors[] = "Subject with ID {$subjectId} in curriculum does not exist";
            }
        }

        // Calculate total hours and validate workload
        $totalHours = 0;
        $subjects = DataStore::findSubjectsByIds($curriculum->subjectIds);
        foreach($subjects as $subject){
            $totalHours += $subject->hoursPerWeek;
        }

        if($totalHours > 40){
            $errors[] = "Total weekly hours ({$totalHours}) exceeds recommended maximum (40 hours)";
        }

        if($totalHours < 12){
            $errors[] = "Total weekly hours ({$totalHours}) is below minimum requirement (12 hours)";
        }

        return $errors;
    }

    /**
     * Validate resource availability
     */
    public static function validateResourceAvailability(array $enrollmentData): array
    {
        $errors = [];
        $curriculumId = $enrollmentData['curriculumId'];
        $studentCount = $enrollmentData['studentCount'];

        $curriculum = DataStore::getCurriculum($curriculumId);
        if(!$curriculum){
            $errors[] = "Curriculum not found";
            return $errors;
        }

        // Check teacher availability for each subject
        foreach($curriculum->subjectIds as $subjectId){
            $availableTeachers = DataStore::findTeachersBySubject($subjectId);
            if(empty($availableTeachers)){
                $subject = DataStore::getSubject($subjectId);
                $subjectTitle = $subject ? $subject->title : "Subject ID {$subjectId}";
                $errors[] = "No qualified teachers available for {$subjectTitle}";
            }
        }

        // Check room availability
        $availableRooms = DataStore::findRoomsByCapacity($studentCount);
        if(empty($availableRooms)){
            $errors[] = "No rooms available with sufficient capacity for {$studentCount} students";
        }

        // Check if there are enough rooms for concurrent classes
        if(count($availableRooms) < count($curriculum->subjectIds)){
            $errors[] = "Insufficient rooms for all subjects. Need " . count($curriculum->subjectIds) . " rooms, but only " . count($availableRooms) . " available";
        }

        return $errors;
    }

    /**
     * Validate schedule conflicts
     */
    public static function validateScheduleConflicts(array $schedules): array
    {
        $conflicts = [];
        $teacherSlots = [];
        $roomSlots = [];
        $studentSlots = [];

        foreach($schedules as $schedule){
            $teacherId = $schedule['teacher']['id'];
            $roomId = $schedule['room']['id'];
            $classId = $schedule['classId'];

            foreach($schedule['schedule'] as $timeSlot){
                $slotKey = "{$timeSlot['day']}_{$timeSlot['startTime']}_{$timeSlot['endTime']}";

                // Check teacher conflicts
                if(isset($teacherSlots[$teacherId][$slotKey])){
                    $conflicts[] = [
                        'type' => 'teacher_conflict',
                        'message' => "Teacher {$schedule['teacher']['name']} has scheduling conflict",
                        'details' => [
                            'day' => $timeSlot['day'],
                            'time' => "{$timeSlot['startTime']} - {$timeSlot['endTime']}",
                            'conflicting_classes' => [$teacherSlots[$teacherId][$slotKey], $classId],
                        ],
                    ];
                }
                else{
                    $teacherSlots[$teacherId][$slotKey] = $classId;
                }

                // Check room conflicts
                if(isset($roomSlots[$roomId][$slotKey])){
                    $conflicts[] = [
                        'type' => 'room_conflict',
                        'message' => "Room {$schedule['room']['name']} has scheduling conflict",
                        'details' => [
                            'day' => $timeSlot['day'],
                            'time' => "{$timeSlot['startTime']} - {$timeSlot['endTime']}",
                            'conflicting_classes' => [$roomSlots[$roomId][$slotKey], $classId],
                        ],
                    ];
                }
                else{
                    $roomSlots[$roomId][$slotKey] = $classId;
                }
            }
        }

        return $conflicts;
    }

    /**
     * Validate business rules
     */
    public static function validateBusinessRules(array $data): array
    {
        $errors = [];

        // Maximum classes per teacher per day
        if(isset($data['teacherSchedule'])){
            $dailyClasses = [];
            foreach($data['teacherSchedule'] as $class){
                foreach($class['schedule'] as $slot){
                    $dailyClasses[$slot['day']] = ($dailyClasses[$slot['day']] ?? 0) + 1;
                }
            }

            foreach($dailyClasses as $day => $count){
                if($count > 6){
                    $errors[] = "Teacher exceeds maximum classes per day ({$count}) on {$day}";
                }
            }
        }

        // Minimum break between classes
        if(isset($data['consecutiveClasses'])){
            foreach($data['consecutiveClasses'] as $i => $class){
                if(isset($data['consecutiveClasses'][$i + 1])){
                    $nextClass = $data['consecutiveClasses'][$i + 1];
                    $gap = strtotime($nextClass['startTime']) - strtotime($class['endTime']);
                    if($gap < 900){ // 15 minutes
                        $errors[] = "Insufficient break time between consecutive classes";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Check if time format is valid (HH:MM)
     */
    private static function isValidTimeFormat(string $time): bool
    {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time) === 1;
    }

    /**
     * Validate email format
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Sanitize input data
     */
    public static function sanitizeInput(array $data): array
    {
        $sanitized = [];

        foreach($data as $key => $value){
            if(is_string($value)){
                $sanitized[$key] = trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
            }
            elseif(is_array($value)){
                $sanitized[$key] = self::sanitizeInput($value);
            }
            else{
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Validate pagination parameters
     */
    public static function validatePagination(array $params): array
    {
        $errors = [];

        if(isset($params['page'])){
            if(!is_numeric($params['page']) || $params['page'] < 1){
                $errors[] = "Page must be a positive integer";
            }
        }

        if(isset($params['limit'])){
            if(!is_numeric($params['limit']) || $params['limit'] < 1 || $params['limit'] > 100){
                $errors[] = "Limit must be between 1 and 100";
            }
        }

        return $errors;
    }
}
            