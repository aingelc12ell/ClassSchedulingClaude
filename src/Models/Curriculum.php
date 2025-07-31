<?php

namespace App\Models;

class Curriculum extends BaseModel
{
    public $name;
    public $term;
    public $yearLevel;
    public $subjectIds;
    public $totalUnits;
    public $description;
    public $prerequisites;

    public function __construct($id = null, $name = '', $term = '', $yearLevel = 1, $subjectIds = [])
    {
        parent::__construct($id);
        $this->name = $name;
        $this->term = $term;
        $this->yearLevel = $yearLevel;
        $this->subjectIds = $subjectIds;
        $this->totalUnits = 0;
        $this->description = '';
        $this->prerequisites = [];
    }

    public function validate(): array
    {
        $errors = [];

        if(empty(trim($this->name))){
            $errors[] = "Curriculum name is required";
        }

        if(empty(trim($this->term))){
            $errors[] = "Term is required";
        }

        $validTerms = ['1st Semester', '2nd Semester', 'Summer', 'Trimester 1', 'Trimester 2', 'Trimester 3'];
        if(!in_array($this->term, $validTerms)){
            $errors[] = "Invalid term. Valid terms: " . implode(', ', $validTerms);
        }

        if(!is_numeric($this->yearLevel) || $this->yearLevel < 1 || $this->yearLevel > 6){
            $errors[] = "Year level must be between 1 and 6";
        }

        if(!is_array($this->subjectIds)){
            $errors[] = "Subject IDs must be an array";
        }

        if(empty($this->subjectIds)){
            $errors[] = "Curriculum must have at least one subject";
        }

        if(!is_array($this->prerequisites)){
            $errors[] = "Prerequisites must be an array";
        }

        return $errors;
    }

    public function addSubject($subjectId): void
    {
        if(!in_array($subjectId, $this->subjectIds)){
            $this->subjectIds[] = $subjectId;
        }
    }

    public function removeSubject($subjectId): void
    {
        $this->subjectIds = array_filter($this->subjectIds, fn($id) => $id !== $subjectId);
        $this->subjectIds = array_values($this->subjectIds);
    }

    public function hasSubject($subjectId): bool
    {
        return in_array($subjectId, $this->subjectIds);
    }

    public function calculateTotalUnits($subjects): void
    {
        $this->totalUnits = 0;
        foreach($this->subjectIds as $subjectId){
            if(isset($subjects[$subjectId])){
                $this->totalUnits += $subjects[$subjectId]->units;
            }
        }
    }
}