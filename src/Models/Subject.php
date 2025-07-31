<?php

namespace App\Models;

class Subject extends BaseModel
{
    public $title;
    public $units;
    public $hoursPerWeek;

    public function __construct($id = null, $title = '', $units = 0, $hoursPerWeek = 0)
    {
        parent::__construct($id);
        $this->title = $title;
        $this->units = $units;
        $this->hoursPerWeek = $hoursPerWeek;
    }

    public function validate(): array
    {
        $errors = [];

        if(empty(trim($this->title))){
            $errors[] = "Title is required";
        }

        if(!is_numeric($this->units) || $this->units <= 0){
            $errors[] = "Units must be a positive number";
        }

        if(!is_numeric($this->hoursPerWeek) || $this->hoursPerWeek <= 0){
            $errors[] = "Hours per week must be a positive number";
        }

        if($this->hoursPerWeek > 40){
            $errors[] = "Hours per week cannot exceed 40";
        }

        return $errors;
    }
}