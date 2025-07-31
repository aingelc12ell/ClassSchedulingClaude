<?php

use App\Controllers\SubjectController;
use App\Controllers\RoomController;
use App\Controllers\TeacherController;
use App\Controllers\StudentController;
use App\Controllers\CurriculumController;
use App\Controllers\ClassController;
use App\Controllers\ScheduleController;
use App\Helpers\ResponseHelper;

// Root endpoint - API information
$app->get('/', function($request, $response){
    return ResponseHelper::apiInfo($response);
});

// Subject routes
$app->group('/subjects', function($group){
    $group->get('', [SubjectController::class, 'index']);
    $group->get('/{id:[0-9]+}', [SubjectController::class, 'show']);
    $group->post('', [SubjectController::class, 'create']);
    $group->put('/{id:[0-9]+}', [SubjectController::class, 'update']);
    $group->delete('/{id:[0-9]+}', [SubjectController::class, 'delete']);
    $group->get('/search', [SubjectController::class, 'search']);
    $group->get('/stats', [SubjectController::class, 'stats']);
    $group->get('/curriculum/{curriculumId:[0-9]+}', [SubjectController::class, 'getByCurriculum']);
});

// Room routes
$app->group('/rooms', function($group){
    $group->get('', [RoomController::class, 'index']);
    $group->get('/{id:[0-9]+}', [RoomController::class, 'show']);
    $group->post('', [RoomController::class, 'create']);
    $group->put('/{id:[0-9]+}', [RoomController::class, 'update']);
    $group->delete('/{id:[0-9]+}', [RoomController::class, 'delete']);
    $group->get('/search', [RoomController::class, 'search']);
    $group->get('/stats', [RoomController::class, 'stats']);
    $group->get('/capacity/{minCapacity:[0-9]+}', [RoomController::class, 'getByCapacity']);
});

// Teacher routes
$app->group('/teachers', function($group){
    $group->get('', [TeacherController::class, 'index']);
    $group->get('/{id:[0-9]+}', [TeacherController::class, 'show']);
    $group->post('', [TeacherController::class, 'create']);
    $group->put('/{id:[0-9]+}', [TeacherController::class, 'update']);
    $group->delete('/{id:[0-9]+}', [TeacherController::class, 'delete']);
    $group->get('/search', [TeacherController::class, 'search']);
    $group->get('/stats', [TeacherController::class, 'stats']);
    $group->get('/subject/{subjectId:[0-9]+}', [TeacherController::class, 'getBySubject']);
    $group->post('/{id:[0-9]+}/subjects', [TeacherController::class, 'addSubjects']);
    $group->delete('/{id:[0-9]+}/subjects/{subjectId:[0-9]+}', [TeacherController::class, 'removeSubject']);
});

// Student routes
$app->group('/students', function($group){
    $group->get('', [StudentController::class, 'index']);
    $group->get('/{id:[0-9]+}', [StudentController::class, 'show']);
    $group->post('', [StudentController::class, 'create']);
    $group->put('/{id:[0-9]+}', [StudentController::class, 'update']);
    $group->delete('/{id:[0-9]+}', [StudentController::class, 'delete']);
    $group->get('/search', [StudentController::class, 'search']);
    $group->get('/stats', [StudentController::class, 'stats']);
    $group->get('/curriculum/{curriculumId:[0-9]+}', [StudentController::class, 'getByCurriculum']);
    $group->post('/{id:[0-9]+}/enroll', [StudentController::class, 'enrollInClass']);
    $group->delete('/{id:[0-9]+}/classes/{classId:[0-9]+}', [StudentController::class, 'dropClass']);
});

// Curriculum routes
$app->group('/curriculums', function($group){
    $group->get('', [CurriculumController::class, 'index']);
    $group->get('/{id:[0-9]+}', [CurriculumController::class, 'show']);
    $group->post('', [CurriculumController::class, 'create']);
    $group->put('/{id:[0-9]+}', [CurriculumController::class, 'update']);
    $group->delete('/{id:[0-9]+}', [CurriculumController::class, 'delete']);
    $group->get('/search', [CurriculumController::class, 'search']);
    $group->get('/stats', [CurriculumController::class, 'stats']);
    $group->get('/term/{term}', [CurriculumController::class, 'getByTerm']);
    $group->post('/{id:[0-9]+}/subjects', [CurriculumController::class, 'addSubjects']);
    $group->delete('/{id:[0-9]+}/subjects/{subjectId:[0-9]+}', [CurriculumController::class, 'removeSubject']);
    $group->get('/{id:[0-9]+}/validate', [CurriculumController::class, 'validate']);
});

// Class routes
$app->group('/classes', function($group){
    $group->get('', [ClassController::class, 'index']);
    $group->get('/{id:[0-9]+}', [ClassController::class, 'show']);
    $group->post('', [ClassController::class, 'create']);
    $group->put('/{id:[0-9]+}', [ClassController::class, 'update']);
    $group->delete('/{id:[0-9]+}', [ClassController::class, 'delete']);
    $group->get('/search', [ClassController::class, 'search']);
    $group->get('/stats', [ClassController::class, 'stats']);
    $group->get('/subject/{subjectId:[0-9]+}', [ClassController::class, 'getBySubject']);
    $group->get('/teacher/{teacherId:[0-9]+}', [ClassController::class, 'getByTeacher']);
    $group->get('/room/{roomId:[0-9]+}', [ClassController::class, 'getByRoom']);
    $group->post('/{id:[0-9]+}/students', [ClassController::class, 'enrollStudents']);
    $group->delete('/{id:[0-9]+}/students/{studentId:[0-9]+}', [ClassController::class, 'removeStudent']);
});

// Schedule routes
$app->group('/schedules', function($group){
    $group->post('/generate', [ScheduleController::class, 'generate']);
    $group->get('', [ScheduleController::class, 'index']);
    $group->get('/class/{classId:[0-9]+}', [ScheduleController::class, 'getClassSchedule']);
    $group->get('/teacher/{teacherId:[0-9]+}', [ScheduleController::class, 'getTeacherSchedule']);
    $group->get('/room/{roomId:[0-9]+}', [ScheduleController::class, 'getRoomSchedule']);
    $group->get('/curriculum/{curriculumId:[0-9]+}', [ScheduleController::class, 'getCurriculumSchedule']);
    $group->post('/validate', [ScheduleController::class, 'validateSchedule']);
    $group->get('/timeslots', [ScheduleController::class, 'getAvailableTimeSlots']);
    $group->get('/optimize', [ScheduleController::class, 'getOptimizationSuggestions']);
});

// Health check endpoint
$app->get('/health', function($request, $response){
    $stats = \App\Services\DataStore::getStats();

    return ResponseHelper::success($response, [
        'status' => 'healthy',
        'timestamp' => date('c'),
        'version' => '1.0.0',
        'database' => $stats,
    ]);
});

// OPTIONS preflight for CORS
$app->options('/{routes:.+}', function($request, $response){
    return $response;
});

// 404 handler
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function($request, $response){
    return ResponseHelper::notFound($response, 'Endpoint');
});