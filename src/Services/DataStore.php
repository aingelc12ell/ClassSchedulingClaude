<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\Room;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\Curriculum;
use App\Models\ClassEntity;

class DataStore
{
    private static $subjects = [];
    private static $rooms = [];
    private static $teachers = [];
    private static $students = [];
    private static $curriculums = [];
    private static $classes = [];
    private static $nextId = 1;

    public static function getNextId(): int
    {
        return self::$nextId++;
    }

    public static function resetData(): void
    {
        self::$subjects = [];
        self::$rooms = [];
        self::$teachers = [];
        self::$students = [];
        self::$curriculums = [];
        self::$classes = [];
        self::$nextId = 1;
    }

    // Subject CRUD operations
    public static function addSubject(Subject $subject): void
    {
        self::$subjects[$subject->id] = $subject;
    }

    public static function getSubject(int $id): ?Subject
    {
        return self::$subjects[$id] ?? null;
    }

    public static function getAllSubjects(): array
    {
        return array_values(self::$subjects);
    }

    public static function updateSubject(int $id, Subject $subject): bool
    {
        if(isset(self::$subjects[$id])){
            self::$subjects[$id] = $subject;
            return true;
        }
        return false;
    }

    public static function deleteSubject(int $id): bool
    {
        if(isset(self::$subjects[$id])){
            unset(self::$subjects[$id]);
            return true;
        }
        return false;
    }

    public static function findSubjectsByIds(array $ids): array
    {
        return array_filter(self::$subjects, fn($subject) => in_array($subject->id, $ids));
    }

    // Room CRUD operations
    public static function addRoom(Room $room): void
    {
        self::$rooms[$room->id] = $room;
    }

    public static function getRoom(int $id): ?Room
    {
        return self::$rooms[$id] ?? null;
    }

    public static function getAllRooms(): array
    {
        return array_values(self::$rooms);
    }

    public static function updateRoom(int $id, Room $room): bool
    {
        if(isset(self::$rooms[$id])){
            self::$rooms[$id] = $room;
            return true;
        }
        return false;
    }

    public static function deleteRoom(int $id): bool
    {
        if(isset(self::$rooms[$id])){
            unset(self::$rooms[$id]);
            return true;
        }
        return false;
    }

    public static function findRoomsByCapacity(int $minCapacity): array
    {
        return array_filter(self::$rooms, fn($room) => $room->capacity >= $minCapacity);
    }

    public static function findRoomsByEquipment(string $equipment): array
    {
        return array_filter(self::$rooms, fn($room) => $room->hasEquipment($equipment));
    }

    // Teacher CRUD operations
    public static function addTeacher(Teacher $teacher): void
    {
        self::$teachers[$teacher->id] = $teacher;
    }

    public static function getTeacher(int $id): ?Teacher
    {
        return self::$teachers[$id] ?? null;
    }

    public static function getAllTeachers(): array
    {
        return array_values(self::$teachers);
    }

    public static function updateTeacher(int $id, Teacher $teacher): bool
    {
        if(isset(self::$teachers[$id])){
            self::$teachers[$id] = $teacher;
            return true;
        }
        return false;
    }

    public static function deleteTeacher(int $id): bool
    {
        if(isset(self::$teachers[$id])){
            unset(self::$teachers[$id]);
            return true;
        }
        return false;
    }

    public static function findTeachersBySubject(int $subjectId): array
    {
        return array_filter(self::$teachers, fn($teacher) => $teacher->canTeach($subjectId));
    }

    // Student CRUD operations
    public static function addStudent(Student $student): void
    {
        self::$students[$student->id] = $student;
    }

    public static function getStudent(int $id): ?Student
    {
        return self::$students[$id] ?? null;
    }

    public static function getAllStudents(): array
    {
        return array_values(self::$students);
    }

    public static function updateStudent(int $id, Student $student): bool
    {
        if(isset(self::$students[$id])){
            self::$students[$id] = $student;
            return true;
        }
        return false;
    }

    public static function deleteStudent(int $id): bool
    {
        if(isset(self::$students[$id])){
            unset(self::$students[$id]);
            return true;
        }
        return false;
    }

    public static function findStudentsByCurriculum(int $curriculumId): array
    {
        return array_filter(self::$students, fn($student) => $student->curriculumId === $curriculumId);
    }

    public static function findStudentsByYearLevel(int $yearLevel): array
    {
        return array_filter(self::$students, fn($student) => $student->yearLevel === $yearLevel);
    }

    // Curriculum CRUD operations
    public static function addCurriculum(Curriculum $curriculum): void
    {
        self::$curriculums[$curriculum->id] = $curriculum;
    }

    public static function getCurriculum(int $id): ?Curriculum
    {
        return self::$curriculums[$id] ?? null;
    }

    public static function getAllCurriculums(): array
    {
        return array_values(self::$curriculums);
    }

    public static function updateCurriculum(int $id, Curriculum $curriculum): bool
    {
        if(isset(self::$curriculums[$id])){
            self::$curriculums[$id] = $curriculum;
            return true;
        }
        return false;
    }

    public static function deleteCurriculum(int $id): bool
    {
        if(isset(self::$curriculums[$id])){
            unset(self::$curriculums[$id]);
            return true;
        }
        return false;
    }

    public static function findCurriculumsByTerm(string $term): array
    {
        return array_filter(self::$curriculums, fn($curriculum) => $curriculum->term === $term);
    }

    public static function findCurriculumsByYearLevel(int $yearLevel): array
    {
        return array_filter(self::$curriculums, fn($curriculum) => $curriculum->yearLevel === $yearLevel);
    }

    // Class CRUD operations
    public static function addClass(ClassEntity $class): void
    {
        self::$classes[$class->id] = $class;
    }

    public static function getClass(int $id): ?ClassEntity
    {
        return self::$classes[$id] ?? null;
    }

    public static function getAllClasses(): array
    {
        return array_values(self::$classes);
    }

    public static function updateClass(int $id, ClassEntity $class): bool
    {
        if(isset(self::$classes[$id])){
            self::$classes[$id] = $class;
            return true;
        }
        return false;
    }

    public static function deleteClass(int $id): bool
    {
        if(isset(self::$classes[$id])){
            unset(self::$classes[$id]);
            return true;
        }
        return false;
    }

    public static function findClassesBySubject(int $subjectId): array
    {
        return array_filter(self::$classes, fn($class) => $class->subjectId === $subjectId);
    }

    public static function findClassesByTeacher(int $teacherId): array
    {
        return array_filter(self::$classes, fn($class) => $class->teacherId === $teacherId);
    }

    public static function findClassesByRoom(int $roomId): array
    {
        return array_filter(self::$classes, fn($class) => $class->roomId === $roomId);
    }

    public static function findClassesByTerm(string $term): array
    {
        return array_filter(self::$classes, fn($class) => $class->term === $term);
    }

    // Data validation methods
    public static function validateSubjectExists(int $subjectId): bool
    {
        return isset(self::$subjects[$subjectId]);
    }

    public static function validateRoomExists(int $roomId): bool
    {
        return isset(self::$rooms[$roomId]);
    }

    public static function validateTeacherExists(int $teacherId): bool
    {
        return isset(self::$teachers[$teacherId]);
    }

    public static function validateStudentExists(int $studentId): bool
    {
        return isset(self::$students[$studentId]);
    }

    public static function validateCurriculumExists(int $curriculumId): bool
    {
        return isset(self::$curriculums[$curriculumId]);
    }

    public static function validateClassExists(int $classId): bool
    {
        return isset(self::$classes[$classId]);
    }

    // Statistics and reporting
    public static function getStats(): array
    {
        return [
            'subjects' => count(self::$subjects),
            'rooms' => count(self::$rooms),
            'teachers' => count(self::$teachers),
            'students' => count(self::$students),
            'curriculums' => count(self::$curriculums),
            'classes' => count(self::$classes),
        ];
    }

}
