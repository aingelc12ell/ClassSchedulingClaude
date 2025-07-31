<?php

namespace App\Models;

abstract class BaseModel
{
    public $id;

    public function __construct($id = null)
    {
        $this->id = $id;
    }

    /**
     * Validate the model data
     * @return array Array of validation errors
     */
    abstract public function validate(): array;

    /**
     * Convert model to array
     * @return array
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * Create model from array data
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $instance = new static();
        foreach($data as $key => $value){
            if(property_exists($instance, $key)){
                $instance->$key = $value;
            }
        }
        return $instance;
    }
}