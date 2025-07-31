<?php

namespace App\Models;

class Teacher extends BaseModel
{
    public $name;
    public $email;
    public $subjectIds;
    public $maxHoursPerWeek;
    public $preferredTimeSlots;

    public function __construct($id = null, $name = '', $email = '', $subjectIds = [], $maxHoursPerWeek = 40)
    {
        parent::__construct($id);
        $this->name = $name;
        $this->email = $email;
        $this->subjectIds = $subjectIds;
        $this->maxHoursPerWeek = $maxHoursPerWeek;
        $this->preferredTimeSlots = [];
    }

    public function validate(): array
    {
        $errors = [];

        if(empty(trim($this->name))){
            $errors[] = "Teacher name is required";
        }

        if(!empty($this->email) && !filter_var($this->email, FILTER_VALIDATE_EMAIL)){
            $errors[] = "Invalid email format";
        }

        if(!is_array($this->subjectIds)){
            $errors[] = "Subject IDs must be an array";
        }

        if(empty($this->subjectIds)){
            $errors[] = "Teacher must be able to teach at least one subject";
        }

        if(!is_numeric($this->maxHoursPerWeek) || $this->maxHoursPerWeek <= 0){
            $errors[] = "Max hours per week must be a positive number";
        }

        if($this->maxHoursPerWeek > 60){
            $errors[] = "Max hours per week cannot exceed 60";
        }

        return $errors;
    }

    public function canTeach($subjectId): bool
    {
        return in_array($subjectId, $this->subjectIds);
    }

    public function addSubject($subjectId): void
    {
        if(!$this->canTeach($subjectId)){
            $this->subjectIds[] = $subjectId;
        }
    }

    public function removeSubject($subjectId): void
    {
        $this->subjectIds = array_filter($this->subjectIds, fn($id) => $id !== $subjectId);
        $this->subjectIds = array_values($this->subjectIds); // Re-index array
    }
}