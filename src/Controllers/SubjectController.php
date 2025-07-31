<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Subject;
use App\Services\DataStore;
use App\Services\ValidationService;
use App\Helpers\ResponseHelper;

class SubjectController
{
    /**
     * Get all subjects
     */
    public function index(Request $request, Response $response): Response
    {
        $subjects = DataStore::getAllSubjects();
        $formattedSubjects = ResponseHelper::formatCollection($subjects);

        return ResponseHelper::success($response, $formattedSubjects);
    }

    /**
     * Get a specific subject
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $subject = DataStore::getSubject($id);

        if(!$subject){
            return ResponseHelper::notFound($response, 'Subject');
        }

        return ResponseHelper::success($response, ResponseHelper::formatModel($subject));
    }

    /**
     * Create a new subject
     */
    public function create(Request $request, Response $response): Response
    {
        $data = ValidationService::sanitizeInput($request->getParsedBody() ?? []);

        $subject = new Subject(
            DataStore::getNextId(),
            $data['title'] ?? '',
            $data['units'] ?? 0,
            $data['hoursPerWeek'] ?? 0
        );

        $errors = $subject->validate();
        if(!empty($errors)){
            return ResponseHelper::validationError($response, $errors);
        }

        DataStore::addSubject($subject);

        return ResponseHelper::created(
            $response,
            ResponseHelper::formatModel($subject),
            'Subject created successfully'
        );
    }

    /**
     * Update an existing subject
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = ValidationService::sanitizeInput($request->getParsedBody() ?? []);

        $existingSubject = DataStore::getSubject($id);
        if(!$existingSubject){
            return ResponseHelper::notFound($response, 'Subject');
        }

        $subject = new Subject(
            $id,
            $data['title'] ?? $existingSubject->title,
            $data['units'] ?? $existingSubject->units,
            $data['hoursPerWeek'] ?? $existingSubject->hoursPerWeek
        );

        $errors = $subject->validate();
        if(!empty($errors)){
            return ResponseHelper::validationError($response, $errors);
        }

        DataStore::updateSubject($id, $subject);

        return ResponseHelper::success(
            $response,
            ResponseHelper::formatModel($subject),
            'Subject updated successfully'
        );
    }

    /**
     * Delete a subject
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        if(!DataStore::getSubject($id)){
            return ResponseHelper::notFound($response, 'Subject');
        }

        // Check if subject is being used in any curriculum
        $curriculums = DataStore::getAllCurriculums();
        foreach($curriculums as $curriculum){
            if(in_array($id, $curriculum->subjectIds)){
                return ResponseHelper::error(
                    $response,
                    "Cannot delete subject. It is currently used in curriculum: {$curriculum->name}",
                    'Constraint Violation',
                    409
                );
            }
        }

        // Check if subject is being used in any class
        $classes = DataStore::findClassesBySubject($id);
        if(!empty($classes)){
            return ResponseHelper::error(
                $response,
                "Cannot delete subject. It is currently used in active classes",
                'Constraint Violation',
                409
            );
        }

        DataStore::deleteSubject($id);

        return ResponseHelper::success($response, null, 'Subject deleted successfully');
    }

    /**
     * Get subjects by curriculum
     */
    public function getByCurriculum(Request $request, Response $response, array $args): Response
    {
        $curriculumId = (int)$args['curriculumId'];
        $curriculum = DataStore::getCurriculum($curriculumId);

        if(!$curriculum){
            return ResponseHelper::notFound($response, 'Curriculum');
        }

        $subjects = DataStore::findSubjectsByIds($curriculum->subjectIds);
        $formattedSubjects = ResponseHelper::formatCollection(array_values($subjects));

        return ResponseHelper::success($response, $formattedSubjects);
    }

    /**
     * Search subjects
     */
    public function search(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $searchTerm = $queryParams['q'] ?? '';
        $units = isset($queryParams['units']) ? (int)$queryParams['units'] : null;
        $minHours = isset($queryParams['minHours']) ? (int)$queryParams['minHours'] : null;
        $maxHours = isset($queryParams['maxHours']) ? (int)$queryParams['maxHours'] : null;

        $subjects = DataStore::getAllSubjects();
        $filteredSubjects = array_filter($subjects, function($subject) use ($searchTerm, $units, $minHours, $maxHours){
            // Text search
            if(!empty($searchTerm)){
                $searchLower = strtolower($searchTerm);
                $titleMatch = strpos(strtolower($subject->title), $searchLower) !== false;
                if(!$titleMatch){
                    return false;
                }
            }

            // Units filter
            if($units !== null && $subject->units !== $units){
                return false;
            }

            // Hours range filter
            if($minHours !== null && $subject->hoursPerWeek < $minHours){
                return false;
            }

            if($maxHours !== null && $subject->hoursPerWeek > $maxHours){
                return false;
            }

            return true;
        });

        $formattedSubjects = ResponseHelper::formatCollection(array_values($filteredSubjects));

        return ResponseHelper::success($response, [
            'subjects' => $formattedSubjects,
            'total' => count($filteredSubjects),
            'filters' => [
                'searchTerm' => $searchTerm,
                'units' => $units,
                'minHours' => $minHours,
                'maxHours' => $maxHours,
            ],
        ]);
    }

    /**
     * Get subject statistics
     */
    public function stats(Request $request, Response $response): Response
    {
        $subjects = DataStore::getAllSubjects();

        $stats = [
            'total' => count($subjects),
            'totalUnits' => array_sum(array_map(fn($s) => $s->units, $subjects)),
            'totalHours' => array_sum(array_map(fn($s) => $s->hoursPerWeek, $subjects)),
            'averageUnits' => count($subjects) > 0 ? round(array_sum(array_map(fn($s) => $s->units, $subjects)) / count($subjects), 2) : 0,
            'averageHours' => count($subjects) > 0 ? round(array_sum(array_map(fn($s) => $s->hoursPerWeek, $subjects)) / count($subjects), 2) : 0,
            'distribution' => [
                'byUnits' => [],
                'byHours' => [],
            ],
        ];

        // Calculate distribution by units
        $unitCounts = [];
        foreach($subjects as $subject){
            $unitCounts[$subject->units] = ($unitCounts[$subject->units] ?? 0) + 1;
        }
        ksort($unitCounts);
        $stats['distribution']['byUnits'] = $unitCounts;

        // Calculate distribution by hours
        $hourCounts = [];
        foreach($subjects as $subject){
            $hourRange = floor($subject->hoursPerWeek / 5) * 5; // Group by 5-hour ranges
            $rangeLabel = "{$hourRange}-" . ($hourRange + 4) . " hours";
            $hourCounts[$rangeLabel] = ($hourCounts[$rangeLabel] ?? 0) + 1;
        }
        $stats['distribution']['byHours'] = $hourCounts;

        return ResponseHelper::success($response, $stats);
    }
}