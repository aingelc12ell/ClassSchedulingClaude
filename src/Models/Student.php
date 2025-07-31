<?php

namespace App\Models;

class Student extends BaseModel
{
    public $name;
    public $email;
    public $studentNumber;
    public $curriculumId;
    public $enrolledClassIds;
    public $yearLevel;

    public function __construct($id = null, $name = '', $email = '', $studentNumber = '', $curriculumId = null, $yearLevel = 1)
    {
        parent::__construct($id);
        $this->name = $name;
        $this->email = $email;
        $this->studentNumber = $studentNumber;
        $this->curriculumId = $curriculumId;
        $this->enrolledClassIds = [];
        $this->yearLevel = $yearLevel;
    }

    public function validate(): array
    {
        $errors = [];

        if(empty(trim($this->name))){
            $errors[] = "Student name is required";
        }

        if(!empty($this->email) && !filter_var($this->email, FILTER_VALIDATE_EMAIL)){
            $errors[] = "Invalid email format";
        }

        if(!empty($this->studentNumber) && strlen($this->studentNumber) < 6){
            $errors[] = "Student number must be at least 6 characters";
        }

        if(!is_numeric($this->yearLevel) || $this->yearLevel < 1 || $this->yearLevel > 6){
            $errors[] = "Year level must be between 1 and 6";
        }

        if(!is_array($this->enrolledClassIds)){
            $errors[] = "Enrolled class IDs must be an array";
        }

        return $errors;
    }

    public function enrollInClass($classId): void
    {
        if(!in_array($classId, $this->enrolledClassIds)){
            $this->enrolledClassIds[] = $classId;
        }
    }

    public function dropClass($classId): void
    {
        $this->enrolledClassIds = array_filter($this->enrolledClassIds, fn($id) => $id !== $classId);
        $this->enrolledClassIds = array_values($this->enrolledClassIds);
    }

    public function isEnrolledInClass($classId): bool
    {
        return in_array($classId, $this->enrolledClassIds);
    }
}