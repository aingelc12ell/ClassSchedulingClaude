<?php

namespace App\Models;

class ClassEntity extends BaseModel
{
    public $code;
    public $subjectId;
    public $teacherId;
    public $roomId;
    public $maxStudents;
    public $enrolledStudentIds;
    public $schedule;
    public $term;
    public $yearLevel;
    public $status;

    public function __construct($id = null, $code = '', $subjectId = null, $teacherId = null, $roomId = null, $maxStudents = 0)
    {
        parent::__construct($id);
        $this->code = $code;
        $this->subjectId = $subjectId;
        $this->teacherId = $teacherId;
        $this->roomId = $roomId;
        $this->maxStudents = $maxStudents;
        $this->enrolledStudentIds = [];
        $this->schedule = [];
        $this->term = '';
        $this->yearLevel = 1;
        $this->status = 'active'; // active, cancelled, completed
    }

    public function validate(): array
    {
        $errors = [];

        if(empty(trim($this->code))){
            $errors[] = "Class code is required";
        }

        if(empty($this->subjectId)){
            $errors[] = "Subject ID is required";
        }

        if(empty($this->teacherId)){
            $errors[] = "Teacher ID is required";
        }

        if(empty($this->roomId)){
            $errors[] = "Room ID is required";
        }

        if(!is_numeric($this->maxStudents) || $this->maxStudents <= 0){
            $errors[] = "Max students must be a positive number";
        }

        if($this->maxStudents > 200){
            $errors[] = "Max students cannot exceed 200";
        }

        if(!is_array($this->enrolledStudentIds)){
            $errors[] = "Enrolled student IDs must be an array";
        }

        if(!is_array($this->schedule)){
            $errors[] = "Schedule must be an array";
        }

        $validStatuses = ['active', 'cancelled', 'completed'];
        if(!in_array($this->status, $validStatuses)){
            $errors[] = "Invalid status. Valid statuses: " . implode(', ', $validStatuses);
        }

        return $errors;
    }

    public function addStudent($studentId): bool
    {
        if(count($this->enrolledStudentIds) < $this->maxStudents && !$this->hasStudent($studentId)){
            $this->enrolledStudentIds[] = $studentId;
            return true;
        }
        return false;
    }

    public function removeStudent($studentId): bool
    {
        $index = array_search($studentId, $this->enrolledStudentIds);
        if($index !== false){
            unset($this->enrolledStudentIds[$index]);
            $this->enrolledStudentIds = array_values($this->enrolledStudentIds);
            return true;
        }
        return false;
    }

    public function hasStudent($studentId): bool
    {
        return in_array($studentId, $this->enrolledStudentIds);
    }

    public function isFull(): bool
    {
        return count($this->enrolledStudentIds) >= $this->maxStudents;
    }

    public function getAvailableSlots(): int
    {
        return $this->maxStudents - count($this->enrolledStudentIds);
    }

    public function addScheduleSlot($day, $startTime, $endTime): void
    {
        $this->schedule[] = [
            'day' => $day,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ];
    }

    public function clearSchedule(): void
    {
        $this->schedule = [];
    }

    public static function generateClassCode($subjectCode, $section = 'A'): string
    {
        return strtoupper($subjectCode) . '-' . strtoupper($section) . '-' . date('Y');
    }
}