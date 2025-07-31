<?php

namespace App\Models;

class TimeSlot extends BaseModel
{
    public $day;
    public $startTime;
    public $endTime;
    public $duration;

    private const VALID_DAYS = [
        'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
    ];

    public function __construct($id = null, $day = '', $startTime = '', $endTime = '')
    {
        parent::__construct($id);
        $this->day = $day;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->duration = $this->calculateDuration();
    }

    public function validate(): array
    {
        $errors = [];

        if(empty($this->day)){
            $errors[] = "Day is required";
        }
        elseif(!in_array($this->day, self::VALID_DAYS)){
            $errors[] = "Invalid day. Valid days: " . implode(', ', self::VALID_DAYS);
        }

        if(empty($this->startTime)){
            $errors[] = "Start time is required";
        }
        elseif(!$this->isValidTimeFormat($this->startTime)){
            $errors[] = "Invalid start time format. Use HH:MM (24-hour format)";
        }

        if(empty($this->endTime)){
            $errors[] = "End time is required";
        }
        elseif(!$this->isValidTimeFormat($this->endTime)){
            $errors[] = "Invalid end time format. Use HH:MM (24-hour format)";
        }

        if(!empty($this->startTime) && !empty($this->endTime)){
            if(strtotime($this->startTime) >= strtotime($this->endTime)){
                $errors[] = "End time must be after start time";
            }

            $duration = $this->calculateDuration();
            if($duration > 4){
                $errors[] = "Duration cannot exceed 4 hours";
            }

            if($duration < 0.5){
                $errors[] = "Duration must be at least 30 minutes";
            }
        }

        return $errors;
    }

    public function conflicts(TimeSlot $other): bool
    {
        if($this->day !== $other->day){
            return false;
        }

        $thisStart = strtotime($this->startTime);
        $thisEnd = strtotime($this->endTime);
        $otherStart = strtotime($other->startTime);
        $otherEnd = strtotime($other->endTime);

        return !($thisEnd <= $otherStart || $thisStart >= $otherEnd);
    }

    public function isWithinBusinessHours(): bool
    {
        $start = strtotime($this->startTime);
        $end = strtotime($this->endTime);
        $businessStart = strtotime('07:00');
        $businessEnd = strtotime('22:00');

        return $start >= $businessStart && $end <= $businessEnd;
    }

    public function calculateDuration(): float
    {
        if(empty($this->startTime) || empty($this->endTime)){
            return 0;
        }

        $start = strtotime($this->startTime);
        $end = strtotime($this->endTime);

        return ($end - $start) / 3600; // Convert seconds to hours
    }

    public function getDurationInMinutes(): int
    {
        return (int)($this->duration * 60);
    }

    public function toString(): string
    {
        return "{$this->day} {$this->startTime}-{$this->endTime}";
    }

    private function isValidTimeFormat($time): bool
    {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time);
    }

    public static function createFromArray(array $data): self
    {
        return new self(
            $data['id'] ?? null,
            $data['day'] ?? '',
            $data['startTime'] ?? '',
            $data['endTime'] ?? ''
        );
    }

    public static function getValidDays(): array
    {
        return self::VALID_DAYS;
    }

    public static function getWorkingDays(): array
    {
        return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    }
}