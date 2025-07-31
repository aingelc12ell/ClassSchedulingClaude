<?php

namespace App\Helpers;

use Psr\Http\Message\ResponseInterface as Response;

class ResponseHelper
{
    /**
     * Create a JSON response
     */
    public static function json(Response $response, $data, int $status = 200): Response
    {
        $payload = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /**
     * Create a success response
     */
    public static function success(Response $response, $data = null, string $message = 'Success', int $status = 200): Response
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('c'),
        ];

        if($data !== null){
            $payload['data'] = $data;
        }

        return self::json($response, $payload, $status);
    }

    /**
     * Create an error response
     */
    public static function error(Response $response, $errors, string $message = 'Error', int $status = 400): Response
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'errors' => is_array($errors) ? $errors : [$errors],
            'timestamp' => date('c'),
        ];

        return self::json($response, $payload, $status);
    }

    /**
     * Create a validation error response
     */
    public static function validationError(Response $response, array $errors): Response
    {
        return self::error($response, $errors, 'Validation failed', 422);
    }

    /**
     * Create a not found response
     */
    public static function notFound(Response $response, string $resource = 'Resource'): Response
    {
        return self::error($response, "{$resource} not found", 'Not Found', 404);
    }

    /**
     * Create a conflict response
     */
    public static function conflict(Response $response, $conflicts): Response
    {
        $payload = [
            'success' => false,
            'message' => 'Scheduling conflicts detected',
            'conflicts' => is_array($conflicts) ? $conflicts : [$conflicts],
            'timestamp' => date('c'),
        ];

        return self::json($response, $payload, 409);
    }

    /**
     * Create a paginated response
     */
    public static function paginated(
        Response $response,
        array    $data,
        int      $page = 1,
        int      $limit = 10,
        int      $total = 0
    ): Response
    {
        $payload = [
            'success' => true,
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => $limit > 0 ? ceil($total / $limit) : 0,
                'hasNext' => ($page * $limit) < $total,
                'hasPrev' => $page > 1,
            ],
            'timestamp' => date('c'),
        ];

        return self::json($response, $payload);
    }

    /**
     * Create a schedule response with detailed information
     */
    public static function scheduleResponse(Response $response, array $scheduleResult): Response
    {
        $status = empty($scheduleResult['conflicts']) ? 200 : 409;

        $payload = [
            'success' => empty($scheduleResult['conflicts']),
            'message' => empty($scheduleResult['conflicts']) ? 'Schedule generated successfully' : 'Schedule generated with conflicts',
            'data' => [
                'schedules' => $scheduleResult['schedules'] ?? [],
                'summary' => [
                    'totalSchedules' => count($scheduleResult['schedules'] ?? []),
                    'totalConflicts' => count($scheduleResult['conflicts'] ?? []),
                    'totalWarnings' => count($scheduleResult['warnings'] ?? []),
                ],
            ],
            'timestamp' => date('c'),
        ];

        if(!empty($scheduleResult['conflicts'])){
            $payload['conflicts'] = $scheduleResult['conflicts'];
        }

        if(!empty($scheduleResult['warnings'])){
            $payload['warnings'] = $scheduleResult['warnings'];
        }

        return self::json($response, $payload, $status);
    }

    /**
     * Create a resource created response
     */
    public static function created(Response $response, $data, string $message = 'Resource created successfully'): Response
    {
        return self::success($response, $data, $message, 201);
    }

    /**
     * Create a no content response
     */
    public static function noContent(Response $response): Response
    {
        return $response->withStatus(204);
    }

    /**
     * Create an unauthorized response
     */
    public static function unauthorized(Response $response, string $message = 'Unauthorized'): Response
    {
        return self::error($response, $message, 'Unauthorized', 401);
    }

    /**
     * Create a forbidden response
     */
    public static function forbidden(Response $response, string $message = 'Forbidden'): Response
    {
        return self::error($response, $message, 'Forbidden', 403);
    }

    /**
     * Create an internal server error response
     */
    public static function serverError(Response $response, string $message = 'Internal server error'): Response
    {
        return self::error($response, $message, 'Internal Server Error', 500);
    }

    /**
     * Create a method not allowed response
     */
    public static function methodNotAllowed(Response $response, array $allowedMethods = []): Response
    {
        $message = 'Method not allowed';
        if(!empty($allowedMethods)){
            $message .= '. Allowed methods: ' . implode(', ', $allowedMethods);
        }

        return self::error($response, $message, 'Method Not Allowed', 405);
    }

    /**
     * Format model data for API response
     */
    public static function formatModel($model): array
    {
        if(method_exists($model, 'toArray')){
            return $model->toArray();
        }

        if(is_object($model)){
            return get_object_vars($model);
        }

        return $model;
    }

    /**
     * Format collection of models for API response
     */
    public static function formatCollection(array $collection): array
    {
        return array_map([self::class, 'formatModel'], $collection);
    }

    /**
     * Add CORS headers to response
     */
    public static function withCors(Response $response): Response
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    }

    /**
     * Create an API documentation response
     */
    public static function apiInfo(Response $response): Response
    {
        $info = [
            'name' => 'Academic Scheduling API',
            'version' => '1.0.0',
            'description' => 'RESTful API for managing academic scheduling',
            'endpoints' => [
                'subjects' => '/subjects',
                'rooms' => '/rooms',
                'teachers' => '/teachers',
                'students' => '/students',
                'curriculums' => '/curriculums',
                'classes' => '/classes',
                'schedules' => '/schedules',
            ],
            'documentation' => '/docs',
            'status' => 'active',
        ];

        return self::success($response, $info, 'API Information');
    }
}