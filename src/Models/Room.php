<?php

namespace App\Models;

class Room extends BaseModel
{
    public $name;
    public $capacity;
    public $location;
    public $equipment;

    public function __construct($id = null, $name = '', $capacity = 0, $location = '', $equipment = [])
    {
        parent::__construct($id);
        $this->name = $name;
        $this->capacity = $capacity;
        $this->location = $location;
        $this->equipment = $equipment;
    }

    public function validate(): array
    {
        $errors = [];

        if(empty(trim($this->name))){
            $errors[] = "Room name is required";
        }

        if(!is_numeric($this->capacity) || $this->capacity <= 0){
            $errors[] = "Capacity must be a positive number";
        }

        if($this->capacity > 1000){
            $errors[] = "Capacity cannot exceed 1000";
        }

        if(!is_array($this->equipment)){
            $errors[] = "Equipment must be an array";
        }

        return $errors;
    }

    public function hasEquipment($equipment): bool
    {
        return in_array($equipment, $this->equipment);
    }
}